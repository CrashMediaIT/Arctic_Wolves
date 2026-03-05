/**
 * Tests for Video Delete Response Handling
 *
 * Verifies:
 * 1. Delete fetch handlers check r.ok before parsing JSON (prevents HTML parse errors)
 * 2. All delete endpoints use the safe response pattern (read text on error, then parse)
 * 3. checkCsrfToken detects fetch API requests via Sec-Fetch-Dest header
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Coach Reviews delete handler checks r.ok
// =====================================================

test.describe('video_coach_reviews.php delete response handling', () => {

  test('delete fetch should check r.ok before parsing JSON', () => {
    const content = readFile('views/video_coach_reviews.php');
    // Find the delete handler section (between confirmDeleteBtn click and its .finally)
    const deleteSection = content.slice(
      content.indexOf("formData.append('action', 'delete_video')"),
      content.indexOf("confirmDeleteBtn.disabled = false")
    );
    expect(deleteSection).toContain('!r.ok');
    expect(deleteSection).toContain('r.text()');
  });

  test('delete fetch should attempt to parse JSON error from non-ok response body', () => {
    const content = readFile('views/video_coach_reviews.php');
    const deleteSection = content.slice(
      content.indexOf("formData.append('action', 'delete_video')"),
      content.indexOf("confirmDeleteBtn.disabled = false")
    );
    expect(deleteSection).toContain('JSON.parse');
    expect(deleteSection).toContain('j.error');
  });

  test('delete fetch should guard r.json() call with r.ok check', () => {
    const content = readFile('views/video_coach_reviews.php');
    const deleteSection = content.slice(
      content.indexOf("formData.append('action', 'delete_video')"),
      content.indexOf("confirmDeleteBtn.disabled = false")
    );
    // The r.ok check must appear before the r.json() call
    const okIndex = deleteSection.indexOf('!r.ok');
    const jsonIndex = deleteSection.indexOf('r.json()');
    expect(okIndex).toBeGreaterThan(-1);
    expect(jsonIndex).toBeGreaterThan(-1);
    expect(okIndex).toBeLessThan(jsonIndex);
  });
});

// =====================================================
// 2. Drill Review delete handler checks r.ok
// =====================================================

test.describe('video_drill_review.php delete response handling', () => {

  test('delete fetch should check r.ok before parsing JSON', () => {
    const content = readFile('views/video_drill_review.php');
    const deleteSection = content.slice(
      content.indexOf("formData.append('action', 'delete_video')"),
      content.indexOf("formData.append('action', 'delete_video')") + 800
    );
    expect(deleteSection).toContain('!r.ok');
    expect(deleteSection).toContain('r.text()');
  });

  test('delete fetch should attempt to parse JSON error from non-ok response body', () => {
    const content = readFile('views/video_drill_review.php');
    const deleteSection = content.slice(
      content.indexOf("formData.append('action', 'delete_video')"),
      content.indexOf("formData.append('action', 'delete_video')") + 800
    );
    expect(deleteSection).toContain('JSON.parse');
    expect(deleteSection).toContain('j.error');
  });
});

// =====================================================
// 3. PWA video delete handler checks r.ok
// =====================================================

test.describe('pwa/video.php delete response handling', () => {

  test('delete fetch should check r.ok before parsing JSON', () => {
    const content = readFile('views/pwa/video.php');
    const funcStart = content.indexOf('function mVidDelete');
    const funcEnd = content.indexOf('\nfunction', funcStart + 1);
    const func = content.slice(funcStart, funcEnd);
    expect(func).toContain('!r.ok');
    expect(func).toContain('r.text()');
  });

  test('delete fetch should attempt to parse JSON error from non-ok response body', () => {
    const content = readFile('views/pwa/video.php');
    const funcStart = content.indexOf('function mVidDelete');
    const funcEnd = content.indexOf('\nfunction', funcStart + 1);
    const func = content.slice(funcStart, funcEnd);
    expect(func).toContain('JSON.parse');
    expect(func).toContain('j.error');
  });
});

// =====================================================
// 4. checkCsrfToken detects fetch API via Sec-Fetch-Dest
// =====================================================

test.describe('checkCsrfToken AJAX detection', () => {

  test('should detect fetch API requests via Sec-Fetch-Dest header', () => {
    const content = readFile('security.php');
    const funcStart = content.indexOf('function checkCsrfToken()');
    const funcEnd = content.indexOf('\n}', funcStart) + 2;
    const func = content.slice(funcStart, funcEnd);
    expect(func).toContain('HTTP_SEC_FETCH_DEST');
    expect(func).toContain("'empty'");
  });

  test('should still detect traditional XHR requests', () => {
    const content = readFile('security.php');
    const funcStart = content.indexOf('function checkCsrfToken()');
    const funcEnd = content.indexOf('\n}', funcStart) + 2;
    const func = content.slice(funcStart, funcEnd);
    expect(func).toContain('HTTP_X_REQUESTED_WITH');
    expect(func).toContain('xmlhttprequest');
  });

  test('should return JSON error for AJAX requests with invalid CSRF', () => {
    const content = readFile('security.php');
    const funcStart = content.indexOf('function checkCsrfToken()');
    const funcEnd = content.indexOf('\n}', funcStart) + 2;
    const func = content.slice(funcStart, funcEnd);
    expect(func).toContain('application/json');
    expect(func).toContain('json_encode');
  });
});
