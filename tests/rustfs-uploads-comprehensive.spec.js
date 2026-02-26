/**
 * Comprehensive test: All uploads handled by RustFS
 *
 * Verifies that:
 * 1. No Nextcloud functions remain in cloud_config.php
 * 2. All upload process files use persistUploadedFile() or RustFS directly
 * 3. Database backup uses RustFS (s3 destination)
 * 4. No Nextcloud settings UI remains
 * 5. No Nextcloud connection/authentication code remains
 * 6. Database migration exists to clean up Nextcloud settings
 * 7. All callers no longer fetch Nextcloud settings
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

function fileExists(relativePath) {
  return fs.existsSync(path.join(ROOT, relativePath));
}

// =====================================================
// 1. No Nextcloud Functions in cloud_config.php
// =====================================================

test.describe('cloud_config.php has no Nextcloud functions', () => {
  const removedFunctions = [
    'getNextcloudSettings',
    'getSecondaryNextcloudSettings',
    'connectNextcloud',
    'listNextcloudFiles',
    'parseWebDAVResponse',
    'downloadNextcloudFile',
    'getFileHash',
    'listNextcloudFilesRecursive',
    'testNextcloudConnection',
    'createNextcloudFolder',
    'nextcloudFolderExists',
    'uploadToNextcloud',
    'ensureNextcloudPath',
    'uploadImageToNextcloud',
    'uploadLargeFileToNextcloud',
    'uploadDrillVideo',
    'getDrillVideoPath',
    'listDrillVideosForDate',
  ];

  for (const fn of removedFunctions) {
    test(`should NOT define ${fn}()`, () => {
      const content = readFile('cloud_config.php');
      expect(content).not.toContain(`function ${fn}(`);
    });
  }

  test('should still define persistUploadedFile()', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function persistUploadedFile(');
  });

  test('should still define uploadTerminationDocuments()', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function uploadTerminationDocuments(');
  });

  test('should still define exportTerminationData()', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function exportTerminationData(');
  });

  test('persistUploadedFile should use RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function persistUploadedFile(');
    const fnEnd = content.indexOf('\n}\n', fnStart) + 3;
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadToRustFS');
    expect(fn).toContain('uploadLargeFileToRustFS');
  });

  test('should require rustfs_storage.php', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain("require_once __DIR__ . '/lib/rustfs_storage.php'");
  });
});

// =====================================================
// 2. All Upload Process Files Use RustFS
// =====================================================

test.describe('all upload files use persistUploadedFile or RustFS', () => {
  const uploadFiles = [
    'process_video.php',
    'process_drills.php',
    'process_eval_goals.php',
    'process_eval_skills.php',
    'process_workout.php',
    'process_practice_plans.php',
    'process_admin_action.php',
    'process_profile_update.php',
    'process_theme.php',
    'process_merchandise_products.php',
    'process_merchandise_categories.php',
    'process_expenses.php',
    'process_recurring_expenses.php',
    'process_onboarding.php',
  ];

  for (const file of uploadFiles) {
    test(`${file} uses persistUploadedFile`, () => {
      const content = readFile(file);
      expect(content).toContain('persistUploadedFile(');
    });

    test(`${file} does not call getNextcloudSettings`, () => {
      const content = readFile(file);
      expect(content).not.toContain('getNextcloudSettings(');
    });

    test(`${file} does not call connectNextcloud`, () => {
      const content = readFile(file);
      expect(content).not.toContain('connectNextcloud(');
    });
  }
});

// =====================================================
// 3. Database Backup Uses RustFS
// =====================================================

test.describe('database backup uses RustFS', () => {
  test('process_database_backup.php defaults to s3 destination', () => {
    const content = readFile('process_database_backup.php');
    expect(content).toContain("'s3'");
  });

  test('process_database_backup.php has force_rustfs action', () => {
    const content = readFile('process_database_backup.php');
    expect(content).toContain("case 'force_rustfs':");
  });

  test('process_database_backup.php does NOT have force_nextcloud action', () => {
    const content = readFile('process_database_backup.php');
    expect(content).not.toContain("case 'force_nextcloud':");
  });

  test('process_database_backup.php uploads to RustFS', () => {
    const content = readFile('process_database_backup.php');
    expect(content).toContain('uploadContentToRustFS');
  });

  test('cron_database_backup.php uses s3 destination', () => {
    const content = readFile('cron_database_backup.php');
    expect(content).toContain("=== 's3'");
  });

  test('cron_database_backup.php does NOT reference nextcloud destination', () => {
    const content = readFile('cron_database_backup.php');
    expect(content).not.toContain("=== 'nextcloud'");
    expect(content).not.toContain("=== 'both_nextcloud'");
  });
});

// =====================================================
// 4. No Nextcloud Settings UI Remains
// =====================================================

test.describe('no Nextcloud settings UI', () => {
  test('admin_system_tools.php has no Nextcloud tab', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).not.toContain('tab=nextcloud');
    expect(content).not.toContain('testNextcloudConnection');
  });

  test('process_settings.php has no Nextcloud cases', () => {
    const content = readFile('process_settings.php');
    expect(content).not.toContain("case 'update_nextcloud':");
    expect(content).not.toContain("case 'test_nextcloud':");
    expect(content).not.toContain("case 'update_nextcloud_backup':");
    expect(content).not.toContain("case 'test_nextcloud_backup':");
  });

  test('admin_database_backup.php has no Nextcloud destination options', () => {
    const content = readFile('views/admin_database_backup.php');
    expect(content).not.toContain('value="nextcloud"');
    expect(content).not.toContain('value="both_nextcloud"');
  });

  test('admin_database_backup.php has RustFS destination option', () => {
    const content = readFile('views/admin_database_backup.php');
    expect(content).toContain('value="s3"');
  });
});

// =====================================================
// 5. No Nextcloud Connection Code Remains
// =====================================================

test.describe('no Nextcloud connection code', () => {
  test('no file calls connectNextcloud', () => {
    const files = [
      'cloud_config.php',
      'cron_receipt_scanner.php',
      'views/video_record_athlete.php',
      'views/video_record_drill.php',
    ];
    for (const file of files) {
      const content = readFile(file);
      expect(content).not.toContain('connectNextcloud(');
    }
  });

  test('video_record_athlete.php checks RustFS instead', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('getRustFSSettings');
    expect(content).toContain('isRustFSConfigured');
  });

  test('video_record_drill.php checks RustFS instead', () => {
    const content = readFile('views/video_record_drill.php');
    expect(content).toContain('getRustFSSettings');
    expect(content).toContain('isRustFSConfigured');
  });

  test('security.php uses .credential_key not .nextcloud_key', () => {
    const content = readFile('security.php');
    expect(content).toContain('.credential_key');
    expect(content).not.toContain('.nextcloud_key');
  });

  test('security.php does not encrypt nextcloud_password', () => {
    const content = readFile('security.php');
    expect(content).not.toContain("'nextcloud_password'");
    expect(content).not.toContain("'nextcloud_backup_password'");
  });
});

// =====================================================
// 6. Database Migration Exists
// =====================================================

test.describe('database migration for Nextcloud cleanup', () => {
  test('remove_nextcloud_settings.sql migration exists', () => {
    expect(fileExists('deployment/sql/remove_nextcloud_settings.sql')).toBe(true);
  });

  test('migration deletes all nextcloud system settings', () => {
    const content = readFile('deployment/sql/remove_nextcloud_settings.sql');
    expect(content).toContain("'nextcloud_url'");
    expect(content).toContain("'nextcloud_username'");
    expect(content).toContain("'nextcloud_password'");
    expect(content).toContain("'nextcloud_backup_url'");
    expect(content).toContain("DELETE FROM `system_settings`");
  });

  test('migration updates backup_jobs destination_type ENUM', () => {
    const content = readFile('deployment/sql/remove_nextcloud_settings.sql');
    expect(content).toContain("destination_type");
    expect(content).toContain("'s3'");
  });

  test('database_schema.sql does not insert Nextcloud settings', () => {
    const content = readFile('database_schema.sql');
    expect(content).not.toContain("'nextcloud_backup_url',");
    expect(content).not.toContain("'nextcloud_images_dir',");
  });

  test('database_schema.sql uses s3 as default backup destination', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("DEFAULT 's3'");
  });
});

// =====================================================
// 7. Callers No Longer Fetch Nextcloud Settings
// =====================================================

test.describe('callers do not fetch Nextcloud settings', () => {
  const callerFiles = [
    'process_onboarding.php',
    'process_payroll.php',
    'process_coach_termination.php',
    'lib/opensign.php',
    'lib/docuseal.php',
  ];

  for (const file of callerFiles) {
    test(`${file} does not call getNextcloudSettings`, () => {
      const content = readFile(file);
      expect(content).not.toContain('getNextcloudSettings(');
    });
  }

  test('cron_receipt_scanner.php no longer scans Nextcloud', () => {
    const content = readFile('cron_receipt_scanner.php');
    expect(content).not.toContain('connectNextcloud(');
    expect(content).not.toContain('listNextcloudFiles(');
  });
});
