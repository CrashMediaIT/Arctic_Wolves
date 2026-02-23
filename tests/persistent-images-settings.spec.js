/**
 * Tests for Persistent Images and Settings
 *
 * Verifies:
 * 1. Nextcloud images directory setting exists in admin UI and backend
 * 2. sync_images option exists in admin UI and backend
 * 3. uploadImageToNextcloud() and restoreImageFromNextcloud() functions exist in cloud_config.php
 * 4. Profile image upload includes Nextcloud persistence in process_profile_update.php
 * 5. Admin profile image upload includes Nextcloud persistence in process_admin_action.php
 * 6. Evaluation media upload includes Nextcloud persistence in process_eval_skills.php
 * 7. Database schema includes nextcloud_image_path on users and nextcloud_path on evaluation_media
 * 8. Image helper library provides resolve functions for restoring from Nextcloud
 * 9. Profile view includes image restoration from Nextcloud
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

  test('uploadImageToNextcloud should use nextcloud_images_dir setting', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function uploadImageToNextcloud(');
    const fnEnd = content.indexOf('function restoreImageFromNextcloud(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("nextcloud_images_dir");
  });

  test('cloud_config.php should define restoreImageFromNextcloud function', () => {
    const content = readFile('cloud_config.php');
    expect(content).toContain('function restoreImageFromNextcloud(');
  });

  test('restoreImageFromNextcloud should use downloadNextcloudFile', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function restoreImageFromNextcloud(');
    const fnSection = content.substring(fnStart, fnStart + 1000);
    expect(fnSection).toContain('downloadNextcloudFile');
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

  test('process_profile_update.php should call uploadImageToNextcloud', () => {
    const content = readFile('process_profile_update.php');
    expect(content).toContain('uploadImageToNextcloud(');
  });

  test('process_profile_update.php should save nextcloud_image_path to users table', () => {
    const content = readFile('process_profile_update.php');
    expect(content).toContain('nextcloud_image_path');
  });

  test('process_profile_update.php should handle Nextcloud upload failure gracefully', () => {
    const content = readFile('process_profile_update.php');
    // Should catch exceptions and log errors
    expect(content).toContain('Nextcloud profile image upload failed');
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

  test('process_admin_action.php should call uploadImageToNextcloud', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('uploadImageToNextcloud(');
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

  test('process_eval_skills.php should call uploadImageToNextcloud', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain('uploadImageToNextcloud(');
  });

  test('process_eval_skills.php should save nextcloud_path to evaluation_media', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain("nextcloud_path = ? WHERE id = ?");
  });

  test('process_eval_skills.php should handle Nextcloud upload failure gracefully', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain('Nextcloud evaluation media upload failed');
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

  test('resolveProfileImage should check file_exists before restoring', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveProfileImage(');
    const fnEnd = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('file_exists');
  });

  test('resolveProfileImage should use restoreImageFromNextcloud', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveProfileImage(');
    const fnEnd = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('restoreImageFromNextcloud');
  });

  test('resolveEvaluationMedia should use restoreImageFromNextcloud', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveEvaluationMedia(');
    const fn = content.substring(fnStart);
    expect(fn).toContain('restoreImageFromNextcloud');
  });

  test('image_helper.php should include cloud_config.php', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('cloud_config.php');
  });

  test('image_helper.php should validate paths to prevent directory traversal', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('isValidImagePath');
    expect(content).toContain("strpos($path, '..')");
  });

  test('isValidImagePath should check path starts with uploads/', () => {
    const content = readFile('lib/image_helper.php');
    const fn = content.substring(
      content.indexOf('function isValidImagePath('),
      content.indexOf('function resolveProfileImage(')
    );
    expect(fn).toContain("strpos($path, 'uploads/')");
  });
});

// =====================================================
// 9. Profile view includes image restoration
// =====================================================

test.describe('Profile view includes image restoration from Nextcloud', () => {
  test('views/profile.php should include image_helper.php', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('image_helper.php');
  });

  test('views/profile.php should call resolveProfileImage when file is missing', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('resolveProfileImage');
  });

  test('views/profile.php should check file_exists before restoring', () => {
    const content = readFile('views/profile.php');
    // Should check if local profile image file exists and validate path safety
    expect(content).toContain('file_exists($img_path)');
    expect(content).toContain("strpos($img_path, '..')");
  });
});
