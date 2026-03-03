/**
 * Tests for SDK-based presigned URL generation and streaming upload proxy.
 *
 * Verifies:
 * 1. Companion server /api/presign endpoint (boto3 SDK)
 * 2. generatePresignedUploadUrlViaSdk in lib/rustfs_storage.php
 * 3. streamUploadToRustFS in lib/rustfs_storage.php
 * 4. Streaming upload proxy api/upload.php
 * 5. Updated JS upload flow with proxy fallback
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Companion /api/presign endpoint
// =====================================================

test.describe('Companion /api/presign endpoint', () => {
  test('should define /api/presign route in companion app.py', () => {
    const content = readFile('companion/app.py');
    expect(content).toContain('/api/presign');
    expect(content).toContain('def presign_upload');
  });

  test('should require API key authentication', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def presign_upload');
    const funcEnd = content.indexOf('\n@app.route', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('_require_api_key');
  });

  test('should use boto3 generate_presigned_url', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def presign_upload');
    const funcEnd = content.indexOf('\n@app.route', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('generate_presigned_url');
    expect(funcBody).toContain('put_object');
  });

  test('should validate object_key is required', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def presign_upload');
    const funcEnd = content.indexOf('\n@app.route', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('object_key');
    expect(funcBody).toContain('required');
  });

  test('should return success, url, and object_key', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def presign_upload');
    const funcEnd = content.indexOf('\n@app.route', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('"success"');
    expect(funcBody).toContain('"url"');
    expect(funcBody).toContain('"object_key"');
  });

  test('should handle S3 client not configured', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def presign_upload');
    const funcEnd = content.indexOf('\n@app.route', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('not s3');
    expect(funcBody).toContain('not configured');
  });
});

// =====================================================
// 2. generatePresignedUploadUrlViaSdk in rustfs_storage.php
// =====================================================

test.describe('generatePresignedUploadUrlViaSdk function', () => {
  test('should exist in lib/rustfs_storage.php', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function generatePresignedUploadUrlViaSdk(');
  });

  test('should accept $pdo parameter for companion settings lookup', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toMatch(/function generatePresignedUploadUrlViaSdk\(\$pdo/);
  });

  test('should call companion /api/presign endpoint', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrlViaSdk(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('/api/presign');
    expect(funcBody).toContain('gameplan_companion_url');
    expect(funcBody).toContain('X-API-Key');
  });

  test('should fall back to local PHP presign on companion failure', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrlViaSdk(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('generatePresignedUploadUrl(');
    expect(funcBody).toContain('falling back to local PHP presign');
  });

  test('should log companion presign success', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrlViaSdk(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('via companion SDK');
  });
});

// =====================================================
// 3. streamUploadToRustFS in rustfs_storage.php
// =====================================================

test.describe('streamUploadToRustFS function', () => {
  test('should exist in lib/rustfs_storage.php', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function streamUploadToRustFS(');
  });

  test('should accept input_stream and content_length parameters', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toMatch(/function streamUploadToRustFS\(\$settings,\s*\$input_stream,\s*\$content_length/);
  });

  test('should use CURLOPT_INFILE for streaming', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function streamUploadToRustFS(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('CURLOPT_INFILE');
    expect(funcBody).toContain('CURLOPT_INFILESIZE');
    expect(funcBody).toContain('CURLOPT_UPLOAD');
  });

  test('should use UNSIGNED-PAYLOAD for streaming', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function streamUploadToRustFS(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('UNSIGNED-PAYLOAD');
  });

  test('should check isRustFSConfigured', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function streamUploadToRustFS(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('isRustFSConfigured');
  });
});

// =====================================================
// 4. Streaming upload proxy api/upload.php
// =====================================================

test.describe('Streaming upload proxy api/upload.php', () => {
  test('should exist', () => {
    expect(fs.existsSync(path.join(ROOT, 'api/upload.php'))).toBe(true);
  });

  test('should only accept PUT method', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain("REQUEST_METHOD");
    expect(content).toContain("'PUT'");
    expect(content).toContain('405');
  });

  test('should validate object key from query string', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain("_GET['key']");
    expect(content).toContain('Missing required parameter');
  });

  test('should prevent path traversal', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain('..');
    expect(content).toContain('Invalid key');
  });

  test('should require session authentication', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain('session_start');
    expect(content).toContain('logged_in');
    expect(content).toContain('user_id');
  });

  test('should validate upload token via X-Upload-Token header', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain('X_UPLOAD_TOKEN');
    expect(content).toContain('upload_proxy_token');
    expect(content).toContain('hash_equals');
  });

  test('should enforce 10GB size limit', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain('10 * 1024 * 1024 * 1024');
    expect(content).toContain('413');
  });

  test('should stream using php://input', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain("php://input");
    expect(content).toContain('streamUploadToRustFS');
  });

  test('should return JSON success with object_key', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain("'success'");
    expect(content).toContain("'object_key'");
    expect(content).toContain("'proxy_url'");
  });
});

// =====================================================
// 5. Updated JS upload flow with proxy fallback
// =====================================================

test.describe('JS upload flow includes streaming proxy fallback', () => {
  test('video_record_athlete.php should extract proxy_upload_url from Step 1 response', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('proxy_upload_url');
    expect(content).toContain('proxy_token');
  });

  test('video_record_athlete.php should try proxy before legacy fallback', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('streaming proxy');
    expect(content).toContain('X-Upload-Token');
    expect(content).toContain('proxyUploadUrl');
    expect(content).toContain('proxyToken');
  });

  test('video_coach_reviews.php should have proxy fallback', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('proxy_upload_url');
    expect(content).toContain('proxy_token');
    expect(content).toContain('X-Upload-Token');
  });

  test('gp_film_room.php should have proxy fallback', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('proxy_upload_url');
    expect(content).toContain('proxy_token');
    expect(content).toContain('X-Upload-Token');
  });

  test('film_room.php should have proxy fallback', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('proxy_upload_url');
    expect(content).toContain('proxy_token');
    expect(content).toContain('X-Upload-Token');
  });

  test('video_record_drill.php should have proxy fallback', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain('proxy_upload_url');
    expect(content).toContain('proxy_token');
    expect(content).toContain('X-Upload-Token');
  });
});

// =====================================================
// 6. PHP handlers return proxy URL and token
// =====================================================

test.describe('PHP handlers return proxy upload URL', () => {
  test('handleGetVideoUploadUrl should return proxy_upload_url', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'proxy_upload_url'");
    expect(funcBody).toContain("'proxy_token'");
    expect(funcBody).toContain('upload_proxy_token');
  });

  test('handleGetAthleteUploadUrl should return proxy_upload_url', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'proxy_upload_url'");
    expect(funcBody).toContain("'proxy_token'");
    expect(funcBody).toContain('upload_proxy_token');
  });

  test('both handlers should use local PHP presign generation (not companion)', () => {
    const content = readFile('process_video.php');
    const func1Start = content.indexOf('function handleGetAthleteUploadUrl()');
    const func1End = content.indexOf('\nfunction ', func1Start + 1);
    const func1Body = content.substring(func1Start, func1End > -1 ? func1End : undefined);

    const func2Start = content.indexOf('function handleGetVideoUploadUrl()');
    const func2End = content.indexOf('\nfunction ', func2Start + 1);
    const func2Body = content.substring(func2Start, func2End > -1 ? func2End : undefined);

    // Presigned URLs are generated locally by PHP — the companion is only for transcoding
    expect(func1Body).toContain('generatePresignedUploadUrl(');
    expect(func1Body).not.toContain('generatePresignedUploadUrlViaSdk');
    expect(func2Body).toContain('generatePresignedUploadUrl(');
    expect(func2Body).not.toContain('generatePresignedUploadUrlViaSdk');
  });
});
