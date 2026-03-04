/**
 * Tests for companion app.py robust S3 upload/download infrastructure.
 *
 * Validates that the companion S3 functions match the test video upload
 * system's resilience: multipart transfers, retry with exponential backoff,
 * detailed error logging, and transfer config matching the test system
 * (64 MB chunks, 3 concurrent, 5 retries).
 */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const APP_PY = path.resolve(__dirname, '..', 'companion', 'app.py');
const appSrc = fs.readFileSync(APP_PY, 'utf8');

// ---------------------------------------------------------------------------
// Companion S3 transfer config
// ---------------------------------------------------------------------------
test.describe('Companion S3 transfer config matches test upload system', () => {
  test('should import S3TransferConfig from boto3', () => {
    expect(appSrc).toContain('from boto3.s3.transfer import TransferConfig');
  });

  test('should import BotoClientError from botocore', () => {
    expect(appSrc).toContain('from botocore.exceptions import ClientError');
  });

  test('should define _S3_TRANSFER_CONFIG with 64 MB multipart threshold', () => {
    expect(appSrc).toContain('_S3_TRANSFER_CONFIG');
    expect(appSrc).toMatch(/multipart_threshold\s*=\s*64\s*\*\s*1024\s*\*\s*1024/);
  });

  test('should define _S3_TRANSFER_CONFIG with 64 MB chunk size', () => {
    expect(appSrc).toMatch(/multipart_chunksize\s*=\s*64\s*\*\s*1024\s*\*\s*1024/);
  });

  test('should define _S3_TRANSFER_CONFIG with 3 concurrent transfers', () => {
    expect(appSrc).toMatch(/max_concurrency\s*=\s*3/);
  });

  test('should define _S3_MAX_RETRIES = 5 matching test system', () => {
    expect(appSrc).toMatch(/_S3_MAX_RETRIES\s*=\s*5/);
  });

  test('should define _S3_RETRY_BACKOFF with exponential backoff delays', () => {
    expect(appSrc).toMatch(/_S3_RETRY_BACKOFF\s*=\s*\[2,\s*4,\s*8,\s*16,\s*30\]/);
  });

  test('should use adaptive retry mode in BotoConfig', () => {
    expect(appSrc).toContain('"mode": "adaptive"');
  });
});

// ---------------------------------------------------------------------------
// Companion _s3_download with retries
// ---------------------------------------------------------------------------
test.describe('Companion _s3_download robust retry logic', () => {
  test('should retry up to _S3_MAX_RETRIES times', () => {
    expect(appSrc).toMatch(/def _s3_download\(.*\).*->.*bool:/);
    expect(appSrc).toContain('for attempt in range(1, _S3_MAX_RETRIES + 1)');
  });

  test('should HEAD object before download to verify existence', () => {
    // _s3_download should call head_object before download_file
    const downloadFn = appSrc.substring(
      appSrc.indexOf('def _s3_download'),
      appSrc.indexOf('def _s3_upload')
    );
    expect(downloadFn).toContain('head_object');
    expect(downloadFn).toContain('ContentLength');
  });

  test('should use _S3_TRANSFER_CONFIG for multipart downloads', () => {
    const downloadFn = appSrc.substring(
      appSrc.indexOf('def _s3_download'),
      appSrc.indexOf('def _s3_upload')
    );
    expect(downloadFn).toContain('Config=_S3_TRANSFER_CONFIG');
  });

  test('should catch BotoClientError and extract error code/message', () => {
    const downloadFn = appSrc.substring(
      appSrc.indexOf('def _s3_download'),
      appSrc.indexOf('def _s3_upload')
    );
    expect(downloadFn).toContain('BotoClientError');
    expect(downloadFn).toContain('Error');
    expect(downloadFn).toContain('Code');
    expect(downloadFn).toContain('HTTPStatusCode');
  });

  test('should not retry on NoSuchKey or AccessDenied', () => {
    const downloadFn = appSrc.substring(
      appSrc.indexOf('def _s3_download'),
      appSrc.indexOf('def _s3_upload')
    );
    expect(downloadFn).toContain('NoSuchKey');
    expect(downloadFn).toContain('AccessDenied');
    expect(downloadFn).toContain('non-retryable');
  });

  test('should apply exponential backoff between retries', () => {
    const downloadFn = appSrc.substring(
      appSrc.indexOf('def _s3_download'),
      appSrc.indexOf('def _s3_upload')
    );
    expect(downloadFn).toContain('_S3_RETRY_BACKOFF');
    expect(downloadFn).toContain('time.sleep(delay)');
  });

  test('should log attempt number and total retries', () => {
    const downloadFn = appSrc.substring(
      appSrc.indexOf('def _s3_download'),
      appSrc.indexOf('def _s3_upload')
    );
    expect(downloadFn).toContain('attempt %d/%d');
  });

  test('should log file size after successful download', () => {
    const downloadFn = appSrc.substring(
      appSrc.indexOf('def _s3_download'),
      appSrc.indexOf('def _s3_upload')
    );
    expect(downloadFn).toContain('bytes written');
  });
});

