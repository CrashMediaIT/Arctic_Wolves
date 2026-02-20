/**
 * Tests for Encryption Consolidation
 * 
 * Verifies:
 * 1. encryptPassword/decryptPassword are defined in security.php (canonical location)
 * 2. No duplicate definitions exist in other files (process_settings, process_database_backup, etc.)
 * 3. All files that need decryptPassword include security.php
 * 4. cron_receipt_scanner uses decryptPassword() instead of inline decryption
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('Encryption Consolidation - security.php (canonical source)', () => {
  test('should define encryptPassword function', async () => {
    const filePath = path.join(__dirname, '..', 'security.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('function encryptPassword($password)');
  });

  test('should define decryptPassword function', async () => {
    const filePath = path.join(__dirname, '..', 'security.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('function decryptPassword($encrypted_data)');
  });

  test('should use .nextcloud_key file for key material', async () => {
    const filePath = path.join(__dirname, '..', 'security.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('.nextcloud_key');
  });

  test('should use AES-256-CBC with :: separator format', async () => {
    const filePath = path.join(__dirname, '..', 'security.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain("'AES-256-CBC'");
    expect(content).toContain("'::'");
  });
});

test.describe('Encryption Consolidation - no duplicate definitions', () => {
  test('process_settings.php should not define encryptPassword/decryptPassword', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toMatch(/^\s*function\s+encryptPassword\s*\(/m);
    expect(content).not.toMatch(/^\s*function\s+decryptPassword\s*\(/m);
  });

  test('process_database_backup.php should not define encryptPassword/decryptPassword', async () => {
    const filePath = path.join(__dirname, '..', 'process_database_backup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toMatch(/^\s*function\s+encryptPassword\s*\(/m);
    expect(content).not.toMatch(/^\s*function\s+decryptPassword\s*\(/m);
  });

  test('cron_database_backup.php should not define decryptPassword', async () => {
    const filePath = path.join(__dirname, '..', 'cron_database_backup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toMatch(/^\s*function\s+decryptPassword\s*\(/m);
  });

  test('process_expenses.php should not define encryptPassword/decryptPassword', async () => {
    const filePath = path.join(__dirname, '..', 'process_expenses.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toMatch(/^\s*function\s+encryptPassword\s*\(/m);
    expect(content).not.toMatch(/^\s*function\s+decryptPassword\s*\(/m);
  });
});

test.describe('Encryption Consolidation - security.php included where needed', () => {
  test('process_expenses.php should include security.php', async () => {
    const filePath = path.join(__dirname, '..', 'process_expenses.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toMatch(/require(_once)?\s+['"]security\.php['"]/);
  });

  test('process_settings.php should include security.php', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toMatch(/require(_once)?\s+['"]security\.php['"]/);
  });

  test('process_database_backup.php should include security.php', async () => {
    const filePath = path.join(__dirname, '..', 'process_database_backup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toMatch(/require(_once)?\s+.*security\.php/);
  });

  test('cron_database_backup.php should include security.php', async () => {
    const filePath = path.join(__dirname, '..', 'cron_database_backup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toMatch(/require(_once)?\s+.*security\.php/);
  });

  test('cron_receipt_scanner.php should include security.php', async () => {
    const filePath = path.join(__dirname, '..', 'cron_receipt_scanner.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toMatch(/require(_once)?\s+.*security\.php/);
  });
});

test.describe('Encryption Consolidation - cron_receipt_scanner uses canonical decryption', () => {
  test('should use decryptPassword() instead of inline decryption', async () => {
    const filePath = path.join(__dirname, '..', 'cron_receipt_scanner.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('decryptPassword(');
  });

  test('should not have inline openssl_decrypt for API token', async () => {
    const filePath = path.join(__dirname, '..', 'cron_receipt_scanner.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    // Should not have the old inline hex2bin + OPENSSL_RAW_DATA decryption
    expect(content).not.toContain('hex2bin($enc_key)');
    expect(content).not.toContain('OPENSSL_RAW_DATA');
  });
});

test.describe('Encryption Consolidation - banking data uses canonical encryption', () => {
  test('process_payroll.php should not define encryptBankingData/decryptBankingData', async () => {
    const filePath = path.join(__dirname, '..', 'process_payroll.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toMatch(/function\s+encryptBankingData\s*\(/);
    expect(content).not.toMatch(/function\s+decryptBankingData\s*\(/);
  });

  test('process_payroll.php should use encryptPassword for banking data', async () => {
    const filePath = path.join(__dirname, '..', 'process_payroll.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('encryptPassword($accountNumber)');
  });

  test('process_onboarding.php should not define encryptBankingData', async () => {
    const filePath = path.join(__dirname, '..', 'process_onboarding.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toMatch(/function\s+encryptBankingData\s*\(/);
  });

  test('process_onboarding.php should use encryptPassword for banking data', async () => {
    const filePath = path.join(__dirname, '..', 'process_onboarding.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('encryptPassword($accountNumber)');
  });

  test('no file should contain encryptBankingData or decryptBankingData', async () => {
    const files = [
      'process_payroll.php',
      'process_onboarding.php',
      'process_settings.php',
      'process_expenses.php'
    ];
    for (const file of files) {
      const filePath = path.join(__dirname, '..', file);
      const content = fs.readFileSync(filePath, 'utf-8');
      expect(content).not.toContain('encryptBankingData');
      expect(content).not.toContain('decryptBankingData');
    }
  });
});
