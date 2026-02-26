/**
 * Tests for All Uploads to RustFS S3 Storage
 *
 * Verifies that ALL upload functions store files in RustFS S3 storage.
 * Zero local file storage — all files go directly to RustFS.
 *
 * Upload functions tested:
 * 1. uploadImageToNextcloud() - images (now uses RustFS)
 * 2. uploadDrillVideo() - drill videos (now uses RustFS)
 * 3. process_expenses.php - receipts (uses persistUploadedFile)
 * 4. process_recurring_expenses.php - contracts (uses persistUploadedFile)
 * 5. uploadPayrollDocuments() - payroll documents (now uses uploadContentToRustFS)
 * 6. uploadOnboardingDocuments() - onboarding documents (now uses persistUploadedFile)
 * 7. uploadTerminationDocuments() - termination documents (now uses RustFS)
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. uploadImageToNextcloud uses RustFS
// =====================================================

test.describe('uploadImageToNextcloud saves to persistent storage', () => {
  test('uploadImageToNextcloud should upload to RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadImageToNextcloud(');
    const fnEnd = content.indexOf('function restoreImageFromNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('uploadToRustFS(');
  });
});

// =====================================================
// 2. uploadDrillVideo uses RustFS
// =====================================================

test.describe('uploadDrillVideo saves to persistent storage', () => {
  test('uploadDrillVideo should upload to RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadDrillVideo(');
    const fnEnd = content.indexOf('function uploadImageToNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('uploadLargeFileToRustFS(');
  });

  test('uploadDrillVideo should save to DrillVideos subfolder in persistent storage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadDrillVideo(');
    const fnEnd = content.indexOf('function uploadImageToNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'DrillVideos/'");
  });

  test('uploadDrillVideo should use RustFS for storage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadDrillVideo(');
    const fnEnd = content.indexOf('function uploadImageToNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('isRustFSConfigured');
  });
});

// =====================================================
// 3. process_expenses.php uses persistUploadedFile for receipts
// =====================================================

test.describe('Receipt uploads use persistUploadedFile for persistent storage', () => {
  test('process_expenses.php should use persistUploadedFile for receipts', () => {
    const content = readFile('process_expenses.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_expenses.php should save receipts to receipts subfolder', () => {
    const content = readFile('process_expenses.php');
    expect(content).toContain("'receipts'");
  });
});

// =====================================================
// 4. process_recurring_expenses.php uses persistUploadedFile for contracts
// =====================================================

test.describe('Contract uploads use persistUploadedFile for persistent storage', () => {
  test('process_recurring_expenses.php should use persistUploadedFile for contracts', () => {
    const content = readFile('process_recurring_expenses.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_recurring_expenses.php should save contracts to contracts subfolder', () => {
    const content = readFile('process_recurring_expenses.php');
    expect(content).toContain("'contracts'");
  });
});

// =====================================================
// 5. uploadPayrollDocuments uses RustFS via uploadContentToRustFS
// =====================================================

test.describe('uploadPayrollDocuments saves to persistent storage', () => {
  test('uploadPayrollDocuments should use RustFS for uploads', () => {
    const content = readFile('process_payroll.php');
    const fnStart = content.indexOf('function uploadPayrollDocuments(');
    const fnEnd = content.indexOf('// Handle Add Employee');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('uploadContentToRustFS(');
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
// 6. uploadOnboardingDocuments uses RustFS
// =====================================================

test.describe('uploadOnboardingDocuments saves to persistent storage', () => {
  test('uploadOnboardingDocuments should use persistUploadedFile for RustFS', () => {
    const content = readFile('process_onboarding.php');
    const fnStart = content.indexOf('function uploadOnboardingDocuments(');
    const fnEnd = content.indexOf('function exportOnboardingData(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('persistUploadedFile(');
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
// 7. uploadTerminationDocuments uses RustFS
// =====================================================

test.describe('uploadTerminationDocuments saves to persistent storage', () => {
  test('uploadTerminationDocuments should use RustFS for uploads', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadTerminationDocuments(');
    const fnEnd = content.indexOf('function exportTerminationData(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('uploadToRustFS(');
  });

  test('uploadTerminationDocuments should save to Terminations subfolder in persistent storage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadTerminationDocuments(');
    const fnEnd = content.indexOf('function exportTerminationData(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'HR/Terminations/'");
  });
});

// =====================================================
// 8. Persistent storage path settings still exist in admin/schema
// =====================================================

test.describe('Persistent storage path settings', () => {
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
// 9. Files use persistUploadedFile / uploadContentToRustFS for uploads
// =====================================================

test.describe('Upload callers use persistUploadedFile or RustFS helpers', () => {
  test('process_expenses.php should use persistUploadedFile', () => {
    const content = readFile('process_expenses.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_payroll.php should use uploadContentToRustFS', () => {
    const content = readFile('process_payroll.php');
    expect(content).toContain('uploadContentToRustFS(');
  });

  test('process_profile_update.php should use persistUploadedFile for profile uploads', () => {
    const content = readFile('process_profile_update.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_recurring_expenses.php should use persistUploadedFile', () => {
    const content = readFile('process_recurring_expenses.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_onboarding.php should use persistUploadedFile', () => {
    const content = readFile('process_onboarding.php');
    expect(content).toContain('persistUploadedFile(');
  });
});

// =====================================================
// 10. image_helper.php simplified — no local restore logic
// =====================================================

test.describe('image_helper.php uses simplified URL validation', () => {
  test('tryRestoreFromPersistent should be a no-op returning false', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('function tryRestoreFromPersistent($local_path, $subfolder, $filename = null, $pdo = null)');
    const fnStart = content.indexOf('function tryRestoreFromPersistent(');
    const fnEnd = content.indexOf('function resolveProfileImage(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('return false');
    expect(fn).not.toContain('restoreFromPersistentStorage');
  });

  test('resolveProfileImage should validate URLs without calling tryRestoreFromPersistent', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveProfileImage(');
    const fnEnd = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).not.toContain('tryRestoreFromPersistent');
    expect(fn).toContain('preg_match');
  });

  test('resolveEvaluationMedia should validate URLs without calling tryRestoreFromPersistent', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveEvaluationMedia(');
    const fnEnd = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).not.toContain('tryRestoreFromPersistent');
    expect(fn).toContain('preg_match');
  });

  test('resolveDrillImage should validate URLs without calling tryRestoreFromPersistent', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart);
    expect(fn).not.toContain('tryRestoreFromPersistent');
    expect(fn).toContain('preg_match');
  });
});