// ---------------------------------------------------------------------------
// Companion _s3_upload with retries and multipart
// ---------------------------------------------------------------------------
test.describe('Companion _s3_upload robust retry and multipart logic', () => {
  test('should retry up to _S3_MAX_RETRIES times', () => {
    const uploadFn = appSrc.substring(
      appSrc.indexOf('def _s3_upload'),
      appSrc.indexOf('def _s3_delete')
    );
    expect(uploadFn).toContain('for attempt in range(1, _S3_MAX_RETRIES + 1)');
  });

  test('should use _S3_TRANSFER_CONFIG for multipart uploads', () => {
    const uploadFn = appSrc.substring(
      appSrc.indexOf('def _s3_upload'),
      appSrc.indexOf('def _s3_delete')
    );
    expect(uploadFn).toContain('Config=_S3_TRANSFER_CONFIG');
  });

  test('should log file size before upload', () => {
    const uploadFn = appSrc.substring(
      appSrc.indexOf('def _s3_upload'),
      appSrc.indexOf('def _s3_delete')
    );
    expect(uploadFn).toContain('file_size');
    expect(uploadFn).toContain('os.path.getsize');
  });

  test('should catch BotoClientError and extract error code/message', () => {
    const uploadFn = appSrc.substring(
      appSrc.indexOf('def _s3_upload'),
      appSrc.indexOf('def _s3_delete')
    );
    expect(uploadFn).toContain('BotoClientError');
    expect(uploadFn).toContain('Code');
    expect(uploadFn).toContain('HTTPStatusCode');
  });

  test('should not retry on AccessDenied', () => {
    const uploadFn = appSrc.substring(
      appSrc.indexOf('def _s3_upload'),
      appSrc.indexOf('def _s3_delete')
    );
    expect(uploadFn).toContain('AccessDenied');
    expect(uploadFn).toContain('non-retryable');
  });

  test('should apply exponential backoff between retries', () => {
    const uploadFn = appSrc.substring(
      appSrc.indexOf('def _s3_upload'),
      appSrc.indexOf('def _s3_delete')
    );
    expect(uploadFn).toContain('_S3_RETRY_BACKOFF');
    expect(uploadFn).toContain('time.sleep(delay)');
  });

  test('should log attempt number and total retries', () => {
    const uploadFn = appSrc.substring(
      appSrc.indexOf('def _s3_upload'),
      appSrc.indexOf('def _s3_delete')
    );
    expect(uploadFn).toContain('attempt %d/%d');
  });
});

// ---------------------------------------------------------------------------
// HLS transcode S3 operations log transfer config
// ---------------------------------------------------------------------------
test.describe('HLS transcode S3 operations reference transfer config', () => {
  test('should log transfer config details during download', () => {
    expect(appSrc).toContain('S3 transfer config');
    expect(appSrc).toContain('multipart threshold');
  });

  test('should log transfer config details during upload', () => {
    expect(appSrc).toContain('multipart for files');
    expect(appSrc).toContain('retries per file');
  });

  test('should mention retry count in download failure message', () => {
    expect(appSrc).toContain('attempts — check companion logs');
  });

  test('should mention retry count in upload failure message', () => {
    // The transcode upload failure should reference the retry count
    const hlsStart = appSrc.indexOf('def _hls_transcode_s3');
    expect(hlsStart).toBeGreaterThan(-1);
    const hlsFn = appSrc.substring(hlsStart, hlsStart + 15000);
    expect(hlsFn).toMatch(/Failed to upload.*attempts/);
  });
});
