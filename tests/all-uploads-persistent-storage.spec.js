/**
 * Tests for All Uploads to Persistent Storage
 *
 * Verifies that ALL upload functions save to persistent storage first,
 * then backup to Nextcloud. This ensures faster local reads and data
 * durability across updates.
 *
 * Upload functions tested:
 * 1. uploadImageToNextcloud() - images (already had persistent storage)
 * 2. uploadDrillVideo() - drill videos
 * 3. uploadReceiptToNextcloud() - receipts
 * 4. uploadContractToNextcloud() - contracts
 * 5. uploadPayrollDocuments() - payroll documents
 * 6. uploadOnboardingDocuments() - onboarding documents
 * 7. uploadTerminationDocuments() - termination documents
 * 8. getPersistentStoragePath() - configurable persistent path
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. uploadImageToNextcloud saves to persistent storage
// =====================================================

test.describe('uploadImageToNextcloud saves to persistent storage', () => {
  test('uploadImageToNextcloud should call saveToPersistentStorage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadImageToNextcloud(');
    const fnEnd = content.indexOf('function restoreImageFromNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('saveToPersistentStorage(');
  });
});

// =====================================================
// 2. uploadDrillVideo saves to persistent storage
// =====================================================

test.describe('uploadDrillVideo saves to persistent storage', () => {
  test('uploadDrillVideo should call saveToPersistentStorage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadDrillVideo(');
    const fnEnd = content.indexOf('function getPersistentStoragePath(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('saveToPersistentStorage(');
  });

  test('uploadDrillVideo should save to DrillVideos subfolder in persistent storage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadDrillVideo(');
    const fnEnd = content.indexOf('function getPersistentStoragePath(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'DrillVideos/'");
  });

  test('uploadDrillVideo should save to persistent storage before uploading to Nextcloud', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadDrillVideo(');
    const fnEnd = content.indexOf('function getPersistentStoragePath(');
    const fn = content.substring(fnStart, fnEnd);
    const persistentIdx = fn.indexOf('saveToPersistentStorage(');
    const uploadIdx = fn.indexOf('uploadToNextcloud(');
    expect(persistentIdx).toBeGreaterThan(-1);
    expect(uploadIdx).toBeGreaterThan(-1);
    expect(persistentIdx).toBeLessThan(uploadIdx);
  });
});

// =====================================================
// 3. uploadReceiptToNextcloud saves to persistent storage
// =====================================================

test.describe('uploadReceiptToNextcloud saves to persistent storage', () => {
  test('uploadReceiptToNextcloud should call saveToPersistentStorage', () => {
    const content = readFile('process_expenses.php');
    const fnStart = content.indexOf('function uploadReceiptToNextcloud(');
    const fnEnd = content.indexOf('function performReceiptOCR(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('saveToPersistentStorage(');
  });

  test('uploadReceiptToNextcloud should save to Receipts subfolder in persistent storage', () => {
    const content = readFile('process_expenses.php');
    const fnStart = content.indexOf('function uploadReceiptToNextcloud(');
    const fnEnd = content.indexOf('function performReceiptOCR(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'Receipts/'");
  });
});

// =====================================================
// 4. uploadContractToNextcloud saves to persistent storage
// =====================================================

test.describe('uploadContractToNextcloud saves to persistent storage', () => {
  test('uploadContractToNextcloud should call saveToPersistentStorage', () => {
    const content = readFile('process_recurring_expenses.php');
    const fnStart = content.indexOf('function uploadContractToNextcloud(');
    const fnEnd = content.indexOf('\ntry {', fnStart);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('saveToPersistentStorage(');
  });

  test('uploadContractToNextcloud should save to Contracts subfolder in persistent storage', () => {
    const content = readFile('process_recurring_expenses.php');
    const fnStart = content.indexOf('function uploadContractToNextcloud(');
    const fnEnd = content.indexOf('\ntry {', fnStart);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'Contracts/'");
  });
});

// =====================================================
// 5. uploadPayrollDocuments saves to persistent storage
// =====================================================

test.describe('uploadPayrollDocuments saves to persistent storage', () => {
  test('uploadPayrollDocuments should call saveToPersistentStorage', () => {
    const content = readFile('process_payroll.php');
    const fnStart = content.indexOf('function uploadPayrollDocuments(');
    const fnEnd = content.indexOf('// Handle Add Employee');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('saveToPersistentStorage(');
  });

  test('uploadPayrollDocuments should save to Payroll subfolder in persistent storage', () => {
    const content = readFile('process_payroll.php');
    const fnStart = content.indexOf('function uploadPayrollDocuments(');
    const fnEnd = content.indexOf('// Handle Add Employee');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'Payroll/'");
  });
});

// =====================================================
// 6. uploadOnboardingDocuments saves to persistent storage
// =====================================================

test.describe('uploadOnboardingDocuments saves to persistent storage', () => {
  test('uploadOnboardingDocuments should call saveToPersistentStorage', () => {
    const content = readFile('process_onboarding.php');
    const fnStart = content.indexOf('function uploadOnboardingDocuments(');
    const fnEnd = content.indexOf('function exportOnboardingData(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('saveToPersistentStorage(');
  });

  test('uploadOnboardingDocuments should save to Onboarding subfolder in persistent storage', () => {
    const content = readFile('process_onboarding.php');
    const fnStart = content.indexOf('function uploadOnboardingDocuments(');
    const fnEnd = content.indexOf('function exportOnboardingData(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'Onboarding/'");
  });
});

// =====================================================
// 7. uploadTerminationDocuments saves to persistent storage
// =====================================================

test.describe('uploadTerminationDocuments saves to persistent storage', () => {
  test('uploadTerminationDocuments should call saveToPersistentStorage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadTerminationDocuments(');
    const fnEnd = content.indexOf('function exportTerminationData(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('saveToPersistentStorage(');
  });

  test('uploadTerminationDocuments should save to Terminations subfolder in persistent storage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadTerminationDocuments(');
    const fnEnd = content.indexOf('function exportTerminationData(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'Terminations/'");
  });
});

// =====================================================
// 8. Configurable persistent storage path
// =====================================================

test.describe('getPersistentStoragePath is configurable', () => {
  test('getPersistentStoragePath should accept optional pdo parameter', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function getPersistentStoragePath($pdo = null)');
  });

  test('getPersistentStoragePath should read nextcloud_persistent_path from database', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function getPersistentStoragePath($pdo = null)');
    const fnBody = content.substring(fnStart, fnStart + 800);
    expect(fnBody).toContain('nextcloud_persistent_path');
  });

  test('admin UI should have editable persistent path field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="nextcloud_persistent_path"');
    // Should not be readonly or disabled
    const inputIdx = content.indexOf('name="nextcloud_persistent_path"');
    const tagStart = content.lastIndexOf('<input', inputIdx);
    const tagEnd = content.indexOf('>', inputIdx);
    const inputTag = content.substring(tagStart, tagEnd + 1);
    expect(inputTag).not.toContain('readonly');
    expect(inputTag).not.toContain('disabled');
  });

  test('process_settings.php should save nextcloud_persistent_path', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'nextcloud_persistent_path'");
  });

  test('database schema should have nextcloud_persistent_path default', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'nextcloud_persistent_path'");
  });
});

// =====================================================
// 9. saveToPersistentStorage forwards $pdo to getPersistentStoragePath
// =====================================================

test.describe('saveToPersistentStorage respects persistent path setting', () => {
  test('saveToPersistentStorage should accept optional $pdo parameter', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function saveToPersistentStorage($local_file_path, $subfolder, $filename, $pdo = null)');
  });

  test('saveToPersistentStorage should pass $pdo to getPersistentStoragePath', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function saveToPersistentStorage(');
    const fnEnd = content.indexOf('function restoreFromPersistentStorage(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getPersistentStoragePath($pdo)');
  });

  test('all callers of saveToPersistentStorage in cloud_config.php should pass $pdo', () => {
    const content = readFile('cloud_config.php');
    // Find all calls to saveToPersistentStorage (excluding the function definition)
    const fnDefEnd = content.indexOf('function restoreFromPersistentStorage(');
    const afterDef = content.substring(fnDefEnd);
    const matches = afterDef.match(/saveToPersistentStorage\([^)]+\)/g) || [];
    for (const call of matches) {
      expect(call).toContain('$pdo');
    }
  });

  test('process_expenses.php should pass $pdo to saveToPersistentStorage', () => {
    const content = readFile('process_expenses.php');
    const calls = content.match(/saveToPersistentStorage\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });

  test('process_payroll.php should pass $pdo to saveToPersistentStorage', () => {
    const content = readFile('process_payroll.php');
    const calls = content.match(/saveToPersistentStorage\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });

  test('process_profile_update.php should use persistUploadedFile for Garage S3 storage', () => {
    const content = readFile('process_profile_update.php');
    // upload_photo action now uses persistUploadedFile instead of direct saveToPersistentStorage
    const lines = content.split('\n').filter(l => l.includes('persistUploadedFile(') && !l.trim().startsWith('//'));
    expect(lines.length).toBeGreaterThan(0);
  });

  test('process_recurring_expenses.php should pass $pdo to saveToPersistentStorage', () => {
    const content = readFile('process_recurring_expenses.php');
    const calls = content.match(/saveToPersistentStorage\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });

  test('process_onboarding.php should pass $pdo to saveToPersistentStorage', () => {
    const content = readFile('process_onboarding.php');
    const calls = content.match(/saveToPersistentStorage\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });
});

// =====================================================
// 10. restoreFromPersistentStorage forwards $pdo to getPersistentStoragePath
// =====================================================

test.describe('restoreFromPersistentStorage respects persistent path setting', () => {
  test('restoreFromPersistentStorage should accept optional $pdo parameter', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function restoreFromPersistentStorage($subfolder, $filename, $local_path, $pdo = null)');
  });

  test('restoreFromPersistentStorage should pass $pdo to getPersistentStoragePath', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function restoreFromPersistentStorage(');
    const fnEnd = content.indexOf('function uploadImageToNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getPersistentStoragePath($pdo)');
  });

  test('restoreImageFromNextcloud should pass $pdo to restoreFromPersistentStorage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function restoreImageFromNextcloud(');
    const fnEnd = content.indexOf('function getDrillVideoPath(');
    const fn = content.substring(fnStart, fnEnd);
    const calls = fn.match(/restoreFromPersistentStorage\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });

  test('tryRestoreFromPersistent should accept and forward $pdo parameter', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('function tryRestoreFromPersistent($local_path, $subfolder, $filename = null, $pdo = null)');
    const fnStart = content.indexOf('function tryRestoreFromPersistent(');
    const fnEnd = content.indexOf('function resolveProfileImage(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('restoreFromPersistentStorage($subfolder, $filename, $local_path, $pdo)');
  });

  test('resolveProfileImage should pass $pdo to tryRestoreFromPersistent', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveProfileImage(');
    const fnEnd = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart, fnEnd);
    const calls = fn.match(/tryRestoreFromPersistent\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });

  test('resolveEvaluationMedia should pass $pdo to tryRestoreFromPersistent', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveEvaluationMedia(');
    const fnEnd = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart, fnEnd);
    const calls = fn.match(/tryRestoreFromPersistent\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });

  test('resolveDrillImage should pass $pdo to tryRestoreFromPersistent', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart);
    const calls = fn.match(/tryRestoreFromPersistent\([^)]+\)/g) || [];
    expect(calls.length).toBeGreaterThan(0);
    for (const call of calls) {
      expect(call).toContain('$pdo');
    }
  });
});
