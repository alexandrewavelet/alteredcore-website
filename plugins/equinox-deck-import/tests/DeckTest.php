<?php

use AlteredCore\EquinoxDeckImport\Domain\Card;
use AlteredCore\EquinoxDeckImport\Domain\Deck;

// --- contentHash is order-independent ----------------------------------------
$d1 = new Deck('Aggro', 'standard', '', [new Card('ALT_A', 2), new Card('ALT_B', 1)]);
$d2 = new Deck('Aggro', 'standard', '', [new Card('ALT_B', 1), new Card('ALT_A', 2)]);
assertSame($d1->contentHash(), $d2->contentHash(), 'hash: independent of card order');

// --- contentHash changes when a quantity changes -----------------------------
$d3 = new Deck('Aggro', 'standard', '', [new Card('ALT_A', 3), new Card('ALT_B', 1)]);
assertTrue($d1->contentHash() !== $d3->contentHash(), 'hash: changes when a quantity changes');

// --- contentHash is case-insensitive on the name -----------------------------
$d4 = new Deck('aggro', 'standard', '', [new Card('ALT_A', 2), new Card('ALT_B', 1)]);
assertSame($d1->contentHash(), $d4->contentHash(), 'hash: deck name is case-insensitive');

// --- normalizedCards injects the hero when absent ----------------------------
$withHero = new Deck('X', 'standard', 'ALT_HERO', [new Card('ALT_A', 1)]);
$norm = $withHero->normalizedCards();
assertSame(2, count($norm), 'normalize: hero added when absent');
assertSame('ALT_HERO', $norm[0]->reference(), 'normalize: hero prepended as first card');

// --- normalizedCards does not duplicate an already-present hero --------------
$heroPresent = new Deck('Y', 'standard', 'ALT_HERO', [new Card('ALT_HERO', 1), new Card('ALT_A', 1)]);
assertSame(2, count($heroPresent->normalizedCards()), 'normalize: hero not duplicated when already present');

// --- hashFrom on raw API arrays matches the Card[] hash ----------------------
$raw = [['cardReference' => 'ALT_A', 'quantity' => 2], ['cardReference' => 'ALT_B', 'quantity' => 1]];
$noHero = new Deck('Aggro', 'standard', '', [new Card('ALT_A', 2), new Card('ALT_B', 1)]);
assertSame($noHero->contentHash(), Deck::hashFrom('Aggro', $raw), 'hashFrom: raw API arrays hash like Card[]');
