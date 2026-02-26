/**
 * Tests for Persistent Images and Settings
 *
 * Verifies:
 * 1. Nextcloud images directory setting exists in admin UI and backend
 * 2. sync_images option exists in admin UI and backend
 * 3. uploadImageToNextcloud() uses RustFS in cloud_config.php
 * 4. Profile image upload includes RustFS persistence in process_profile_update.php
 * 5. Admin profile image upload includes RustFS persistence in process_admin_action.php
 * 6. Evaluation media upload includes RustFS persistence in process_eval_skills.php
 * 7. Database schema includes nextcloud_image_path on users and nextcloud_path on evaluation_media
 * 8. Image helper library provides URL-based resolve functions
 * 9. Profile view includes image_helper.php
 * 10. getNextcloudSettings() includes nextcloud_images_dir
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Nextcloud images directory setting in admin UI
// =====================================================

test.describe('Nextcloud images directory setting in admin UI', () => {
  test('admin_system_tools.php should have nextcloud_images_dir input field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="nextcloud_images_dir"');
  });

  test('admin_system_tools.php should show Images Directory label', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Images Directory');
  });

  test('admin_system_tools.php should default images dir to /Images', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("nextcloud_images_dir'] ?? '/Images'");
  });
});

// =====================================================
// 2. sync_images option in admin UI and backend
// =====================================================

test.describe('sync_images option in admin UI and backend', () => {
  test('admin_system_tools.php should have sync_images checkbox', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="sync_images"');
  });

  test('admin_system_tools.php should label sync_images as Images', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Images (Profiles');
  });

  test('process_settings.php should save sync_images setting', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'sync_images'");
  });

  test('process_settings.php should save nextcloud_images_dir setting', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'nextcloud_images_dir'");
  });
});

// =====================================================
// 3. uploadImageToNextcloud and restoreImageFromNextcloud
// =====================================================

test.describe('Image upload and restore functions in cloud_config.php', () => {
  test('cloud_config.php should define uploadImageToNextcloud function', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function uploadImageToNextcloud(');
  });

  test('uploadImageToNextcloud should accept pdo, settings, local_path, subfolder, filename params', () => {
    const content = readFile('cloud_config.php');
    const fnMatch = content.match(/function uploadImageToNextcloud\(([^)]+)\)/);
    expect(fnMatch).not.toBeNull();
    expect(fnMatch[1]).toContain('$pdo');
    expect(fnMatch[1]).toContain('$settings');
    expect(fnMatch[1]).toContain('$local_file_path');
    expect(fnMatch[1]).toContain('$subfolder');
    expect(fnMatch[1]).toContain('$filename');
  });

  test('uploadImageToNextcloud should use RustFS storage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadImageToNextcloud(');
    const fnEnd = content.indexOf('function uploadLargeFileToNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("getRustFSSettings");
    expect(fn).toContain("uploadToRustFS");
  });

  test('getNextcloudSettings should include nextcloud_images_dir', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function getNextcloudSettings(');
    const fnEnd = content.indexOf('}', fnStart);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('nextcloud_images_dir');
  });
});

// =====================================================
// 4. Profile image upload persistence
// =====================================================

test.describe('Profile image upload includes Nextcloud persistence', () => {
  test('process_profile_update.php should include cloud_config.php', () => {
    const content = readFile('process_profile_update.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_profile_update.php should call persistUploadedFile for Nextcloud persistence', () => {
    const content = readFile('process_profile_update.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_profile_update.php should save nextcloud_image_path to users table', () => {
    const content = readFile('process_profile_update.php');
    expect(content).toContain('nextcloud_image_path');
  });

  test('process_profile_update.php should handle Nextcloud upload result', () => {
    const content = readFile('process_profile_update.php');
    // Should check persist result for nextcloud_path
    expect(content).toContain("persist['nextcloud_path']");
  });
});

// =====================================================
// 5. Admin profile image upload persistence
// =====================================================

test.describe('Admin profile image upload includes Nextcloud persistence', () => {
  test('process_admin_action.php should include cloud_config.php', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_admin_action.php should call persistUploadedFile', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_admin_action.php should save nextcloud_image_path to users table', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('nextcloud_image_path');
  });
});

// =====================================================
// 6. Evaluation media upload persistence
// =====================================================

test.describe('Evaluation media upload includes Nextcloud persistence', () => {
  test('process_eval_skills.php should include cloud_config.php', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_eval_skills.php should call persistUploadedFile for Nextcloud persistence', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_eval_skills.php should save nextcloud_path to evaluation_media', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain("nextcloud_path = ? WHERE id = ?");
  });

  test('process_eval_skills.php should handle Nextcloud upload result', () => {
    const content = readFile('process_eval_skills.php');
    // Should check persist result for nextcloud_path
    expect(content).toContain("persist['nextcloud_path']");
  });
});

// =====================================================
// 7. Database schema migrations
// =====================================================

test.describe('Database schema includes persistent image columns', () => {
  test('database_schema.sql should add nextcloud_image_path to users table', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('nextcloud_image_path');
  });

  test('database_schema.sql should add nextcloud_path to evaluation_media table', () => {
    const content = readFile('database_schema.sql');
    // Should have ALTER TABLE for evaluation_media nextcloud_path
    const alterSection = content.substring(content.lastIndexOf('ALTER TABLE `evaluation_media`'));
    expect(alterSection).toContain('nextcloud_path');
  });

  test('database_schema.sql should insert default nextcloud_images_dir setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'nextcloud_images_dir'");
    expect(content).toContain("'/Images'");
  });
});

// =====================================================
// 8. Image helper library
// =====================================================

test.describe('Image helper library provides resolve functions', () => {
  test('lib/image_helper.php should exist', () => {
    const exists = fs.existsSync(path.join(ROOT, 'lib/image_helper.php'));
    expect(exists).toBe(true);
  });

  test('image_helper.php should define resolveProfileImage function', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('function resolveProfileImage(');
  });

  test('image_helper.php should define resolveEvaluationMedia function', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('function resolveEvaluationMedia(');
  });

  test('resolveProfileImage should validate URL paths', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveProfileImage(');
    const fnEnd = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('https?://');
  });

  test('resolveProfileImage should return null for non-URL paths', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveProfileImage(');
    const fnEnd = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('return null');
  });

  test('resolveEvaluationMedia should validate URL paths', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart);
    expect(fn).toContain('https?://');
  });

  test('image_helper.php should include cloud_config.php', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('cloud_config.php');
  });

  test('image_helper.php should validate paths with URL check', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('isValidImagePath');
    expect(content).toContain('https?://');
  });

  test('isValidImagePath should only accept URLs', () => {
    const content = readFile('lib/image_helper.php');
    const fn = content.substring(
      content.indexOf('function isValidImagePath('),
      content.indexOf('function tryRestoreFromPersistent(')
    );
    expect(fn).toContain("preg_match");
    expect(fn).toContain("https?://");
  });
});

// =====================================================
// 9. Profile view includes image restoration
// =====================================================

test.describe('Profile view includes image helper', () => {
  test('views/profile.php should include image_helper.php', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('image_helper.php');
  });

  test('views/profile.php should display profile image from database', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('profile_image');
  });
});

// =====================================================
// 10. persistUploadedFile uses RustFS
// =====================================================

test.describe('persistUploadedFile function in cloud_config.php', () => {
  test('cloud_config.php should define persistUploadedFile function', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function persistUploadedFile(');
  });

  test('persistUploadedFile should use RustFS for storage', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function persistUploadedFile(');
    const fnEnd = content.indexOf('function getDrillVideoPath(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadToRustFS');
  });

  test('persistUploadedFile should return nextcloud_path for backward compatibility', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function persistUploadedFile(');
    const fnEnd = content.indexOf('function getDrillVideoPath(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'nextcloud_path'");
    expect(fn).toContain("'rustfs_url'");
  });
});

// =====================================================
// 11. uploadImageToNextcloud also saves to persistent storage
// =====================================================

test.describe('uploadImageToNextcloud uses RustFS', () => {
  test('uploadImageToNextcloud should call uploadToRustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadImageToNextcloud(');
    const fnEnd = content.indexOf('function uploadLargeFileToNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('uploadToRustFS(');
  });
});

// =====================================================
// 12. uploadLargeFileToNextcloud uses RustFS
// =====================================================

test.describe('uploadLargeFileToNextcloud uses RustFS', () => {
  test('cloud_config.php should define uploadLargeFileToNextcloud function', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function uploadLargeFileToNextcloud(');
  });

  test('uploadLargeFileToNextcloud should use RustFS', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadLargeFileToNextcloud(');
    const fnEnd = content.indexOf('function persistUploadedFile(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('getRustFSSettings');
    expect(fn).toContain('uploadLargeFileToRustFS');
  });
});

// =====================================================
// 13. Image helper resolve functions are URL-based
// =====================================================

test.describe('Image helper resolve functions are URL-based', () => {
  test('image_helper.php should define tryRestoreFromPersistent as no-op', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('function tryRestoreFromPersistent(');
    // Should be a no-op returning false
    const fnStart = content.indexOf('function tryRestoreFromPersistent(');
    const fnEnd = content.indexOf('function resolveProfileImage(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('return false');
  });

  test('resolveProfileImage should return path only for URLs', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveProfileImage(');
    const fnEnd = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('https?://');
    expect(fn).toContain('return null');
  });

  test('resolveEvaluationMedia should return path only for URLs', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveEvaluationMedia(');
    const fnEnd = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('https?://');
    expect(fn).toContain('return null');
  });

  test('resolveDrillImage should return path only for URLs', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart);
    expect(fn).toContain('https?://');
    expect(fn).toContain('return null');
  });
});

// =====================================================
// 14. Persistent storage in admin UI and gitignore
// =====================================================

test.describe('Persistent storage in admin UI and infrastructure', () => {
  test('admin_system_tools.php should show persistent local storage path', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Persistent Local Storage');
    expect(content).toContain('persistent_uploads');
  });

  test('.gitignore should exclude persistent_uploads', () => {
    const content = readFile('.gitignore');
    expect(content).toContain('persistent_uploads');
  });
});

// =====================================================
// 15. Editable persistent data path
// =====================================================

test.describe('Persistent data path is editable in nextcloud config', () => {
  test('admin_system_tools.php should have editable nextcloud_persistent_path input', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('name="nextcloud_persistent_path"');
  });

  test('admin_system_tools.php persistent path input should not be readonly or disabled', () => {
    const content = readFile('views/admin_system_tools.php');
    // Find the persistent path input and ensure it is not readonly/disabled
    const inputIdx = content.indexOf('name="nextcloud_persistent_path"');
    expect(inputIdx).toBeGreaterThan(-1);
    // Get the surrounding input tag
    const tagStart = content.lastIndexOf('<input', inputIdx);
    const tagEnd = content.indexOf('>', inputIdx);
    const inputTag = content.substring(tagStart, tagEnd + 1);
    expect(inputTag).not.toContain('readonly');
    expect(inputTag).not.toContain('disabled');
  });

  test('admin_system_tools.php should load nextcloud_persistent_path from settings', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("nextcloud_persistent_path']");
  });

  test('process_settings.php should read nextcloud_persistent_path from POST', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("nextcloud_persistent_path");
  });

  test('process_settings.php should save nextcloud_persistent_path setting', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'nextcloud_persistent_path'");
  });

  test('getNextcloudSettings should include nextcloud_persistent_path', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function getNextcloudSettings(');
    const fnEnd = content.indexOf('}', fnStart);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('nextcloud_persistent_path');
  });

  test('database_schema.sql should insert default nextcloud_persistent_path setting', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("'nextcloud_persistent_path'");
  });
});
