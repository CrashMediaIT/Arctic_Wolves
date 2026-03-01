/**
 * Tests for Direct S3 Upload Fix — Endpoint URL Scheme Normalization
 *
 * When the RustFS S3 API runs on a non-standard Docker port (e.g., 30292),
 * endpoint URLs entered without a scheme prefix ('host:30292') cause PHP's
 * parse_url() to misinterpret 'host:port' as 'scheme:path'.  This breaks:
 *   - Presigned URL host/port extraction (garbage host in signature)
 *   - CSP connect-src origin construction (origin silently skipped)
 *
 * These tests verify the ensureEndpointScheme() helper exists and is used
 * in all critical paths.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. ensureEndpointScheme helper in rustfs_storage.php
// =====================================================

test.describe('ensureEndpointScheme helper function', () => {
  test('should exist in lib/rustfs_storage.php', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function ensureEndpointScheme(');
  });

  test('should accept url and use_ssl parameters', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toMatch(/function ensureEndpointScheme\(\$url,\s*\$use_ssl/);
  });

  test('should check for http:// and https:// prefixes', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureEndpointScheme(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'http://'");
    expect(funcBody).toContain("'https://'");
  });

  test('should prepend scheme based on use_ssl flag', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureEndpointScheme(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("$use_ssl ? 'https://' : 'http://'");
  });

  test('should return empty string unchanged', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureEndpointScheme(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("=== ''");
  });
});

// =====================================================
// 2. generatePresignedUploadUrl uses ensureEndpointScheme
// =====================================================

test.describe('generatePresignedUploadUrl normalizes public_endpoint', () => {
  test('should call ensureEndpointScheme on public_endpoint', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('ensureEndpointScheme');
  });
});

// =====================================================
// 3. generatePresignedUploadUrlViaSdk uses ensureEndpointScheme
// =====================================================

test.describe('generatePresignedUploadUrlViaSdk normalizes public_endpoint', () => {
  test('should call ensureEndpointScheme on public_endpoint', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrlViaSdk(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('ensureEndpointScheme');
  });
});

// =====================================================
// 4. getRustFSBaseUrl uses ensureEndpointScheme
// =====================================================

test.describe('getRustFSBaseUrl normalizes endpoint', () => {
  test('should call ensureEndpointScheme on endpoint', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function getRustFSBaseUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('ensureEndpointScheme');
  });
});

// =====================================================
// 5. ensureRustFSBucketCors uses ensureEndpointScheme
// =====================================================

test.describe('ensureRustFSBucketCors normalizes endpoint', () => {
  test('should call ensureEndpointScheme on endpoint', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function ensureRustFSBucketCors(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('ensureEndpointScheme');
  });
});

// =====================================================
// 6. CSP connect-src normalizes endpoints in all entry points
// =====================================================

test.describe('CSP connect-src normalizes endpoint URLs', () => {
  const entryPoints = ['dashboard.php', 'gameplan.php', 'pwa.php', 'pwa_tablet.php'];

  for (const file of entryPoints) {
    test(`${file} should call ensureEndpointScheme for rustfs_endpoint`, () => {
      const content = readFile(file);
      expect(content).toContain('ensureEndpointScheme');
      expect(content).toContain('normalizedEndpoint');
    });

    test(`${file} should call ensureEndpointScheme for rustfs_public_endpoint`, () => {
      const content = readFile(file);
      expect(content).toContain('normalizedPub');
    });
  }
});

// =====================================================
// 7. process_settings.php normalizes endpoints on save
// =====================================================

test.describe('process_settings.php normalizes endpoints on save', () => {
  test('should call ensureEndpointScheme for rustfs_endpoint', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'update_rustfs':");
    const caseEnd = content.indexOf("case '", caseStart + 25);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('ensureEndpointScheme($rustfs_endpoint');
  });

  test('should call ensureEndpointScheme for rustfs_public_endpoint', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'update_rustfs':");
    const caseEnd = content.indexOf("case '", caseStart + 25);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('ensureEndpointScheme($rustfs_public_endpoint');
  });
});

// =====================================================
// 8. Admin UI includes port in placeholder/help text
// =====================================================

test.describe('Admin UI public endpoint field mentions port', () => {
  test('should mention port in help text', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain(':30292');
  });

  test('should include port in placeholder', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('placeholder="https://s3.example.com:30292"');
  });
});
