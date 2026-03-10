/**
 * Tests for Application Time Offset and HLS Error Cache Prevention
 *
 * Verifies:
 * 1. db_config.php defines APP_TIME_OFFSET constant (always 0) and appTime()/appDate() helpers
 * 2. db_config.php sets timezone from Docker TZ env var
 * 3. error_logger.php uses appDate() for timestamps
 * 4. lib/logger.php uses appDate() for timestamps
 * 5. api/media.php has Cache-Control: no-store on all error responses
 * 6. js/hls-player.js has cache-buster and retry improvements
 * 7. Time sync actions (ntp_sync, set_manual_time) are removed
 * 8. Admin Date & Time card is simplified (no NTP/manual controls)
 * 9. views/pwa/settings.php shows timezone from Docker ENV
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. db_config.php time infrastructure (ENV-based)
// =====================================================

test.describe('db_config.php app time helpers', () => {
  const content = () => readFile('db_config.php');

  test('does NOT call date_default_timezone_set', () => {
    const c = content();
    expect(c).not.toContain('date_default_timezone_set');
  });

  test('defines APP_TIME_OFFSET constant as 0', () => {
    const c = content();
    expect(c).toContain("define('APP_TIME_OFFSET', 0)");
  });

  test('defines appTime() helper function', () => {
    const c = content();
    expect(c).toContain('function appTime()');
  });

  test('defines appDate() helper function', () => {
    const c = content();
    expect(c).toContain('function appDate(');
  });

  test('uses date_default_timezone_get for MySQL sync', () => {
    const c = content();
    expect(c).toContain('date_default_timezone_get()');
  });

  test('MySQL SET time_zone is present', () => {
    const c = content();
    expect(c).toContain("SET time_zone");
  });
});

// =====================================================
// 2. Error logger uses corrected timestamps
// =====================================================

test.describe('ErrorLogger uses appDate() for timestamps', () => {
  const content = () => readFile('error_logger.php');

  test('file log timestamps use appDate()', () => {
    const c = content();
    // All timestamp assignments should use appDate with fallback
    expect(c).toContain("function_exists('appDate') ? appDate('Y-m-d H:i:s') : date('Y-m-d H:i:s')");
  });

  test('no bare date() calls for timestamp assignments', () => {
    const c = content();
    // Should not have just $timestamp = date('Y-m-d H:i:s') without appDate check
    const barePattern = /\$timestamp = date\('Y-m-d H:i:s'\);/;
    expect(c).not.toMatch(barePattern);
  });
});

test.describe('Logger uses appDate() for timestamps', () => {
  const content = () => readFile('lib/logger.php');

  test('file log timestamps use appDate()', () => {
    const c = content();
    expect(c).toContain("function_exists('appDate')");
  });

  test('log filename date uses appDate()', () => {
    const c = content();
    expect(c).toContain("appDate('Y-m-d')");
  });
});

// =====================================================
// 3. media.php Cache-Control on error responses
// =====================================================

test.describe('api/media.php error response caching prevention', () => {
  const content = () => readFile('api/media.php');

  test('400 Bad Request responses include no-store', () => {
    const c = content();
    // Find 400 error blocks
    const idx400 = c.indexOf("http_response_code(400)");
    expect(idx400).toBeGreaterThan(-1);
    const block = c.substring(idx400, idx400 + 200);
    expect(block).toContain('no-store');
  });

  test('502 Bad Gateway responses include no-store', () => {
    const c = content();
    const idx502 = c.indexOf("http_response_code(502)");
    expect(idx502).toBeGreaterThan(-1);
    const block = c.substring(idx502, idx502 + 200);
    expect(block).toContain('no-store');
  });

  test('404 Not Found responses include no-store', () => {
    const c = content();
    const idx404 = c.indexOf("http_response_code(404)");
    expect(idx404).toBeGreaterThan(-1);
    const block = c.substring(idx404, idx404 + 200);
    expect(block).toContain('no-store');
  });

  test('503 Service Unavailable responses include no-store', () => {
    const c = content();
    const idx503 = c.indexOf("http_response_code(503)");
    expect(idx503).toBeGreaterThan(-1);
    const block = c.substring(idx503, idx503 + 200);
    expect(block).toContain('no-store');
  });

  test('streaming path error responses include no-store', () => {
    const c = content();
    // Find stream proxy error section
    const streamIdx = c.indexOf('stream proxy:');
    expect(streamIdx).toBeGreaterThan(-1);
    const block = c.substring(streamIdx - 300, streamIdx + 300);
    expect(block).toContain('no-store');
  });
});

// =====================================================
// 4. HLS.js cache-busting and retry improvements
// =====================================================

test.describe('HLS.js cache-busting and retry improvements', () => {
  const content = () => readFile('js/hls-player.js');

  test('HLS.js config does NOT use xhrSetup xhr.open (causes bufferAppendError)', () => {
    const c = content();
    // xhrSetup with xhr.open() resets responseType on .ts segment requests,
    // causing bufferAppendError. Server-side Cache-Control: no-store on error
    // responses (api/media.php) handles caching prevention instead.
    expect(c).not.toContain("xhr.open('GET'");
    expect(c).toContain('do NOT use xhrSetup');
  });

  test('reports empty URL as silent failure', () => {
    const c = content();
    expect(c).toContain('silent_failure');
    expect(c).toContain('_reportPlaybackError');
  });

  test('uses exponential backoff for network retries', () => {
    const c = content();
    expect(c).toContain('Math.pow(2');
    expect(c).toContain('setTimeout');
  });

  test('has increased max retry count', () => {
    const c = content();
    expect(c).toContain('_MAX_NETWORK_RETRIES = 4');
  });

  test('logs each retry attempt', () => {
    const c = content();
    expect(c).toContain("'HLS FATAL network error (retry '");
  });

  test('destroys previous HLS instance to prevent conflicts', () => {
    const c = content();
    expect(c).toContain('video._awHls');
    expect(c).toContain('_awHls.destroy()');
  });

  test('detects page URL as empty source and reports', () => {
    const c = content();
    expect(c).toContain('window.location.href');
    expect(c).toContain('source likely empty');
  });

  test('reports when HLS.js is not available for m3u8 URL', () => {
    const c = content();
    expect(c).toContain('hls_unavailable');
    expect(c).toContain('HLS stream cannot play');
  });

  test('reports direct video play failures', () => {
    const c = content();
    expect(c).toContain('direct_play_error');
  });
});

// =====================================================
// 4b. Video views use getAttribute for source URL
// =====================================================

test.describe('Video views use getAttribute to avoid empty src resolution', () => {
  test('desktop video_review_detail.php uses getAttribute("src")', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain("getAttribute('src')");
    expect(c).not.toMatch(/\bsrc\.src\b/);
  });

  test('PWA video_review_detail.php uses getAttribute("src")', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain("getAttribute('src')");
    expect(c).not.toMatch(/\bsrc\.src\b/);
  });

  test('desktop video_review_detail.php has preload="none"', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('preload="none"');
  });

  test('PWA video_review_detail.php has preload="none"', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('preload="none"');
  });

  test('video_coach_reviews.php has preload="none"', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('preload="none"');
  });

  test('video_drill_review.php has preload="none"', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('preload="none"');
  });

  test('detail views report empty source attribute', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('empty_source');
  });
});

// =====================================================
// 5. Time sync handlers removed from process_settings.php
// =====================================================

test.describe('Time synchronisation handlers removed from process_settings.php', () => {
  const content = () => readFile('process_settings.php');

  test('ntp_sync action is NOT in JSON actions list', () => {
    const c = content();
    expect(c).not.toContain("'ntp_sync'");
  });

  test('set_manual_time action is NOT in JSON actions list', () => {
    const c = content();
    expect(c).not.toContain("'set_manual_time'");
  });

  test('reset_time_offset action is NOT in JSON actions list', () => {
    const c = content();
    expect(c).not.toContain("'reset_time_offset'");
  });

  test('update_general does not save timezone to DB', () => {
    const c = content();
    const generalIdx = c.indexOf("case 'update_general':");
    expect(generalIdx).toBeGreaterThan(-1);
    const handler = c.substring(generalIdx, generalIdx + 800);
    expect(handler).not.toContain("updateSetting($pdo, 'timezone'");
  });
});

// =====================================================
// 6. Admin System Tools Date & Time card (simplified)
// =====================================================

test.describe('Admin System Tools Date & Time display', () => {
  const content = () => readFile('views/admin_system_tools.php');

  test('has Date & Time card header', () => {
    const c = content();
    expect(c).toContain('Date &amp; Time');
    expect(c).toContain('fa-clock');
  });

  test('shows application clock with live tick', () => {
    const c = content();
    expect(c).toContain('id="app-clock"');
    expect(c).toContain('tickClock');
    expect(c).toContain('setInterval');
  });

  test('does NOT have NTP sync button', () => {
    const c = content();
    expect(c).not.toContain('awNtpSync');
    expect(c).not.toContain('btn-ntp-sync');
  });

  test('does NOT have manual time setter', () => {
    const c = content();
    expect(c).not.toContain('awSetManualTime');
    expect(c).not.toContain('manual-datetime');
  });

  test('shows timezone source as Docker ENV', () => {
    const c = content();
    expect(c).toContain('Docker TZ environment variable');
  });
});

// =====================================================
// 7. PWA settings timezone — read-only from Docker ENV
// =====================================================

test.describe('PWA settings timezone display', () => {
  const content = () => readFile('views/pwa/settings.php');

  test('shows timezone from Docker ENV as read-only', () => {
    const c = content();
    expect(c).toContain('Docker ENV');
    expect(c).toContain('date_default_timezone_get()');
  });

  test('does not have a timezone select dropdown', () => {
    const c = content();
    expect(c).not.toContain('name="timezone"');
  });
});
