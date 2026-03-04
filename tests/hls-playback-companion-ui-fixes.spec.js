/**
 * Tests for HLS Playback Fix, Companion UI Improvements, and JS Bug Fixes
 *
 * Verifies:
 * 1. api/media.php rewrites relative URLs in m3u8 playlists through the proxy
 * 2. companion/app.py health endpoint counts all active job statuses
 * 3. companion dashboard has clickable job rows with log panels
 * 4. companion history page preserves expanded log panels during refresh
 * 5. companion dashboard removed capability guide section
 * 6. js/hls-player.js wrapper._closeHandler is assigned after wrapper creation
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. HLS m3u8 URL rewriting in media.php
// =====================================================

test.describe('api/media.php HLS m3u8 URL rewriting', () => {
  const content = () => readFile('api/media.php');

  test('should detect m3u8 extension from object key', () => {
    const c = content();
    expect(c).toContain("m3u8_ext");
    expect(c).toContain("pathinfo($object_key_clean, PATHINFO_EXTENSION)");
  });

  test('should compute base directory from the m3u8 key for URL resolution', () => {
    const c = content();
    expect(c).toContain("dirname($object_key_clean)");
  });

  test('should split m3u8 body into lines for rewriting', () => {
    const c = content();
    expect(c).toContain("explode(\"\\n\", $body)");
  });

  test('should preserve comment lines (starting with #) unchanged', () => {
    const c = content();
    // Should check for # prefix to skip HLS tags
    expect(c).toContain("$trimmed[0] === '#'");
  });

  test('should preserve empty lines unchanged', () => {
    const c = content();
    expect(c).toContain("$trimmed === ''");
  });

  test('should skip already-absolute URLs (http/https)', () => {
    const c = content();
    // The m3u8 rewrite block should check for http:// or https:// URLs
    const rewriteBlock = c.substring(c.indexOf('m3u8_ext'));
    expect(rewriteBlock).toContain("preg_match('#^https?://#'");
  });

  test('should resolve relative paths against base directory', () => {
    const c = content();
    expect(c).toContain("$base_dir . '/' . $trimmed");
  });

  test('should wrap resolved paths in media.php proxy URL', () => {
    const c = content();
    expect(c).toContain("'media.php?key=' . rawurlencode($resolved_key)");
  });

  test('should reassemble body from rewritten lines', () => {
    const c = content();
    expect(c).toContain('implode("\\n", $rewritten)');
  });

  test('should set Content-Length from rewritten body', () => {
    const c = content();
    // Content-Length must be set AFTER rewriting so it reflects the new body size
    const rewritePos = c.indexOf('implode("\\n", $rewritten)');
    const contentLengthPos = c.indexOf("'Content-Length: ' . strlen($body)");
    expect(rewritePos).toBeLessThan(contentLengthPos);
  });

  test('m3u8 MIME type mapping should exist for both range and non-range paths', () => {
    const c = content();
    const matches = c.match(/m3u8.*application\/vnd\.apple\.mpegurl/g) || [];
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });
});

// =====================================================
// 2. Companion health endpoint active_jobs count
// =====================================================

test.describe('Companion app.py health endpoint active_jobs count', () => {
  const content = () => readFile('companion/app.py');

  test('active_jobs should include downloading status', () => {
    const c = content();
    // Find the active_jobs line in the health function
    const healthFn = c.substring(c.indexOf('def health():'));
    const activeJobsLine = healthFn.match(/active_jobs\s*=\s*sum.*\n/);
    expect(activeJobsLine).not.toBeNull();
    expect(activeJobsLine[0]).toContain('"downloading"');
  });

  test('active_jobs should include transcoding status', () => {
    const c = content();
    const healthFn = c.substring(c.indexOf('def health():'));
    const activeJobsLine = healthFn.match(/active_jobs\s*=\s*sum.*\n/);
    expect(activeJobsLine).not.toBeNull();
    expect(activeJobsLine[0]).toContain('"transcoding"');
  });

  test('active_jobs should include uploading status', () => {
    const c = content();
    const healthFn = c.substring(c.indexOf('def health():'));
    const activeJobsLine = healthFn.match(/active_jobs\s*=\s*sum.*\n/);
    expect(activeJobsLine).not.toBeNull();
    expect(activeJobsLine[0]).toContain('"uploading"');
  });

  test('active_jobs should include queued and running statuses', () => {
    const c = content();
    const healthFn = c.substring(c.indexOf('def health():'));
    const activeJobsLine = healthFn.match(/active_jobs\s*=\s*sum.*\n/);
    expect(activeJobsLine).not.toBeNull();
    expect(activeJobsLine[0]).toContain('"queued"');
    expect(activeJobsLine[0]).toContain('"running"');
  });

  test('_load_jobs should mark all active statuses as failed on restart', () => {
    const c = content();
    const loadJobsFn = c.substring(c.indexOf('def _load_jobs()'));
    expect(loadJobsFn).toContain('"downloading"');
    expect(loadJobsFn).toContain('"transcoding"');
    expect(loadJobsFn).toContain('"uploading"');
  });
});

// =====================================================
// 3. Companion dashboard clickable job rows
// =====================================================

test.describe('Companion dashboard active jobs with clickable log panels', () => {
  const content = () => readFile('companion/templates/index.html');

  test('should have job-row CSS class for clickable rows', () => {
    const c = content();
    expect(c).toContain('tr.job-row');
    expect(c).toContain('cursor: pointer');
  });

  test('should have job-detail-row CSS and job-log-panel styles', () => {
    const c = content();
    expect(c).toContain('.job-detail-row');
    expect(c).toContain('.job-log-panel');
    expect(c).toContain('.log-entry');
  });

  test('should render clickable rows with data-job-id and onclick', () => {
    const c = content();
    expect(c).toContain("data-job-id");
    expect(c).toContain("toggleJobLog(this.dataset.jobId)");
  });

  test('should render expandable detail rows for each job', () => {
    const c = content();
    expect(c).toContain("job-detail-");
    expect(c).toContain("job-log-panel");
  });

  test('should define toggleJobLog function', () => {
    const c = content();
    expect(c).toContain('function toggleJobLog(jobId)');
  });

  test('should define _fetchJobLog function to load job details', () => {
    const c = content();
    expect(c).toContain('function _fetchJobLog(jobId)');
    expect(c).toContain("/api/job/");
  });

  test('should define _renderJobLog function for shared rendering', () => {
    const c = content();
    expect(c).toContain('function _renderJobLog(job)');
  });

  test('should track expanded state to preserve during refresh', () => {
    const c = content();
    expect(c).toContain('expandedJobs');
    expect(c).toContain('wasExpanded');
  });

  test('should use escapeHtml for job description to prevent XSS', () => {
    const c = content();
    expect(c).toContain('escapeHtml(j.description');
  });

  test('should use escapeHtml for log rendering', () => {
    const c = content();
    expect(c).toContain('escapeHtml(job.id');
    expect(c).toContain("escapeHtml(entry.msg");
  });

  test('cancel button should stop event propagation to avoid toggling log', () => {
    const c = content();
    expect(c).toContain('event.stopPropagation()');
  });

  test('active jobs should refresh every 5 seconds for responsiveness', () => {
    const c = content();
    expect(c).toContain('setInterval(refreshJobs, 5000)');
  });
});

// =====================================================
// 4. Companion history page log panel persistence
// =====================================================

test.describe('Companion history page log panel persistence during refresh', () => {
  const content = () => readFile('companion/templates/history.html');

  test('should track expanded state in expandedJobs object', () => {
    const c = content();
    expect(c).toContain('var expandedJobs = {}');
  });

  test('toggleJobLog should set expandedJobs[jobId] = true on expand', () => {
    const c = content();
    expect(c).toContain('expandedJobs[jobId] = true');
  });

  test('toggleJobLog should set expandedJobs[jobId] = false on collapse', () => {
    const c = content();
    expect(c).toContain('expandedJobs[jobId] = false');
  });

  test('refreshJobs should remember expanded rows before rebuilding', () => {
    const c = content();
    expect(c).toContain('wasExpanded');
  });

  test('refreshJobs should restore expanded state after rebuilding', () => {
    const c = content();
    // Detail rows should be rendered with display based on wasExpanded
    expect(c).toContain("isOpen ? '' : 'none'");
  });

  test('refreshJobs should re-fetch logs for previously expanded rows', () => {
    const c = content();
    // After rebuilding, should call _fetchJobLog for each expanded row
    expect(c).toContain("_fetchJobLog(id)");
  });

  test('should use shared _renderJobLog for consistent log display', () => {
    const c = content();
    expect(c).toContain('function _renderJobLog(job)');
  });

  test('should use shared _fetchJobLog for API calls', () => {
    const c = content();
    expect(c).toContain('function _fetchJobLog(jobId)');
  });

  test('should escape job description in table rows', () => {
    const c = content();
    expect(c).toContain("escapeHtml(j.description");
  });

  test('should escape job ID and status in log panel', () => {
    const c = content();
    expect(c).toContain("escapeHtml(job.id");
    expect(c).toContain("escapeHtml(job.status");
  });
});

// =====================================================
// 5. Capability guide removed from dashboard
// =====================================================

test.describe('Companion dashboard capability guide removed', () => {
  const content = () => readFile('companion/templates/index.html');

  test('should NOT contain Hardware Readiness section', () => {
    const c = content();
    expect(c).not.toContain('hw-readiness');
    expect(c).not.toContain('hw-readiness-icon');
    expect(c).not.toContain('hw-readiness-title');
    expect(c).not.toContain('hw-readiness-detail');
  });

  test('should NOT contain Acceleration & Capabilities header', () => {
    const c = content();
    expect(c).not.toContain('Acceleration &amp; Capabilities');
  });

  test('should NOT contain updateHwReadiness function', () => {
    const c = content();
    expect(c).not.toContain('updateHwReadiness');
  });

  test('should NOT contain hw-mode, hw-methods, hw-encoders, hw-decoders elements', () => {
    const c = content();
    expect(c).not.toContain('id="hw-mode"');
    expect(c).not.toContain('id="hw-methods"');
    expect(c).not.toContain('id="hw-encoders"');
    expect(c).not.toContain('id="hw-decoders"');
  });

  test('should still contain Server Status section', () => {
    const c = content();
    expect(c).toContain('System Overview');
    expect(c).toContain('id="version"');
    expect(c).toContain('id="active-jobs"');
  });

  test('should still contain Active Jobs section', () => {
    const c = content();
    expect(c).toContain('Active Jobs');
    expect(c).toContain('id="jobs-body"');
  });
});

// =====================================================
// 6. hls-player.js wrapper._closeHandler fix
// =====================================================

test.describe('hls-player.js quality menu wrapper._closeHandler fix', () => {
  const content = () => readFile('js/hls-player.js');

  test('wrapper should be created before _closeHandler is assigned to it', () => {
    const c = content();
    const wrapperCreation = c.indexOf("var wrapper = document.createElement('div')");
    const closeHandlerAssignment = c.indexOf('wrapper._closeHandler = _closeHandler');
    expect(wrapperCreation).toBeGreaterThan(-1);
    expect(closeHandlerAssignment).toBeGreaterThan(-1);
    // wrapper must be created BEFORE _closeHandler is assigned
    expect(wrapperCreation).toBeLessThan(closeHandlerAssignment);
  });

  test('wrapper should have aw-quality-wrapper class', () => {
    const c = content();
    expect(c).toContain("wrapper.className = 'aw-quality-wrapper'");
  });

  test('existing wrapper removal should check for _closeHandler', () => {
    const c = content();
    expect(c).toContain('existing._closeHandler');
  });

  test('_closeHandler should close the quality menu on outside click', () => {
    const c = content();
    expect(c).toContain("document.addEventListener('click', _closeHandler)");
  });
});
