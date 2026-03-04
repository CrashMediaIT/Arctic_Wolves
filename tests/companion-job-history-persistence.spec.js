/**
 * Tests for Companion Job History Persistence and Clickable Log Viewer
 *
 * Verifies:
 * 1. Job history is persisted to a JSON file in CONFIG_DIR
 * 2. Jobs are loaded from disk on startup
 * 3. In-progress jobs are marked failed on reload (interrupted by restart)
 * 4. _save_jobs is called when jobs are created and when they finish
 * 5. History page rows are clickable to expand/collapse job logs
 * 6. toggleJobLog fetches /api/job/<id> and renders log entries
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Job history file configuration
// =====================================================

test.describe('Companion app.py persistent job history file', () => {
  const content = () => readFile('companion/app.py');

  test('should define JOBS_FILE in CONFIG_DIR', () => {
    const c = content();
    expect(c).toContain('JOBS_FILE = os.path.join(CONFIG_DIR, "companion_jobs.json")');
  });

  test('should define _MAX_PERSISTED_JOBS limit', () => {
    const c = content();
    expect(c).toContain('_MAX_PERSISTED_JOBS');
  });
});

// =====================================================
// 2. _load_jobs function
// =====================================================

test.describe('Companion app.py _load_jobs function', () => {
  const content = () => readFile('companion/app.py');

  test('should define _load_jobs function', () => {
    expect(content()).toContain('def _load_jobs()');
  });

  test('should return empty dict when file does not exist', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _load_jobs'), c.indexOf('def _save_jobs'));
    expect(func).toContain('os.path.isfile(JOBS_FILE)');
    expect(func).toContain('return {}');
  });

  test('should load jobs from JSON file', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _load_jobs'), c.indexOf('def _save_jobs'));
    expect(func).toContain('json.load(');
    expect(func).toContain('JOBS_FILE');
  });

  test('should mark in-progress jobs as failed on load', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _load_jobs'), c.indexOf('def _save_jobs'));
    expect(func).toContain('"running"');
    expect(func).toContain('"queued"');
    expect(func).toContain('"failed"');
    expect(func).toContain('Interrupted by restart');
  });

  test('jobs dict should be seeded from _load_jobs on startup', () => {
    const c = content();
    expect(c).toContain('jobs: dict = _load_jobs()');
  });
});

// =====================================================
// 3. _save_jobs function
// =====================================================

test.describe('Companion app.py _save_jobs function', () => {
  const content = () => readFile('companion/app.py');

  test('should define _save_jobs function', () => {
    expect(content()).toContain('def _save_jobs()');
  });

  test('should write to JOBS_FILE as JSON', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _save_jobs'), c.indexOf('\ndef ', c.indexOf('def _save_jobs') + 1));
    expect(func).toContain('json.dump(');
    expect(func).toContain('JOBS_FILE');
  });

  test('should limit persisted jobs to _MAX_PERSISTED_JOBS', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _save_jobs'), c.indexOf('\ndef ', c.indexOf('def _save_jobs') + 1));
    expect(func).toContain('_MAX_PERSISTED_JOBS');
  });

  test('should sort jobs by created_at descending', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _save_jobs'), c.indexOf('\ndef ', c.indexOf('def _save_jobs') + 1));
    expect(func).toContain('created_at');
    expect(func).toContain('reverse=True');
  });
});

// =====================================================
// 4. _save_jobs called at key points
// =====================================================

test.describe('Companion app.py _save_jobs call sites', () => {
  const content = () => readFile('companion/app.py');

  test('_save_jobs should be called after _create_job stores the job', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _create_job'), c.indexOf('\ndef ', c.indexOf('def _create_job') + 1));
    expect(func).toContain('_save_jobs()');
  });

  test('_save_jobs should be called after _run_job finishes', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _run_job'), c.indexOf('\ndef ', c.indexOf('def _run_job') + 1));
    expect(func).toContain('_save_jobs()');
  });

  test('_save_jobs should be called when HLS job is created', () => {
    const c = content();
    const hlsFunc = c.substring(c.indexOf('def hls_transcode('), c.indexOf('def hls_retry('));
    expect(hlsFunc).toContain('_save_jobs()');
  });

  test('_save_jobs should be called when HLS retry job is created', () => {
    const c = content();
    const retryFunc = c.substring(c.indexOf('def hls_retry('), c.indexOf('\ndef ', c.indexOf('def hls_retry(') + 1));
    expect(retryFunc).toContain('_save_jobs()');
  });

  test('_save_jobs should be called when HLS transcode completes', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _hls_transcode_s3'), c.indexOf('\ndef ', c.indexOf('def _hls_transcode_s3') + 1));
    // Should appear after status = "completed" and after status = "failed"
    const completedIdx = func.indexOf('"completed"');
    const saveAfterCompleted = func.indexOf('_save_jobs()', completedIdx);
    expect(saveAfterCompleted).toBeGreaterThan(completedIdx);
  });

  test('_save_jobs should be called when HLS transcode fails', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _hls_transcode_s3'), c.indexOf('\ndef ', c.indexOf('def _hls_transcode_s3') + 1));
    // _save_jobs should appear after the except block that sets status = "failed"
    const statusFailedIdx = func.indexOf('["status"] = "failed"');
    const saveAfterFailed = func.indexOf('_save_jobs()', statusFailedIdx);
    expect(saveAfterFailed).toBeGreaterThan(statusFailedIdx);
  });
});

// =====================================================
// 5. History page clickable rows
// =====================================================

test.describe('History page clickable job rows with log viewer', () => {
  const content = () => readFile('companion/templates/history.html');

  test('job rows should have job-row class for pointer cursor', () => {
    const c = content();
    expect(c).toContain('job-row');
    expect(c).toContain('cursor: pointer');
  });

  test('job rows should call toggleJobLog on click', () => {
    const c = content();
    expect(c).toContain('toggleJobLog');
    expect(c).toContain('onclick');
  });

  test('should render hidden detail row for each job', () => {
    const c = content();
    expect(c).toContain('job-detail-row');
    expect(c).toContain('job-log-panel');
    expect(c).toContain("style=\"display:none;\"");
  });

  test('toggleJobLog should fetch /api/job/ endpoint', () => {
    const c = content();
    expect(c).toContain("'/api/job/'");
    expect(c).toContain('encodeURIComponent(jobId)');
  });

  test('toggleJobLog should toggle visibility of detail row', () => {
    const c = content();
    const func = c.substring(c.indexOf('async function toggleJobLog'), c.indexOf('function escapeHtml'));
    expect(func).toContain("style.display !== 'none'");
    expect(func).toContain("style.display = 'none'");
    expect(func).toContain("style.display = ''");
  });

  test('should render log entries with timestamp and level', () => {
    const c = content();
    expect(c).toContain('log-entry');
    expect(c).toContain('toLocaleTimeString');
    expect(c).toContain('toUpperCase');
  });

  test('should color-code log entries by level', () => {
    const c = content();
    expect(c).toContain('.log-entry.info');
    expect(c).toContain('.log-entry.warn');
    expect(c).toContain('.log-entry.error');
  });

  test('should show message when no log entries exist', () => {
    const c = content();
    expect(c).toContain('No log entries');
  });

  test('should escape HTML in log messages', () => {
    const c = content();
    expect(c).toContain('escapeHtml');
    expect(c).toContain('createTextNode');
  });

  test('should show job metadata in log panel header', () => {
    const c = content();
    expect(c).toContain('log-meta');
    expect(c).toContain('Job:');
    expect(c).toContain('Status:');
  });

  test('should show error message in log panel when job has error', () => {
    const c = content();
    const func = c.substring(c.indexOf('async function toggleJobLog'), c.indexOf('function escapeHtml'));
    expect(func).toContain('job.error');
    expect(func).toContain('Error:');
  });
});
