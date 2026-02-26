import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Business Card Front Background Fix Tests
 * Tests that the business card front background settings area in System Tools > Theme tab
 * has proper upload input, always-visible preview area, and correct backend handling.
 *
 * Root cause: The front background preview section was only shown when an image was already
 * uploaded (conditional on PHP variable). When empty, users had no visual area showing where
 * the front background preview should appear, unlike the back background which happened to
 * have a previously uploaded image. Also, upload error handling lacked logging.
 *
 * Fix:
 * - Always show front/back background preview areas with "No image uploaded" placeholder
 * - Added onchange preview handlers for immediate feedback when selecting a file
 * - Used separate result variables for front/back uploads to avoid any variable reuse issues
 * - Added error logging for failed uploads
 * - Changed catch blocks to Throwable for PHP 8 compatibility
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// System Tools - Business Card Front Background Fix
// =====================================================

test.describe('Business Card Front Background - System Tools Theme Tab', () => {

  test('theme form contains file input for front card background', () => {
    const content = readFile('views/admin_system_tools.php');
    const formStart = content.indexOf('id="theme-form"');
    const formEnd = content.indexOf('</form>', formStart);
    const formContent = content.substring(formStart, formEnd);
    expect(formContent).toContain('name="bc_front_bg"');
    expect(formContent).toContain('accept=".png,.jpg,.jpeg,.webp"');
  });

  test('theme form contains file input for back card background', () => {
    const content = readFile('views/admin_system_tools.php');
    const formStart = content.indexOf('id="theme-form"');
    const formEnd = content.indexOf('</form>', formStart);
    const formContent = content.substring(formStart, formEnd);
    expect(formContent).toContain('name="bc_back_bg"');
  });

  test('front background has always-visible preview area with id', () => {
    const content = readFile('views/admin_system_tools.php');
    // The preview area should always be rendered (not conditional)
    expect(content).toContain('id="bc-front-bg-preview"');
    expect(content).toContain('No front background uploaded');
  });

  test('back background has always-visible preview area with id', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="bc-back-bg-preview"');
    expect(content).toContain('No back background uploaded');
  });

  test('front background upload has onchange preview handler', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("previewBcBackground(this, 'bc-front-bg-preview')");
  });

  test('back background upload has onchange preview handler', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("previewBcBackground(this, 'bc-back-bg-preview')");
  });

  test('previewBcBackground JavaScript function is defined', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function previewBcBackground(');
    expect(content).toContain('FileReader');
  });

  test('both file inputs are inside the theme form', () => {
    const content = readFile('views/admin_system_tools.php');
    const formStart = content.indexOf('id="theme-form"');
    const formEnd = content.indexOf('</form>', formStart);
    const formContent = content.substring(formStart, formEnd);
    
    // Both inputs must be inside the form
    expect(formContent).toContain('name="bc_front_bg"');
    expect(formContent).toContain('name="bc_back_bg"');
    
    // Front input should come before back input
    const frontPos = formContent.indexOf('name="bc_front_bg"');
    const backPos = formContent.indexOf('name="bc_back_bg"');
    expect(frontPos).toBeLessThan(backPos);
  });
});

// =====================================================
// Backend - Business Card Background Upload Handling
// =====================================================

test.describe('Business Card Front Background - Backend Handling', () => {

  test('save_theme handler uses separate result variables for front and back', () => {
    const content = readFile('process_theme.php');
    // Find the save_theme case
    const saveThemeStart = content.indexOf("case 'save_theme':");
    const saveThemeEnd = content.indexOf('exit;', saveThemeStart);
    const saveThemeContent = content.substring(saveThemeStart, saveThemeEnd);
    
    // Should use $front_bg_result and $back_bg_result instead of shared $result
    expect(saveThemeContent).toContain('$front_bg_result');
    expect(saveThemeContent).toContain('$back_bg_result');
  });

  test('save_theme handler saves front bg URL to correct setting name', () => {
    const content = readFile('process_theme.php');
    const saveThemeStart = content.indexOf("case 'save_theme':");
    const saveThemeEnd = content.indexOf('exit;', saveThemeStart);
    const saveThemeContent = content.substring(saveThemeStart, saveThemeEnd);
    
    expect(saveThemeContent).toContain("saveThemeUploadResult($pdo, 'business_card_front_bg_url'");
    expect(saveThemeContent).toContain("saveThemeUploadResult($pdo, 'business_card_back_bg_url'");
  });

  test('save_theme handler logs errors for failed front bg uploads', () => {
    const content = readFile('process_theme.php');
    const saveThemeStart = content.indexOf("case 'save_theme':");
    const saveThemeEnd = content.indexOf('exit;', saveThemeStart);
    const saveThemeContent = content.substring(saveThemeStart, saveThemeEnd);
    
    expect(saveThemeContent).toContain('Front card background upload failed');
  });

  test('update_all_theme_settings handler uses separate result variables', () => {
    const content = readFile('process_theme.php');
    const handlerStart = content.indexOf("case 'update_all_theme_settings':");
    const handlerEnd = content.indexOf('exit;', handlerStart);
    const handlerContent = content.substring(handlerStart, handlerEnd);
    
    expect(handlerContent).toContain('$front_bg_result');
    expect(handlerContent).toContain('$back_bg_result');
  });

  test('outer try/catch uses Throwable for PHP 8 compatibility', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain('catch (\\Throwable $e)');
  });

  test('handleFileUpload uses Throwable in catch blocks', () => {
    const content = readFile('process_theme.php');
    const funcStart = content.indexOf('function handleFileUpload');
    const funcEnd = content.indexOf('\n}', funcStart);
    const funcContent = content.substring(funcStart, funcEnd);
    
    expect(funcContent).toContain('catch (\\Throwable');
  });
});

// =====================================================
// Data Loading - Theme Settings Defaults
// =====================================================

test.describe('Business Card Front Background - Data Loading', () => {

  test('admin_system_tools.php has default for business_card_front_bg_url', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("'business_card_front_bg_url' => ''");
  });

  test('admin_system_tools.php has default for business_card_back_bg_url', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("'business_card_back_bg_url' => ''");
  });

  test('database schema has INSERT for business_card_front_bg_url', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('business_card_front_bg_url');
  });
});
