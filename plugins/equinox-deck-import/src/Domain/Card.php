<?php

namespace AlteredCore\EquinoxDeckImport\Domain;

use DomainException;

/**
 * A single deck card: an Altered card reference and a quantity.
 *
 * Immutable value object that enforces the invariants the Altered Deck API
 * requires — a valid `ALT_...` reference and a quantity in 1..99. Constructing
 * an invalid card throws, so callers (parser / import) decide whether to skip
 * the row or reject the whole deck.
 */
final class Card
{
    private string $reference;
    private int $quantity;

    public function __construct(string $reference, int $quantity)
    {
        $reference = strtoupper(trim($reference));
        if (!preg_match('/^ALT_[A-Z0-9_]+$/', $reference)) {
            throw new DomainException('Invalid card reference: ' . $reference);
        }
        if ($quantity < 1 || $quantity > 99) {
            throw new DomainException('Invalid card quantity: ' . $quantity);
        }
        $this->reference = $reference;
        $this->quantity = $quantity;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    /**
     * Shape sent to / received from the Deck API.
     *
     * @return array{cardReference: string, quantity: int}
     */
    public function toApiArray(): array
    {
        return ['cardReference' => $this->reference, 'quantity' => $this->quantity];
    }
}
