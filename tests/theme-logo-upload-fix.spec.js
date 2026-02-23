import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Theme Save Button Fix Tests
 * Tests that the Save Theme button in the system tools theme tab works correctly.
 * 
 * Root cause: logo_url and center_ice_logo_url_input used type="url" but could
 * contain relative upload paths (e.g. uploads/theme/logo_xxx.png) that fail
 * browser URL validation. When the inputs are hidden, the browser error
 * "An invalid form control with name='...' is not focusable" silently blocks
 * form submission.
 * 
 * Fix: Changed these inputs to type="text" since they can hold either full URLs
 * or relative upload paths.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// Theme Save Button - Form Submission Fix
// =====================================================

test.describe('Theme Save Button - Form Submission Fix', () => {
  test('logo_url input uses type="text" to allow relative upload paths', () => {
    const content = readFile('views/admin_system_tools.php');
    // The logo_url input must NOT use type="url" because it can contain
    // relative paths like "uploads/theme/logo_xxx.png" from file uploads.
    // type="url" would reject these and silently block form submission.
    expect(content).toContain('type="text" name="logo_url"');
    expect(content).not.toMatch(/type="url"\s+name="logo_url"/);
  });

  test('center_ice_logo_url_input uses type="text" to allow relative upload paths', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('type="text" name="center_ice_logo_url_input"');
    expect(content).not.toMatch(/type="url"\s+name="center_ice_logo_url_input"/);
  });

  test('theme form has correct method and action', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="theme-form"');
    expect(content).toContain('method="POST" action="process_theme.php"');
    expect(content).toContain('enctype="multipart/form-data"');
  });

  test('theme form has file input for logo upload', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('type="file" name="logo"');
  });

  test('theme form has a submit button', () => {
    const content = readFile('views/admin_system_tools.php');
    // Find the theme form content
    const formStart = content.indexOf('id="theme-form"');
    const formEnd = content.indexOf('</form>', formStart);
    const formContent = content.substring(formStart, formEnd);
    expect(formContent).toContain('type="submit"');
    expect(formContent).toContain('Save Theme');
  });

  test('process_theme.php handles save_theme action with redirect', () => {
    const content = readFile('process_theme.php');
    expect(content).toContain("case 'save_theme':");
    expect(content).toContain("$_FILES['logo']");
    expect(content).toContain("handleFileUpload");
    expect(content).toContain("dashboard.php?page=system_tools&tab=theme&success=1");
  });
});
