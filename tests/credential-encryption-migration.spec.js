import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. ensureCredentialsEncrypted() function in security.php
 * 2. isValueEncrypted() function in security.php
 * 3. getEncryptedSettingKeys() function in security.php
 * 4. decryptCredential() no longer silently returns plaintext
 * 5. setup.php calls ensureCredentialsEncrypted() during finalization
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. ensureCredentialsEncrypted function
// =====================================================

test.describe('ensureCredentialsEncrypted function', () => {
  test('security.php defines ensureCredentialsEncrypted', () => {
    const content = readFile('security.php');
    expect(content).toContain('function ensureCredentialsEncrypted($pdo)');
  });

  test('ensureCredentialsEncrypted queries all encrypted setting keys', () => {
    const content = readFile('security.php');
    const fnBody = content.substring(
      content.indexOf('function ensureCredentialsEncrypted'),
      content.indexOf('}', content.lastIndexOf("return $results;")) + 1
    );
    expect(fnBody).toContain('getEncryptedSettingKeys()');
    expect(fnBody).toContain('system_settings');
    expect(fnBody).toContain('setting_key');
    expect(fnBody).toContain('setting_value');
  });

  test('ensureCredentialsEncrypted encrypts plaintext values in-place', () => {
    const content = readFile('security.php');
    const fnBody = content.substring(
      content.indexOf('function ensureCredentialsEncrypted'),
      content.indexOf('}', content.lastIndexOf("return $results;")) + 1
    );
    expect(fnBody).toContain('encryptPassword($value)');
    expect(fnBody).toContain("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
  });

  test('ensureCredentialsEncrypted skips already-encrypted values', () => {
    const content = readFile('security.php');
    const fnBody = content.substring(
      content.indexOf('function ensureCredentialsEncrypted'),
      content.indexOf('}', content.lastIndexOf("return $results;")) + 1
    );
    expect(fnBody).toContain('isValueEncrypted');
    expect(fnBody).toContain("already_encrypted");
  });

  test('ensureCredentialsEncrypted returns migration summary', () => {
    const content = readFile('security.php');
    const fnBody = content.substring(
      content.indexOf('function ensureCredentialsEncrypted'),
      content.indexOf('}', content.lastIndexOf("return $results;")) + 1
    );
    expect(fnBody).toContain("'migrated'");
    expect(fnBody).toContain("'already_encrypted'");
    expect(fnBody).toContain("'empty'");
    expect(fnBody).toContain("return $results");
  });
});

// =====================================================
// 2. isValueEncrypted function
// =====================================================

test.describe('isValueEncrypted function', () => {
  test('security.php defines isValueEncrypted', () => {
    const content = readFile('security.php');
    expect(content).toContain('function isValueEncrypted($value)');
  });

  test('isValueEncrypted checks for base64 and :: separator', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function isValueEncrypted');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('base64_decode');
    expect(fnBody).toContain('::');
    expect(fnBody).toContain('explode');
  });

  test('isValueEncrypted validates IV length of 16 bytes', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function isValueEncrypted');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('16');
    expect(fnBody).toContain("strlen");
  });

  test('isValueEncrypted returns false for empty values', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function isValueEncrypted');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('empty($value)');
    expect(fnBody).toContain('return false');
  });
});

// =====================================================
// 3. getEncryptedSettingKeys function
// =====================================================

test.describe('getEncryptedSettingKeys function', () => {
  test('security.php defines getEncryptedSettingKeys', () => {
    const content = readFile('security.php');
    expect(content).toContain('function getEncryptedSettingKeys()');
  });

  test('getEncryptedSettingKeys includes all credential keys', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function getEncryptedSettingKeys');
    const fnEnd = content.indexOf('];\n}', fnStart) + 4;
    const fnBody = content.substring(fnStart, fnEnd);

    const requiredKeys = [
      'smtp_pass',
      'nextcloud_password',
      'nextcloud_backup_password',
      'paperless_api_token',
      'stripe_publishable_key',
      'stripe_secret_key',
      'google_maps_api_key',
      'github_token',
      'docuseal_api_key',
      'docuseal_webhook_secret',
      'stallion_api_key',
      'stallion_api_secret',
    ];

    for (const key of requiredKeys) {
      expect(fnBody).toContain(`'${key}'`);
    }
  });
});

// =====================================================
// 4. decryptCredential no longer silently returns plaintext
// =====================================================

test.describe('decryptCredential updated behavior', () => {
  test('decryptCredential logs error when decryption fails', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptCredential');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('error_log');
    expect(fnBody).toContain('Failed to decrypt');
  });

  test('decryptCredential still returns value for backward compatibility', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptCredential');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    // Should return value (not empty) to avoid breaking existing functionality
    // until credentials are migrated
    expect(fnBody).toContain('return $value');
  });
});

// =====================================================
// 5. setup.php calls ensureCredentialsEncrypted
// =====================================================

test.describe('setup.php credential migration', () => {
  test('setup.php step 5 calls ensureCredentialsEncrypted', () => {
    const content = readFile('setup.php');
    // Find the PHP logic section for step 5 (not the HTML template)
    const phpStep5Start = content.indexOf("elseif ($step == 5)");
    const phpStep5End = content.indexOf("} catch (PDOException", phpStep5Start);
    const step5Section = content.substring(phpStep5Start, phpStep5End);
    expect(step5Section).toContain('ensureCredentialsEncrypted');
  });

  test('setup.php loads security.php before calling migration', () => {
    const content = readFile('setup.php');
    const phpStep5Start = content.indexOf("elseif ($step == 5)");
    const phpStep5End = content.indexOf("} catch (PDOException", phpStep5Start);
    const step5Section = content.substring(phpStep5Start, phpStep5End);
    expect(step5Section).toContain("require_once");
    expect(step5Section).toContain("security.php");
    // Ensure security.php is loaded BEFORE the call
    const securityRequire = step5Section.indexOf('security.php');
    const migrationCall = step5Section.indexOf('ensureCredentialsEncrypted');
    expect(securityRequire).toBeGreaterThan(-1);
    expect(migrationCall).toBeGreaterThan(-1);
    expect(securityRequire).toBeLessThan(migrationCall);
  });

  test('setup.php logs migration results', () => {
    const content = readFile('setup.php');
    const phpStep5Start = content.indexOf("elseif ($step == 5)");
    const phpStep5End = content.indexOf("} catch (PDOException", phpStep5Start);
    const step5Section = content.substring(phpStep5Start, phpStep5End);
    expect(step5Section).toContain("error_log");
    expect(step5Section).toContain("migrated");
  });

  test('setup.php runs migration before marking setup complete', () => {
    const content = readFile('setup.php');
    const phpStep5Start = content.indexOf("elseif ($step == 5)");
    const phpStep5End = content.indexOf("} catch (PDOException", phpStep5Start);
    const step5Section = content.substring(phpStep5Start, phpStep5End);
    const migrationIdx = step5Section.indexOf('ensureCredentialsEncrypted');
    const completeIdx = step5Section.indexOf('setup_complete_file');
    expect(migrationIdx).toBeGreaterThan(-1);
    expect(completeIdx).toBeGreaterThan(-1);
    expect(migrationIdx).toBeLessThan(completeIdx);
  });
});
