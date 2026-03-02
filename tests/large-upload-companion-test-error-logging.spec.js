/**
 * Tests for Large File Upload Fix, Companion /api/test Endpoint,
 * and Companion Transcode Error Logging
 *
 * Verifies:
 * 1. streamUploadToRustFS uses low-speed stall detection instead of hard timeout
 * 2. Companion app has /api/test endpoint for hw acceleration and RustFS tests
 * 3. triggerHlsTranscode logs errors via ErrorLogger on companion failures
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// Constant used in tests — the companion references FFMPEG_PATH variable
const FFMPEG_PATH_PATTERN = 'FFMPEG_PATH';

// =====================================================
// 1. streamUploadToRustFS uses stall detection for large files
// =====================================================

test.describe('streamUploadToRustFS large file timeout fix', () => {
  const content = () => readFile('lib/rustfs_storage.php');

  test('should set CURLOPT_TIMEOUT to 0 (no hard limit) in streamUploadToRustFS', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('CURLOPT_TIMEOUT, 0');
  });

  test('should set CURLOPT_LOW_SPEED_LIMIT for stall detection', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('CURLOPT_LOW_SPEED_LIMIT');
  });

  test('should set CURLOPT_LOW_SPEED_TIME for stall detection', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('CURLOPT_LOW_SPEED_TIME');
  });

  test('should NOT have hard 600s CURLOPT_TIMEOUT in streamUploadToRustFS', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).not.toContain('CURLOPT_TIMEOUT, 600');
  });

  test('should still have CURLOPT_CONNECTTIMEOUT for connection phase', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('CURLOPT_CONNECTTIMEOUT');
  });
});

// =====================================================
// 2. Companion /api/test endpoint for hw accel and RustFS
// =====================================================

test.describe('Companion /api/test diagnostic endpoint', () => {
  const content = () => readFile('companion/app.py');

  test('should have a POST /api/test route', () => {
    expect(content()).toContain('"/api/test"');
    expect(content()).toContain('def run_diagnostics');
  });

  test('should require API key authentication', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('_require_api_key');
  });

  test('should test hardware encoding with a real FFmpeg encode', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('hw_encode');
    expect(funcBody).toContain(FFMPEG_PATH_PATTERN);
    expect(funcBody).toContain('_select_encoder');
  });

  test('should test RustFS upload, download, and delete', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('put_object');
    expect(funcBody).toContain('get_object');
    expect(funcBody).toContain('delete_object');
    expect(funcBody).toContain('rustfs');
  });

  test('should return all_passed boolean and tests dict', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('all_passed');
    expect(funcBody).toContain('"tests"');
  });

  test('should clean up temporary test files', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('shutil.rmtree');
  });

  test('should log diagnostic results', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('logger.info');
    expect(funcBody).toContain('Diagnostic');
  });
});

// =====================================================
// 3. triggerHlsTranscode error logging via ErrorLogger
// =====================================================

test.describe('triggerHlsTranscode companion error logging', () => {
  const content = () => readFile('process_video.php');

  test('should capture curl_errno for connection failures', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('curl_errno');
  });

  test('should capture curl_error for connection failures', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('curl_error');
  });

  test('should use ErrorLogger::error for curl failures', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    // Should use ErrorLogger for curl connection errors
    expect(func).toContain('ErrorLogger::error');
    expect(func).toContain('curl error');
  });

  test('should log non-202 HTTP responses from companion', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    // Should explicitly handle non-202 responses with logging
    expect(func).toContain('ErrorLogger::error');
    expect(func).toContain('HTTP');
  });

  test('should log response body snippet on failure', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('body_snippet');
    expect(func).toContain('substr($response');
  });

  test('should use ErrorLogger::error in exception handler', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    // The catch block should use ErrorLogger, not plain error_log
    expect(func).toContain("ErrorLogger::error(\"Companion transcode trigger exception");
  });

  test('should use ErrorLogger instead of error_log in curl and exception handlers', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    // The curl error handler and catch block should use ErrorLogger
    const curlErrBlock = func.substring(func.indexOf('curl_errno'));
    expect(curlErrBlock).toContain('ErrorLogger::error');
    const catchBlock = func.substring(func.indexOf('} catch'));
    expect(catchBlock).toContain('ErrorLogger::error');
    expect(catchBlock).not.toContain('error_log(');
  });

  test('should include companion URL in error messages for diagnostics', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('companion_url');
    expect(func).toContain('/api/hls');
  });
});
