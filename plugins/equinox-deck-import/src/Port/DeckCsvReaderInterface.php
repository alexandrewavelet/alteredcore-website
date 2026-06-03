<?php

namespace AlteredCore\EquinoxDeckImport\Port;

/**
 * Port for extracting the raw `decks.csv` payload from an uploaded archive.
 */
interface DeckCsvReaderInterface
{
    /**
     * Whether reading is supported in this environment (e.g. the ZIP extension
     * is available).
     */
    public function isSupported(): bool;

    /**
     * @return string|null  CSV content, '' when present but empty, null when the
     *                       source cannot be read.
     */
    public function read(string $path): ?string;
}
