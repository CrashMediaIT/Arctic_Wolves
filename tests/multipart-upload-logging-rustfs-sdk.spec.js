/**
 * Tests for Multipart Upload Support, Comprehensive Upload Logging,
 * and RustFS SDK Alignment
 *
 * Verifies:
 * 1. api/upload.php uses multipart upload for large files (>64MB) per RustFS SDK
 * 2. lib/rustfs_storage.php has multipartStreamUploadToRustFS function
 * 3. Companion app has multipart upload endpoints (create, presign-part, complete, abort)
 * 4. Comprehensive upload logging with ErrorLogger throughout the upload flow
 * 5. Client-side error detail logging in upload views
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. api/upload.php multipart upload for large files
// =====================================================

test.describe('api/upload.php multipart upload support', () => {
  const content = () => readFile('api/upload.php');

  test('should define MULTIPART_THRESHOLD constant for large file detection', () => {
    expect(content()).toContain('MULTIPART_THRESHOLD');
    // 64 MB threshold
    expect(content()).toContain('64 * 1024 * 1024');
  });

  test('should define MULTIPART_PART_SIZE constant', () => {
    expect(content()).toContain('MULTIPART_PART_SIZE');
    // 16 MB part size
    expect(content()).toContain('16 * 1024 * 1024');
  });

  test('should use multipartStreamUploadToRustFS for files above threshold', () => {
    const c = content();
    expect(c).toContain('multipartStreamUploadToRustFS');
    expect(c).toContain('MULTIPART_THRESHOLD');
  });

  test('should use streamUploadToRustFS for files below threshold', () => {
    expect(content()).toContain('streamUploadToRustFS');
  });

  test('should include ErrorLogger for upload lifecycle logging', () => {
    const c = content();
    expect(c).toContain('ErrorLogger::info');
    expect(c).toContain('ErrorLogger::error');
    expect(c).toContain('ErrorLogger::warning');
  });

  test('should log upload start with size, user, and key', () => {
    const c = content();
    expect(c).toContain('starting upload');
    expect(c).toContain('size=');
    expect(c).toContain('user=');
  });

  test('should log upload completion with elapsed time', () => {
    const c = content();
    expect(c).toContain('completed');
    expect(c).toContain('elapsed=');
  });

  test('should log upload failure with error details', () => {
    const c = content();
    expect(c).toContain('FAILED');
    expect(c).toContain('error=');
  });

  test('should require error_logger.php', () => {
    expect(content()).toContain("require_once __DIR__ . '/../error_logger.php'");
  });
});

// =====================================================
// 2. multipartStreamUploadToRustFS function
// =====================================================

test.describe('multipartStreamUploadToRustFS in rustfs_storage.php', () => {
  const content = () => readFile('lib/rustfs_storage.php');

  test('should define multipartStreamUploadToRustFS function', () => {
    expect(content()).toContain('function multipartStreamUploadToRustFS(');
  });

  test('should accept settings, input_stream, content_length, object_key, content_type, and part_size params', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcSig = c.substring(funcStart, c.indexOf(')', funcStart) + 1);
    expect(funcSig).toContain('$settings');
    expect(funcSig).toContain('$input_stream');
    expect(funcSig).toContain('$content_length');
    expect(funcSig).toContain('$object_key');
    expect(funcSig).toContain('$content_type');
    expect(funcSig).toContain('$part_size');
  });

  test('should enforce minimum 5MB part size per S3 spec', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain('5 * 1024 * 1024');
  });

  test('should implement CreateMultipartUpload (Step 1)', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain("'POST', 'uploads=");
    expect(funcBody).toContain('UploadId');
  });

  test('should implement UploadPart (Step 2) with part numbers', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain('partNumber=');
    expect(funcBody).toContain('uploadId=');
    expect(funcBody).toContain('ETag');
  });

  test('should implement CompleteMultipartUpload (Step 3)', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain('CompleteMultipartUpload');
    expect(funcBody).toContain('<Part>');
    expect(funcBody).toContain('<PartNumber>');
    expect(funcBody).toContain('<ETag>');
  });

  test('should abort multipart upload on failure', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain("'DELETE', 'uploadId=");
    expect(funcBody).toContain('aborted upload_id=');
  });

  test('should log each part upload with progress', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain('part #');
    expect(funcBody).toContain('uploaded');
    expect(funcBody).toContain('total=');
  });

  test('should use AWS Signature V4 for multipart requests', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain('AWS4-HMAC-SHA256');
    expect(funcBody).toContain('aws4_request');
  });

  test('should read input stream in chunks for memory efficiency', () => {
    const c = content();
    const funcStart = c.indexOf('function multipartStreamUploadToRustFS(');
    const funcBody = c.substring(funcStart);
    expect(funcBody).toContain('fread');
    expect(funcBody).toContain('feof');
  });
});

// =====================================================
// 3. streamUploadToRustFS improved logging
// =====================================================

test.describe('streamUploadToRustFS improved logging', () => {
  const content = () => readFile('lib/rustfs_storage.php');

  test('should log start of single PUT upload', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('starting single PUT');
  });

  test('should log cURL error details including errno', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('curl_errno');
    expect(func).toContain('effective_url');
    expect(func).toContain('total_time');
  });

  test('should log HTTP error response body', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('response=');
  });

  test('should log successful upload', () => {
    const c = content();
    const funcStart = c.indexOf('function streamUploadToRustFS(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('SUCCESS');
  });
});

// =====================================================
// 4. generatePresignedUploadUrlViaSdk improved logging
// =====================================================

test.describe('generatePresignedUploadUrlViaSdk improved logging', () => {
  const content = () => readFile('lib/rustfs_storage.php');

  test('should delegate directly to local PHP presign (no companion involvement)', () => {
    const c = content();
    const funcStart = c.indexOf('function generatePresignedUploadUrlViaSdk(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    // Companion should NOT be called for presigned URL generation
    expect(func).not.toContain('/api/presign');
    expect(func).not.toContain('curl_init');
    expect(func).toContain('generatePresignedUploadUrl(');
  });

  test('should log local PHP presign generation', () => {
    const c = content();
    const funcStart = c.indexOf('function generatePresignedUploadUrlViaSdk(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('generating via local PHP');
  });
});

// =====================================================
// 5. Companion multipart upload endpoints
// =====================================================

test.describe('Companion multipart upload endpoints', () => {
  const content = () => readFile('companion/app.py');

  test('should have /api/multipart/create endpoint', () => {
    const c = content();
    expect(c).toContain('"/api/multipart/create"');
    expect(c).toContain('def multipart_create');
  });

  test('should use boto3 create_multipart_upload', () => {
    const c = content();
    const funcStart = c.indexOf('def multipart_create');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('create_multipart_upload');
    expect(func).toContain('UploadId');
  });

  test('should have /api/multipart/presign-part endpoint', () => {
    const c = content();
    expect(c).toContain('"/api/multipart/presign-part"');
    expect(c).toContain('def multipart_presign_part');
  });

  test('should use boto3 generate_presigned_url for upload_part', () => {
    const c = content();
    const funcStart = c.indexOf('def multipart_presign_part');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('generate_presigned_url');
    expect(func).toContain('"upload_part"');
    expect(func).toContain('PartNumber');
    expect(func).toContain('UploadId');
  });

  test('should have /api/multipart/complete endpoint', () => {
    const c = content();
    expect(c).toContain('"/api/multipart/complete"');
    expect(c).toContain('def multipart_complete');
  });

  test('should use boto3 complete_multipart_upload', () => {
    const c = content();
    const funcStart = c.indexOf('def multipart_complete');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('complete_multipart_upload');
    expect(func).toContain('MultipartUpload');
    expect(func).toContain('"Parts"');
  });

  test('should have /api/multipart/abort endpoint', () => {
    const c = content();
    expect(c).toContain('"/api/multipart/abort"');
    expect(c).toContain('def multipart_abort');
  });

  test('should use boto3 abort_multipart_upload', () => {
    const c = content();
    const funcStart = c.indexOf('def multipart_abort');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('abort_multipart_upload');
  });

  test('all multipart endpoints should require API key', () => {
    const c = content();
    const endpoints = ['def multipart_create', 'def multipart_presign_part', 'def multipart_complete', 'def multipart_abort'];
    for (const ep of endpoints) {
      const funcStart = c.indexOf(ep);
      const funcEnd = c.indexOf('\ndef ', funcStart + 1);
      const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
      expect(func).toContain('_require_api_key');
    }
  });

  test('all multipart endpoints should require S3 client', () => {
    const c = content();
    const endpoints = ['def multipart_create', 'def multipart_presign_part', 'def multipart_complete', 'def multipart_abort'];
    for (const ep of endpoints) {
      const funcStart = c.indexOf(ep);
      const funcEnd = c.indexOf('\ndef ', funcStart + 1);
      const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
      expect(func).toContain('_get_s3_client');
    }
  });
});

// =====================================================
// 6. process_video.php upload URL logging
// =====================================================

test.describe('process_video.php upload URL generation logging', () => {
  const content = () => readFile('process_video.php');

  test('should log video upload URL requests with ErrorLogger::info', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('ErrorLogger::info');
    expect(func).toContain('Video upload URL');
  });

  test('should log file size in upload URL requests', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('size=');
  });

  test('should log RustFS endpoint details', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('endpoint=');
    expect(func).toContain('public_endpoint=');
  });

  test('should log presign failures with ErrorLogger::error', () => {
    const c = content();
    const funcStart = c.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('ErrorLogger::error');
    expect(func).toContain('presign failed');
  });
});

// =====================================================
// 7. Client-side error detail logging
// =====================================================

test.describe('Client-side upload error detail logging', () => {
  const content = () => readFile('views/video_record_athlete.php');

  test('should log upload start with URL and file size', () => {
    const c = content();
    expect(c).toContain('[Upload]');
    expect(c).toContain('starting PUT');
    expect(c).toContain('file.size');
  });

  test('should log HTTP error responses with body detail', () => {
    const c = content();
    expect(c).toContain('xhr.responseText');
    expect(c).toContain('console.error');
  });

  test('should log network errors with readyState', () => {
    const c = content();
    expect(c).toContain('readyState');
    expect(c).toContain('network error');
  });

  test('should log connection timeout with URL', () => {
    const c = content();
    expect(c).toContain('connection timeout');
    expect(c).toContain('console.error');
  });

  test('should log fallback to legacy with error stack', () => {
    const c = content();
    expect(c).toContain('Primary upload failed');
    expect(c).toContain('err.stack');
  });
});
