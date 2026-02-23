import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Theme Logo Upload Fix Tests
 * Tests that the theme tab logo upload in system tools works correctly
 * by ensuring hidden URL inputs are disabled to prevent browser validation
 * from blocking form submission when they contain relative upload paths.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// Theme Logo Upload - URL Input Disabled When Hidden
// =====================================================

test.describe('Theme Logo Upload - Form Validation Fix', () => {
  test('toggleThemeLogoInput disables logo_url input when upload method is selected', () => {
    const content = readFile('views/admin_system_tools.php');
    // The toggle function should disable the URL input to prevent
    // browser type="url" validation from blocking form submission
    // when the URL field contains a relative upload path
    expect(content).toContain('logoUrlInput.disabled');
    expect(content).toContain("input[name=\"logo_url\"]");
  });

  test('toggleCenterIceLogoInput disables center ice URL input when upload method is selected', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('centerIceUrlInput.disabled');
    expect(content).toContain("input[name=\"center_ice_logo_url_input\"]");
  });

  test('toggle functions are called on page load to set initial disabled state', () => {
    const content = readFile('views/admin_system_tools.php');
    // After function definitions, both toggles should be called
    // to set the correct initial disabled state based on the current logo_method
    const themeToggleDefEnd = content.indexOf('toggleCenterIceLogoInput()');
    const initCallIndex = content.indexOf('toggleThemeLogoInput();', themeToggleDefEnd);
    expect(initCallIndex).toBeGreaterThan(-1);

    const centerIceInitCall = content.indexOf('toggleCenterIceLogoInput();', initCallIndex);
    expect(centerIceInitCall).toBeGreaterThan(-1);
  });

  test('theme form has enctype multipart/form-data for file uploads', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="theme-form"');
    expect(content).toContain('enctype="multipart/form-data"');
  });

  test('theme form has file input for logo upload', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('type="file" name="logo"');
  });

  test('process_theme.php handles logo file upload in save_theme action', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain("case 'save_theme':");
    expect(content).toContain("$_FILES['logo']");
    expect(content).toContain("handleFileUpload");
  });
});
