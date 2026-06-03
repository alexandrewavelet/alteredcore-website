<?php

use AlteredCore\EquinoxDeckImport\Domain\DeckParser;

$parser = new DeckParser();

// --- duplicate cardReference rows are summed ---------------------------------
$csv = "id;name;format;hero;col;ref;col;qty\n"
     . "D1;My Deck;standard;ALT_HERO;x;ALT_CARD_A;y;2\n"
     . "D1;My Deck;standard;ALT_HERO;x;ALT_CARD_A;y;1\n"
     . "D1;My Deck;standard;ALT_HERO;x;ALT_CARD_B;y;3\n";
$decks = $parser->parse($csv);
assertSame(1, count($decks), 'parser: one deck parsed');
assertSame('My Deck', $decks[0]->name(), 'parser: deck name read from column 1');

$map = [];
foreach ($decks[0]->cards() as $c) {
    $map[$c->reference()] = $c->quantity();
}
assertSame(2, count($map), 'parser: two distinct cards after merge');
assertSame(3, $map['ALT_CARD_A'] ?? null, 'parser: duplicate refs summed (A = 2 + 1 = 3)');
assertSame(3, $map['ALT_CARD_B'] ?? null, 'parser: B = 3');

// --- BOM stripped + quoted field containing a semicolon ----------------------
$csv2 = "\xEF\xBB\xBFid;name;format;hero;col;ref;col;qty\n"
      . "D9;\"Subhash; & Marmo\";standard;ALT_HERO;x;ALT_CARD_C;y;1\n";
$decks2 = $parser->parse($csv2);
assertSame(1, count($decks2), 'parser: BOM + quoted row → one deck');
assertSame('Subhash; & Marmo', $decks2[0]->name(), 'parser: quoted name with inner semicolon preserved');

// --- rows with fewer than 8 columns are skipped ------------------------------
$csv3 = "id;name;format;hero;col;ref;col;qty\n"
      . "D1;Short;standard;ALT_HERO;x;ALT_CARD_A\n"           // 6 columns → skipped entirely
      . "D2;Good;standard;ALT_HERO;x;ALT_CARD_A;y;1\n";
$decks3 = $parser->parse($csv3);
assertSame(1, count($decks3), 'parser: row with <8 columns skipped');
assertSame('Good', $decks3[0]->name(), 'parser: only the valid deck remains');
