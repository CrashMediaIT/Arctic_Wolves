/**
 * Video Playback After Transcoding – Fallback & Source Type Tests
 *
 * Verifies:
 * 1. <source> type attribute is dynamic (video/mp4 vs application/vnd.apple.mpegurl)
 * 2. HLS fallback URL is computed and passed as data-fallback-url attribute
 * 3. JS error handler retries with HLS URL when primary source fails
 * 4. Both desktop and PWA detail pages have the same resilience
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  1. Dynamic <source> type attribute                                */
/* ------------------------------------------------------------------ */
test.describe('Source type attribute is dynamic based on URL', () => {

  test('desktop: computes $video_type from URL extension', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('$video_type');
    // Should detect m3u8 URLs and set correct MIME type
    expect(c).toMatch(/video_type.*m3u8.*application\/vnd\.apple\.mpegurl/s);
    // Should fall back to video/mp4 otherwise
    expect(c).toContain("'video/mp4'");
  });

  test('desktop: <source> uses $video_type not hardcoded video/mp4', () => {
    const c = readFile('views/video_review_detail.php');
    // Should use the dynamic type variable in a source tag
    expect(c).toContain('type="<?= $video_type ?>">');
    // Should NOT have hardcoded type="video/mp4" on any source line
    const lines = c.split('\n');
    const sourceLines = lines.filter(l => l.includes('<source') && l.includes('type='));
    expect(sourceLines.length).toBeGreaterThan(0);
    for (const line of sourceLines) {
      expect(line).not.toContain('type="video/mp4"');
    }
  });

  test('PWA: computes $video_type from URL extension', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('$video_type');
    expect(c).toMatch(/video_type.*m3u8.*application\/vnd\.apple\.mpegurl/s);
    expect(c).toContain("'video/mp4'");
  });

  test('PWA: <source> uses $video_type not hardcoded video/mp4', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    // Should use the dynamic type variable in a source tag
    expect(c).toContain('type="<?= $video_type ?>">');
    // Should NOT have hardcoded type="video/mp4" on any source line
    const lines = c.split('\n');
    const sourceLines = lines.filter(l => l.includes('<source') && l.includes('type='));
    expect(sourceLines.length).toBeGreaterThan(0);
    for (const line of sourceLines) {
      expect(line).not.toContain('type="video/mp4"');
    }
  });
});

/* ------------------------------------------------------------------ */
/*  2. HLS fallback URL computed and passed as data attribute          */
/* ------------------------------------------------------------------ */
test.describe('HLS fallback URL is passed as data-fallback-url', () => {

  test('desktop: computes $fallback_url from video hls_url', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('$fallback_url');
    // Should resolve hls_url via resolveRustfsUrl
    expect(c).toMatch(/fallback_url[\s\S]*resolveRustfsUrl\(\$pdo,\s*\$video\['hls_url'\]\)/);
  });

  test('desktop: only sets fallback when different from primary URL', () => {
    const c = readFile('views/video_review_detail.php');
    // Should check that resolved HLS URL differs from the primary video_url
    expect(c).toMatch(/\$resolved_hls\s*&&\s*\$resolved_hls\s*!==\s*\$video_url/);
  });

  test('desktop: video element conditionally has data-fallback-url attribute', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('data-fallback-url');
    expect(c).toContain('$fallback_url');
  });

  test('PWA: computes $fallback_url from video hls_url', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('$fallback_url');
    expect(c).toMatch(/fallback_url[\s\S]*resolveRustfsUrl\(\$pdo,\s*\$video\['hls_url'\]\)/);
  });

  test('PWA: only sets fallback when different from primary URL', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/\$resolved_hls\s*&&\s*\$resolved_hls\s*!==\s*\$video_url/);
  });

  test('PWA: video element conditionally has data-fallback-url attribute', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('data-fallback-url');
    expect(c).toContain('$fallback_url');
  });
});

/* ------------------------------------------------------------------ */
/*  3. JS error handler retries with HLS fallback URL                  */
/* ------------------------------------------------------------------ */
test.describe('JS error handler retries with HLS fallback', () => {

  test('desktop: registers error event listener on video element', () => {
    const c = readFile('views/video_review_detail.php');
    // Should have an error listener with capture=true to catch source errors
    expect(c).toMatch(/addEventListener\(\s*'error'/);
    expect(c).toContain(', true)');
  });

  test('desktop: error handler reads data-fallback-url from dataset', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('dataset.fallbackUrl');
  });

  test('desktop: error handler calls awInitHlsPlayer with HLS URL', () => {
    const c = readFile('views/video_review_detail.php');
    // Find the error handler section
    const errorSection = c.match(/addEventListener\('error'[\s\S]*?true\)/);
    expect(errorSection).not.toBeNull();
    expect(errorSection[0]).toContain('awInitHlsPlayer');
  });

  test('desktop: error handler prevents infinite retry loop', () => {
    const c = readFile('views/video_review_detail.php');
    // Should have a flag to prevent multiple fallback attempts
    expect(c).toContain('_fallbackTried');
  });

  test('PWA: registers error event listener on video element', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/addEventListener\(\s*'error'/);
    expect(c).toContain(', true)');
  });

  test('PWA: error handler reads data-fallback-url from dataset', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('dataset.fallbackUrl');
  });

  test('PWA: error handler calls awInitHlsPlayer with HLS URL', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    const errorSection = c.match(/addEventListener\('error'[\s\S]*?true\)/);
    expect(errorSection).not.toBeNull();
    expect(errorSection[0]).toContain('awInitHlsPlayer');
  });

  test('PWA: error handler prevents infinite retry loop', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('_fallbackTried');
  });
});

/* ------------------------------------------------------------------ */
/*  4. Existing behavior preserved                                     */
/* ------------------------------------------------------------------ */
test.describe('Existing playback behavior preserved', () => {

  test('desktop: still uses getPreferredVideoUrl for primary URL', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toMatch(/\$video_url\s*=\s*resolveRustfsUrl\(\$pdo,\s*getPreferredVideoUrl\(\$video\)\)/);
  });

  test('PWA: still uses getPreferredVideoUrl for primary URL', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/\$video_url\s*=\s*resolveRustfsUrl\(\$pdo,\s*getPreferredVideoUrl\(\$video\)\)/);
  });

  test('desktop: still initializes HLS player on DOMContentLoaded', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('awInitHlsPlayer');
    expect(c).toContain("document.getElementById('detailVideoPlayer')");
  });

  test('PWA: still initializes HLS player on DOMContentLoaded', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('awInitHlsPlayer');
    expect(c).toContain("document.getElementById('detailVideoPlayer')");
  });

  test('getPreferredVideoUrl still returns hls_url when status is ready', () => {
    const c = readFile('lib/image_helper.php');
    expect(c).toMatch(/function getPreferredVideoUrl/);
    expect(c).toContain("'ready'");
    expect(c).toContain('hls_url');
  });
});
