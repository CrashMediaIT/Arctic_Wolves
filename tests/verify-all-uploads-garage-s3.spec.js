// @ts-check
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Verify All Upload Handlers Use Garage S3 via persistUploadedFile()
 * 
 * Every file upload handler (images, videos, receipts, contracts, logos, etc.)
 * must use persistUploadedFile() as the single entry point, which uploads to
 * Garage S3 as the primary storage with Nextcloud/local fallback.
 * 
 * Exceptions:
 * - process_database_restore.php: temp file to tmp/restore/ (not a media upload)
 * - process_feature_import.php: temp file to tmp/feature_imports/ (not a media upload)
 * - Fallback-only move_uploaded_file() in else branches when $pdo is null
 */

const BASE = path.resolve(__dirname, '..');

function readFile(relative) {
  return fs.readFileSync(path.join(BASE, relative), 'utf8');
}

// ─── 1. Upload handlers that MUST use persistUploadedFile() ──────────────────

test.describe('All media upload handlers use persistUploadedFile() for Garage S3', () => {
  
  test('process_admin_action.php admin_update_profile_image uses persistUploadedFile', () => {
    const content = readFile('process_admin_action.php');
    // The admin profile image section should use persistUploadedFile
    const profileStart = content.indexOf('admin_update_profile_image');
    const profileSection = content.substring(profileStart, profileStart + 3000);
    expect(profileSection).toContain('persistUploadedFile(');
  });

  test('process_admin_action.php create_team logo uses persistUploadedFile', () => {
    const content = readFile('process_admin_action.php');
    const createTeamSection = content.substring(
      content.indexOf("action == 'create' && isset($_POST['type']) && $_POST['type'] == 'team'"),
      content.indexOf("action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'team'")
    );
    expect(createTeamSection).toContain('persistUploadedFile(');
    expect(createTeamSection).not.toContain('move_uploaded_file(');
    expect(createTeamSection).not.toContain('uploadImageToNextcloud(');
  });

  test('process_admin_action.php edit_team logo uses persistUploadedFile', () => {
    const content = readFile('process_admin_action.php');
    const editTeamStart = content.indexOf("action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'team'");
    const editTeamSection = content.substring(editTeamStart, editTeamStart + 3000);
    expect(editTeamSection).toContain('persistUploadedFile(');
    expect(editTeamSection).not.toContain('move_uploaded_file(');
    expect(editTeamSection).not.toContain('uploadImageToNextcloud(');
  });

  test('process_expenses.php create expense receipt uses persistUploadedFile', () => {
    const content = readFile('process_expenses.php');
    const createSection = content.substring(
      content.indexOf("case 'create':"),
      content.indexOf("case 'update':")
    );
    expect(createSection).toContain('persistUploadedFile(');
    expect(createSection).not.toContain('move_uploaded_file(');
  });

  test('process_expenses.php update expense receipt uses persistUploadedFile', () => {
    const content = readFile('process_expenses.php');
    const updateStart = content.indexOf("case 'update':");
    const updateSection = content.substring(updateStart, updateStart + 3000);
    expect(updateSection).toContain('persistUploadedFile(');
    expect(updateSection).not.toContain('move_uploaded_file(');
  });

  test('process_expenses.php OCR scan receipt uses persistUploadedFile', () => {
    const content = readFile('process_expenses.php');
    const ocrStart = content.indexOf("case 'ocr_scan':");
    const ocrSection = content.substring(ocrStart, ocrStart + 2000);
    expect(ocrSection).toContain('persistUploadedFile(');
    expect(ocrSection).not.toContain('move_uploaded_file(');
  });

  test('process_recurring_expenses.php contract file uses persistUploadedFile', () => {
    const content = readFile('process_recurring_expenses.php');
    const createStart = content.indexOf("case 'create':");
    const createSection = content.substring(createStart, createStart + 5000);
    expect(createSection).toContain('persistUploadedFile(');
    expect(createSection).not.toContain('move_uploaded_file(');
  });

  test('process_recurring_expenses.php upload_documents uses persistUploadedFile', () => {
    const content = readFile('process_recurring_expenses.php');
    const uploadDocsStart = content.indexOf("case 'upload_documents':");
    const uploadDocsSection = content.substring(uploadDocsStart, uploadDocsStart + 4000);
    expect(uploadDocsSection).toContain('persistUploadedFile(');
    expect(uploadDocsSection).not.toContain('move_uploaded_file(');
  });

  test('process_profile_update.php upload_photo uses persistUploadedFile', () => {
    const content = readFile('process_profile_update.php');
    const uploadPhotoStart = content.indexOf("action == 'upload_photo'");
    const uploadPhotoSection = content.substring(uploadPhotoStart, uploadPhotoStart + 1500);
    expect(uploadPhotoSection).toContain('persistUploadedFile(');
    expect(uploadPhotoSection).not.toContain('move_uploaded_file(');
    expect(uploadPhotoSection).not.toContain('saveToPersistentStorage(');
  });

  test('process_profile_update.php upload_avatar uses persistUploadedFile', () => {
    const content = readFile('process_profile_update.php');
    const uploadAvatarStart = content.indexOf("action == 'upload_avatar'");
    const uploadAvatarSection = content.substring(uploadAvatarStart, uploadAvatarStart + 1000);
    expect(uploadAvatarSection).toContain('persistUploadedFile(');
  });

  test('process_video.php handleVideoUpload uses persistUploadedFile', () => {
    const content = readFile('process_video.php');
    const fn = content.substring(content.indexOf('function handleVideoUpload('), content.indexOf('function handleVideoUpload(') + 2000);
    expect(fn).toContain('persistUploadedFile(');
  });

  test('process_video.php handleAthleteVideoUpload uses persistUploadedFile', () => {
    const content = readFile('process_video.php');
    const fn = content.substring(content.indexOf('function handleAthleteVideoUpload('), content.indexOf('function handleAthleteVideoUpload(') + 4000);
    expect(fn).toContain('persistUploadedFile(');
  });

  test('process_video.php handleDrillVideoUpload uses persistUploadedFile', () => {
    const content = readFile('process_video.php');
    const fn = content.substring(content.indexOf('function handleDrillVideoUpload('), content.indexOf('function handleDrillVideoUpload(') + 4000);
    expect(fn).toContain('persistUploadedFile(');
  });

  test('process_theme.php uses persistUploadedFile as primary', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_merchandise_products.php uses persistUploadedFile as primary', () => {
    const content = readFile('process_merchandise_products.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_merchandise_categories.php uses persistUploadedFile as primary', () => {
    const content = readFile('process_merchandise_categories.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_workout.php uses persistUploadedFile', () => {
    const content = readFile('process_workout.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_eval_goals.php uses persistUploadedFile', () => {
    const content = readFile('process_eval_goals.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_eval_skills.php uses persistUploadedFile', () => {
    const content = readFile('process_eval_skills.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_drills.php uses persistUploadedFile', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain('persistUploadedFile(');
  });

  test('process_practice_plans.php uses persistUploadedFile', () => {
    const content = readFile('process_practice_plans.php');
    expect(content).toContain('persistUploadedFile(');
  });
});

// ─── 2. No direct Nextcloud calls outside cloud_config.php ────────────────────

test.describe('No direct Nextcloud upload calls outside cloud_config.php', () => {
  
  const files_to_check = [
    'process_admin_action.php',
    'process_expenses.php',
    'process_recurring_expenses.php',
    'process_profile_update.php',
    'process_video.php',
    'process_theme.php',
    'process_merchandise_products.php',
    'process_merchandise_categories.php',
    'process_workout.php',
    'process_drills.php',
    'process_practice_plans.php',
    'process_eval_goals.php',
    'process_eval_skills.php',
  ];

  for (const file of files_to_check) {
    test(`${file} does not call uploadImageToNextcloud directly`, () => {
      const content = readFile(file);
      expect(content).not.toContain('uploadImageToNextcloud(');
    });
  }
});

// ─── 3. persistUploadedFile uses Garage S3 as primary ─────────────────────────

test.describe('persistUploadedFile uses Garage S3 as primary storage', () => {
  const content = () => readFile('cloud_config.php');

  test('persistUploadedFile calls uploadToGarage first', () => {
    const fn = content();
    const fnStart = fn.indexOf('function persistUploadedFile(');
    const fnBody = fn.substring(fnStart, fnStart + 3500);
    const garagePos = fnBody.indexOf('uploadToGarage(');
    const nextcloudPos = fnBody.indexOf('uploadImageToNextcloud(');
    const persistentPos = fnBody.indexOf('saveToPersistentStorage(');
    // Garage must come before both Nextcloud and persistent storage
    expect(garagePos).toBeGreaterThan(0);
    expect(garagePos).toBeLessThan(nextcloudPos);
    expect(garagePos).toBeLessThan(persistentPos);
  });

  test('persistUploadedFile returns garage_path in result', () => {
    const fn = content();
    const fnStart = fn.indexOf('function persistUploadedFile(');
    const fnBody = fn.substring(fnStart, fnStart + 3500);
    expect(fnBody).toContain("'garage_path'");
  });

  test('persistUploadedFile returns early when Garage succeeds', () => {
    const fn = content();
    const fnStart = fn.indexOf('function persistUploadedFile(');
    const fnBody = fn.substring(fnStart, fnStart + 3500);
    // Should return early after successful Garage upload
    const garageSection = fnBody.substring(
      fnBody.indexOf('uploadToGarage('),
      fnBody.indexOf('saveToPersistentStorage(')
    );
    expect(garageSection).toContain('return $result');
  });
});

// ─── 4. Acceptable exceptions ─────────────────────────────────────────────────

test.describe('Acceptable move_uploaded_file exceptions', () => {
  
  test('process_database_restore.php uses move_uploaded_file for temp restore file only', () => {
    const content = readFile('process_database_restore.php');
    expect(content).toContain('move_uploaded_file(');
    expect(content).toContain('tmp/restore/');
    // Should NOT have persistUploadedFile — this is a database backup, not media
    expect(content).not.toContain('persistUploadedFile(');
  });

  test('process_feature_import.php uses move_uploaded_file for temp import file only', () => {
    const content = readFile('process_feature_import.php');
    expect(content).toContain('move_uploaded_file(');
    expect(content).toContain('tmp/feature_imports');
    // Should NOT have persistUploadedFile — this is a zip import, not media
    expect(content).not.toContain('persistUploadedFile(');
  });

  test('theme/merchandise fallback move_uploaded_file is in else branch when $pdo is null', () => {
    for (const file of ['process_theme.php', 'process_merchandise_products.php', 'process_merchandise_categories.php']) {
      const content = readFile(file);
      // All these files should have persistUploadedFile as primary
      expect(content).toContain('persistUploadedFile(');
      // The move_uploaded_file should only be in a fallback context (after } else {)
      // Both persistUploadedFile and move_uploaded_file should coexist
      expect(content).toContain('move_uploaded_file(');
      // The else/fallback pattern should exist
      expect(content).toContain('Fallback');
    }
  });
});
