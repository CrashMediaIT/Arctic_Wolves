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

test.describe('Companion app.py persistent job history — SQLite', () => {
  const content = () => readFile('companion/app.py');

  test('should define JOBS_DB_FILE in CONFIG_DIR', () => {
    const c = content();
    expect(c).toContain('JOBS_DB_FILE = os.path.join(CONFIG_DIR, "companion_jobs.db")');
  });

  test('should define _MAX_PERSISTED_JOBS limit', () => {
    const c = content();
    expect(c).toContain('_MAX_PERSISTED_JOBS');
  });

  test('should keep legacy JOBS_FILE constant for migration', () => {
    const c = content();
    expect(c).toContain('JOBS_FILE = os.path.join(CONFIG_DIR, "companion_jobs.json")');
  });
});

// =====================================================
// 2. SQLite JobStore module
// =====================================================

test.describe('Companion job_store.py SQLite module', () => {
  const content = () => readFile('companion/job_store.py');

  test('should define JobStore class', () => {
    expect(content()).toContain('class JobStore');
  });

  test('should create jobs table with correct columns', () => {
    const c = content();
    expect(c).toContain('CREATE TABLE IF NOT EXISTS jobs');
    expect(c).toContain('id               TEXT PRIMARY KEY');
    expect(c).toContain('status           TEXT NOT NULL');
  });

  test('should create job_logs table with FK to jobs', () => {
    const c = content();
    expect(c).toContain('CREATE TABLE IF NOT EXISTS job_logs');
    expect(c).toContain('FOREIGN KEY (job_id) REFERENCES jobs(id)');
  });

  test('should use WAL journal mode for concurrent access', () => {
    const c = content();
    expect(c).toContain('PRAGMA journal_mode=WAL');
  });

  test('should provide upsert_job method', () => {
    expect(content()).toContain('def upsert_job(self, job: dict)');
  });

  test('should provide append_log method', () => {
    expect(content()).toContain('def append_log(self, job_id: str, ts: float, level: str, msg: str)');
  });

  test('should provide get_job method that includes logs', () => {
    const c = content();
    const func = c.substring(c.indexOf('def get_job('), c.indexOf('\n    def ', c.indexOf('def get_job(') + 1));
    expect(func).toContain('_get_logs');
    expect(func).toContain('job["log"]');
  });

  test('should provide get_all_jobs_summary without logs', () => {
    const c = content();
    expect(c).toContain('def get_all_jobs_summary');
  });

  test('should provide prune method', () => {
    const c = content();
    expect(c).toContain('def prune(self)');
  });

  test('should provide mark_interrupted method', () => {
    const c = content();
    const func = c.substring(c.indexOf('def mark_interrupted'), c.indexOf('\n    def ', c.indexOf('def mark_interrupted') + 1));
    expect(func).toContain("'failed'");
    expect(func).toContain('Interrupted by restart');
  });

  test('should provide migrate_from_json method', () => {
    const c = content();
    const func = c.substring(c.indexOf('def migrate_from_json'), c.indexOf('\n    def ', c.indexOf('def migrate_from_json') + 1));
    expect(func).toContain('.migrated');
    expect(func).toContain('upsert_job');
    expect(func).toContain('append_log');
  });

  test('should use per-thread connections for thread safety', () => {
    const c = content();
    expect(c).toContain('threading.local');
  });
});

// =====================================================
// 3. app.py loads jobs from SQLite
// =====================================================

test.describe('Companion app.py _load_jobs function', () => {
  const content = () => readFile('companion/app.py');

  test('should define _load_jobs function', () => {
    expect(content()).toContain('def _load_jobs()');
  });

  test('should load jobs from SQLite via job_store', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _load_jobs'), c.indexOf('\ndef _save_job'));
    expect(func).toContain('job_store.load_all_to_dict');
  });

  test('should create job_store from JobStore class', () => {
    const c = content();
    expect(c).toContain('job_store = JobStore(JOBS_DB_FILE');
  });

  test('should migrate legacy JSON on startup', () => {
    const c = content();
    expect(c).toContain('job_store.migrate_from_json(JOBS_FILE)');
  });

  test('should mark interrupted jobs on startup', () => {
    const c = content();
    expect(c).toContain('job_store.mark_interrupted()');
  });

  test('jobs dict should be seeded from _load_jobs on startup', () => {
    const c = content();
    expect(c).toContain('jobs: dict = _load_jobs()');
  });
});

// =====================================================
// 4. _save_job called at key points
// =====================================================

test.describe('Companion app.py _save_job call sites', () => {
  const content = () => readFile('companion/app.py');

  test('should define _save_job function', () => {
    expect(content()).toContain('def _save_job(job_id');
  });

  test('_save_job should persist via job_store.upsert_job', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _save_job('), c.indexOf('\ndef ', c.indexOf('def _save_job(') + 1));
    expect(func).toContain('job_store.upsert_job');
  });

  test('_save_job should be called after _create_job stores the job', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _create_job'), c.indexOf('\ndef ', c.indexOf('def _create_job') + 1));
    expect(func).toContain('_save_job(job_id)');
  });

  test('_save_job should be called after _run_job finishes', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _run_job'), c.indexOf('\ndef ', c.indexOf('def _run_job') + 1));
    expect(func).toContain('_save_job(job_id)');
  });

  test('_save_job should be called when HLS job is created', () => {
    const c = content();
    const hlsFunc = c.substring(c.indexOf('def hls_transcode('), c.indexOf('def hls_retry('));
    expect(hlsFunc).toContain('_save_job(job_id)');
  });

  test('_save_job should be called when HLS retry job is created', () => {
    const c = content();
    const retryFunc = c.substring(c.indexOf('def hls_retry('), c.indexOf('def hls_retry_callback('));
    expect(retryFunc).toContain('_save_job(job_id)');
  });

  test('_save_job should be called when HLS transcode completes', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _hls_transcode_s3'), c.indexOf('\ndef ', c.indexOf('def _hls_transcode_s3') + 1));
    const completedIdx = func.indexOf('"completed"');
    const saveAfterCompleted = func.indexOf('_save_job(job_id)', completedIdx);
    expect(saveAfterCompleted).toBeGreaterThan(completedIdx);
  });

  test('_save_job should be called when HLS transcode fails', () => {
    const c = content();
    const func = c.substring(c.indexOf('def _hls_transcode_s3'), c.indexOf('\ndef ', c.indexOf('def _hls_transcode_s3') + 1));
    const statusFailedIdx = func.indexOf('["status"] = "failed"');
    const saveAfterFailed = func.indexOf('_save_job(job_id)', statusFailedIdx);
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

  test('job rows should use data attribute for job ID instead of inline string', () => {
    const c = content();
    expect(c).toContain('data-job-id');
    expect(c).toContain('this.dataset.jobId');
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
