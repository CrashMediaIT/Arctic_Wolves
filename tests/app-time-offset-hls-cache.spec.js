/**
 * Tests for Application Time Offset and HLS Error Cache Prevention
 *
 * Verifies:
 * 1. db_config.php loads app_time_offset from system_settings
 * 2. db_config.php defines APP_TIME_OFFSET constant and appTime()/appDate() helpers
 * 3. db_config.php includes app_time_offset in MySQL session timezone calculation
 * 4. error_logger.php uses appDate() for timestamps
 * 5. lib/logger.php uses appDate() for timestamps
 * 6. api/media.php has Cache-Control: no-store on all error responses
 * 7. js/hls-player.js has xhrSetup cache-buster for HLS.js
 * 8. js/hls-player.js reports empty URL as silent failure
 * 9. js/hls-player.js uses exponential backoff for retries
 * 10. process_settings.php has ntp_sync and set_manual_time handlers
 * 11. views/admin_system_tools.php has Date & Time management card
 * 12. views/pwa/settings.php uses proper timezone identifiers
 * 13. process_settings.php update_general validates timezone
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. db_config.php time offset infrastructure
// =====================================================

test.describe('db_config.php app time offset', () => {
  const content = () => readFile('db_config.php');

  test('loads app_time_offset alongside timezone from system_settings', () => {
    const c = content();
    expect(c).toContain("'app_time_offset'");
    expect(c).toContain("'timezone'");
  });

  test('defines APP_TIME_OFFSET constant', () => {
    const c = content();
    expect(c).toContain("define('APP_TIME_OFFSET'");
  });

  test('defines appTime() helper function', () => {
    const c = content();
    expect(c).toContain('function appTime()');
    expect(c).toContain('APP_TIME_OFFSET');
  });

  test('defines appDate() helper function', () => {
    const c = content();
    expect(c).toContain('function appDate(');
    expect(c).toContain('appTime()');
  });

  test('includes app_time_offset in MySQL timezone calculation', () => {
    const c = content();
    expect(c).toContain('$_app_time_offset');
    expect(c).toContain('$total_offset');
  });

  test('applies total_offset (tz + app offset) to MySQL SET time_zone', () => {
    const c = content();
    const offsetCalc = c.indexOf('$total_offset = $offset_s + $_app_time_offset');
    const setTz = c.indexOf("SET time_zone");
    expect(offsetCalc).toBeGreaterThan(-1);
    expect(setTz).toBeGreaterThan(-1);
    expect(offsetCalc).toBeLessThan(setTz);
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

  test('HLS.js config includes xhrSetup for cache-busting', () => {
    const c = content();
    expect(c).toContain('xhrSetup');
    expect(c).toContain('_t=');
    expect(c).toContain('Date.now()');
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
    expect(c).toContain("'HLS network error (retry '");
  });
});

// =====================================================
// 5. Time sync handlers in process_settings.php
// =====================================================

test.describe('Time synchronisation handlers in process_settings.php', () => {
  const content = () => readFile('process_settings.php');

  test('ntp_sync action is in JSON actions list', () => {
    const c = content();
    expect(c).toContain("'ntp_sync'");
  });

  test('set_manual_time action is in JSON actions list', () => {
    const c = content();
    expect(c).toContain("'set_manual_time'");
  });

  test('reset_time_offset action is in JSON actions list', () => {
    const c = content();
    expect(c).toContain("'reset_time_offset'");
  });

  test('ntp_sync handler queries HTTP Date headers', () => {
    const c = content();
    const ntpIdx = c.indexOf("case 'ntp_sync':");
    expect(ntpIdx).toBeGreaterThan(-1);
    const handler = c.substring(ntpIdx, ntpIdx + 3000);
    expect(handler).toContain('CURLOPT_NOBODY');
    expect(handler).toContain("Date:");
    expect(handler).toContain('app_time_offset');
  });

  test('set_manual_time handler computes offset', () => {
    const c = content();
    const manualIdx = c.indexOf("case 'set_manual_time':");
    expect(manualIdx).toBeGreaterThan(-1);
    const handler = c.substring(manualIdx, manualIdx + 1500);
    expect(handler).toContain('manual_datetime');
    expect(handler).toContain('strtotime');
    expect(handler).toContain('app_time_offset');
  });

  test('update_general validates timezone', () => {
    const c = content();
    const generalIdx = c.indexOf("case 'update_general':");
    expect(generalIdx).toBeGreaterThan(-1);
    const handler = c.substring(generalIdx, generalIdx + 500);
    expect(handler).toContain('$valid_timezones');
  });
});

// =====================================================
// 6. Admin System Tools Date & Time card
// =====================================================

test.describe('Admin System Tools Date & Time management', () => {
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

  test('has NTP sync button', () => {
    const c = content();
    expect(c).toContain('btn-ntp-sync');
    expect(c).toContain('awNtpSync');
  });

  test('has manual time setter input', () => {
    const c = content();
    expect(c).toContain('manual-datetime');
    expect(c).toContain('datetime-local');
    expect(c).toContain('awSetManualTime');
  });

  test('shows active offset', () => {
    const c = content();
    expect(c).toContain('app_time_offset');
    expect(c).toContain('time-offset-display');
  });

  test('has reset offset button when offset is non-zero', () => {
    const c = content();
    expect(c).toContain('awResetTimeOffset');
    expect(c).toContain('btn-reset-offset');
  });
});

// =====================================================
// 7. PWA settings timezone fix
// =====================================================

test.describe('PWA settings timezone format fix', () => {
  const content = () => readFile('views/pwa/settings.php');

  test('timezone options use proper identifiers without labels in values', () => {
    const c = content();
    // Should have value="America/New_York" not value="America/New_York (EST)"
    expect(c).toContain("'America/New_York' =>");
    expect(c).not.toContain("'America/New_York (EST)'");
  });

  test('includes Newfoundland and Atlantic timezones', () => {
    const c = content();
    expect(c).toContain("'America/St_Johns'");
    expect(c).toContain("'America/Halifax'");
  });

  test('default timezone does not include label suffix', () => {
    const c = content();
    expect(c).toContain("?? 'America/New_York'");
    expect(c).not.toContain("?? 'America/New_York (EST)'");
  });
});
