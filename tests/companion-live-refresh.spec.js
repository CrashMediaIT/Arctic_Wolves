/**
 * Tests for Companion App Live Refresh (Differential DOM Updates)
 *
 * Verifies:
 * 1. Dashboard (index.html) uses differential DOM updates instead of full innerHTML rebuild
 * 2. History (history.html) uses differential DOM updates instead of full innerHTML rebuild
 * 3. Expanded log panels are preserved across refreshes (no "Loading..." flicker)
 * 4. Job rows are updated in-place, added, and removed without destroying the table
 * 5. Version uses date-based format (YYYYMMDD.N)
 * 6. Upload progress is logged per-file with file count, name, and size
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Dashboard (index.html) — differential DOM updates
// =====================================================

test.describe('Dashboard live refresh — differential DOM updates', () => {
  const content = () => readFile('companion/templates/index.html');

  test('should NOT use innerHTML to rebuild entire jobs table body', () => {
    const c = content();
    const refreshFn = c.substring(
      c.indexOf('async function refreshJobs()'),
      c.indexOf('/* --- Cancel a job ---')
    );
    // Should not contain innerHTML = jobsList.map (the old destructive pattern)
    expect(refreshFn).not.toContain('innerHTML = jobsList.map');
    expect(refreshFn).not.toContain('.innerHTML = jobsList.map');
  });

  test('should track previous job data with _lastJobData', () => {
    const c = content();
    expect(c).toContain('var _lastJobData = {}');
  });

  test('should have _buildJobRowHtml helper for row data extraction', () => {
    const c = content();
    expect(c).toContain('function _buildJobRowHtml(j)');
  });

  test('should have _createJobRows for creating new DOM rows', () => {
    const c = content();
    expect(c).toContain('function _createJobRows(j)');
    // Should create TR elements via DOM API
    expect(c).toContain("document.createElement('tr')");
  });

  test('should have _updateJobRow for updating existing rows in-place', () => {
    const c = content();
    expect(c).toContain('function _updateJobRow(existingRow, j)');
  });

  test('refreshJobs should query existing rows by data-job-id', () => {
    const c = content();
    const refreshFn = c.substring(
      c.indexOf('async function refreshJobs()'),
      c.indexOf('/* --- Cancel a job ---')
    );
    expect(refreshFn).toContain("querySelector('tr.job-row[data-job-id=");
  });

  test('refreshJobs should remove jobs that are no longer active', () => {
    const c = content();
    const refreshFn = c.substring(
      c.indexOf('async function refreshJobs()'),
      c.indexOf('/* --- Cancel a job ---')
    );
    expect(refreshFn).toContain('row.remove()');
    expect(refreshFn).toContain('detail.remove()');
  });

  test('refreshJobs should only re-fetch logs when job status changes', () => {
    const c = content();
    const refreshFn = c.substring(
      c.indexOf('async function refreshJobs()'),
      c.indexOf('/* --- Cancel a job ---')
    );
    expect(refreshFn).toContain('oldData.status !== j.status');
    expect(refreshFn).toContain('_fetchJobLog(safeId)');
  });

  test('should update status span in-place via _updateJobRow', () => {
    const c = content();
    const updateFn = c.substring(
      c.indexOf('function _updateJobRow'),
      c.indexOf('/* --- Fetch active jobs')
    );
    expect(updateFn).toContain('statusSpan.className');
    expect(updateFn).toContain('statusSpan.textContent');
  });

  test('should preserve expandedJobs across refreshes', () => {
    const c = content();
    expect(c).toContain('var expandedJobs = {}');
    // Should NOT have the old wasExpanded pattern
    expect(c).not.toContain('wasExpanded');
  });
});

// =====================================================
// 2. History page (history.html) — differential DOM updates
// =====================================================

test.describe('History page live refresh — differential DOM updates', () => {
  const content = () => readFile('companion/templates/history.html');

  test('should NOT use innerHTML to rebuild entire jobs table body', () => {
    const c = content();
    const refreshFn = c.substring(
      c.indexOf('async function refreshJobs()'),
      c.indexOf('async function retryJob')
    );
    expect(refreshFn).not.toContain('innerHTML = jobsList.map');
    expect(refreshFn).not.toContain('.innerHTML = jobsList.map');
  });

  test('should track previous job data with _lastJobData', () => {
    const c = content();
    expect(c).toContain('var _lastJobData = {}');
  });

  test('should have _buildHistoryRowHtml helper', () => {
    const c = content();
    expect(c).toContain('function _buildHistoryRowHtml(j)');
  });

  test('should have _createHistoryRows for creating new DOM rows', () => {
    const c = content();
    expect(c).toContain('function _createHistoryRows(j)');
    expect(c).toContain("document.createElement('tr')");
  });

  test('should have _updateHistoryRow for in-place updates', () => {
    const c = content();
    expect(c).toContain('function _updateHistoryRow(existingRow, j)');
  });

  test('refreshJobs should remove jobs no longer in filtered list', () => {
    const c = content();
    const refreshFn = c.substring(
      c.indexOf('async function refreshJobs()'),
      c.indexOf('async function retryJob')
    );
    expect(refreshFn).toContain('row.remove()');
    expect(refreshFn).toContain('detail.remove()');
  });

  test('refreshJobs should only re-fetch logs when job status changes', () => {
    const c = content();
    const refreshFn = c.substring(
      c.indexOf('async function refreshJobs()'),
      c.indexOf('async function retryJob')
    );
    expect(refreshFn).toContain('oldData.status !== j.status');
  });

  test('should NOT have the old wasExpanded pattern', () => {
    const c = content();
    expect(c).not.toContain('wasExpanded');
  });

  test('_updateHistoryRow should update duration cell', () => {
    const c = content();
    const updateFn = c.substring(
      c.indexOf('function _updateHistoryRow'),
      c.indexOf('async function refreshJobs')
    );
    expect(updateFn).toContain('d.duration');
  });
});

// =====================================================
// 3. Version number — date-based format
// =====================================================

test.describe('Companion version number uses date-based format', () => {
  const content = () => readFile('companion/app.py');

  test('VERSION should use YYYYMMDD.N format', () => {
    const c = content();
    const match = c.match(/VERSION\s*=\s*"(\d{8}\.\d+)"/);
    expect(match).not.toBeNull();
    expect(match[1]).toMatch(/^\d{8}\.\d+$/);
  });

  test('VERSION should not use semver format', () => {
    const c = content();
    expect(c).not.toContain('VERSION = "1.3.0"');
  });
});

// =====================================================
// 4. Upload progress logging
// =====================================================

test.describe('Companion upload progress logging', () => {
  const content = () => readFile('companion/app.py');

  test('should count total files before upload loop', () => {
    const c = content();
    expect(c).toContain('total_files = sum(len(f) for _, _, f in os.walk(hls_output))');
  });

  test('should log total file count before uploading', () => {
    const c = content();
    expect(c).toContain('jlog(f"Found {total_files} files to upload")');
  });

  test('should log per-file upload progress with count and filename', () => {
    const c = content();
    expect(c).toContain('jlog(f"Uploading file {upload_count}/{total_files}: {relative}');
  });

  test('should include file size in upload progress log', () => {
    const c = content();
    // Should compute file size and format it
    expect(c).toContain('file_size = os.path.getsize(local_file)');
    expect(c).toContain('size_label');
    expect(c).toContain('({size_label})');
  });

  test('should format large files in MB and small files in KB', () => {
    const c = content();
    const uploadSection = c.substring(
      c.indexOf('# ── Upload'),
      c.indexOf('Upload complete')
    );
    expect(uploadSection).toContain('MB');
    expect(uploadSection).toContain('KB');
    expect(uploadSection).toContain('1024 * 1024');
  });
});
