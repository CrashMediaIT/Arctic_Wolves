/**
 * Tests for Companion Callback Retry & Idempotent Confirmation
 *
 * Verifies:
 * 1. PHP callback handler is idempotent (checks existing status when rowCount=0)
 * 2. Companion stores callback_url and delete_original in job dict
 * 3. Retry-callback endpoint re-sends callback without re-transcoding
 * 4. History UI has "Retry Callback Only" button
 * 5. _job_log persists logs periodically for crash safety
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. PHP callback handler — idempotent confirmation
// =====================================================

test.describe('PHP companion callback idempotent confirmation', () => {
  const content = () => readFile('api/v1/companion.php');

  test('should check record status when rowCount is zero', () => {
    const c = content();
    // After the UPDATE, if rowCount() returns 0, the handler should
    // SELECT the current hls_status to see if it already matches.
    expect(c).toContain('SELECT hls_status FROM');
    expect(c).toContain('$hls_status_value');
  });

  test('should treat matching hls_status as confirmed (idempotent)', () => {
    const c = content();
    // When the record already has the correct status, confirmed = true
    const selectIdx = c.indexOf('SELECT hls_status FROM');
    const confirmedAfterSelect = c.indexOf('$confirmed = true', selectIdx);
    expect(confirmedAfterSelect).toBeGreaterThan(selectIdx);
  });

  test('should log idempotent confirmation', () => {
    const c = content();
    expect(c).toContain('idempotent');
  });

  test('should still warn when record is genuinely missing', () => {
    const c = content();
    expect(c).toContain('record may have been deleted');
  });

  test('should return confirmed and rows_affected in response', () => {
    const c = content();
    expect(c).toContain("'confirmed'");
    expect(c).toContain("'rows_affected'");
  });
});

// =====================================================
// 2. Companion job dict stores callback_url
// =====================================================

test.describe('Companion app.py stores callback_url in job dict', () => {
  const content = () => readFile('companion/app.py');

  test('hls_transcode job dict should include callback_url', () => {
    const c = content();
    const hlsFunc = c.substring(c.indexOf('def hls_transcode('), c.indexOf('\ndef hls_retry('));
    expect(hlsFunc).toContain('"callback_url": callback_url');
  });

  test('hls_transcode job dict should include delete_original', () => {
    const c = content();
    const hlsFunc = c.substring(c.indexOf('def hls_transcode('), c.indexOf('\ndef hls_retry('));
    expect(hlsFunc).toContain('"delete_original": delete_original');
  });

  test('hls_retry job dict should include callback_url', () => {
    const c = content();
    const retryFunc = c.substring(c.indexOf('def hls_retry('), c.indexOf('def hls_retry_callback('));
    expect(retryFunc).toContain('"callback_url": callback_url');
  });

  test('hls_retry should resolve callback_url from old job', () => {
    const c = content();
    const retryFunc = c.substring(c.indexOf('def hls_retry('), c.indexOf('def hls_retry_callback('));
    expect(retryFunc).toContain('old_job.get("callback_url"');
  });
});

// =====================================================
// 3. Retry-callback endpoint
// =====================================================

test.describe('Companion app.py retry-callback endpoint', () => {
  const content = () => readFile('companion/app.py');

  test('should define the /api/hls/retry-callback route', () => {
    const c = content();
    expect(c).toContain('/api/hls/retry-callback');
  });

  test('should define hls_retry_callback function', () => {
    const c = content();
    expect(c).toContain('def hls_retry_callback()');
  });

  test('should require API key authentication', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('_require_api_key()');
  });

  test('should require job_id in request', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('"job_id is required"');
  });

  test('should only allow completed jobs', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('"completed"');
    expect(func).toContain('Job is not completed');
  });

  test('should call _send_callback with job data', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('_send_callback(cb_url');
    expect(func).toContain('"hls_status": "ready"');
    expect(func).toContain('"status": "completed"');
  });

  test('should update callback_ok and callback_confirmed on job', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('["callback_ok"]');
    expect(func).toContain('["callback_confirmed"]');
  });

  test('should resolve callback URL from job, request, or MAIN_APP_URL', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('job.get("callback_url"');
    expect(func).toContain('MAIN_APP_URL');
  });

  test('should optionally delete original source when now confirmed', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('delete_original');
    expect(func).toContain('_s3_delete');
    expect(func).toContain('"original_deleted"');
  });

  test('should log retry-callback events to job log', () => {
    const c = content();
    const func = c.substring(c.indexOf('def hls_retry_callback'), c.indexOf('\ndef ', c.indexOf('def hls_retry_callback') + 1));
    expect(func).toContain('Retry-callback requested');
    expect(func).toContain('Retry-callback confirmed');
  });
});

// =====================================================
// 4. History UI retry callback button
// =====================================================

test.describe('History page retry callback button', () => {
  const content = () => readFile('companion/templates/history.html');

  test('should have btn-retry-cb CSS class', () => {
    const c = content();
    expect(c).toContain('.btn-retry-cb');
  });

  test('should show Retry Callback Only button for unconfirmed completed jobs', () => {
    const c = content();
    expect(c).toContain('Retry Callback Only');
    expect(c).toContain('btn-retry-cb');
    expect(c).toContain('callback_confirmed !== true');
  });

  test('should define retryCallback JavaScript function', () => {
    const c = content();
    expect(c).toContain('async function retryCallback(jobId)');
  });

  test('retryCallback should POST to /api/hls/retry-callback', () => {
    const c = content();
    const func = c.substring(c.indexOf('async function retryCallback'), c.indexOf('\n        async function', c.indexOf('async function retryCallback') + 1));
    expect(func).toContain('/api/hls/retry-callback');
    expect(func).toContain("method: 'POST'");
  });

  test('retryCallback should show result feedback to user', () => {
    const c = content();
    const func = c.substring(c.indexOf('async function retryCallback'), c.indexOf('\n        async function', c.indexOf('async function retryCallback') + 1));
    expect(func).toContain('callback_confirmed');
    expect(func).toContain('alert(');
  });

  test('retryCallback should refresh job detail and list after', () => {
    const c = content();
    const func = c.substring(c.indexOf('async function retryCallback'), c.indexOf('\n        async function', c.indexOf('async function retryCallback') + 1));
    expect(func).toContain('_fetchJobLog(jobId)');
    expect(func).toContain('refreshJobs()');
  });
});

// =====================================================
// 5. _job_log periodic persistence
// =====================================================

test.describe('Companion app.py _job_log periodic persistence', () => {
  const content = () => readFile('companion/app.py');

  test('should define _LOG_SAVE_INTERVAL constant', () => {
    const c = content();
    expect(c).toContain('_LOG_SAVE_INTERVAL');
  });

  test('_job_log should call _save_jobs periodically', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _job_log('), c.indexOf('\ndef ', c.indexOf('def _job_log(') + 1));
    expect(func).toContain('_save_jobs()');
    expect(func).toContain('_LOG_SAVE_INTERVAL');
  });

  test('_job_log should save immediately on warn or error level', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _job_log('), c.indexOf('\ndef ', c.indexOf('def _job_log(') + 1));
    expect(func).toContain('"warn"');
    expect(func).toContain('"error"');
  });
});
