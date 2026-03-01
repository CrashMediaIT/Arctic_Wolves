/**
 * Tests for Direct-to-RustFS Video Upload with CORS Configuration
 *
 * Verifies that uploads go directly from the browser to RustFS (not through PHP proxy):
 *
 * 1. lib/rustfs_storage.php has ensureRustFSBucketCors() to set CORS on the bucket
 * 2. generatePresignedUploadUrl() calls ensureRustFSBucketCors() automatically
 * 3. process_settings.php applies CORS when RustFS settings are saved
 * 4. All upload views PUT directly to the presigned URL (no proxy)
 * 5. Legacy fallback is still available as a safety net
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. ensureRustFSBucketCors in lib/rustfs_storage.php
// =====================================================

test.describe('ensureRustFSBucketCors in lib/rustfs_storage.php', () => {
  test('should define ensureRustFSBucketCors function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function ensureRustFSBucketCors(');
  });

  test('should use PutBucketCors S3 API (/?cors endpoint)', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('/?cors');
    expect(funcBody).toContain('CORSConfiguration');
    expect(funcBody).toContain('CORSRule');
  });

  test('should allow PUT and GET methods in CORS policy', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('<AllowedMethod>PUT</AllowedMethod>');
    expect(funcBody).toContain('<AllowedMethod>GET</AllowedMethod>');
  });

  test('should allow all origins for presigned URL uploads', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('<AllowedOrigin>*</AllowedOrigin>');
  });

  test('should allow all headers in CORS policy', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('<AllowedHeader>*</AllowedHeader>');
  });

  test('should use static cache to avoid repeat calls', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('static $done');
  });

  test('should sign request with signRustFSRequest', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('signRustFSRequest');
  });
});

// =====================================================
// 2. generatePresignedUploadUrl calls ensureRustFSBucketCors
// =====================================================

test.describe('generatePresignedUploadUrl calls ensureRustFSBucketCors', () => {
  test('should call ensureRustFSBucketCors before generating URL', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('ensureRustFSBucketCors');
  });
});

// =====================================================
// 3. process_settings.php applies CORS on save
// =====================================================

test.describe('process_settings.php applies CORS when RustFS settings are saved', () => {
  test('should call ensureRustFSBucketCors in update_rustfs case', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'update_rustfs':");
    const caseEnd = content.indexOf("case '", caseStart + 20);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('ensureRustFSBucketCors');
  });
});

// =====================================================
// 4. All upload views PUT directly to presigned URL
// =====================================================

test.describe('video_record_athlete.php uses direct PUT', () => {
  test('should PUT directly to presigned URL', () => {
    const content = readFile('views/video_record_athlete.php');
    // The presigned URL is passed to a PUT helper; either direct or via xhrPut
    expect(content).toMatch(/xhr\.open\('PUT',\s*(presignedUrl|url)/);
    expect(content).toContain('presigned_url');
  });

  test('should NOT use proxy_video_upload', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).not.toContain('proxy_video_upload');
  });

  test('should set Content-Type header on PUT', () => {
    const content = readFile('views/video_record_athlete.php');
    // Content-Type is set either directly or via headers object
    expect(content).toContain('Content-Type');
    expect(content).toContain('contentType');
  });

  test('should still have legacy fallback', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('athlete_upload_video');
    expect(content).toContain('Retrying via server');
  });
});

test.describe('video_coach_reviews.php uses direct PUT', () => {
  test('should PUT directly to presigned URL', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain("xhr.open('PUT', presignedUrl");
  });

  test('should NOT use proxy_video_upload', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).not.toContain('proxy_video_upload');
  });
});

test.describe('gp_film_room.php uses direct PUT', () => {
  test('should PUT directly to presigned URL', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain("xhr.open('PUT', presignedUrl");
  });

  test('should NOT use proxy_video_upload', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).not.toContain('proxy_video_upload');
  });
});

test.describe('film_room.php uses direct PUT', () => {
  test('should PUT directly to presigned URL', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain("xhr.open('PUT', presignedUrl");
  });

  test('should NOT use proxy_video_upload', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).not.toContain('proxy_video_upload');
  });
});

test.describe('pwa/video_record_drill.php uses direct PUT', () => {
  test('should PUT directly to presigned URL', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain("xhr.open('PUT', presignedUrl");
  });

  test('should NOT use proxy_video_upload', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).not.toContain('proxy_video_upload');
  });
});

// =====================================================
// 5. No proxy_video_upload handler in process_video.php
// =====================================================

test.describe('process_video.php has no proxy handler', () => {
  test('should NOT have proxy_video_upload case', () => {
    const content = readFile('process_video.php');
    expect(content).not.toContain("case 'proxy_video_upload':");
  });

  test('should NOT have handleProxyVideoUpload function', () => {
    const content = readFile('process_video.php');
    expect(content).not.toContain('function handleProxyVideoUpload()');
  });

  test('should still have presigned URL endpoints', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'get_video_upload_url':");
    expect(content).toContain("case 'confirm_video_upload':");
    expect(content).toContain("case 'get_athlete_upload_url':");
    expect(content).toContain("case 'confirm_athlete_upload':");
  });
});

