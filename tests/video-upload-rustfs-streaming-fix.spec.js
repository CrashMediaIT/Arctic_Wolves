/**
 * Tests for Video Upload RustFS Streaming Fix
 *
 * Verifies fixes for:
 * 1. uploadLargeFileToRustFS includes Content-Length in signed headers and curl headers
 * 2. uploadLargeFileToRustFS disables Expect: 100-continue to prevent S3 upload failures
 * 3. uploadLargeFileToRustFS includes response body in error messages for debugging
 * 4. All video upload handlers check persistUploadedFile success before DB insert
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. uploadLargeFileToRustFS signing includes Content-Length
// =====================================================

test.describe('uploadLargeFileToRustFS signed headers', () => {
  test('should include content-length in headers_to_sign', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function uploadLargeFileToRustFS(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // content-length must be in the signed headers array
    expect(funcBody).toContain("'content-length'");
    // Verify it's in the headers_to_sign block (associated with file_size)
    expect(funcBody).toMatch(/'content-length'\s*=>\s*\(string\)\$file_size/);
  });

  test('should include Content-Length in curl headers', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function uploadLargeFileToRustFS(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // Content-Length must be in the curl_headers array
    expect(funcBody).toMatch(/'Content-Length:\s*'\s*\.\s*\$file_size/);
  });
});

// =====================================================
// 2. Expect: 100-continue is disabled for streaming uploads
// =====================================================

test.describe('uploadLargeFileToRustFS disables Expect header', () => {
  test('should include empty Expect header to prevent 100-continue', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function uploadLargeFileToRustFS(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // An empty Expect header disables curl's automatic 100-continue
    expect(funcBody).toContain("'Expect: '");
  });
});

// =====================================================
// 3. Error messages include response body for debugging
// =====================================================

test.describe('uploadLargeFileToRustFS error messages include response', () => {
  test('should include response body in HTTP error exception', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function uploadLargeFileToRustFS(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // Error message should include the response body for debugging
    expect(funcBody).toContain('$response');
    expect(funcBody).toMatch(/streaming upload failed.*Response/);
  });
});

// =====================================================
// 4. All video upload handlers check persist success
// =====================================================

test.describe('Video upload handlers validate RustFS upload success', () => {
  test('handleVideoUpload should check persist success before DB insert', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("persistUploadedFile(");
    expect(funcBody).toContain("!$persist['success']");
  });

  test('handleAthleteVideoUpload should check persist success before DB insert', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleAthleteVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("persistUploadedFile(");
    expect(funcBody).toContain("!$persist['success']");
  });

  test('handleDrillVideoUpload should check persist success before DB insert', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleDrillVideoUpload()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("persistUploadedFile(");
    expect(funcBody).toContain("!$persist['success']");
  });

  test('handleUploadVideoSource should check persist success before DB insert', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleUploadVideoSource()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("persistUploadedFile(");
    expect(funcBody).toContain("!$persist['success']");
  });

  test('persist success check should throw exception on failure', () => {
    const content = readFile('process_video.php');
    // All four handlers should have the same error message pattern
    const matches = content.match(/throw new Exception\('Video upload to storage failed/g);
    expect(matches).not.toBeNull();
    expect(matches.length).toBe(4);
  });
});
