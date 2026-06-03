<?php

use AlteredCore\EquinoxDeckImport\Domain\Card;

// --- valid card: reference uppercased, quantity kept -------------------------
$c = new Card('alt_core_b_ax_01_c', 3);
assertSame('ALT_CORE_B_AX_01_C', $c->reference(), 'card: reference is upper-cased');
assertSame(3, $c->quantity(), 'card: quantity preserved');

// --- invariants: each invalid input throws DomainException -------------------
assertThrows(function () {
    new Card('XYZ_1', 1);
}, DomainException::class, 'card: non-ALT reference throws');

assertThrows(function () {
    new Card('ALT_A', 0);
}, DomainException::class, 'card: quantity 0 throws');

assertThrows(function () {
    new Card('ALT_A', 100);
}, DomainException::class, 'card: quantity 100 (>99) throws');
