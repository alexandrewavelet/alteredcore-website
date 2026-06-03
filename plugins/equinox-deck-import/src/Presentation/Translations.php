<?php

namespace AlteredCore\EquinoxDeckImport\Presentation;

/**
 * Single source of UI strings (en / fr), grouped by where they are used:
 *  - page():   chrome rendered by the upload view
 *  - parse():  parse-zip endpoint messages
 *  - import(): import-deck endpoint messages (incl. API-error variants)
 *  - js():     the EDI_TXT payload consumed by the front-end queue
 *
 * Unknown languages fall back to English.
 */
final class Translations
{
    public static function page(string $lang): array
    {
        return self::dict($lang)['page'];
    }

    public static function parse(string $lang): array
    {
        return self::dict($lang)['parse'];
    }

    public static function import(string $lang): array
    {
        return self::dict($lang)['import'];
    }

    public static function js(string $lang): array
    {
        return self::dict($lang)['js'];
    }

    /**
     * @return array<string, array<string,string>>
     */
    private static function dict(string $lang): array
    {
        $all = [
            'en' => [
                'page' => [
                    'page_title' => 'Import Decks from the Altered Site Export',
                    'intro' => 'Upload the <code>.zip</code> exported by Equinox. It must contain a <code>decks.csv</code> file.',
                    'file_label' => 'Equinox export (.zip)',
                    'submit' => 'Import',
                    'noscript' => 'This importer requires JavaScript. Please enable it to import your decks.',
                ],
                'parse' => [
                    'unauthorized' => 'Unauthorized.',
                    'csrf' => 'Invalid form token.',
                    'no_zipext' => 'The ZipArchive extension is not available on this server.',
                    'no_file' => 'Please select a .zip file.',
                    'not_zip' => 'The file must be a .zip.',
                    'cant_read' => 'Could not read the ZIP file.',
                    'empty_csv' => 'The decks.csv file is empty.',
                    'no_decks' => 'No valid decks found in the CSV.',
                ],
                'import' => [
                    'unauthorized' => 'Unauthorized.',
                    'invalid_body' => 'Invalid request body.',
                    'csrf' => 'Invalid form token.',
                    'invalid_deck' => 'This deck has no valid cards and cannot be imported.',
                    'invalid_card' => 'This deck contains an invalid card reference.',
                    'no_token' => 'Session expired — please log in again.',
                    'err_network' => 'Cannot reach the server. Check your connection.',
                    'err_session' => 'Session expired — please log in again.',
                    'err_rate' => 'Too many requests. Try again shortly.',
                    'err_bad_request' => 'This deck contains data the server rejected.',
                    'err_unavailable' => 'Server temporarily unavailable.',
                    'err_server' => 'Unexpected server error.',
                    'err_generic' => 'An error occurred while importing this deck.',
                ],
                'js' => [
                    'submit' => 'Import',
                    'token_err' => 'Could not obtain an API token. Please log out and back in.',
                    'deck_ok' => 'Imported',
                    'deck_skip' => 'Already exists',
                    'deck_err' => 'Failed',
                    'col_deck' => 'Deck',
                    'col_status' => 'Status',
                    'q_parsing' => 'Analysing file…',
                    'q_progress' => 'Import in progress — %1 / %2 decks',
                    'q_done' => 'Import complete',
                    'q_cancelled' => 'Import cancelled',
                    'q_eta_sec' => '~%1 sec remaining',
                    'q_eta_min' => '~%1 min remaining',
                    'q_eta_min_sec' => '~%1 min %2 sec remaining',
                    'q_pause' => 'Pause',
                    'q_resume' => 'Resume',
                    'q_cancel' => 'Cancel',
                    'q_cancel_confirm' => 'Cancel import? Decks already imported will be kept.',
                    'q_dedup_warn' => 'Could not verify duplicates — all decks will be imported.',
                    'q_reset' => 'Import another file',
                    'q_current' => 'Importing…',
                    'q_failed_final' => 'Final failure',
                    'q_cancelled_item' => 'Cancelled',
                    'q_pending' => 'Pending',
                    'q_retry' => 'Retry',
                    'q_retry_left_1' => '%1 attempt remaining',
                    'q_retry_left_n' => '%1 attempts remaining',
                    'q_show_all' => 'Show all decks (%1)',
                    'q_sum_imported_1' => '%1 imported',
                    'q_sum_imported_n' => '%1 imported',
                    'q_sum_skip_1' => '%1 already exists',
                    'q_sum_skip_n' => '%1 already exist',
                    'q_sum_failed_1' => '%1 failure',
                    'q_sum_failed_n' => '%1 failures',
                    'q_sum_cancelled_1' => '%1 cancelled',
                    'q_sum_cancelled_n' => '%1 cancelled',
                    'q_err_network' => 'Cannot reach server. Check your connection.',
                    'q_err_generic' => 'An error occurred.',
                ],
            ],
            'fr' => [
                'page' => [
                    'page_title' => "Importer des decks depuis l'export du site Altered",
                    'intro' => 'Uploadez le <code>.zip</code> exporté par Equinox. Il doit contenir un fichier <code>decks.csv</code>.',
                    'file_label' => 'Export Equinox (.zip)',
                    'submit' => 'Importer',
                    'noscript' => "Cet outil d'import nécessite JavaScript. Veuillez l'activer pour importer vos decks.",
                ],
                'parse' => [
                    'unauthorized' => 'Non autorisé.',
                    'csrf' => 'Jeton de formulaire invalide.',
                    'no_zipext' => "L'extension ZipArchive n'est pas disponible sur ce serveur.",
                    'no_file' => 'Veuillez sélectionner un fichier .zip.',
                    'not_zip' => 'Le fichier doit être un .zip.',
                    'cant_read' => 'Impossible de lire le fichier ZIP.',
                    'empty_csv' => 'Le fichier decks.csv est vide.',
                    'no_decks' => 'Aucun deck valide trouvé dans le CSV.',
                ],
                'import' => [
                    'unauthorized' => 'Non autorisé.',
                    'invalid_body' => 'Corps de requête invalide.',
                    'csrf' => 'Jeton de formulaire invalide.',
                    'invalid_deck' => 'Ce deck ne contient aucune carte valide et ne peut pas être importé.',
                    'invalid_card' => 'Ce deck contient une référence de carte invalide.',
                    'no_token' => 'Session expirée — veuillez vous reconnecter.',
                    'err_network' => 'Impossible de joindre le serveur. Vérifiez votre connexion.',
                    'err_session' => 'Session expirée — veuillez vous reconnecter.',
                    'err_rate' => 'Trop de requêtes. Réessayez dans quelques instants.',
                    'err_bad_request' => "Ce deck contient des données que le serveur n'accepte pas.",
                    'err_unavailable' => 'Serveur temporairement indisponible.',
                    'err_server' => 'Erreur serveur inattendue.',
                    'err_generic' => "Une erreur est survenue lors de l'import de ce deck.",
                ],
                'js' => [
                    'submit' => 'Importer',
                    'token_err' => "Impossible d'obtenir un token API. Veuillez vous déconnecter et reconnecter.",
                    'deck_ok' => 'Importé',
                    'deck_skip' => 'Déjà existant',
                    'deck_err' => 'Échec',
                    'col_deck' => 'Deck',
                    'col_status' => 'Statut',
                    'q_parsing' => 'Analyse du fichier…',
                    'q_progress' => 'Import en cours — %1 / %2 decks',
                    'q_done' => 'Import terminé',
                    'q_cancelled' => 'Import annulé',
                    'q_eta_sec' => '~%1 sec restantes',
                    'q_eta_min' => '~%1 min restantes',
                    'q_eta_min_sec' => '~%1 min %2 sec restantes',
                    'q_pause' => 'Pause',
                    'q_resume' => 'Reprendre',
                    'q_cancel' => 'Annuler',
                    'q_cancel_confirm' => "Annuler l'import ? Les decks déjà importés seront conservés.",
                    'q_dedup_warn' => 'Impossible de vérifier les doublons — tous les decks seront importés.',
                    'q_reset' => 'Importer un autre fichier',
                    'q_current' => 'En cours…',
                    'q_failed_final' => 'Échec définitif',
                    'q_cancelled_item' => 'Annulé',
                    'q_pending' => 'En attente',
                    'q_retry' => 'Réessayer',
                    'q_retry_left_1' => '%1 essai restant',
                    'q_retry_left_n' => '%1 essais restants',
                    'q_show_all' => 'Voir tous les decks (%1)',
                    'q_sum_imported_1' => '%1 importé',
                    'q_sum_imported_n' => '%1 importés',
                    'q_sum_skip_1' => '%1 déjà existant',
                    'q_sum_skip_n' => '%1 déjà existants',
                    'q_sum_failed_1' => '%1 échec',
                    'q_sum_failed_n' => '%1 échecs',
                    'q_sum_cancelled_1' => '%1 annulé',
                    'q_sum_cancelled_n' => '%1 annulés',
                    'q_err_network' => 'Impossible de joindre le serveur. Vérifiez votre connexion.',
                    'q_err_generic' => 'Une erreur est survenue.',
                ],
            ],
        ];

        return $all[$lang] ?? $all['en'];
    }
}
