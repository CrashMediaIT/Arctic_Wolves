/**
 * Tests for General-Purpose Video Upload Endpoints and Companion UI Views
 *
 * Verifies:
 * 1. get_video_upload_url and confirm_video_upload endpoints exist and handle all types
 * 2. All video upload views use direct-to-S3 presigned URL flow
 * 3. Companion app has separate History and Settings pages
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. General-purpose presigned URL endpoints
// =====================================================

test.describe('General-purpose presigned URL endpoints', () => {
  const content = () => readFile('process_video.php');

  test('should route get_video_upload_url action', () => {
    expect(content()).toContain("case 'get_video_upload_url':");
    expect(content()).toContain('handleGetVideoUploadUrl()');
  });

  test('should route confirm_video_upload action', () => {
    expect(content()).toContain("case 'confirm_video_upload':");
    expect(content()).toContain('handleConfirmVideoUpload()');
  });

  test('handleGetVideoUploadUrl should accept upload_type parameter', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("upload_type");
    expect(func).toContain("athlete_video");
    expect(func).toContain("coach_video");
    expect(func).toContain("drill_video");
    expect(func).toContain("video_source");
  });

  test('handleGetVideoUploadUrl should validate file extension', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('allowed_extensions');
    expect(func).toContain('mp4');
    expect(func).toContain('mkv');
    expect(func).toContain('mov');
  });

  test('handleGetVideoUploadUrl should generate presigned URL', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('generatePresignedUploadUrl');
    expect(func).toContain('presigned_url');
    expect(func).toContain('upload_nonce');
  });

  test('handleGetVideoUploadUrl should enforce 10GB limit', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('10 * 1024 * 1024 * 1024');
  });

  test('handleConfirmVideoUpload should handle all upload types', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain("video_source");
    expect(func).toContain("drill_video");
    expect(func).toContain("coach_video");
    expect(func).toContain("athlete_video");
  });

  test('handleConfirmVideoUpload should verify object exists in RustFS', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('rustfsObjectExists');
  });

  test('handleConfirmVideoUpload should trigger HLS transcode for video types', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('triggerHlsTranscode');
  });

  test('handleConfirmVideoUpload should validate nonce with hash_equals', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('hash_equals');
  });
});

// =====================================================
// 2. All upload views use direct-to-S3 presigned URL flow
// =====================================================

test.describe('All upload views use direct-to-S3 presigned URL flow', () => {
  test('video_record_athlete.php should use get_video_upload_url', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('get_video_upload_url');
    expect(content).toContain('confirm_video_upload');
    expect(content).toContain('presigned_url');
  });

  test('video_coach_reviews.php should use presigned URL flow', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('presigned_url');
    expect(content).toContain('confirm_athlete_upload');
  });

  test('gp_film_room.php should use get_video_upload_url', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('get_video_upload_url');
    expect(content).toContain('confirm_video_upload');
    expect(content).toContain('video_source');
    expect(content).toContain('presigned_url');
  });

  test('film_room.php should use get_video_upload_url', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('get_video_upload_url');
    expect(content).toContain('confirm_video_upload');
    expect(content).toContain('presigned_url');
  });

  test('pwa/video_record_drill.php should use get_video_upload_url', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain('get_video_upload_url');
    expect(content).toContain('confirm_video_upload');
    expect(content).toContain('presigned_url');
  });

  test('all upload views include legacy fallback', () => {
    const athleteView = readFile('views/video_record_athlete.php');
    const filmRoom = readFile('views/gameplan/gp_film_room.php');
    const pwa = readFile('views/pwa/video_record_drill.php');
    expect(athleteView).toContain('Fall back to legacy');
    expect(filmRoom).toContain('Fall back to legacy');
    expect(pwa).toContain('Fall back to legacy');
  });
});

// =====================================================
// 3. Companion app separate views
// =====================================================

test.describe('Companion app has separate History and Settings views', () => {
  test('app.py should have /history route', () => {
    const content = readFile('companion/app.py');
    expect(content).toContain('@app.route("/history")');
    expect(content).toContain('def history_page()');
    expect(content).toContain('render_template("history.html")');
  });

  test('app.py should have /settings route', () => {
    const content = readFile('companion/app.py');
    expect(content).toContain('@app.route("/settings")');
    expect(content).toContain('def settings_page()');
    expect(content).toContain('render_template("settings.html")');
  });

  test('history.html template should exist with job filtering', () => {
    const content = readFile('companion/templates/history.html');
    expect(content).toContain('Job History');
    expect(content).toContain('filter');
    expect(content).toContain('completed');
    expect(content).toContain('failed');
    expect(content).toContain('/api/jobs');
  });

  test('settings.html template should exist with config form', () => {
    const content = readFile('companion/templates/settings.html');
    expect(content).toContain('Settings');
    expect(content).toContain('/api/config');
    expect(content).toContain('s3_endpoint');
    expect(content).toContain('hw_accel');
    expect(content).toContain('Save Settings');
  });

  test('index.html dashboard should link to History and Settings', () => {
    const content = readFile('companion/templates/index.html');
    expect(content).toContain('href="/history"');
    expect(content).toContain('href="/settings"');
  });

  test('index.html dashboard should only show active jobs', () => {
    const content = readFile('companion/templates/index.html');
    expect(content).toContain('Active Jobs');
    expect(content).toContain('activeStatuses');
    expect(content).toContain('view history');
  });

  test('history.html should have navigation to Dashboard and Settings', () => {
    const content = readFile('companion/templates/history.html');
    expect(content).toContain('href="/"');
    expect(content).toContain('href="/settings"');
  });

  test('settings.html should have navigation to Dashboard and History', () => {
    const content = readFile('companion/templates/settings.html');
    expect(content).toContain('href="/"');
    expect(content).toContain('href="/history"');
  });
});
