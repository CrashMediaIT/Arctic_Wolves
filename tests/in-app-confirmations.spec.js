/**
 * Tests for In-App Confirmations (no browser popups)
 *
 * Verifies that native browser confirm(), alert(), and prompt() calls
 * have been replaced with in-app modals (showConfirmModal, showToast,
 * showPromptModal) across all application source files.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

/**
 * Helper: return non-comment JS lines that match a pattern.
 * Skips lines that are purely comments (// or * or /*).
 */
function jsCallLines(content, pattern) {
  return content.split('\n').filter(line => {
    const trimmed = line.trim();
    if (trimmed.startsWith('//') || trimmed.startsWith('*') || trimmed.startsWith('/*')) return false;
    return pattern.test(trimmed);
  });
}

// =====================================================
// 1. app.js exposes in-app modal utilities
// =====================================================

test.describe('In-app modal utilities exist in app.js', () => {
  const content = readFile('js/app.js');

  test('showConfirmModal is defined and exposed globally', () => {
    expect(content).toContain('function showConfirmModal(');
    expect(content).toContain('window.showConfirmModal = showConfirmModal');
  });

  test('showAlertModal is defined and exposed globally', () => {
    expect(content).toContain('function showAlertModal(');
    expect(content).toContain('window.showAlertModal = showAlertModal');
  });

  test('showPromptModal is defined and exposed globally', () => {
    expect(content).toContain('function showPromptModal(');
    expect(content).toContain('window.showPromptModal = showPromptModal');
  });

  test('showConfirmModal returns a Promise', () => {
    const funcStart = content.indexOf('function showConfirmModal(');
    const section = content.substring(funcStart, funcStart + 2000);
    expect(section).toContain('return new Promise');
  });

  test('showAlertModal returns a Promise', () => {
    const funcStart = content.indexOf('function showAlertModal(');
    const section = content.substring(funcStart, funcStart + 2000);
    expect(section).toContain('return new Promise');
  });

  test('showPromptModal returns a Promise', () => {
    const funcStart = content.indexOf('function showPromptModal(');
    const section = content.substring(funcStart, funcStart + 2000);
    expect(section).toContain('return new Promise');
  });

  test('data-confirm delegated handler is initialised', () => {
    expect(content).toContain('initializeDataConfirm');
    expect(content).toContain("data-confirm");
  });
});

// =====================================================
// 2. No native confirm() in non-test source files
// =====================================================

test.describe('No native confirm() in source files', () => {
  test('app.js has no confirm() calls (only comments)', () => {
    const content = readFile('js/app.js');
    const calls = jsCallLines(content, /\bconfirm\s*\(/);
    expect(calls).toHaveLength(0);
  });

  test('drill_designer.js uses showConfirmModal/showPromptModal, not confirm/prompt', () => {
    const content = readFile('js/drill_designer.js');
    expect(content).not.toContain('window.confirm(');
    const promptCalls = jsCallLines(content, /(?<!show)\bprompt\s*\(/);
    expect(promptCalls).toHaveLength(0);
  });

  test('no inline onsubmit/onclick confirm() in view files', () => {
    const viewsDir = path.join(ROOT, 'views');
    const files = fs.readdirSync(viewsDir, { recursive: true })
      .filter(f => f.endsWith('.php'));

    for (const file of files) {
      const content = fs.readFileSync(path.join(viewsDir, file), 'utf-8');
      const inlineConfirm = /on(?:submit|click)=["'][^"']*\bconfirm\s*\(/i;
      expect(inlineConfirm.test(content), `${file} still has inline confirm()`).toBe(false);
    }
  });
});

// =====================================================
// 3. No native alert() in non-test source files
// =====================================================

test.describe('No native alert() in source files', () => {
  const alertPattern = /(?<!show)\balert\s*\(/;

  test('no alert() in main PHP files', () => {
    const mainFiles = [
      'shop_cart.php', 'shop_product.php', 'dashboard.php',
      'dashboard_kiosk.php', 'register.php', 'verify_2fa.php', 'setup.php'
    ];
    for (const file of mainFiles) {
      const content = readFile(file);
      const calls = jsCallLines(content, alertPattern);
      expect(calls, `${file} still has alert()`).toHaveLength(0);
    }
  });

  test('no alert() in view files', () => {
    const viewsDir = path.join(ROOT, 'views');
    const files = fs.readdirSync(viewsDir, { recursive: true })
      .filter(f => f.endsWith('.php'));

    for (const file of files) {
      const content = fs.readFileSync(path.join(viewsDir, file), 'utf-8');
      const calls = jsCallLines(content, alertPattern);
      expect(calls, `views/${file} still has alert()`).toHaveLength(0);
    }
  });
});

// =====================================================
// 4. No native prompt() in non-test source files
// =====================================================

test.describe('No native prompt() in source files', () => {
  // Match prompt( but not deferredPrompt.prompt( or showPromptModal(
  const promptPattern = /(?<!deferred|show\w*)\bprompt\s*\(/;

  test('no prompt() in JS files', () => {
    const content = readFile('js/drill_designer.js');
    const calls = jsCallLines(content, promptPattern);
    expect(calls).toHaveLength(0);
  });

  test('no prompt() in dashboard files', () => {
    for (const file of ['dashboard.php', 'dashboard_kiosk.php']) {
      const content = readFile(file);
      const calls = jsCallLines(content, promptPattern);
      expect(calls, `${file} still has prompt()`).toHaveLength(0);
    }
  });
});

// =====================================================
// 5. data-confirm attributes are used
// =====================================================

test.describe('data-confirm attributes replace inline handlers', () => {
  test('admin_business_partners.php uses data-confirm on forms', () => {
    const content = readFile('views/admin_business_partners.php');
    expect(content).toContain('data-confirm=');
    expect(content).not.toMatch(/onsubmit=["'][^"']*confirm\s*\(/);
  });

  test('admin_team_coaches.php uses data-confirm on forms', () => {
    const content = readFile('views/admin_team_coaches.php');
    expect(content).toContain('data-confirm=');
    expect(content).not.toMatch(/onsubmit=["'][^"']*confirm\s*\(/);
  });

  test('profile.php uses data-confirm on forms/buttons', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('data-confirm=');
    expect(content).not.toMatch(/on(?:submit|click)=["'][^"']*\breturn confirm\s*\(/);
  });
});
