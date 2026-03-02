/**
 * Tests for RustFS Upload API Endpoint Test in System Tools
 *
 * Verifies:
 * 1. rustfsObjectExists has retry logic and timeouts for reliability
 * 2. Test Upload API button exists in admin system tools
 * 3. testUploadApiEndpoint JS function exists in admin system tools
 * 4. test_upload_api action is handled in process_settings.php
 * 5. test_upload_api performs write + verify + cleanup round-trip
 * 6. api/upload.php streaming proxy exists and validates correctly
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. rustfsObjectExists has retry logic and timeouts
// =====================================================

test.describe('rustfsObjectExists reliability improvements', () => {
  test('should have retry logic with multiple attempts', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function rustfsObjectExists(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('max_retries');
    expect(funcBody).toContain('$attempt');
    expect(funcBody).toContain('sleep(');
  });

  test('should set CURLOPT_CONNECTTIMEOUT', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function rustfsObjectExists(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('CURLOPT_CONNECTTIMEOUT');
  });

  test('should set CURLOPT_TIMEOUT', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function rustfsObjectExists(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('CURLOPT_TIMEOUT');
  });

  test('should log errors with HTTP code on failure', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function rustfsObjectExists(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('error_log(');
    expect(funcBody).toContain('http_code');
  });

  test('should capture and log curl errors', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function rustfsObjectExists(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('curl_error');
  });
});

// =====================================================
// 2. Test Upload API button in admin system tools
// =====================================================

test.describe('Test Upload API button in system tools', () => {
  test('should have Test Upload API button in RustFS section', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('testUploadApiEndpoint()');
    expect(content).toContain('Test Upload API');
  });

  test('button should use cloud-upload-alt icon', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('fa-cloud-upload-alt');
  });
});

// =====================================================
// 3. testUploadApiEndpoint JS function
// =====================================================

test.describe('testUploadApiEndpoint JavaScript function', () => {
  test('should define testUploadApiEndpoint function', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function testUploadApiEndpoint()');
  });

  test('should send test_upload_api action to process_settings.php', () => {
    const content = readFile('views/admin_system_tools.php');
    const funcStart = content.indexOf('function testUploadApiEndpoint()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'action', 'test_upload_api'");
    expect(funcBody).toContain('process_settings.php');
  });

  test('should use the rustfs-form data', () => {
    const content = readFile('views/admin_system_tools.php');
    const funcStart = content.indexOf('function testUploadApiEndpoint()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('rustfs-form');
  });

  test('should show success/error toast messages', () => {
    const content = readFile('views/admin_system_tools.php');
    const funcStart = content.indexOf('function testUploadApiEndpoint()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('showToast');
    expect(funcBody).toContain('success');
    expect(funcBody).toContain('error');
  });
});

// =====================================================
// 4. test_upload_api action in process_settings.php
// =====================================================

test.describe('test_upload_api action in process_settings.php', () => {
  test('should be in the json_actions list', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("'test_upload_api'");
  });

  test('should have the case handler', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'test_upload_api':");
  });

  test('should check api/upload.php file exists', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'test_upload_api':");
    const caseEnd = content.indexOf("case '", caseStart + 1);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('api/upload.php');
  });

  test('should perform a write + verify round-trip', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'test_upload_api':");
    const caseEnd = content.indexOf("case '", caseStart + 1);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('uploadContentToRustFS');
    expect(caseBody).toContain('rustfsObjectExists');
  });

  test('should clean up test objects', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'test_upload_api':");
    const caseEnd = content.indexOf("case '", caseStart + 1);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('deleteFromRustFS');
  });

  test('should return structured results with api_reachable, rustfs_write, rustfs_verify', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'test_upload_api':");
    const caseEnd = content.indexOf("case '", caseStart + 1);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('api_reachable');
    expect(caseBody).toContain('rustfs_write');
    expect(caseBody).toContain('rustfs_verify');
  });
});

// =====================================================
// 5. api/upload.php streaming proxy exists and validates
// =====================================================

test.describe('api/upload.php streaming proxy endpoint', () => {
  test('should exist', () => {
    expect(fs.existsSync(path.join(ROOT, 'api/upload.php'))).toBe(true);
  });

  test('should require PUT method', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain("REQUEST_METHOD");
    expect(content).toContain("PUT");
  });

  test('should validate upload token from session', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain('upload_proxy_token');
    expect(content).toContain('X-Upload-Token');
    expect(content).toContain('hash_equals');
  });

  test('should call streamUploadToRustFS', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain('streamUploadToRustFS');
  });

  test('should validate Content-Length', () => {
    const content = readFile('api/upload.php');
    expect(content).toContain('CONTENT_LENGTH');
  });
});
