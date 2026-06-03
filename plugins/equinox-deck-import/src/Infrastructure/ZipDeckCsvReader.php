<?php

namespace AlteredCore\EquinoxDeckImport\Infrastructure;

use AlteredCore\EquinoxDeckImport\Port\DeckCsvReaderInterface;
use ZipArchive;

/**
 * Reads the `decks.csv` payload out of an uploaded Equinox ZIP (at any nesting
 * depth). Pure file I/O — never extracts to disk (no zip-slip surface).
 */
final class ZipDeckCsvReader implements DeckCsvReaderInterface
{
    public function isSupported(): bool
    {
        return class_exists('ZipArchive');
    }

    /**
     * @return string|null  CSV content, '' when decks.csv is present but empty,
     *                       null when the archive cannot be read or has no decks.csv.
     */
    public function read(string $zipPath): ?string
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $csv = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (basename($zip->getNameIndex($i)) === 'decks.csv') {
                $content = $zip->getFromIndex($i);
                $csv = ($content === false) ? null : $content;
                break;
            }
        }

        $zip->close();
        return $csv;
    }
}
