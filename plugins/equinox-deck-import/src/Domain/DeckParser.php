<?php

namespace AlteredCore\EquinoxDeckImport\Domain;

use DomainException;

/**
 * Parses an Equinox `decks.csv` export into Deck value objects. Pure — no I/O.
 *
 * The CSV is semicolon-separated with a header row; per row the columns used are
 * [0] deck id, [1] name, [2] format, [3] hero, [5] card reference, [7] quantity.
 * Duplicate rows for the same card within a deck are summed. Rows with an
 * invalid reference or a non-positive quantity are skipped (matching the
 * original behaviour); a summed quantity that exceeds the Card invariant (>99)
 * drops that card rather than aborting the parse.
 */
final class DeckParser
{
    /**
     * @return Deck[]
     */
    public function parse(string $raw): array
    {
        $raw   = ltrim($raw, "\xEF\xBB\xBF"); // strip UTF-8 BOM
        $lines = explode("\n", str_replace("\r", '', $raw));

        // did => ['name','format','hero','cards' => [ref => qty]]
        $decks = [];
        $order = [];
        $first = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($first) {
                $first = false; // skip header
                continue;
            }

            $cols = str_getcsv($line, ';', '"');
            if (count($cols) < 8) {
                continue;
            }

            $did = trim($cols[0]);
            if ($did === '') {
                continue;
            }

            $dname = trim($cols[1]);
            $dfmt  = strtolower(trim($cols[2]));
            $hero  = strtoupper(trim($cols[3]));
            $cref  = strtoupper(trim($cols[5]));
            $cqty  = (int) trim($cols[7]);

            if (!isset($decks[$did])) {
                $decks[$did] = ['name' => $dname, 'format' => $dfmt, 'hero' => $hero, 'cards' => []];
                $order[]     = $did;
            }

            if ($cref !== '' && $cqty > 0 && preg_match('/^ALT_[A-Z0-9_]+$/', $cref)) {
                $decks[$did]['cards'][$cref] = ($decks[$did]['cards'][$cref] ?? 0) + $cqty;
            }
        }

        $result = [];
        foreach ($order as $id) {
            $d     = $decks[$id];
            $cards = [];
            foreach ($d['cards'] as $ref => $qty) {
                try {
                    $cards[] = new Card($ref, $qty);
                } catch (DomainException $e) {
                    // Skip a card whose summed quantity falls outside 1..99.
                }
            }
            $result[] = new Deck($d['name'], $d['format'], $d['hero'], $cards);
        }
        return $result;
    }
}
