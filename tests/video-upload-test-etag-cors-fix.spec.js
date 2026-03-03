/**
 * Tests for Video Upload Test ETag/CORS Fix
 *
 * Verifies:
 * 1. ensureCors() in process_video_test.php includes Content-MD5 header
 *    (required by S3 spec for PutBucketCors)
 * 2. ensureCors() CORS XML includes POST and DELETE methods (matching production)
 * 3. uploadPart() in admin_video_test.php handles null ETag gracefully
 *    by failing fast with a clear error instead of collecting null ETags
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. ensureCors Content-MD5 header in process_video_test.php
// =====================================================

test.describe('ensureCors CORS setup in process_video_test.php', () => {
  const content = () => readFile('process_video_test.php');

  test('should compute Content-MD5 of CORS XML body', () => {
    const c = content();
    const funcStart = c.indexOf('function ensureCors(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('base64_encode(md5($corsXml, true))');
  });

  test('should include content-md5 in signed headers', () => {
    const c = content();
    const funcStart = c.indexOf('function ensureCors(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain("'content-md5'");
  });

  test('should send Content-MD5 curl header', () => {
    const c = content();
    const funcStart = c.indexOf('function ensureCors(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain("'Content-MD5: '");
  });

  test('should include POST in AllowedMethod', () => {
    const c = content();
    const funcStart = c.indexOf('function ensureCors(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('<AllowedMethod>POST</AllowedMethod>');
  });

  test('should include DELETE in AllowedMethod', () => {
    const c = content();
    const funcStart = c.indexOf('function ensureCors(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('<AllowedMethod>DELETE</AllowedMethod>');
  });

  test('should expose ETag header in CORS configuration', () => {
    const c = content();
    const funcStart = c.indexOf('function ensureCors(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('<ExposeHeader>ETag</ExposeHeader>');
  });

  test('should include all five HTTP methods (GET, PUT, POST, DELETE, HEAD)', () => {
    const c = content();
    const funcStart = c.indexOf('function ensureCors(');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('<AllowedMethod>GET</AllowedMethod>');
    expect(func).toContain('<AllowedMethod>PUT</AllowedMethod>');
    expect(func).toContain('<AllowedMethod>POST</AllowedMethod>');
    expect(func).toContain('<AllowedMethod>DELETE</AllowedMethod>');
    expect(func).toContain('<AllowedMethod>HEAD</AllowedMethod>');
  });
});

// =====================================================
// 2. uploadPart null ETag handling in admin_video_test.php
// =====================================================

test.describe('uploadPart ETag handling in admin_video_test.php', () => {
  const content = () => readFile('views/admin_video_test.php');

  test('should check for null ETag after getResponseHeader', () => {
    const c = content();
    expect(c).toContain("if (!etag)");
  });

  test('should reject with clear CORS error message when ETag is null', () => {
    const c = content();
    expect(c).toContain('did not return an ETag header');
    expect(c).toContain('CORS');
  });

  test('should log a warning when ETag is missing', () => {
    const c = content();
    expect(c).toContain('WARNING: No ETag returned for part');
  });

  test('should still strip quotes from valid ETag values', () => {
    const c = content();
    expect(c).toContain("etag.replace(/\"/g, '')");
  });

  test('should call getResponseHeader for ETag', () => {
    const c = content();
    expect(c).toContain("getResponseHeader('ETag')");
  });
});
