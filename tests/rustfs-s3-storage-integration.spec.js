/**
 * Tests for RustFS S3 Storage Integration
 *
 * Verifies:
 * 1. RustFS storage library exists with required functions
 * 2. RustFS settings tab exists in admin system tools
 * 3. process_settings.php handles update_rustfs and test_rustfs actions
 * 4. cloud_config.php uses RustFS for all uploads (zero local storage)
 * 5. All process files store RustFS URLs in database
 * 6. No local move_uploaded_file calls remain for file storage
 * 7. Database schema includes RustFS settings
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. RustFS Storage Library
// =====================================================

test.describe('RustFS storage library', () => {
  test('lib/rustfs_storage.php should exist', () => {
    expect(fs.existsSync(path.join(ROOT, 'lib/rustfs_storage.php'))).toBe(true);
  });

  test('should define getRustFSSettings function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function getRustFSSettings(');
  });

  test('should define isRustFSConfigured function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function isRustFSConfigured(');
  });

  test('should define uploadToRustFS function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function uploadToRustFS(');
  });

  test('should define uploadLargeFileToRustFS function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function uploadLargeFileToRustFS(');
  });

  test('should define uploadContentToRustFS function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function uploadContentToRustFS(');
  });

  test('should define downloadFromRustFS function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function downloadFromRustFS(');
  });

  test('should define deleteFromRustFS function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function deleteFromRustFS(');
  });

  test('should define testRustFSConnection function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function testRustFSConnection(');
  });

  test('should define signRustFSRequest for AWS Signature V4', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function signRustFSRequest(');
    expect(content).toContain('AWS4-HMAC-SHA256');
  });

  test('should define getRustFSPublicUrl function', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function getRustFSPublicUrl(');
  });
});

// =====================================================
// 2. RustFS Settings Tab in Admin System Tools
// =====================================================

test.describe('RustFS settings tab in admin system tools', () => {
  test('should have a RustFS Storage tab link', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("tab=rustfs");
    expect(content).toContain("RustFS Storage");
  });

  test('should have a RustFS tab content div', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="rustfs-tab"');
  });

  test('should have RustFS endpoint input field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="rustfs_endpoint"');
  });

  test('should have RustFS access key input field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="rustfs_access_key"');
  });

  test('should have RustFS secret key input field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="rustfs_secret_key"');
  });

  test('should have RustFS bucket input field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="rustfs_bucket"');
  });

  test('should have RustFS region input field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="rustfs_region"');
  });

  test('should have RustFS SSL toggle', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="rustfs_use_ssl"');
  });

  test('should have RustFS path style toggle', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="rustfs_path_style"');
  });

  test('should have test connection button', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('testRustFSConnection()');
  });

  test('should have RustFS form with update_rustfs action', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('value="update_rustfs"');
  });

  test('should define testRustFSConnection JavaScript function', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function testRustFSConnection()');
  });
});

// =====================================================
// 3. Process Settings Handles RustFS Actions
// =====================================================

test.describe('process_settings.php handles RustFS actions', () => {
  test('should include test_rustfs in json_actions', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("'test_rustfs'");
  });

  test('should have update_rustfs case', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'update_rustfs':");
  });

  test('should have test_rustfs case', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'test_rustfs':");
  });

  test('should encrypt rustfs_secret_key before storing', () => {
    const content = readFile('process_settings.php');
    const rustfsSection = content.substring(
      content.indexOf("case 'update_rustfs':"),
      content.indexOf("case 'test_rustfs':")
    );
    expect(rustfsSection).toContain('encryptPassword');
    expect(rustfsSection).toContain("'rustfs_secret_key'");
  });

  test('should save all RustFS settings', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("'rustfs_endpoint'");
    expect(content).toContain("'rustfs_access_key'");
    expect(content).toContain("'rustfs_bucket'");
    expect(content).toContain("'rustfs_region'");
    expect(content).toContain("'rustfs_use_ssl'");
    expect(content).toContain("'rustfs_path_style'");
  });

  test('should redirect to rustfs tab after update', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("tab=rustfs&success=1");
  });

  test('should map update_rustfs to rustfs tab in error handler', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("'update_rustfs'      => 'rustfs'");
  });
});

// =====================================================
// 4. cloud_config.php Uses RustFS for All Uploads
// =====================================================

test.describe('cloud_config.php uses RustFS for all uploads', () => {
  test('should require rustfs_storage.php', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain("require_once __DIR__ . '/lib/rustfs_storage.php'");
  });

  test('persistUploadedFile should upload to RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function persistUploadedFile(');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 3;
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadToRustFS');
    expect(fn).toContain('uploadLargeFileToRustFS');
    expect(fn).toContain("'rustfs_url'");
  });

  test('persistUploadedFile should NOT copy to local cache', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function persistUploadedFile(');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 3;
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).not.toContain('copy(');
    expect(fn).not.toContain('mkdir(');
  });

  test('saveToPersistentStorage should be removed (no longer needed with RustFS)', () => {
    const content = readFile('cloud_config.php');
    expect(content).not.toContain('function saveToPersistentStorage(');
  });

  test('uploadImageToNextcloud should upload to RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadImageToNextcloud(');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 3;
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadToRustFS');
  });

  test('uploadLargeFileToNextcloud should use RustFS streaming', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadLargeFileToNextcloud(');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 3;
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadLargeFileToRustFS');
  });

  test('uploadDrillVideo should upload to RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadDrillVideo(');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 3;
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadLargeFileToRustFS');
  });

  test('uploadTerminationDocuments should upload to RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadTerminationDocuments(');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 3;
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadToRustFS');
  });

  test('restoreThemeImagesFromPersistentStorage should be removed (no longer needed with RustFS)', () => {
    const content = readFile('cloud_config.php');
    expect(content).not.toContain('function restoreThemeImagesFromPersistentStorage(');
  });

  test('restoreAllFilesFromPersistentStorage should be removed (no longer needed with RustFS)', () => {
    const content = readFile('cloud_config.php');
    expect(content).not.toContain('function restoreAllFilesFromPersistentStorage(');
  });
});

// =====================================================
// 5. All Process Files Store RustFS URLs in DB
// =====================================================

test.describe('process files store RustFS URLs in database', () => {
  test('process_video.php stores rustfs_url for coach video', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_drills.php stores rustfs_url for drill video', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_eval_goals.php stores rustfs_url', () => {
    const content = readFile('process_eval_goals.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_eval_skills.php stores rustfs_url', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_workout.php stores rustfs_url', () => {
    const content = readFile('process_workout.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_practice_plans.php stores rustfs_url', () => {
    const content = readFile('process_practice_plans.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_admin_action.php uses persistUploadedFile for profile images', () => {
    const content = readFile('process_admin_action.php');
    // Should use persistUploadedFile instead of move_uploaded_file for profile images
    expect(content).toContain("persistUploadedFile($pdo, $_FILES['profile_image']");
  });

  test('process_profile_update.php stores rustfs_url', () => {
    const content = readFile('process_profile_update.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_theme.php stores rustfs_url', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_merchandise_products.php stores rustfs_url', () => {
    const content = readFile('process_merchandise_products.php');
    expect(content).toContain("persist['rustfs_url']");
  });

  test('process_merchandise_categories.php stores rustfs_url', () => {
    const content = readFile('process_merchandise_categories.php');
    expect(content).toContain("persist['rustfs_url']");
  });
});

// =====================================================
// 6. No Local move_uploaded_file for File Storage
// =====================================================

test.describe('no local move_uploaded_file for file storage', () => {
  const storageFiles = [
    'process_admin_action.php',
    'process_profile_update.php',
    'process_theme.php',
    'process_merchandise_products.php',
    'process_merchandise_categories.php',
    'process_expenses.php',
    'process_recurring_expenses.php',
    'process_onboarding.php',
    'process_payroll.php',
    'process_video.php',
    'process_drills.php',
    'process_workout.php',
    'process_eval_goals.php',
    'process_eval_skills.php',
    'process_practice_plans.php',
    'process_coach_termination.php',
  ];

  for (const file of storageFiles) {
    test(`${file} should not use move_uploaded_file`, () => {
      const content = readFile(file);
      expect(content).not.toContain('move_uploaded_file(');
    });
  }
});

// =====================================================
// 7. Database Schema Includes RustFS Settings
// =====================================================

test.describe('database schema includes RustFS settings', () => {
  test('should have rustfs_endpoint setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'rustfs_endpoint'");
  });

  test('should have rustfs_access_key setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'rustfs_access_key'");
  });

  test('should have rustfs_secret_key setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'rustfs_secret_key'");
  });

  test('should have rustfs_bucket setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'rustfs_bucket'");
  });

  test('should have rustfs_region setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'rustfs_region'");
  });

  test('should have rustfs_use_ssl setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'rustfs_use_ssl'");
  });

  test('should have rustfs_path_style setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'rustfs_path_style'");
  });
});

// =====================================================
// 8. Direct Nextcloud Upload Calls Replaced
// =====================================================

test.describe('direct Nextcloud/local upload calls replaced', () => {
  const filesWithOldCalls = [
    'process_expenses.php',
    'process_recurring_expenses.php',
    'process_onboarding.php',
    'process_payroll.php',
    'process_database_backup.php',
    'cron_database_backup.php',
    'lib/opensign.php',
    'lib/docuseal.php',
  ];

  for (const file of filesWithOldCalls) {
    test(`${file} should use RustFS instead of direct uploadToNextcloud`, () => {
      const content = readFile(file);
      // These files should not contain direct calls to uploadToNextcloud
      // (the function is defined in cloud_config.php but calls there are to Nextcloud WebDAV)
      expect(content).not.toMatch(/uploadToNextcloud\s*\(/);
    });
  }

  for (const file of filesWithOldCalls) {
    test(`${file} should not use saveToPersistentStorage directly`, () => {
      const content = readFile(file);
      // These files should not use the old saveToPersistentStorage
      expect(content).not.toMatch(/saveToPersistentStorage\s*\(/);
    });
  }
});
