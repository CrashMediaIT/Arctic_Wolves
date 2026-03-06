/**
 * Tests for DASH MPD proxy URL rewriting in api/media.php
 *
 * Verifies that media.php rewrites relative segment URLs in DASH MPD manifests
 * to absolute proxy URLs (media.php?key=…), replacing the former <BaseURL>
 * injection that didn't work with query-string based proxies.
 *
 * The same approach is already used for HLS .m3u8 playlists.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. _resolve_media_path helper function exists
// =====================================================
test.describe('DASH MPD path resolution helper', () => {
    const content = () => readFile('api/media.php');

    test('_resolve_media_path function is defined', () => {
        expect(content()).toContain('function _resolve_media_path(');
    });

    test('_resolve_media_path normalises ../ path components', () => {
        const c = content();
        const fn = c.substring(
            c.indexOf('function _resolve_media_path('),
            c.indexOf('}', c.indexOf('function _resolve_media_path(') + 100) + 1
        );
        expect(fn).toContain("'..'");
        expect(fn).toContain('array_pop');
    });

    test('_resolve_media_path combines base_dir and relative path', () => {
        const c = content();
        const fn = c.substring(
            c.indexOf('function _resolve_media_path('),
            c.indexOf('}', c.indexOf('function _resolve_media_path(') + 100) + 1
        );
        expect(fn).toContain("$base_dir . '/' . $relative");
    });
});

// =====================================================
// 2. DASH MPD rewriting replaces <BaseURL> injection
// =====================================================
test.describe('DASH MPD segment URL rewriting (no BaseURL)', () => {
    const content = () => readFile('api/media.php');

    test('should NOT inject <BaseURL> elements', () => {
        const c = content();
        // The old approach injected <BaseURL> after the <MPD> tag.
        // Verify that pattern is no longer present.
        expect(c).not.toContain("'\\$1' . \"\\n  <BaseURL>\"");
        expect(c).not.toContain("htmlspecialchars($proxy_base) . \"</BaseURL>\"");
    });

    test('should remove existing <BaseURL> elements from MPD', () => {
        const c = content();
        // Should strip any <BaseURL> already in the MPD
        expect(c).toContain('<BaseURL>');
        expect(c).toContain("preg_replace('#\\s*<BaseURL>[^<]*</BaseURL>#i'");
    });

    test('should rewrite sourceURL attributes', () => {
        const c = content();
        expect(c).toContain('sourceURL');
        expect(c).toContain('preg_replace_callback');
    });

    test('should rewrite media attributes in SegmentURL', () => {
        const c = content();
        // The regex handles sourceURL, media, and initialization
        expect(c).toMatch(/sourceURL\|media\|initialization/i);
    });

    test('should rewrite initialization attributes in SegmentTemplate', () => {
        const c = content();
        expect(c).toContain('initialization');
    });

    test('should skip absolute URLs (http/https)', () => {
        const c = content();
        expect(c).toContain("preg_match('#^https?://#i', $url)");
    });

    test('should skip byte-range values', () => {
        const c = content();
        expect(c).toContain("preg_match('#^\\d+-\\d+$#', $url)");
    });

    test('should preserve DASH template variables ($RepresentationID$, etc.)', () => {
        const c = content();
        expect(c).toContain("strpos($url, '$')");
        expect(c).toContain('proxy_prefix');
    });

    test('should use _resolve_media_path for literal URLs', () => {
        const c = content();
        expect(c).toContain('_resolve_media_path($base_dir, $url)');
    });

    test('should produce media.php?key= proxy URLs', () => {
        const c = content();
        // Check that the rewriting produces proxy URLs
        const mpdBlock = c.substring(
            c.indexOf("=== 'mpd'"),
            c.indexOf("} else {", c.indexOf("=== 'mpd'"))
        );
        expect(mpdBlock).toContain("'media.php?key='");
        expect(mpdBlock).toContain('rawurlencode');
    });

    test('should set content type to application/dash+xml', () => {
        const c = content();
        expect(c).toContain("$content_type = 'application/dash+xml'");
    });
});

// =====================================================
// 3. HLS rewriting is preserved and enhanced
// =====================================================
test.describe('HLS playlist rewriting still works', () => {
    const content = () => readFile('api/media.php');

    test('HLS rewriting block still exists', () => {
        const c = content();
        expect(c).toContain("$content_type = 'application/vnd.apple.mpegurl'");
    });

    test('HLS rewriting resolves relative paths through proxy using _resolve_media_path', () => {
        const c = content();
        expect(c).toContain("_resolve_media_path($base_dir, $trimmed)");
        expect(c).toContain("'media.php?key=' . rawurlencode($resolved)");
    });

    test('HLS rewriting handles EXT-X-MAP URI for fMP4 init segments', () => {
        const c = content();
        expect(c).toContain('EXT-X-MAP');
        expect(c).toContain('URI="');
        expect(c).toContain("_resolve_media_path($base_dir, $uri)");
    });

    test('HLS rewriting handles EXT-X-KEY URI for encryption keys', () => {
        const c = content();
        // The URI rewriting is generic — covers both EXT-X-MAP and EXT-X-KEY
        expect(c).toContain("preg_match('/URI=\"([^\"]+)\"/'");
    });
});

// =====================================================
// 4. getDashUrl returns proxy URL
// =====================================================
test.describe('getDashUrl helper produces proxy URLs', () => {
    const content = () => readFile('lib/image_helper.php');

    test('getDashUrl function is defined', () => {
        expect(content()).toContain('function getDashUrl(');
    });

    test('getDashUrl returns dash_url when set', () => {
        expect(content()).toContain("$video['dash_url']");
    });

    test('getDashUrl falls back to dash_manifest_url through proxy', () => {
        const c = content();
        expect(c).toContain("'api/media.php?key='");
        expect(c).toContain("$video['dash_manifest_url']");
    });
});

// =====================================================
// 5. DASH→HLS fallback in hls-player.js
// =====================================================
test.describe('DASH to HLS fallback prevents re-entry loop', () => {
    const content = () => readFile('js/hls-player.js');

    test('preferDash checks _awDashFallbackAttempted flag', () => {
        const c = content();
        expect(c).toContain('_awDashFallbackAttempted');
        // The preferDash condition must include the flag check
        const preferDashLine = c.substring(
            c.indexOf('var preferDash'),
            c.indexOf(';', c.indexOf('var preferDash')) + 1
        );
        expect(preferDashLine).toContain('_awDashFallbackAttempted');
    });

    test('DASH error handler sets _awDashFallbackAttempted before calling awInitHlsPlayer', () => {
        const c = content();
        // _awDashFallbackAttempted is set BEFORE calling awInitHlsPlayer
        const flagSetPos = c.indexOf('_awDashFallbackAttempted = true');
        const hlsCallPos = c.indexOf('awInitHlsPlayer(video, hlsFallback)');
        expect(flagSetPos).toBeGreaterThan(0);
        expect(hlsCallPos).toBeGreaterThan(0);
        expect(flagSetPos).toBeLessThan(hlsCallPos);
    });

    test('DASH error handler calls awInitHlsPlayer with HLS URL', () => {
        const c = content();
        expect(c).toContain('awInitHlsPlayer(video, hlsFallback)');
    });
});
