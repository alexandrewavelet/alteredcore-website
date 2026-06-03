<?php
/**
 * Upload view (global namespace → host h() resolves directly). Variables are
 * provided by ImportView::render(): $pageTitle, $intro, $fileLabel, $submit,
 * $noscript, $csrf, $siteBase, $jsTxt.
 *
 * The front-end queue (js/) intercepts the form, uploads to parse-zip, then
 * imports each deck via import-deck — rendering progress into .container.
 */
?>
<script>
var SITE_BASE = <?= json_encode($siteBase, JSON_UNESCAPED_SLASHES) ?>;
var EDI_CSRF = <?= json_encode($csrf) ?>;
var EDI_TXT = <?= json_encode($jsTxt, JSON_UNESCAPED_UNICODE) ?>;
</script>
<div class="container py-4" style="max-width:680px">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="section-title mb-0"><span><?= h($pageTitle) ?></span></div>
    </div>

    <noscript>
        <div class="alert alert-warning py-2 mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= h($noscript) ?>
        </div>
    </noscript>

    <div class="card-altered p-4">
        <p class="text-muted small mb-4"><?= $intro ?></p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

            <div class="mb-4">
                <label class="form-label fw-semibold">
                    <i class="fa-solid fa-file-zipper me-1"></i><?= h($fileLabel) ?>
                </label>
                <input type="file" name="equinox_zip" class="form-control" accept=".zip,application/zip">
            </div>

            <button type="submit" class="btn btn-primary-altered btn-sm">
                <i class="fa-solid fa-file-import me-1"></i><?= h($submit) ?>
            </button>
        </form>
    </div>

</div>
