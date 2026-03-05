/**
 * Tests for large file HLS playback fixes
 *
 * Verifies:
 * 1. api/media.php streams non-m3u8 files (e.g. .ts segments) directly without
 *    buffering the entire response in PHP memory
 * 2. api/media.php includes CORS headers on error responses
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. media.php streaming path for non-m3u8 files
// =====================================================

test.describe('api/media.php streaming path for non-m3u8 files', () => {
  const content = () => readFile('api/media.php');

  test('should have streaming path that checks m3u8_ext !== m3u8', () => {
    const c = content();
    // Non-m3u8 files should take the streaming path
    expect(c).toContain("$m3u8_ext !== 'm3u8'");
  });

  test('should use CURLOPT_WRITEFUNCTION for streaming non-m3u8 files', () => {
    const c = content();
    // The streaming path should use CURLOPT_WRITEFUNCTION instead of CURLOPT_RETURNTRANSFER
    expect(c).toContain('CURLOPT_WRITEFUNCTION');
  });

  test('streaming path should set CURLOPT_RETURNTRANSFER to false', () => {
    const c = content();
    // Before the streaming block, RETURNTRANSFER should be set to false
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const bufferBlock = streamBlock.substring(streamBlock.indexOf('Buffered path'));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    expect(streamOnly).toContain('CURLOPT_RETURNTRANSFER, false');
  });

  test('buffered path for m3u8 should still use CURLOPT_RETURNTRANSFER true', () => {
    const c = content();
    const bufferBlock = c.substring(c.indexOf('Buffered path'));
    expect(bufferBlock).toContain('CURLOPT_RETURNTRANSFER, true');
  });

  test('streaming path should flush output after each chunk', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    expect(streamOnly).toContain('ob_flush');
    expect(streamOnly).toContain('flush()');
  });

  test('streaming path should have a generous timeout (>= 300s)', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    // Should have CURLOPT_TIMEOUT >= 300 for large segment downloads
    expect(streamOnly).toMatch(/CURLOPT_TIMEOUT,\s*300/);
  });

  test('streaming path should determine content type from extension', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    expect(streamOnly).toContain('$guessed_ct');
    expect(streamOnly).toContain("$mime_map");
  });

  test('streaming path should forward Content-Length from S3 response', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    expect(streamOnly).toContain("content-length");
    expect(streamOnly).toContain("Content-Length");
  });

  test('streaming path should handle 404 from S3', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    expect(streamOnly).toContain('404');
    expect(streamOnly).toContain("'File not found'");
  });

  test('streaming path should handle non-200 errors from S3', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    expect(streamOnly).toContain('502');
    expect(streamOnly).toContain("'Storage error'");
  });

  test('streaming path should handle curl connection errors', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    expect(streamOnly).toContain('$stream_error');
    expect(streamOnly).toContain("'Storage connection error'");
  });

  test('m3u8 files should still use the buffered path with rewriting', () => {
    const c = content();
    // After the streaming path exits, m3u8 files should still be buffered + rewritten
    const bufferBlock = c.substring(c.indexOf('Buffered path'));
    expect(bufferBlock).toContain('CURLOPT_RETURNTRANSFER, true');
    expect(bufferBlock).toContain("dirname($object_key_clean)");
    expect(bufferBlock).toContain("explode(\"\\n\", $body)");
    expect(bufferBlock).toContain("'media.php?key=' . rawurlencode($resolved_key)");
  });

  test('MIME map should include ts extension for HLS segments', () => {
    const c = content();
    expect(c).toContain("'ts'");
    expect(c).toContain("video/mp2t");
  });
});

// =====================================================
// 2. media.php CORS headers on error responses
// =====================================================

test.describe('api/media.php CORS headers on error responses', () => {
  const content = () => readFile('api/media.php');

  test('should define _media_cors_headers helper function', () => {
    const c = content();
    expect(c).toContain('function _media_cors_headers()');
    expect(c).toContain("Access-Control-Allow-Origin: *");
  });

  test('buffered path error responses should include CORS headers', () => {
    const c = content();
    // Find the buffered path section
    const bufferBlock = c.substring(c.indexOf('Buffered path'));
    // All error responses in the buffered path should call _media_cors_headers
    const errorBlocks = bufferBlock.split('_media_cors_headers()');
    // At least: curl_error, 404, non-200, and success response = 4 calls
    expect(errorBlocks.length).toBeGreaterThanOrEqual(5);
  });

  test('streaming path error responses should include CORS headers', () => {
    const c = content();
    const streamBlock = c.substring(c.indexOf("$m3u8_ext !== 'm3u8'"));
    const streamOnly = streamBlock.substring(0, streamBlock.indexOf('Buffered path'));
    // CORS headers should be in the streaming path for errors and success
    expect(streamOnly).toContain('_media_cors_headers()');
  });
});
