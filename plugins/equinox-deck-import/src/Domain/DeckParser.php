<?php

namespace AlteredCore\EquinoxDeckImport\Domain;

use DomainException;

/**
 * Parses an Equinox `decks.csv` export into Deck value objects. Pure — no I/O.
 *
 * The CSV is semicolon-separated with a header row; per row the columns used are
 * [0] deck id, [1] name, [2] format, [3] hero, [5] card reference, [7] quantity.
 */
final class DeckParser
{
    /**
     * @return Deck[]
     */
    public function parse(string $raw): array
    {
        return array_map([$this, 'buildDeck'], $this->accumulateDecks($raw));
    }

    /**
     * Group CSV rows by deck id (first-seen order), summing duplicate cards.
     *
     * @return array<int, array{name: string, format: string, hero: string, cards: array<string, int>}>
     */
    private function accumulateDecks(string $raw): array
    {
        $lines = explode("\n", str_replace("\r", '', ltrim($raw, "\xEF\xBB\xBF")));

        $decks = [];
        $order = [];
        $isHeader = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($isHeader) {
                $isHeader = false;

                continue;
            }

            $cols = str_getcsv($line, ';', '"');
            if (count($cols) < 8) {
                continue;
            }

            $id = trim($cols[0]);
            if ($id === '') {
                continue;
            }

            if (!isset($decks[$id])) {
                $decks[$id] = [
                    'name' => trim($cols[1]),
                    'format' => strtolower(trim($cols[2])),
                    'hero' => strtoupper(trim($cols[3])),
                    'cards' => [],
                ];
                $order[] = $id;
            }

            $this->addCard($decks[$id]['cards'], strtoupper(trim($cols[5])), (int) trim($cols[7]));
        }

        return array_map(static function (string $id) use ($decks) {
            return $decks[$id];
        }, $order);
    }

    /**
     * Add a card row to a deck's reference => quantity map, summing duplicates
     * and skipping rows with an invalid reference or a non-positive quantity.
     *
     * @param array<string, int> $cards
     */
    private function addCard(array &$cards, string $reference, int $quantity): void
    {
        if ($reference === '' || $quantity <= 0 || !preg_match('/^ALT_[A-Z0-9_]+$/', $reference)) {
            return;
        }
        $cards[$reference] = ($cards[$reference] ?? 0) + $quantity;
    }

    /**
     * @param array{name: string, format: string, hero: string, cards: array<string, int>} $row
     */
    private function buildDeck(array $row): Deck
    {
        $cards = [];
        foreach ($row['cards'] as $reference => $quantity) {
            try {
                $cards[] = new Card($reference, $quantity);
            } catch (DomainException $e) {
                // Skip a card whose summed quantity falls outside the 1..99 range.
            }
        }

        return new Deck($row['name'], $row['format'], $row['hero'], $cards);
    }
}
