/**
 * Tests for PII Encryption Consolidation
 *
 * Verifies that all encryption/decryption across the site uses the single
 * FieldEncryption (PII) system from lib/encryption.php, and that the
 * credential functions (encryptPassword, decryptPassword, decryptCredential)
 * delegate to FieldEncryption.
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. encryptPassword delegates to FieldEncryption
// =====================================================

test.describe('encryptPassword uses PII FieldEncryption', () => {
  test('encryptPassword delegates to FieldEncryption::encrypt', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function encryptPassword($password)');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('FieldEncryption::encrypt');
  });

  test('encryptPassword does not use inline openssl_encrypt', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function encryptPassword($password)');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).not.toContain('openssl_encrypt');
  });

  test('encryptPassword requires lib/encryption.php', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function encryptPassword($password)');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("lib/encryption.php");
  });
});

// =====================================================
// 2. decryptPassword uses PII FieldEncryption primarily
// =====================================================

test.describe('decryptPassword uses PII FieldEncryption primarily', () => {
  test('decryptPassword tries FieldEncryption::decrypt first', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptPassword($encrypted_data)');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('FieldEncryption::decrypt');
    expect(fnBody).toContain('FieldEncryption::isConfigured');
  });

  test('decryptPassword retains legacy fallback for backward compatibility', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptPassword($encrypted_data)');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    // Legacy fallback still present for old format
    expect(fnBody).toContain('.nextcloud_key');
    expect(fnBody).toContain('::');
  });

  test('decryptPassword requires lib/encryption.php', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptPassword($encrypted_data)');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("lib/encryption.php");
  });
});

// =====================================================
// 3. decryptCredential uses PII FieldEncryption
// =====================================================

test.describe('decryptCredential uses PII FieldEncryption', () => {
  test('decryptCredential delegates to decryptPassword which uses FieldEncryption', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptCredential($value)');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('decryptPassword');
  });

  test('decryptCredential docstring mentions PII FieldEncryption', () => {
    const content = readFile('security.php');
    // Find the docblock before decryptCredential
    const fnStart = content.indexOf('function decryptCredential($value)');
    const docStart = content.lastIndexOf('/**', fnStart);
    const docBlock = content.substring(docStart, fnStart);
    expect(docBlock).toContain('PII FieldEncryption');
  });
});

// =====================================================
// 4. isValueEncrypted detects both formats
// =====================================================

test.describe('isValueEncrypted detects PII and legacy formats', () => {
  test('isValueEncrypted checks PII FieldEncryption format', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function isValueEncrypted($value)');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('FieldEncryption::decrypt');
    expect(fnBody).toContain('FieldEncryption::isConfigured');
  });

  test('isValueEncrypted still checks legacy :: separator format', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function isValueEncrypted($value)');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('::');
    expect(fnBody).toContain('explode');
  });

  test('isValueEncrypted requires lib/encryption.php', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function isValueEncrypted($value)');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("lib/encryption.php");
  });
});

// =====================================================
// 5. ensureCredentialsEncrypted uses PII FieldEncryption
// =====================================================

test.describe('ensureCredentialsEncrypted uses PII FieldEncryption', () => {
  test('ensureCredentialsEncrypted requires lib/encryption.php', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function ensureCredentialsEncrypted($pdo)');
    const fnEnd = content.indexOf("return $results;", fnStart);
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("lib/encryption.php");
  });

  test('ensureCredentialsEncrypted checks PII format before legacy', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function ensureCredentialsEncrypted($pdo)');
    const fnEnd = content.indexOf("return $results;", fnStart);
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('FieldEncryption::isConfigured');
    expect(fnBody).toContain('FieldEncryption::decrypt');
  });

  test('ensureCredentialsEncrypted re-encrypts legacy values with PII', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function ensureCredentialsEncrypted($pdo)');
    const fnEnd = content.indexOf("return $results;", fnStart);
    const fnBody = content.substring(fnStart, fnEnd);
    // Should decrypt legacy values and re-encrypt with PII
    expect(fnBody).toContain('isValueEncrypted');
    expect(fnBody).toContain('decryptPassword');
    expect(fnBody).toContain('encryptPassword');
  });
});

// =====================================================
// 6. No direct openssl calls outside lib/encryption.php
// =====================================================

test.describe('No direct openssl encryption calls in application code', () => {
  test('encryptPassword does not call openssl_encrypt directly', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function encryptPassword($password)');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).not.toContain('openssl_encrypt');
    expect(fnBody).not.toContain('openssl_decrypt');
  });

  test('FieldEncryption is the sole encryption implementation', () => {
    const content = readFile('lib/encryption.php');
    expect(content).toContain('openssl_encrypt');
    expect(content).toContain('openssl_decrypt');
    expect(content).toContain('aes-256-cbc');
  });
});
