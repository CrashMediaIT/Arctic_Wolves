/**
 * Tests for Nextcloud Integration Fixes
 *
 * Verifies:
 * 1. Duplicate testNextcloudConnection JS function removed from admin_system_tools.php
 * 2. cron_database_backup.php uses cloud_config.php uploadToNextcloud (no local duplicate)
 * 3. process_settings.php test handlers use null coalescing for missing POST keys
 * 4. uploadToNextcloud in cloud_config.php auto-creates parent directories
 * 5. testNextcloudConnection follows redirects and has timeout
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Duplicate JS function removal
// =====================================================

test.describe('Duplicate testNextcloudConnection JS function removal', () => {
  test('admin_system_tools.php should have only one testNextcloudConnection function', () => {
    const content = readFile('views/admin_system_tools.php');
    // Count the number of 'function testNextcloudConnection' declarations
    const matches = content.match(/function testNextcloudConnection/g);
    expect(matches).not.toBeNull();
    expect(matches.length).toBe(1);
  });

  test('testNextcloudConnection should accept serverType parameter', () => {
    const content = readFile('views/admin_system_tools.php');
    // The single function should accept a serverType parameter
    expect(content).toContain("function testNextcloudConnection(serverType");
  });

  test('testNextcloudConnection should use correct form for backup server', () => {
    const content = readFile('views/admin_system_tools.php');
    // Should select form based on serverType
    expect(content).toContain("nextcloud-backup-form");
    expect(content).toContain("test_nextcloud_backup");
  });
});

// =====================================================
// 2. cron_database_backup.php function collision fix
// =====================================================

test.describe('Cron backup Nextcloud function collision fix', () => {
  test('cron_database_backup.php should not define its own uploadToNextcloud function', () => {
    const content = readFile('cron_database_backup.php');
    // Should NOT have a local uploadToNextcloud function definition
    const functionDefs = content.match(/function uploadToNextcloud\s*\(/g);
    expect(functionDefs).toBeNull();
  });

  test('cron_database_backup.php should include cloud_config.php', () => {
    const content = readFile('cron_database_backup.php');
    expect(content).toContain("cloud_config.php");
  });

  test('cron_database_backup.php primary upload should read file content before uploading', () => {
    const content = readFile('cron_database_backup.php');
    // Should read file contents for primary upload (now uploads to RustFS)
    const primaryStart = content.indexOf('Upload to primary');
    const secondaryStart = content.indexOf('secondary copy');
    const primarySection = content.substring(primaryStart, secondaryStart > -1 ? secondaryStart : primaryStart + 2000);
    expect(primarySection).toContain('file_get_contents');
  });

  test('cron_database_backup.php secondary upload should read file content before uploading', () => {
    const content = readFile('cron_database_backup.php');
    // Should read file contents for secondary upload (now uploads to RustFS)
    const secondaryStart = content.indexOf('secondary');
    const secondaryEnd = content.indexOf('Upload to SMB', secondaryStart);
    const secondarySection = content.substring(secondaryStart, secondaryEnd > -1 ? secondaryEnd : secondaryStart + 2000);
    expect(secondarySection).toContain('file_get_contents');
  });
});

// =====================================================
// 3. process_settings.php test handlers null coalescing
// =====================================================

test.describe('process_settings.php test handler fixes', () => {
  test('test_nextcloud handler uses null coalescing for all POST fields', () => {
    const content = readFile('process_settings.php');
    const testSection = content.substring(
      content.indexOf("case 'test_nextcloud':"),
      content.indexOf("case 'update_paperless':")
    );
    // All POST field reads should use ?? operator
    expect(testSection).toContain("$_POST['nextcloud_url'] ?? ''");
    expect(testSection).toContain("$_POST['nextcloud_username'] ?? ''");
    expect(testSection).toContain("$_POST['nextcloud_password'] ?? ''");
    expect(testSection).toContain("$_POST['nextcloud_receipt_folder'] ?? ''");
    expect(testSection).toContain("$_POST['nextcloud_webdav_path'] ?? ''");
  });

  test('test_nextcloud handler validates decryptPassword return value', () => {
    const content = readFile('process_settings.php');
    const testSection = content.substring(
      content.indexOf("case 'test_nextcloud':"),
      content.indexOf("case 'update_paperless':")
    );
    // Should check if decrypted value is non-empty before using it
    expect(testSection).toContain("!empty($decrypted)");
  });
});

// =====================================================
// 4. uploadToNextcloud auto-creates parent directories
// =====================================================

test.describe('uploadToNextcloud auto-creates parent directories', () => {
  test('uploadToNextcloud should check if parent directory exists', () => {
    const content = readFile('cloud_config.php');
    const uploadFn = content.substring(
      content.indexOf('function uploadToNextcloud('),
      content.indexOf('return $remote_path;', content.indexOf('function uploadToNextcloud('))
    );
    expect(uploadFn).toContain('dirname($remote_path)');
    expect(uploadFn).toContain('nextcloudFolderExists');
  });

  test('uploadToNextcloud should create parent directories recursively', () => {
    const content = readFile('cloud_config.php');
    const uploadFn = content.substring(
      content.indexOf('function uploadToNextcloud('),
      content.indexOf('return $remote_path;', content.indexOf('function uploadToNextcloud('))
    );
    expect(uploadFn).toContain('createNextcloudFolder');
  });
});

// =====================================================
// 5. testNextcloudConnection improvements
// =====================================================

test.describe('testNextcloudConnection improvements', () => {
  test('testNextcloudConnection should follow redirects', () => {
    const content = readFile('cloud_config.php');
    const testFn = content.substring(
      content.indexOf('function testNextcloudConnection('),
      content.indexOf('}', content.lastIndexOf("'server_type' => $server_type"))
    );
    expect(testFn).toContain('CURLOPT_FOLLOWLOCATION');
  });

  test('testNextcloudConnection should have a timeout', () => {
    const content = readFile('cloud_config.php');
    const testFn = content.substring(
      content.indexOf('function testNextcloudConnection('),
      content.indexOf('}', content.lastIndexOf("'server_type' => $server_type"))
    );
    expect(testFn).toContain('CURLOPT_TIMEOUT');
  });

  test('testNextcloudConnection should report curl errors', () => {
    const content = readFile('cloud_config.php');
    const testFn = content.substring(
      content.indexOf('function testNextcloudConnection('),
      content.indexOf('}', content.lastIndexOf("'server_type' => $server_type"))
    );
    expect(testFn).toContain('curl_error');
  });
});
