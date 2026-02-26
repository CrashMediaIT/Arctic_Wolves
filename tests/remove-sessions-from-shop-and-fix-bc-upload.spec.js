import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. Shop pages should NOT show Sessions navigation link
 * 2. Business card front background upload improvements:
 *    - AJAX handler returns JSON with _ajax flag
 *    - FormData merging uses set() for files to avoid empty File duplicates
 *    - Error logging for upload failures with specific error codes
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Shop pages should NOT show Sessions link
// =====================================================

test.describe('Shop sub-pages do not show Sessions', () => {

  const shopSubPages = [
    { file: 'shop_product.php', name: 'Shop product page' },
    { file: 'shop_cart.php', name: 'Shop cart page' },
    { file: 'shop_checkout.php', name: 'Shop checkout page' },
    { file: 'shop_success.php', name: 'Shop success page' },
  ];

  for (const page of shopSubPages) {
    test(`${page.name} (${page.file}) does not contain Sessions nav link`, () => {
      const content = readFile(page.file);
      // Find the nav-menu section
      const navStart = content.indexOf('class="nav-menu"');
      expect(navStart).toBeGreaterThan(-1);
      const navEnd = content.indexOf('</nav>', navStart);
      const navContent = content.substring(navStart, navEnd);
      // Sessions link should NOT be present in nav
      expect(navContent).not.toContain('sessions_public.php');
      expect(navContent).not.toContain('>Sessions<');
    });

    test(`${page.name} (${page.file}) still has Home and Shop nav links`, () => {
      const content = readFile(page.file);
      const navStart = content.indexOf('class="nav-menu"');
      const navEnd = content.indexOf('</nav>', navStart);
      const navContent = content.substring(navStart, navEnd);
      expect(navContent).toContain('href="index.php"');
      expect(navContent).toContain('href="shop.php"');
      expect(navContent).toContain('href="login.php"');
    });
  }
});

test.describe('Shop main page has correct navigation matching sessions_public.php', () => {

  test('shop.php contains Sessions nav link', () => {
    const content = readFile('shop.php');
    const navStart = content.indexOf('class="nav-menu"');
    expect(navStart).toBeGreaterThan(-1);
    const navEnd = content.indexOf('</nav>', navStart);
    const navContent = content.substring(navStart, navEnd);
    expect(navContent).toContain('sessions_public.php');
    expect(navContent).toContain('>Sessions<');
  });

  test('shop.php has Home, Sessions, Shop, and Login nav links', () => {
    const content = readFile('shop.php');
    const navStart = content.indexOf('class="nav-menu"');
    const navEnd = content.indexOf('</nav>', navStart);
    const navContent = content.substring(navStart, navEnd);
    expect(navContent).toContain('href="index.php"');
    expect(navContent).toContain('href="sessions_public.php"');
    expect(navContent).toContain('href="shop.php"');
    expect(navContent).toContain('href="login.php"');
  });

  test('shop.php logo area uses anchor tag directly like sessions_public.php', () => {
    const content = readFile('shop.php');
    // Should use <a> tag with class="logo-area" directly, not a wrapping <div>
    expect(content).toContain('<a href="index.php" class="logo-area"');
  });
});

// =====================================================
// 2. Business card upload - AJAX JSON response
// =====================================================

test.describe('Business card upload - AJAX JSON response', () => {

  test('update_all_theme_settings returns JSON when _ajax flag is set', () => {
    const content = readFile('process_theme.php');
    const handlerStart = content.indexOf("case 'update_all_theme_settings':");
    const handlerEnd = content.indexOf('exit;', handlerStart);
    const handlerContent = content.substring(handlerStart, handlerEnd);

    expect(handlerContent).toContain("$_POST['_ajax']");
    expect(handlerContent).toContain('application/json');
    expect(handlerContent).toContain('json_encode');
  });

  test('update_all_theme_settings includes upload warnings in JSON response', () => {
    const content = readFile('process_theme.php');
    const handlerStart = content.indexOf("case 'update_all_theme_settings':");
    const handlerEnd = content.indexOf('exit;', handlerStart);
    const handlerContent = content.substring(handlerStart, handlerEnd);

    expect(handlerContent).toContain('$bc_upload_warnings');
    expect(handlerContent).toContain("'warnings'");
  });

  test('saveAllThemeSettings sends _ajax flag in FormData', () => {
    const content = readFile('views/admin_theme_settings.php');
    const funcStart = content.indexOf('function saveAllThemeSettings()');
    const funcEnd = content.indexOf('xhr.send(formData)', funcStart);
    const funcContent = content.substring(funcStart, funcEnd);

    expect(funcContent).toContain("formData.set('_ajax', '1')");
  });

  test('saveAllThemeSettings uses set() for file inputs to avoid duplicates', () => {
    const content = readFile('views/admin_theme_settings.php');
    const funcStart = content.indexOf('function saveAllThemeSettings()');
    const funcEnd = content.indexOf('xhr.send(formData)', funcStart);
    const funcContent = content.substring(funcStart, funcEnd);

    // Should use set() for files, not append()
    expect(funcContent).toContain('formData.set(pair[0], pair[1], pair[1].name)');
    // Should filter out empty files (size === 0)
    expect(funcContent).toContain('pair[1].size > 0');
  });

  test('saveAllThemeSettings handles warnings in JSON response', () => {
    const content = readFile('views/admin_theme_settings.php');
    const funcStart = content.indexOf('function saveAllThemeSettings()');
    const funcEnd = content.indexOf('\n}', funcStart + 100);
    const funcContent = content.substring(funcStart, funcEnd);

    expect(funcContent).toContain('data.warnings');
  });
});

// =====================================================
// 3. Business card upload - Error logging improvements
// =====================================================

test.describe('Business card upload - Error logging', () => {

  test('save_theme logs error code when front bg has upload error', () => {
    const content = readFile('process_theme.php');
    const saveThemeStart = content.indexOf("case 'save_theme':");
    const saveThemeEnd = content.indexOf('exit;', saveThemeStart);
    const section = content.substring(saveThemeStart, saveThemeEnd);

    // Should have an elseif that catches non-UPLOAD_ERR_NO_FILE errors
    expect(section).toContain("UPLOAD_ERR_NO_FILE");
    expect(section).toContain("Front card background file error");
  });

  test('update_all_theme_settings logs detailed error for front bg upload failure', () => {
    const content = readFile('process_theme.php');
    const handlerStart = content.indexOf("case 'update_all_theme_settings':");
    const handlerEnd = content.indexOf('exit;', handlerStart);
    const section = content.substring(handlerStart, handlerEnd);

    // Should have detailed error descriptions for common upload errors
    expect(section).toContain('UPLOAD_ERR_INI_SIZE');
    expect(section).toContain('upload_max_filesize');
    expect(section).toContain('UPLOAD_ERR_PARTIAL');
  });

  test('catch block returns JSON for AJAX requests', () => {
    const content = readFile('process_theme.php');
    // Find the outer catch block (after the switch statement's default case)
    const defaultStart = content.indexOf("throw new Exception('Invalid action')");
    const catchStart = content.indexOf("catch (\\Throwable $e)", defaultStart);
    const catchSection = content.substring(catchStart, catchStart + 500);

    expect(catchSection).toContain("$_POST['_ajax']");
    expect(catchSection).toContain('application/json');
  });
});
