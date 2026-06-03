<?php

namespace AlteredCore\EquinoxDeckImport\Presentation;

/**
 * Presentation only: renders the upload page. No business logic — importing is
 * driven client-side by the queue calling the parse-zip / import-deck endpoints.
 */
final class ImportView
{
    public function render(): void
    {
        $lang = function_exists('getUiLang') ? \getUiLang() : 'en';
        $page = Translations::page($lang);
        $jsTxt = Translations::js($lang);
        $csrf = function_exists('csrfToken') ? \csrfToken() : '';
        $siteBase = defined('BASE_URL') ? \BASE_URL : '';

        // Variables consumed by the (global-namespace) view template.
        $pageTitle = $page['page_title'];
        $intro = $page['intro'];
        $fileLabel = $page['file_label'];
        $submit = $page['submit'];
        $noscript = $page['noscript'];

        include __DIR__ . '/views/import.php';
    }
}
