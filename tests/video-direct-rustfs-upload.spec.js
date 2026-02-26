/**
 * Tests for Direct-to-RustFS Video Upload
 *
 * Verifies the presigned-URL direct upload flow:
 * 1. generatePresignedUploadUrl in lib/rustfs_storage.php
 * 2. get_athlete_upload_url action in process_video.php
 * 3. confirm_athlete_upload action in process_video.php
 * 4. Updated JS in views/video_record_athlete.php for 3-step upload
 * 5. Legacy fallback still works when direct upload is unavailable
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. generatePresignedUploadUrl in rustfs_storage.php
// =====================================================

test.describe('generatePresignedUploadUrl function', () => {
  test('should exist in lib/rustfs_storage.php', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function generatePresignedUploadUrl(');
  });

  test('should accept settings, object_key, content_type, and expires parameters', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toMatch(/function generatePresignedUploadUrl\(\$settings,\s*\$object_key/);
  });

  test('should use AWS Signature V4 query string auth', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('X-Amz-Algorithm');
    expect(funcBody).toContain('AWS4-HMAC-SHA256');
    expect(funcBody).toContain('X-Amz-Credential');
    expect(funcBody).toContain('X-Amz-Date');
    expect(funcBody).toContain('X-Amz-Expires');
    expect(funcBody).toContain('X-Amz-SignedHeaders');
    expect(funcBody).toContain('X-Amz-Signature');
  });

  test('should use UNSIGNED-PAYLOAD for presigned URL', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('UNSIGNED-PAYLOAD');
  });

  test('should return success, url, and object_key', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'success'");
    expect(funcBody).toContain("'url'");
    expect(funcBody).toContain("'object_key'");
  });

  test('should check isRustFSConfigured', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('isRustFSConfigured');
  });
});

// =====================================================
// 2. get_athlete_upload_url action in process_video.php
// =====================================================

test.describe('get_athlete_upload_url action', () => {
  test('should have the action case in process_video.php', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'get_athlete_upload_url':");
    expect(content).toContain('handleGetAthleteUploadUrl()');
  });

  test('should define handleGetAthleteUploadUrl function', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('function handleGetAthleteUploadUrl()');
  });

  test('should validate video title', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'title'");
    expect(funcBody).toContain('Video title is required');
  });

  test('should validate file extension', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('allowed_extensions');
    expect(funcBody).toContain("'mp4'");
    expect(funcBody).toContain("'webm'");
  });

  test('should enforce 10GB file size limit', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('10 * 1024 * 1024 * 1024');
  });

  test('should call generatePresignedUploadUrl', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('generatePresignedUploadUrl(');
  });

  test('should store pending upload in session with nonce', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('pending_video_upload');
    expect(funcBody).toContain('upload_nonce');
    expect(funcBody).toContain('random_bytes');
  });

  test('should return presigned_url and upload_nonce in JSON', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'presigned_url'");
    expect(funcBody).toContain("'upload_nonce'");
    expect(funcBody).toContain('json_encode');
  });
});

// =====================================================
// 3. confirm_athlete_upload action in process_video.php
// =====================================================

test.describe('confirm_athlete_upload action', () => {
  test('should have the action case in process_video.php', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'confirm_athlete_upload':");
    expect(content).toContain('handleConfirmAthleteUpload()');
  });

  test('should define handleConfirmAthleteUpload function', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('function handleConfirmAthleteUpload()');
  });

  test('should validate nonce with hash_equals', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleConfirmAthleteUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('hash_equals');
    expect(funcBody).toContain('upload_nonce');
  });

  test('should verify object exists in RustFS before DB insert', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleConfirmAthleteUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('rustfsObjectExists');
  });

  test('should insert video record into database', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleConfirmAthleteUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('INSERT INTO videos');
    expect(funcBody).toContain('pending_review');
  });

  test('should clean up session after confirmation', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleConfirmAthleteUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("unset($_SESSION['pending_video_upload'])");
  });

  test('should expire sessions older than 2 hours', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleConfirmAthleteUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('7200');
    expect(funcBody).toContain('expired');
  });

  test('should return redirect on success', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleConfirmAthleteUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'redirect'");
    expect(funcBody).toContain('coaches_reviews');
  });
});

// =====================================================
// 4. Simplified upload JS in video_record_athlete.php
//    Uses same single-step POST pattern as drill video upload
// =====================================================

test.describe('Simplified upload JS in video_record_athlete.php', () => {
  test('should have progress bar elements', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('id="uploadProgressOverlay"');
    expect(content).toContain('id="uploadProgressBar"');
    expect(content).toContain('id="uploadProgressPercent"');
    expect(content).toContain('id="uploadProgressStatus"');
  });

  test('should use XHR with upload progress tracking', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('XMLHttpRequest');
    expect(content).toContain('upload.onprogress');
    expect(content).toContain('X-Requested-With');
  });

  test('should POST form data directly to process_video.php', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('new FormData(uploadForm)');
    expect(content).toContain('xhr.open(\'POST\', uploadForm.action');
  });

  test('should prevent default form submission', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('e.preventDefault()');
  });

  test('should handle upload success with redirect', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('response.success');
    expect(content).toContain('window.location.href');
  });

  test('should handle upload errors gracefully', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('xhr.onerror');
    expect(content).toContain('submitBtn.disabled = false');
  });

  test('should show saving to cloud storage status', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('Saving to cloud storage');
  });

  test('should not use complex presigned URL multi-step flow', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).not.toContain('get_athlete_upload_url');
    expect(content).not.toContain('presignedUrl');
    expect(content).not.toContain('confirm_athlete_upload');
    expect(content).not.toContain('fallbackServerUpload');
  });
});

// =====================================================
// 6. Original handleAthleteVideoUpload still exists
// =====================================================

test.describe('Original upload handler preserved for backward compatibility', () => {
  test('handleAthleteVideoUpload function should still exist', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('function handleAthleteVideoUpload()');
  });

  test('athlete_upload_video action should still be routed', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'athlete_upload_video':");
  });
});
