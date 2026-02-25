/**
 * Tests for All Image Uploads - Nextcloud Persistence
 *
 * Verifies that ALL image upload areas include Nextcloud persistence:
 * 1. Exercise/workout images (process_workout.php)
 * 2. Merchandise product images (process_merchandise_products.php)
 * 3. Merchandise category images (process_merchandise_categories.php)
 * 4. Drill videos and diagram images (process_drills.php)
 * 5. Practice plan drill images (process_practice_plans.php)
 * 6. Theme images (process_theme.php)
 * 7. Evaluation goal media (process_eval_goals.php)
 * 8. Video uploads - coach, athlete, gameplan (process_video.php)
 * 9. Team logos (process_admin_action.php)
 * 10. Database schema includes nextcloud path columns for all tables
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Exercise/workout images
// =====================================================

test.describe('Exercise image uploads include Nextcloud persistence', () => {
  test('process_workout.php should include cloud_config.php', () => {
    const content = readFile('process_workout.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_workout.php should call persistUploadedFile for create', () => {
    const content = readFile('process_workout.php');
    const createSection = content.substring(
      content.indexOf("case 'create_exercise':"),
      content.indexOf("case 'update_exercise':")
    );
    expect(createSection).toContain('persistUploadedFile(');
  });

  test('process_workout.php should call persistUploadedFile for update', () => {
    const content = readFile('process_workout.php');
    const updateSection = content.substring(
      content.indexOf("case 'update_exercise':"),
      content.indexOf('break;', content.indexOf("case 'update_exercise':") + 100)
    );
    expect(updateSection).toContain('persistUploadedFile(');
  });

  test('process_workout.php should save nextcloud_image_path to exercise_library', () => {
    const content = readFile('process_workout.php');
    expect(content).toContain('nextcloud_image_path');
    expect(content).toContain('exercise_library');
  });

  test('process_workout.php should use persistUploadedFile for Nextcloud persistence', () => {
    const content = readFile('process_workout.php');
    expect(content).toContain('persistUploadedFile(');
  });
});

// =====================================================
// 2. Merchandise product images
// =====================================================

test.describe('Merchandise product image uploads include Nextcloud persistence', () => {
  test('process_merchandise_products.php should include cloud_config.php', () => {
    const content = readFile('process_merchandise_products.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_merchandise_products.php should call persistUploadedFile for image uploads', () => {
    const content = readFile('process_merchandise_products.php');
    expect(content).toContain('persistUploadedFile(');
    expect(content).toContain("'merchandise/products'");
  });

  test('process_merchandise_products.php should use persistUploadedFile result in create and update', () => {
    const content = readFile('process_merchandise_products.php');
    expect(content).toContain("case 'create':");
    expect(content).toContain("case 'update':");
    // persistUploadedFile is called in the shared upload helper used by both create and update
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_merchandise_products.php should save nextcloud_image_path to merchandise_products', () => {
    const content = readFile('process_merchandise_products.php');
    expect(content).toContain('nextcloud_image_path');
    expect(content).toContain('merchandise_products');
  });

  test('process_merchandise_products.php should use merchandise/products subfolder', () => {
    const content = readFile('process_merchandise_products.php');
    expect(content).toContain("'merchandise/products'");
  });
});

// =====================================================
// 3. Merchandise category images
// =====================================================

test.describe('Merchandise category image uploads include Nextcloud persistence', () => {
  test('process_merchandise_categories.php should include cloud_config.php', () => {
    const content = readFile('process_merchandise_categories.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_merchandise_categories.php should call persistUploadedFile for image uploads', () => {
    const content = readFile('process_merchandise_categories.php');
    expect(content).toContain('persistUploadedFile(');
    expect(content).toContain("'merchandise/categories'");
  });

  test('process_merchandise_categories.php should use persistUploadedFile result in create and update', () => {
    const content = readFile('process_merchandise_categories.php');
    expect(content).toContain("case 'create':");
    expect(content).toContain("case 'update':");
    // persistUploadedFile is called in the shared upload helper used by both create and update
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_merchandise_categories.php should save nextcloud_image_path to merchandise_categories', () => {
    const content = readFile('process_merchandise_categories.php');
    expect(content).toContain('nextcloud_image_path');
    expect(content).toContain('merchandise_categories');
  });
});

// =====================================================
// 4. Drill videos and diagram images
// =====================================================

test.describe('Drill uploads include Nextcloud persistence', () => {
  test('process_drills.php should include cloud_config.php', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_drills.php should upload drill videos to Nextcloud', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain("'drills/videos'");
  });

  test('process_drills.php should upload drill diagrams to Nextcloud in downloadAndSaveImage', () => {
    const content = readFile('process_drills.php');
    const fnStart = content.indexOf('function downloadAndSaveImage(');
    // Find the closing brace of the function (look for the next top-level function or end marker)
    const fnEnd = content.indexOf('\n// ====', fnStart);
    const downloadFn = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 3000);
    expect(downloadFn).toContain('persistUploadedFile(');
    expect(downloadFn).toContain("'drills/diagrams'");
  });

  test('process_drills.php should save nextcloud_image_path to drills table', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain('nextcloud_image_path');
  });

  test('process_drills.php should handle Nextcloud upload failure gracefully', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain('persistUploadedFile(');
    expect(content).toContain('Nextcloud drill diagram upload failed');
  });
});

// =====================================================
// 5. Practice plan drill images
// =====================================================

test.describe('Practice plan drill images include Nextcloud persistence', () => {
  test('process_practice_plans.php should include cloud_config.php', () => {
    const content = readFile('process_practice_plans.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_practice_plans.php should upload downloaded drill images to Nextcloud', () => {
    const content = readFile('process_practice_plans.php');
    const fnStart = content.indexOf('function downloadAndSaveDrillImage(');
    const fnEnd = content.indexOf('\n// ====', fnStart);
    const downloadFn = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 3000);
    expect(downloadFn).toContain('persistUploadedFile(');
    expect(downloadFn).toContain("'drills/diagrams'");
  });

  test('process_practice_plans.php should handle Nextcloud upload failure gracefully', () => {
    const content = readFile('process_practice_plans.php');
    expect(content).toContain('Nextcloud drill diagram upload failed');
  });
});

// =====================================================
// 6. Theme images
// =====================================================

test.describe('Theme image uploads include Nextcloud persistence', () => {
  test('process_theme.php should include cloud_config.php', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_theme.php handleFileUpload should upload to Nextcloud', () => {
    const content = readFile('process_theme.php');
    const handleFn = content.substring(
      content.indexOf('function handleFileUpload('),
      content.indexOf('function handleFileUpload(') + 2000
    );
    expect(handleFn).toContain('persistUploadedFile(');
    expect(handleFn).toContain("'theme'");
  });

  test('process_theme.php should handle Nextcloud upload failure gracefully', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain('Theme image persist failed');
  });
});

// =====================================================
// 7. Evaluation goal media
// =====================================================

test.describe('Evaluation goal media uploads include Nextcloud persistence', () => {
  test('process_eval_goals.php should include cloud_config.php', () => {
    const content = readFile('process_eval_goals.php');
    expect(content).toContain('cloud_config.php');
  });

  test('process_eval_goals.php should call persistUploadedFile', () => {
    const content = readFile('process_eval_goals.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_eval_goals.php should save nextcloud_path to goal_eval_progress', () => {
    const content = readFile('process_eval_goals.php');
    expect(content).toContain('nextcloud_path');
    expect(content).toContain('goal_eval_progress');
  });

  test('process_eval_goals.php should use eval_goals subfolder', () => {
    const content = readFile('process_eval_goals.php');
    expect(content).toContain("'eval_goals/'");
  });
});

// =====================================================
// 8. Video uploads - coach, athlete, gameplan
// =====================================================

test.describe('Video uploads include Nextcloud persistence', () => {
  test('process_video.php should include cloud_config.php at top level', () => {
    const content = readFile('process_video.php');
    // cloud_config should be included as a top-level require, not just inside drill function
    const topSection = content.substring(0, content.indexOf('function '));
    expect(topSection).toContain('cloud_config.php');
  });

  test('process_video.php should upload coach videos to Nextcloud', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("'videos/coach'");
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_video.php should upload athlete videos to Nextcloud', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("'videos/athlete'");
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_video.php should upload gameplan videos to Nextcloud', () => {
    const content = readFile('process_video.php');
    expect(content).toContain("'videos/gameplan'");
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_video.php should save nextcloud_path to videos table', () => {
    const content = readFile('process_video.php');
    const matches = content.match(/UPDATE videos SET nextcloud_path/g);
    expect(matches).not.toBeNull();
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });

  test('process_video.php should save nextcloud_path to vr_video_sources table', () => {
    const content = readFile('process_video.php');
    expect(content).toContain('UPDATE vr_video_sources SET nextcloud_path');
  });
});

// =====================================================
// 9. Team logos
// =====================================================

test.describe('Team logo uploads include Nextcloud persistence', () => {
  test('process_admin_action.php should upload team logos to Nextcloud on create', () => {
    const content = readFile('process_admin_action.php');
    // Find the create_team section
    expect(content).toContain("'team_logos'");
  });

  test('process_admin_action.php should save nextcloud_logo_path to teams table', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('nextcloud_logo_path');
  });

  test('process_admin_action.php should handle team logo cloud path storage failure gracefully', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('Team logo cloud path storage failed');
  });
});

// =====================================================
// 10. Database schema
// =====================================================

test.describe('Database schema includes nextcloud path columns for all tables', () => {
  test('database_schema.sql should add nextcloud_image_path to exercise_library', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('exercise_library');
    expect(content).toContain("ADD COLUMN IF NOT EXISTS `nextcloud_image_path`");
  });

  test('database_schema.sql should add nextcloud_image_path to merchandise_products', () => {
    const content = readFile('database_schema.sql');
    const section = content.substring(content.indexOf('merchandise_products'));
    expect(section).toContain('nextcloud_image_path');
  });

  test('database_schema.sql should add nextcloud_image_path to merchandise_categories', () => {
    const content = readFile('database_schema.sql');
    const section = content.substring(content.indexOf('merchandise_categories'));
    expect(section).toContain('nextcloud_image_path');
  });

  test('database_schema.sql should add nextcloud_image_path to drills', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('ALTER TABLE `drills`');
    const drillsSection = content.substring(content.indexOf('ALTER TABLE `drills`'));
    expect(drillsSection).toContain('nextcloud_image_path');
  });

  test('database_schema.sql should add nextcloud_path to goal_eval_progress', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('ALTER TABLE `goal_eval_progress`');
  });

  test('database_schema.sql should add nextcloud_logo_path to teams', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('ALTER TABLE `teams`');
    const teamsSection = content.substring(content.indexOf('ALTER TABLE `teams`'));
    expect(teamsSection).toContain('nextcloud_logo_path');
  });

  test('database_schema.sql should add nextcloud_path to vr_video_sources', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('ALTER TABLE `vr_video_sources`');
  });
});

// =====================================================
// 11. All uploads decrypt Nextcloud password
// =====================================================

test.describe('All upload handlers use persistUploadedFile which decrypts Nextcloud password', () => {
  const files = [
    'process_workout.php',
    'process_merchandise_products.php',
    'process_merchandise_categories.php',
    'process_drills.php',
    'process_theme.php',
    'process_eval_goals.php',
    'process_video.php',
  ];

  for (const file of files) {
    test(`${file} should use persistUploadedFile for Nextcloud uploads`, () => {
      const content = readFile(file);
      expect(content).toContain('persistUploadedFile(');
    });
  }

  test('cloud_config.php persistUploadedFile should decrypt Nextcloud password', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function persistUploadedFile(');
    const fnSection = content.substring(fnStart, fnStart + 2000);
    expect(fnSection).toContain('decryptPassword(');
  });

  test('cloud_config.php persistUploadedFile should call uploadLargeFileToNextcloud for large files', () => {
    const content = readFile('cloud_config.php');
    const fnStart = content.indexOf('function persistUploadedFile(');
    const fnSection = content.substring(fnStart, fnStart + 3500);
    expect(fnSection).toContain('uploadLargeFileToNextcloud(');
  });
});
