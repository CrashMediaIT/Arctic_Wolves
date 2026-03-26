<?php
/**
 * Centralized CDN Resource Management
 *
 * All external CDN resources should be loaded through these helpers so that
 * Subresource Integrity (SRI) hashes and crossorigin attributes are applied
 * consistently. This provides supply-chain attack protection by ensuring
 * browsers verify each external resource against its known hash before
 * executing or applying it.
 *
 * HOW TO REGENERATE AN SRI HASH
 * -----------------------------
 *   curl -sL '<URL>' | openssl dgst -sha384 -binary | openssl base64 -A
 *
 * Prefix the result with "sha384-" and place it in the array below.
 *
 * After updating a library version, always regenerate and verify the hash
 * from a trusted network before deploying.
 */

/**
 * Registry of CDN resources with SRI integrity hashes.
 *
 * Each key is a short alias used throughout the application.
 * Values contain:
 *   - url:       full CDN URL
 *   - type:      'css' or 'js'
 *   - integrity: SRI hash string (sha256/sha384/sha512). Empty string means
 *                the hash has not yet been generated — the helper will still
 *                emit the tag with crossorigin="anonymous" so browsers enforce
 *                anonymous CORS, and a TODO comment reminds developers to add
 *                the hash.
 */
function getCdnResources() {
    return [
        // ── Stylesheets ─────────────────────────────────────────────────
        'font-awesome' => [
            'url'       => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            'type'      => 'css',
            'integrity' => 'sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==',
        ],
        'swiper' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
            'type'      => 'css',
            'integrity' => '', // TODO: generate SRI hash after pinning exact version
        ],

        // ── Scripts ─────────────────────────────────────────────────────
        'hls-js' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js',
            'type'      => 'js',
            'integrity' => '', // TODO: generate — curl -sL <url> | openssl dgst -sha384 -binary | openssl base64 -A
        ],
        'dash-js' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/dashjs@5.0.0/dist/dash.all.min.js',
            'type'      => 'js',
            'integrity' => '', // TODO: generate
        ],
        'chart-js' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            'type'      => 'js',
            'integrity' => '', // TODO: generate
        ],
        'sortable-js' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            'type'      => 'js',
            'integrity' => 'sha256-ipiJrswvAR4VAx/th+6zWsdeYmVae0iJuiR+6OqHJHQ=',
        ],
        'qrcode' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',
            'type'      => 'js',
            'integrity' => '', // TODO: generate
        ],
        'qrcodejs' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
            'type'      => 'js',
            'integrity' => '', // TODO: generate
        ],
        'html2canvas' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
            'type'      => 'js',
            'integrity' => '', // TODO: generate
        ],
        'jsqr' => [
            'url'       => 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js',
            'type'      => 'js',
            'integrity' => '', // TODO: generate
        ],
    ];
}

/**
 * Emit an HTML tag for a registered CDN resource.
 *
 * @param string $alias  Key from getCdnResources()
 * @return string        HTML <link> or <script> tag with SRI attributes
 */
function cdnTag($alias) {
    $resources = getCdnResources();
    if (!isset($resources[$alias])) {
        return "<!-- CDN resource '$alias' not registered -->";
    }

    $r = $resources[$alias];
    $sri = '';
    if (!empty($r['integrity'])) {
        $sri = ' integrity="' . htmlspecialchars($r['integrity'], ENT_QUOTES, 'UTF-8') . '"';
    }

    if ($r['type'] === 'css') {
        return '<link rel="stylesheet" href="' . htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') . '"'
             . $sri . ' crossorigin="anonymous" referrerpolicy="no-referrer">';
    }

    return '<script src="' . htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') . '"'
         . $sri . ' crossorigin="anonymous"></script>';
}
