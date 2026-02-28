/**
 * Tests for Video Upload CSP and Fallback Fixes
 *
 * Verifies fixes for:
 * 1. CSP connect-src includes RustFS endpoint so presigned URL uploads are not blocked
 * 2. setSecurityHeaders() accepts optional extra connect-src origins
 * 3. dashboard.php, pwa.php, pwa_tablet.php pass RustFS origin to CSP
 * 4. JS fallback: JSON parse failure falls back to legacy upload instead of dead-ending
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. setSecurityHeaders accepts extra connect-src
// =====================================================

test.describe('setSecurityHeaders accepts extra connect-src origins', () => {
  test('should accept optional $extraConnectSrc parameter with default empty array', () => {
    const content = readFile('security.php');
    expect(content).toContain('function setSecurityHeaders($extraConnectSrc = [])');
  });

  test('should build connect-src from base domains plus extra origins', () => {
    const content = readFile('security.php');
    const funcStart = content.indexOf('function setSecurityHeaders(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // Should have the base connect-src string
    expect(funcBody).toContain("'self' wss: https://maps.googleapis.com");
    // Should iterate over extraConnectSrc
    expect(funcBody).toContain('foreach ($extraConnectSrc');
    expect(funcBody).toContain('$connectSrc');
  });

  test('should use $connectSrc variable in the CSP connect-src directive', () => {
    const content = readFile('security.php');
    const funcStart = content.indexOf('function setSecurityHeaders(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('"connect-src $connectSrc; "');
  });
});

// =====================================================
// 2. dashboard.php passes RustFS origin to CSP
// =====================================================

test.describe('dashboard.php includes RustFS endpoint in CSP connect-src', () => {
  test('should require cloud_config.php', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain("require_once __DIR__ . '/cloud_config.php'");
  });

  test('should get RustFS settings and extract endpoint origin', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('getRustFSSettings($pdo)');
    expect(content).toContain('isRustFSConfigured($rustfs)');
    expect(content).toContain("parse_url(rtrim($rustfs['rustfs_endpoint']");
  });

  test('should build origin from parsed endpoint', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain("$parsedEndpoint['scheme']");
    expect(content).toContain("$parsedEndpoint['host']");
    expect(content).toContain("$parsedEndpoint['port']");
    expect(content).toContain('$extraConnectSrc[]');
  });

  test('should pass $extraConnectSrc to setSecurityHeaders', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('setSecurityHeaders($extraConnectSrc)');
  });

  test('should wrap RustFS lookup in try-catch to avoid breaking on DB errors', () => {
    const content = readFile('dashboard.php');
    const csrfIdx = content.indexOf('$extraConnectSrc = []');
    const headerIdx = content.indexOf('setSecurityHeaders($extraConnectSrc)');
    const block = content.substring(csrfIdx, headerIdx);
    expect(block).toContain('try {');
    expect(block).toContain('} catch');
  });
});

// =====================================================
// 3. pwa.php includes RustFS endpoint in CSP
// =====================================================

test.describe('pwa.php includes RustFS endpoint in CSP connect-src', () => {
  test('should pass $extraConnectSrc to setSecurityHeaders', () => {
    const content = readFile('pwa.php');
    expect(content).toContain('setSecurityHeaders($extraConnectSrc)');
  });

  test('should get RustFS settings and extract endpoint origin', () => {
    const content = readFile('pwa.php');
    expect(content).toContain('getRustFSSettings($pdo)');
    expect(content).toContain('isRustFSConfigured($rustfs)');
    expect(content).toContain("parse_url(rtrim($rustfs['rustfs_endpoint']");
  });
});

// =====================================================
// 4. pwa_tablet.php includes RustFS endpoint in CSP
// =====================================================

test.describe('pwa_tablet.php includes RustFS endpoint in CSP connect-src', () => {
  test('should pass $extraConnectSrc to setSecurityHeaders', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain('setSecurityHeaders($extraConnectSrc)');
  });

  test('should get RustFS settings and extract endpoint origin', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain('getRustFSSettings($pdo)');
    expect(content).toContain('isRustFSConfigured($rustfs)');
    expect(content).toContain("parse_url(rtrim($rustfs['rustfs_endpoint']");
  });
});

// =====================================================
// 5. Upload uses direct PUT to presigned URL with CORS
// =====================================================

test.describe('Upload uses direct PUT to presigned URL (CORS configured on bucket)', () => {
  test('should PUT directly to presigned URL on RustFS', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain("xhr.open('PUT', presignedUrl");
    expect(content).toContain("xhr.setRequestHeader('Content-Type', contentType)");
  });

  test('should not use proxy_video_upload', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).not.toContain('proxy_video_upload');
  });

  test('should handle errors with legacy fallback and toast message', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('catch');
    expect(content).toContain('showToast');
    expect(content).toContain('submitBtn.disabled = false');
  });
});
