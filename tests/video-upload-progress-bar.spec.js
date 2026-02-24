/**
 * Tests for Video Upload Progress Bar
 *
 * Verifies that video upload forms have a progress bar overlay
 * similar to the practice plan import function, providing visual
 * feedback during file uploads.
 *
 * Files tested:
 * 1. views/video_coach_reviews.php - Coach review video upload
 * 2. views/video_record_athlete.php - Athlete video upload
 * 3. process_video.php - Server-side XHR/JSON support
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Coach Reviews Upload Progress Bar
// =====================================================

test.describe('Video coach reviews upload has progress bar', () => {
  test('should have upload progress overlay HTML', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('id="uploadProgressOverlay"');
    expect(content).toContain('class="upload-progress-overlay"');
    expect(content).toContain('class="upload-progress-card"');
  });

  test('should have progress bar elements', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('id="uploadProgressBar"');
    expect(content).toContain('class="upload-progress-bar-container"');
    expect(content).toContain('class="upload-progress-bar"');
    expect(content).toContain('id="uploadProgressPercent"');
    expect(content).toContain('id="uploadProgressStatus"');
  });

  test('should have progress overlay CSS styles', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('.upload-progress-overlay');
    expect(content).toContain('.upload-progress-card');
    expect(content).toContain('.upload-progress-bar-container');
    expect(content).toContain('.upload-progress-bar');
  });

  test('should use XHR with upload progress tracking', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('XMLHttpRequest');
    expect(content).toContain('xhr.upload.onprogress');
    expect(content).toContain('e.lengthComputable');
    expect(content).toContain('X-Requested-With');
  });

  test('should prevent default form submission', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('e.preventDefault()');
    expect(content).toContain('new FormData(uploadForm)');
  });

  test('should handle upload success with redirect', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('response.success');
    expect(content).toContain('response.redirect');
    expect(content).toContain('window.location.href');
  });

  test('should handle upload errors gracefully', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('xhr.onerror');
    expect(content).toContain('submitBtn.disabled = false');
  });
});

// =====================================================
// 2. Athlete Record/Upload Progress Bar
// =====================================================

test.describe('Video record athlete upload has progress bar', () => {
  test('should have upload progress overlay HTML', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('id="uploadProgressOverlay"');
    expect(content).toContain('class="upload-progress-overlay"');
    expect(content).toContain('class="upload-progress-card"');
  });

  test('should have progress bar elements', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('id="uploadProgressBar"');
    expect(content).toContain('class="upload-progress-bar-container"');
    expect(content).toContain('class="upload-progress-bar"');
    expect(content).toContain('id="uploadProgressPercent"');
    expect(content).toContain('id="uploadProgressStatus"');
  });

  test('should have progress overlay CSS styles', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('.upload-progress-overlay');
    expect(content).toContain('.upload-progress-card');
    expect(content).toContain('.upload-progress-bar-container');
    expect(content).toContain('.upload-progress-bar');
  });

  test('should use XHR with upload progress tracking', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('XMLHttpRequest');
    expect(content).toContain('xhr.upload.onprogress');
    expect(content).toContain('e.lengthComputable');
    expect(content).toContain('X-Requested-With');
  });

  test('should prevent default form submission', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('e.preventDefault()');
    expect(content).toContain('new FormData(uploadForm)');
  });

  test('should handle upload success with redirect', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('response.success');
    expect(content).toContain('response.redirect');
    expect(content).toContain('window.location.href');
  });

  test('should handle upload errors gracefully', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('xhr.onerror');
    expect(content).toContain('submitBtn.disabled = false');
  });
});

// =====================================================
// 3. Server-side XHR support
// =====================================================

test.describe('process_video.php supports XHR responses', () => {
  test('handleAthleteVideoUpload should detect XHR requests', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('HTTP_X_REQUESTED_WITH');
    expect(content).toContain('xmlhttprequest');
  });

  test('handleAthleteVideoUpload should return JSON for XHR', () => {
    const content = readFile('process_video.php');
    // Find the athlete upload function and check it returns JSON for XHR
    const fnStart = content.indexOf('function handleAthleteVideoUpload()');
    const fnEnd = content.indexOf('function handleDrillVideoUpload()');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('json_encode');
    expect(fn).toContain("'success' => true");
    expect(fn).toContain("'redirect'");
  });
});

// =====================================================
// 4. Consistency with practice plan import pattern
// =====================================================

test.describe('Upload progress bar matches practice import pattern', () => {
  test('coach reviews progress overlay should have same structure as practice import', () => {
    const practiceImport = readFile('views/practice_import.php');
    const coachReviews = readFile('views/video_coach_reviews.php');

    // Both should have overlay, card, bar container, bar, and status elements
    expect(practiceImport).toContain('progress-overlay');
    expect(coachReviews).toContain('progress-overlay');
    expect(practiceImport).toContain('progress-card');
    expect(coachReviews).toContain('progress-card');
    expect(practiceImport).toContain('progress-bar-container');
    expect(coachReviews).toContain('progress-bar-container');
    expect(practiceImport).toContain('progress-bar');
    expect(coachReviews).toContain('progress-bar');
    expect(practiceImport).toContain('progress-status');
    expect(coachReviews).toContain('progress-status');
  });

  test('athlete upload progress overlay should have same structure as practice import', () => {
    const practiceImport = readFile('views/practice_import.php');
    const athleteUpload = readFile('views/video_record_athlete.php');

    expect(practiceImport).toContain('progress-overlay');
    expect(athleteUpload).toContain('progress-overlay');
    expect(practiceImport).toContain('progress-card');
    expect(athleteUpload).toContain('progress-card');
    expect(practiceImport).toContain('progress-bar-container');
    expect(athleteUpload).toContain('progress-bar-container');
    expect(practiceImport).toContain('progress-bar');
    expect(athleteUpload).toContain('progress-bar');
    expect(practiceImport).toContain('progress-status');
    expect(athleteUpload).toContain('progress-status');
  });
});
