<?php

namespace AlteredCore\EquinoxDeckImport\Domain;

/**
 * A parsed deck: name, format, hero reference, and its cards.
 *
 * Pure value object — no I/O. Knows how to produce its API card list (hero
 * injected) and a stable content hash used for duplicate detection.
 */
final class Deck
{
    private string $name;
    private string $format;
    private string $hero;
    /** @var Card[] */
    private array $cards;

    /**
     * @param Card[] $cards
     */
    public function __construct(string $name, string $format, string $hero, array $cards)
    {
        $this->name = $name;
        $this->format = $format !== '' ? $format : 'standard';
        $this->hero = strtoupper(trim($hero));
        $this->cards = array_values($cards);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function hero(): string
    {
        return $this->hero;
    }

    /**
     * @return Card[]
     */
    public function cards(): array
    {
        return $this->cards;
    }

    /**
     * Cards as sent to the API: the hero prepended as a 1-of when it is a valid
     * reference not already present in the list.
     *
     * @return Card[]
     */
    public function normalizedCards(): array
    {
        $cards = $this->cards;
        if ($this->hero !== '' && preg_match('/^ALT_[A-Z0-9_]+$/', $this->hero)) {
            $found = false;
            foreach ($cards as $c) {
                if ($c->reference() === $this->hero) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                array_unshift($cards, new Card($this->hero, 1));
            }
        }
        return $cards;
    }

    /**
     * Content hash over the normalized cards + lowercased name, used to detect
     * duplicates of this deck.
     */
    public function contentHash(): string
    {
        return self::hashFrom($this->name, $this->normalizedCards());
    }

    /**
     * Compute the same content hash from an arbitrary name + card list. Accepts
     * either Card[] or raw API arrays ([{cardReference, quantity}]), so a parsed
     * deck and an API-returned deck hash identically when their contents match.
     *
     * @param array<int, Card|array> $cards
     */
    public static function hashFrom(string $name, array $cards): string
    {
        $map = [];
        foreach ($cards as $c) {
            if ($c instanceof Card) {
                $ref = $c->reference();
                $qty = $c->quantity();
            } else {
                $ref = strtoupper(trim((string) ($c['cardReference'] ?? '')));
                $qty = (int) ($c['quantity'] ?? 1);
            }
            if ($ref !== '') {
                $map[$ref] = ($map[$ref] ?? 0) + $qty;
            }
        }
        ksort($map);
        return md5(mb_strtolower(trim($name)) . '|' . json_encode($map));
    }

    /**
     * Wire shape for the front-end queue (a `matching_ids` slot is added by the
     * caller after dedup prep).
     *
     * @return array{name: string, format: string, hero: string, cards: array}
     */
    public function toArray(): array
    {
        $cards = [];
        foreach ($this->cards as $c) {
            $cards[] = $c->toApiArray();
        }
        return [
            'name' => $this->name,
            'format' => $this->format,
            'hero' => $this->hero,
            'cards' => $cards,
        ];
    }
}
