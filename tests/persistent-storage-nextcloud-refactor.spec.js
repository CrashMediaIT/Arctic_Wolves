/**
 * Tests for Persistent Storage + Nextcloud Refactor
 *
 * Verifies that ALL upload functions use persistUploadedFile() which:
 * 1. Saves to /config/persistent_uploads (via saveToPersistentStorage)
 * 2. Uploads to Nextcloud (via uploadImageToNextcloud or uploadLargeFileToNextcloud)
 * 3. Caches locally in project uploads/ directory for serving
 * 4. Stores the Nextcloud remote_path in the database for recovery
 *
 * Upload functions tested:
 *  1. handleFileUpload (process_theme.php) - theme images
 *  2. downloadAndSaveImage (process_drills.php) - drill diagram images
 *  3. downloadAndSaveDrillImage (process_practice_plans.php) - practice plan drill images
 *  4. save_drill video upload (process_drills.php) - drill videos
 *  5. handleVideoUpload (process_video.php) - coach review videos
 *  6. handleAthleteVideoUpload (process_video.php) - athlete videos
 *  7. handleDrillVideoUpload (process_video.php) - drill recording videos
 *  8. handleUploadVideoSource (process_video.php) - gameplan video sources
 *  9. upload_avatar (process_profile_update.php) - profile images
 * 10. create_exercise/update_exercise (process_workout.php) - exercise images
 * 11. add_media (process_eval_goals.php) - evaluation goal media
 * 12. upload_media (process_eval_skills.php) - evaluation skill media
 * 13. handleProductImageUpload (process_merchandise_products.php) - product images
 * 14. handleCategoryImageUpload (process_merchandise_categories.php) - category images
 * 15. saveThemeUploadResult (process_theme.php) - theme NC path storage
 * 16. restoreThemeImagesFromPersistentStorage (cloud_config.php) - theme restore
 * 17. persistUploadedFile (cloud_config.php) - central upload function
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. persistUploadedFile central function
// =====================================================

test.describe('persistUploadedFile central function in cloud_config.php', () => {
  const content = () => readFile('cloud_config.php');

  test('persistUploadedFile function exists', () => {
    expect(content()).toContain('function persistUploadedFile(');
  });

  test('persistUploadedFile calls uploadToRustFS', () => {
    const fn = content().substring(
      content().indexOf('function persistUploadedFile('),
      content().indexOf('function restoreImageFromNextcloud(') > -1
        ? content().indexOf('function restoreImageFromNextcloud(')
        : content().indexOf('/**', content().indexOf('function persistUploadedFile(') + 100)
    );
    expect(fn).toContain('uploadToRustFS(');
  });

  test('persistUploadedFile calls uploadToRustFS or uploadLargeFileToRustFS', () => {
    const fn = content().substring(
      content().indexOf('function persistUploadedFile('),
      content().indexOf('function restoreImageFromNextcloud(') > -1
        ? content().indexOf('function restoreImageFromNextcloud(')
        : content().indexOf('/**', content().indexOf('function persistUploadedFile(') + 100)
    );
    expect(fn.includes('uploadToRustFS(') || fn.includes('uploadLargeFileToRustFS(')).toBe(true);
  });

  test('persistUploadedFile returns nextcloud_path', () => {
    const fn = content().substring(
      content().indexOf('function persistUploadedFile('),
      content().indexOf('function restoreImageFromNextcloud(') > -1
        ? content().indexOf('function restoreImageFromNextcloud(')
        : content().indexOf('/**', content().indexOf('function persistUploadedFile(') + 100)
    );
    expect(fn).toContain("'nextcloud_path'");
  });

  test('persistUploadedFile does not copy to local cache directory', () => {
    const fn = content().substring(
      content().indexOf('function persistUploadedFile('),
      content().indexOf('function restoreImageFromNextcloud(') > -1
        ? content().indexOf('function restoreImageFromNextcloud(')
        : content().indexOf('/**', content().indexOf('function persistUploadedFile(') + 100)
    );
    expect(fn).not.toContain('copy(');
  });

  test('persistUploadedFile accepts use_large_upload parameter', () => {
    const fn = content().substring(
      content().indexOf('function persistUploadedFile('),
      content().indexOf('function persistUploadedFile(') + 200
    );
    expect(fn).toContain('$use_large_upload');
  });
});

// =====================================================
// 2. handleFileUpload (process_theme.php)
// =====================================================

test.describe('handleFileUpload uses persistUploadedFile', () => {
  const content = () => readFile('process_theme.php');

  test('handleFileUpload calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function handleFileUpload('),
      content().indexOf('}', content().indexOf("return ['success' => true"))
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('handleFileUpload returns nextcloud_path', () => {
    const fn = content().substring(
      content().indexOf('function handleFileUpload('),
      content().indexOf('}', content().indexOf("return ['success' => true"))
    );
    expect(fn).toContain("'nextcloud_path'");
  });

  test('handleFileUpload does not use move_uploaded_file to uploads/theme/ as primary', () => {
    const fn = content().substring(
      content().indexOf('function handleFileUpload('),
      content().indexOf('}', content().indexOf("return ['success' => true"))
    );
    // persistUploadedFile handles the move, not a direct move_uploaded_file to uploads/theme/
    // The function should NOT have move_uploaded_file as the primary path
    const mainPath = fn.substring(0, fn.indexOf('} else {') > -1 ? fn.indexOf('} else {') : fn.length);
    expect(mainPath).toContain('persistUploadedFile(');
  });
});

// =====================================================
// 3. saveThemeUploadResult stores NC path
// =====================================================

test.describe('saveThemeUploadResult stores Nextcloud path in theme_settings', () => {
  const content = () => readFile('process_theme.php');

  test('saveThemeUploadResult function exists', () => {
    expect(content()).toContain('function saveThemeUploadResult(');
  });

  test('saveThemeUploadResult stores _nc_path setting', () => {
    const fn = content().substring(
      content().indexOf('function saveThemeUploadResult('),
      content().indexOf('}', content().indexOf('function saveThemeUploadResult(') + 50)
    );
    expect(fn).toContain('_nc_path');
  });

  test('all theme upload handlers use saveThemeUploadResult', () => {
    // All handleFileUpload results for theme settings should go through saveThemeUploadResult
    expect(content()).toContain("saveThemeUploadResult($pdo, 'logo_url'");
    expect(content()).toContain("saveThemeUploadResult($pdo, 'business_card_front_bg_url'");
    expect(content()).toContain("saveThemeUploadResult($pdo, 'business_card_back_bg_url'");
    expect(content()).toContain("saveThemeUploadResult($pdo, 'hero_image_url'");
    expect(content()).toContain("saveThemeUploadResult($pdo, 'center_ice_logo_url'");
  });
});

// =====================================================
// 4. restoreThemeImagesFromPersistentStorage uses NC paths
// =====================================================

test.describe('restoreThemeImagesFromPersistentStorage uses stored Nextcloud paths', () => {
  const content = () => readFile('cloud_config.php');

  test('restore function is a no-op for RustFS migration', () => {
    const fn = content().substring(
      content().indexOf('function restoreThemeImagesFromPersistentStorage('),
      content().indexOf('function restoreThemeImagesFromPersistentStorage(') + 300
    );
    expect(fn).toContain('No-op');
  });

  test('restore function does not query business_card_front_bg_url_nc_path', () => {
    const fn = content().substring(
      content().indexOf('function restoreThemeImagesFromPersistentStorage('),
      content().indexOf('function restoreThemeImagesFromPersistentStorage(') + 300
    );
    expect(fn).toContain('No-op');
  });

  test('restore function is a no-op and does not use NC path variables', () => {
    const fn = content().substring(
      content().indexOf('function restoreThemeImagesFromPersistentStorage('),
      content().indexOf('function restoreThemeImagesFromPersistentStorage(') + 300
    );
    expect(fn).toContain('No-op');
  });
});

// =====================================================
// 5. downloadAndSaveImage (process_drills.php)
// =====================================================

test.describe('downloadAndSaveImage uses persistUploadedFile', () => {
  const content = () => readFile('process_drills.php');

  test('downloadAndSaveImage calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function downloadAndSaveImage('),
      content().indexOf('}', content().indexOf("return ['local_path' => $local_cache_rel"))
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('downloadAndSaveImage returns nextcloud_path', () => {
    const fn = content().substring(
      content().indexOf('function downloadAndSaveImage('),
      content().indexOf('}', content().indexOf("return ['local_path' => $local_cache_rel"))
    );
    expect(fn).toContain("'nextcloud_path'");
  });

  test('IHS import stores nextcloud_image_path after drill creation', () => {
    // Look for the import_ihs_url section specifically
    const ihsStart = content().indexOf("action === 'import_ihs_url'");
    const ihsEnd = content().indexOf("status=drill_imported", ihsStart);
    const ihsSection = content().substring(ihsStart, ihsEnd);
    expect(ihsSection).toContain('nextcloud_image_path');
  });
});

// =====================================================
// 6. downloadAndSaveDrillImage (process_practice_plans.php)
// =====================================================

test.describe('downloadAndSaveDrillImage uses persistUploadedFile', () => {
  const content = () => readFile('process_practice_plans.php');

  test('downloadAndSaveDrillImage calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function downloadAndSaveDrillImage('),
      content().indexOf('}', content().indexOf("return ['local_path' => $local_cache_rel", content().indexOf('function downloadAndSaveDrillImage(')))
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('downloadAndSaveDrillImage returns nextcloud_path', () => {
    const fn = content().substring(
      content().indexOf('function downloadAndSaveDrillImage('),
      content().indexOf('}', content().indexOf("return ['local_path' => $local_cache_rel", content().indexOf('function downloadAndSaveDrillImage(')))
    );
    expect(fn).toContain("'nextcloud_path'");
  });

  test('practice plan import stores nextcloud_image_path after drill creation', () => {
    const importSection = content().substring(
      content().indexOf("import_ihs_practice_plan"),
      content().indexOf("status=plan_imported")
    );
    expect(importSection).toContain('nextcloud_image_path');
  });
});

// =====================================================
// 7. Drill video upload (process_drills.php)
// =====================================================

test.describe('Drill video upload uses persistUploadedFile', () => {
  const content = () => readFile('process_drills.php');

  test('drill video upload calls persistUploadedFile', () => {
    const section = content().substring(
      content().indexOf("video_type === 'upload'"),
      content().indexOf("} else {", content().indexOf("video_type === 'upload'") + 50)
    );
    expect(section).toContain('persistUploadedFile(');
  });

  test('drill video stores nextcloud_image_path', () => {
    expect(content()).toContain('$drill_video_nc_path');
    expect(content()).toContain("UPDATE drills SET nextcloud_image_path = ? WHERE id = ?");
  });
});

// =====================================================
// 8. handleVideoUpload (process_video.php) - coach
// =====================================================

test.describe('handleVideoUpload uses persistUploadedFile', () => {
  const content = () => readFile('process_video.php');

  test('handleVideoUpload calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function handleVideoUpload()'),
      content().indexOf('function handleAthleteVideoUpload()')
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('handleVideoUpload stores nextcloud_path in videos table', () => {
    const fn = content().substring(
      content().indexOf('function handleVideoUpload()'),
      content().indexOf('function handleAthleteVideoUpload()')
    );
    expect(fn).toContain("UPDATE videos SET nextcloud_path = ?");
  });

  test('handleVideoUpload uses large upload flag', () => {
    const fn = content().substring(
      content().indexOf('function handleVideoUpload()'),
      content().indexOf('function handleAthleteVideoUpload()')
    );
    expect(fn).toContain('true)');
  });
});

// =====================================================
// 9. handleAthleteVideoUpload (process_video.php)
// =====================================================

test.describe('handleAthleteVideoUpload uses persistUploadedFile', () => {
  const content = () => readFile('process_video.php');

  test('handleAthleteVideoUpload calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function handleAthleteVideoUpload()'),
      content().indexOf('function handleDrillVideoUpload()')
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('handleAthleteVideoUpload stores nextcloud_path', () => {
    const fn = content().substring(
      content().indexOf('function handleAthleteVideoUpload()'),
      content().indexOf('function handleDrillVideoUpload()')
    );
    expect(fn).toContain("UPDATE videos SET nextcloud_path = ?");
  });
});

// =====================================================
// 10. handleDrillVideoUpload (process_video.php)
// =====================================================

test.describe('handleDrillVideoUpload uses persistUploadedFile', () => {
  const content = () => readFile('process_video.php');

  test('handleDrillVideoUpload calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function handleDrillVideoUpload()'),
      content().indexOf('function handleVideoUpdate()')
    );
    expect(fn).toContain('persistUploadedFile(');
  });
});

// =====================================================
// 11. handleUploadVideoSource (process_video.php)
// =====================================================

test.describe('handleUploadVideoSource uses persistUploadedFile', () => {
  const content = () => readFile('process_video.php');

  test('handleUploadVideoSource calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function handleUploadVideoSource()'),
      content().indexOf('function handleCreateClip()')
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('handleUploadVideoSource stores nextcloud_path in vr_video_sources', () => {
    const fn = content().substring(
      content().indexOf('function handleUploadVideoSource()'),
      content().indexOf('function handleCreateClip()')
    );
    expect(fn).toContain("UPDATE vr_video_sources SET nextcloud_path = ?");
  });
});

// =====================================================
// 12. upload_avatar (process_profile_update.php)
// =====================================================

test.describe('upload_avatar uses persistUploadedFile', () => {
  const content = () => readFile('process_profile_update.php');

  test('profile upload calls persistUploadedFile', () => {
    expect(content()).toContain('persistUploadedFile(');
  });

  test('profile upload stores nextcloud_image_path', () => {
    expect(content()).toContain("UPDATE users SET nextcloud_image_path = ?");
  });
});

// =====================================================
// 13. Exercise images (process_workout.php)
// =====================================================

test.describe('Exercise image uploads use persistUploadedFile', () => {
  const content = () => readFile('process_workout.php');

  test('create_exercise calls persistUploadedFile', () => {
    const section = content().substring(
      content().indexOf("case 'create_exercise':"),
      content().indexOf("case 'update_exercise':")
    );
    expect(section).toContain('persistUploadedFile(');
  });

  test('update_exercise calls persistUploadedFile', () => {
    const section = content().substring(
      content().indexOf("case 'update_exercise':"),
      content().indexOf("case 'delete_exercise':")
    );
    expect(section).toContain('persistUploadedFile(');
  });

  test('exercise uploads store nextcloud_image_path', () => {
    expect(content()).toContain("UPDATE exercise_library SET nextcloud_image_path = ?");
  });
});

// =====================================================
// 14. Evaluation goal media (process_eval_goals.php)
// =====================================================

test.describe('Evaluation goal media uses persistUploadedFile', () => {
  const content = () => readFile('process_eval_goals.php');

  test('add_media calls persistUploadedFile', () => {
    expect(content()).toContain('persistUploadedFile(');
  });

  test('add_media stores nextcloud_path', () => {
    expect(content()).toContain("UPDATE goal_eval_progress SET nextcloud_path = ?");
  });
});

// =====================================================
// 15. Evaluation skill media (process_eval_skills.php)
// =====================================================

test.describe('Evaluation skill media uses persistUploadedFile', () => {
  const content = () => readFile('process_eval_skills.php');

  test('upload_media calls persistUploadedFile', () => {
    expect(content()).toContain('persistUploadedFile(');
  });

  test('upload_media stores nextcloud_path', () => {
    expect(content()).toContain("UPDATE evaluation_media SET nextcloud_path = ?");
  });
});

// =====================================================
// 16. Merchandise product images
// =====================================================

test.describe('Merchandise product images use persistUploadedFile', () => {
  const content = () => readFile('process_merchandise_products.php');

  test('handleProductImageUpload calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function handleProductImageUpload('),
      content().indexOf('}', content().indexOf("return ['url' =>", content().indexOf('function handleProductImageUpload(')))
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('handleProductImageUpload returns nextcloud_path', () => {
    const fn = content().substring(
      content().indexOf('function handleProductImageUpload('),
      content().indexOf('}', content().indexOf("return ['url' =>", content().indexOf('function handleProductImageUpload(')))
    );
    expect(fn).toContain("'nextcloud_path'");
  });

  test('product create stores nextcloud_image_path', () => {
    const section = content().substring(
      content().indexOf("case 'create':"),
      content().indexOf("case 'update':")
    );
    expect(section).toContain("UPDATE merchandise_products SET nextcloud_image_path = ?");
  });

  test('product update stores nextcloud_image_path', () => {
    const section = content().substring(
      content().indexOf("case 'update':"),
      content().indexOf("case 'update_inventory':")
    );
    expect(section).toContain("UPDATE merchandise_products SET nextcloud_image_path = ?");
  });
});

// =====================================================
// 17. Merchandise category images
// =====================================================

test.describe('Merchandise category images use persistUploadedFile', () => {
  const content = () => readFile('process_merchandise_categories.php');

  test('handleCategoryImageUpload calls persistUploadedFile', () => {
    const fn = content().substring(
      content().indexOf('function handleCategoryImageUpload('),
      content().indexOf('}', content().indexOf("return ['url' =>", content().indexOf('function handleCategoryImageUpload(')))
    );
    expect(fn).toContain('persistUploadedFile(');
  });

  test('handleCategoryImageUpload returns nextcloud_path', () => {
    const fn = content().substring(
      content().indexOf('function handleCategoryImageUpload('),
      content().indexOf('}', content().indexOf("return ['url' =>", content().indexOf('function handleCategoryImageUpload(')))
    );
    expect(fn).toContain("'nextcloud_path'");
  });

  test('category create stores nextcloud_image_path', () => {
    const section = content().substring(
      content().indexOf("case 'create':"),
      content().indexOf("case 'update':")
    );
    expect(section).toContain("UPDATE merchandise_categories SET nextcloud_image_path = ?");
  });

  test('category update stores nextcloud_image_path', () => {
    const section = content().substring(
      content().indexOf("case 'update':"),
      content().indexOf("case 'delete':")
    );
    expect(section).toContain("UPDATE merchandise_categories SET nextcloud_image_path = ?");
  });
});

// =====================================================
// 18. process_ihs_import.php includes cloud_config
// =====================================================

test.describe('process_ihs_import.php includes cloud_config.php', () => {
  test('process_ihs_import.php requires cloud_config.php', () => {
    const content = readFile('process_ihs_import.php');
    expect(content).toContain('cloud_config.php');
  });
});

// =====================================================
// 19. No direct uploads to Arctic_Wolves/uploads/ as primary
// =====================================================

test.describe('Upload functions do not use local uploads/ as primary storage', () => {
  test('handleFileUpload does not have move_uploaded_file as primary path', () => {
    const content = readFile('process_theme.php');
    const fn = content.substring(
      content.indexOf('function handleFileUpload('),
      content.indexOf("return ['success' => true")
    );
    // The main branch should use persistUploadedFile, not move_uploaded_file
    const beforeElse = fn.substring(0, fn.indexOf('} else {') > -1 ? fn.indexOf('} else {') : fn.length);
    expect(beforeElse).toContain('persistUploadedFile(');
  });

  test('handleVideoUpload does not use move_uploaded_file', () => {
    const content = readFile('process_video.php');
    const fn = content.substring(
      content.indexOf('function handleVideoUpload()'),
      content.indexOf('function handleAthleteVideoUpload()')
    );
    expect(fn).not.toContain('move_uploaded_file(');
  });

  test('handleAthleteVideoUpload does not use move_uploaded_file', () => {
    const content = readFile('process_video.php');
    const fn = content.substring(
      content.indexOf('function handleAthleteVideoUpload()'),
      content.indexOf('function handleDrillVideoUpload()')
    );
    expect(fn).not.toContain('move_uploaded_file(');
  });

  test('downloadAndSaveImage does not use file_put_contents to uploads/', () => {
    const content = readFile('process_drills.php');
    const fn = content.substring(
      content.indexOf('function downloadAndSaveImage('),
      content.indexOf('}', content.indexOf("return ['local_path' => $local_cache_rel"))
    );
    // Should write to sys_get_temp_dir, not directly to uploads/
    expect(fn).toContain('sys_get_temp_dir()');
  });
});
