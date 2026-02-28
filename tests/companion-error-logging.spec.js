/**
 * Tests for Companion App Error Logging
 *
 * Verifies:
 * 1. Rotating file handler setup
 * 2. Flask error handler for unhandled exceptions
 * 3. Request/response logging (after_request)
 * 4. /api/logs endpoint
 * 5. Auth failure logging
 * 6. S3 client missing-config logging
 * 7. S3 health check error logging
 * 8. Job lifecycle logging (start, complete, fail, timeout)
 * 9. HLS phase transition logging
 * 10. Callback logging
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

const companionContent = readFile('companion/app.py');

// =====================================================
// 1. Rotating file handler
// =====================================================

test.describe('Rotating file log handler', () => {
  test('should import RotatingFileHandler', () => {
    expect(companionContent).toContain('RotatingFileHandler');
  });

  test('should create log directory in /config/logs/', () => {
    expect(companionContent).toContain('"logs"');
    expect(companionContent).toContain('os.makedirs(_log_dir');
  });

  test('should configure max file size and backup count', () => {
    expect(companionContent).toContain('maxBytes=');
    expect(companionContent).toContain('backupCount=');
  });

  test('should write to companion.log', () => {
    expect(companionContent).toContain('companion.log');
  });
});

// =====================================================
// 2. Flask error handler
// =====================================================

test.describe('Flask unhandled exception handler', () => {
  test('should register an errorhandler for Exception', () => {
    expect(companionContent).toContain('@app.errorhandler(Exception)');
  });

  test('should log with exc_info for full stack trace', () => {
    expect(companionContent).toContain('exc_info=True');
  });

  test('should return 500 JSON response', () => {
    expect(companionContent).toContain('"Internal server error"');
  });
});

// =====================================================
// 3. Request/response logging
// =====================================================

test.describe('Request/response logging', () => {
  test('should register after_request handler', () => {
    expect(companionContent).toContain('@app.after_request');
  });

  test('should log method, path, and status code', () => {
    expect(companionContent).toContain('request.method');
    expect(companionContent).toContain('request.path');
    expect(companionContent).toContain('response.status_code');
  });

  test('should use debug level for health checks to avoid noise', () => {
    // Health endpoint should log at debug to not flood logs
    const afterReqStart = companionContent.indexOf('def _log_request');
    const afterReqEnd = companionContent.indexOf('\ndef ', afterReqStart + 1);
    const afterReqBody = companionContent.substring(afterReqStart, afterReqEnd > -1 ? afterReqEnd : undefined);
    expect(afterReqBody).toContain('/api/health');
    expect(afterReqBody).toContain('logger.debug');
  });
});

// =====================================================
// 4. /api/logs endpoint
// =====================================================

test.describe('/api/logs endpoint', () => {
  test('should have a GET /api/logs route', () => {
    expect(companionContent).toContain('"/api/logs"');
    expect(companionContent).toContain('def get_logs');
  });

  test('should require API key authentication', () => {
    const funcStart = companionContent.indexOf('def get_logs');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('_require_api_key');
  });

  test('should support lines and level query parameters', () => {
    const funcStart = companionContent.indexOf('def get_logs');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('"lines"');
    expect(funcBody).toContain('"level"');
  });

  test('should limit maximum returned lines to 2000', () => {
    const funcStart = companionContent.indexOf('def get_logs');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('2000');
  });
});

// =====================================================
// 5. Auth failure logging
// =====================================================

test.describe('Authentication failure logging', () => {
  test('should log unauthorized requests with IP address', () => {
    const funcStart = companionContent.indexOf('def _require_api_key');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('logger.warning');
    expect(funcBody).toContain('Unauthorized');
    expect(funcBody).toContain('remote_addr');
  });
});

// =====================================================
// 6. S3 client missing-config logging
// =====================================================

test.describe('S3 client missing config logging', () => {
  test('should log which S3 settings are missing', () => {
    const funcStart = companionContent.indexOf('def _get_s3_client');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('logger.warning');
    expect(funcBody).toContain('missing');
  });
});

// =====================================================
// 7. S3 health check error logging
// =====================================================

test.describe('S3 health check error logging', () => {
  test('should log S3 health check failures in /api/health', () => {
    const funcStart = companionContent.indexOf('def health():');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('logger.warning');
    expect(funcBody).toContain('S3 health check failed');
  });
});

// =====================================================
// 8. Job lifecycle logging
// =====================================================

test.describe('Job lifecycle logging', () => {
  test('should log job start', () => {
    const funcStart = companionContent.indexOf('def _run_job(');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('Job %s started');
  });

  test('should log job completion', () => {
    const funcStart = companionContent.indexOf('def _run_job(');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('completed successfully');
  });

  test('should log job failure with stderr', () => {
    const funcStart = companionContent.indexOf('def _run_job(');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('Job %s failed');
  });

  test('should log job timeout', () => {
    const funcStart = companionContent.indexOf('def _run_job(');
    const funcEnd = companionContent.indexOf('\ndef ', funcStart + 1);
    const funcBody = companionContent.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('timed out');
  });
});

// =====================================================
// 9. HLS phase transition logging
// =====================================================

test.describe('HLS phase transition logging', () => {
  test('should log download phase start', () => {
    expect(companionContent).toContain('downloading source');
  });

  test('should log upload phase start', () => {
    expect(companionContent).toContain('uploading segments to S3');
  });

  test('should log HLS job completion with variant count', () => {
    expect(companionContent).toContain('HLS job %s completed');
  });
});

// =====================================================
// 10. Callback logging
// =====================================================

test.describe('Callback logging', () => {
  test('should log when no callback URL is configured', () => {
    expect(companionContent).toContain('No callback URL configured');
  });

  test('should log successful callback delivery', () => {
    expect(companionContent).toContain('Callback sent to');
  });

  test('should log callback failures', () => {
    expect(companionContent).toContain('Callback to %s failed');
  });
});
