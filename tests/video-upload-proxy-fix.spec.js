/**
 * Tests for Server-Side Proxy Video Upload Fix
 *
 * Verifies that the direct browser-to-S3 PUT (which fails due to CORS)
 * has been replaced with a server-side proxy upload flow:
 *
 * 1. process_video.php has a proxy_video_upload action and handler
 * 2. All upload views POST to the proxy instead of PUTting to presigned URL
 * 3. The proxy handler validates nonce, uploads via RustFS, returns nonce
 * 4. Legacy fallback is still available as a safety net
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. process_video.php proxy_video_upload action
// =====================================================

test.describe('proxy_video_upload action in process_video.php', () => {
  test('should have proxy_video_upload case in switch', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'proxy_video_upload':");
    expect(content).toContain('handleProxyVideoUpload()');
  });

  test('should define handleProxyVideoUpload function', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('function handleProxyVideoUpload()');
  });

  test('should validate upload_nonce from POST', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleProxyVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('upload_nonce');
    expect(funcBody).toContain('hash_equals');
  });

  test('should check both session keys for pending upload', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleProxyVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('pending_video_upload_general');
    expect(funcBody).toContain('pending_video_upload');
  });

  test('should accept uploaded file from $_FILES', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleProxyVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("$_FILES['video_file']");
    expect(funcBody).toContain('UPLOAD_ERR_OK');
  });

  test('should use RustFS upload functions for server-side upload', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleProxyVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('uploadLargeFileToRustFS');
    expect(funcBody).toContain('uploadToRustFS');
    expect(funcBody).toContain('getRustFSSettings');
  });

  test('should return JSON with success and upload_nonce', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleProxyVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'success'");
    expect(funcBody).toContain("'upload_nonce'");
    expect(funcBody).toContain('json_encode');
  });

  test('should expire sessions older than 2 hours', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleProxyVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('7200');
    expect(funcBody).toContain('expired');
  });
});

// =====================================================
// 2. Upload views use proxy instead of direct PUT
// =====================================================

test.describe('video_record_athlete.php uses proxy upload', () => {
  test('should POST to process_video.php with proxy_video_upload action', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain("proxyData.append('action', 'proxy_video_upload')");
  });

  test('should NOT have direct PUT to presigned URL', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).not.toContain("xhr.open('PUT', presignedUrl");
  });

  test('should send video_file in proxy FormData', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain("proxyData.append('video_file', videoFile)");
  });

  test('should send upload_nonce in proxy FormData', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain("proxyData.append('upload_nonce', uploadNonce)");
  });

  test('should still have legacy fallback', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('athlete_upload_video');
    expect(content).toContain('Retrying via server');
  });
});

test.describe('video_coach_reviews.php uses proxy upload', () => {
  test('should POST to process_video.php with proxy_video_upload action', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain("proxyData.append('action', 'proxy_video_upload')");
  });

  test('should NOT have direct PUT to presigned URL', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).not.toContain("xhr.open('PUT', presignedUrl");
  });
});

test.describe('gp_film_room.php uses proxy upload', () => {
  test('should POST to process_video.php with proxy_video_upload action', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain("proxyData.append('action', 'proxy_video_upload')");
  });

  test('should NOT have direct PUT to presigned URL', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).not.toContain("xhr.open('PUT', presignedUrl");
  });
});

test.describe('film_room.php uses proxy upload', () => {
  test('should POST to process_video.php with proxy_video_upload action', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain("proxyData.append('action', 'proxy_video_upload')");
  });

  test('should NOT have direct PUT to presigned URL', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).not.toContain("xhr.open('PUT', presignedUrl");
  });
});

test.describe('pwa/video_record_drill.php uses proxy upload', () => {
  test('should POST to process_video.php with proxy_video_upload action', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).toContain("proxyData.append('action', 'proxy_video_upload')");
  });

  test('should NOT have direct PUT to presigned URL', () => {
    const content = readFile('views/pwa/video_record_drill.php');
    expect(content).not.toContain("xhr.open('PUT', presignedUrl");
  });
});

// =====================================================
// 3. Presigned URL generation still exists (for session setup)
// =====================================================

test.describe('Presigned URL generation preserved for session setup', () => {
  test('get_video_upload_url action should still exist', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'get_video_upload_url':");
  });

  test('get_athlete_upload_url action should still exist', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'get_athlete_upload_url':");
  });

  test('confirm_video_upload action should still exist', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'confirm_video_upload':");
  });

  test('confirm_athlete_upload action should still exist', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("case 'confirm_athlete_upload':");
  });
});
