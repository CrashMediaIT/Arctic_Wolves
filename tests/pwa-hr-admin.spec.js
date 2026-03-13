/**
 * PWA HR & Administration Section Tests
 *
 * Validates that all HR and Administration PWA views:
 * - Have correct mobile-native implementations
 * - Use proper permission variables ($canAccessHR for HR, $isAdmin for Admin)
 * - Include CSRF tokens where needed
 * - Don't use dashboard.php in links/fetch
 * - Have all onclick handlers defined
 * - Match the desktop sidebar menu items
 * - Route pages correctly in pwa.php and pwa_tablet.php
 */

const { test, expect } = require('@playwright/test');
const { readFileSync, existsSync } = require('fs');
const { join } = require('path');

const ROOT = join(__dirname, '..');

function readFile(name) {
  return readFileSync(join(ROOT, name), 'utf-8');
}

function readPwaFile(name) {
  return readFileSync(join(ROOT, 'views', 'pwa', name), 'utf-8');
}

function pwaFileExists(name) {
  return existsSync(join(ROOT, 'views', 'pwa', name));
}

// ── HR SECTION ─────────────────────────────────────────────────────

test.describe('HR PWA views', () => {

  test('all HR PWA view files exist', () => {
    expect(pwaFileExists('hr_payroll.php')).toBe(true);
    expect(pwaFileExists('hr_time_tracking.php')).toBe(true);
    expect(pwaFileExists('hr_complaints.php')).toBe(true);
    expect(pwaFileExists('hr_employee_contracts.php')).toBe(true);
    expect(pwaFileExists('hr_onboarding.php')).toBe(true);
    expect(pwaFileExists('termination.php')).toBe(true);
    expect(pwaFileExists('admin_staff_scheduling.php')).toBe(true);
    // Aliases
    expect(pwaFileExists('payroll.php')).toBe(true);
    expect(pwaFileExists('complaints.php')).toBe(true);
    expect(pwaFileExists('onboarding.php')).toBe(true);
    expect(pwaFileExists('employee_contracts.php')).toBe(true);
  });

  test('hr_payroll.php uses canAccessHR (not isAdmin)', () => {
    const content = readPwaFile('hr_payroll.php');
    expect(content).toMatch(/\$canAccessHR/);
    const lines = content.split('\n').slice(0, 15);
    const permBlock = lines.join('\n');
    expect(permBlock).not.toMatch(/if\s*\(\s*!\s*\$isAdmin\s*\)/);
  });

  test('hr_time_tracking.php uses canAccessHR', () => {
    const content = readPwaFile('hr_time_tracking.php');
    expect(content).toMatch(/\$canAccessHR/);
  });

  test('hr_complaints.php uses canAccessHR', () => {
    const content = readPwaFile('hr_complaints.php');
    expect(content).toMatch(/\$canAccessHR/);
  });

  test('hr_employee_contracts.php uses canAccessHR', () => {
    const content = readPwaFile('hr_employee_contracts.php');
    expect(content).toMatch(/\$canAccessHR/);
  });

  test('hr_onboarding.php uses canAccessHR', () => {
    const content = readPwaFile('hr_onboarding.php');
    expect(content).toMatch(/\$canAccessHR/);
  });

  test('termination.php uses canAccessHR', () => {
    const content = readPwaFile('termination.php');
    expect(content).toMatch(/\$canAccessHR/);
  });

  test('admin_staff_scheduling.php uses canAccessHR', () => {
    const content = readPwaFile('admin_staff_scheduling.php');
    expect(content).toMatch(/\$canAccessHR/);
  });

  test('HR views show "HR access required" error (not "Admin access required")', () => {
    const hrViews = [
      'hr_payroll.php', 'hr_time_tracking.php', 'hr_complaints.php',
      'hr_employee_contracts.php', 'hr_onboarding.php', 'admin_staff_scheduling.php'
    ];
    for (const file of hrViews) {
      const content = readPwaFile(file);
      expect(content).toContain('HR access required');
    }
  });

  test('termination.php shows "HR access required" error', () => {
    const content = readPwaFile('termination.php');
    expect(content).toContain('HR access required');
  });

  test('hr_complaints.php has CSRF for complaint forms', () => {
    const content = readPwaFile('hr_complaints.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('hr_employee_contracts.php has CSRF for contract management', () => {
    const content = readPwaFile('hr_employee_contracts.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('hr_onboarding.php has CSRF for onboarding forms', () => {
    const content = readPwaFile('hr_onboarding.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('termination.php has CSRF for termination form', () => {
    const content = readPwaFile('termination.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('admin_staff_scheduling.php has CSRF for scheduling', () => {
    const content = readPwaFile('admin_staff_scheduling.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('hr_time_tracking.php has CSRF for clock actions', () => {
    const content = readPwaFile('hr_time_tracking.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('HR alias files redirect properly', () => {
    expect(readPwaFile('payroll.php')).toMatch(/include.*hr_payroll\.php/);
    expect(readPwaFile('complaints.php')).toMatch(/include.*hr_complaints\.php/);
    expect(readPwaFile('onboarding.php')).toMatch(/include.*hr_onboarding\.php/);
    expect(readPwaFile('employee_contracts.php')).toMatch(/include.*hr_employee_contracts\.php/);
  });

  test('hr_payroll.php is mobile-native', () => {
    const content = readPwaFile('hr_payroll.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('hr_time_tracking.php is mobile-native', () => {
    const content = readPwaFile('hr_time_tracking.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('hr_complaints.php is mobile-native', () => {
    const content = readPwaFile('hr_complaints.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('hr_employee_contracts.php is mobile-native', () => {
    const content = readPwaFile('hr_employee_contracts.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('hr_onboarding.php is mobile-native', () => {
    const content = readPwaFile('hr_onboarding.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('termination.php is mobile-native', () => {
    const content = readPwaFile('termination.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('admin_staff_scheduling.php is mobile-native', () => {
    const content = readPwaFile('admin_staff_scheduling.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('pwa_more_menu.php has HR section with canAccessHR', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toMatch(/canAccessHR.*HR/s);
    expect(content).toContain('page=admin_staff_scheduling');
    expect(content).toContain('page=hr_time_tracking');
    expect(content).toContain('page=payroll');
    expect(content).toContain('page=onboarding');
    expect(content).toContain('page=employee_contracts');
    expect(content).toContain('page=complaints');
    expect(content).toContain('page=termination');
  });

  test('desktop dashboard has same HR menu items as PWA', () => {
    const desktop = readFile('dashboard.php');
    const pwa = readFile('pwa_more_menu.php');
    const hrItems = [
      'admin_staff_scheduling', 'hr_time_tracking', 'payroll',
      'onboarding', 'employee_contracts', 'complaints', 'termination'
    ];
    for (const item of hrItems) {
      expect(desktop).toContain(`page=${item}`);
      expect(pwa).toContain(`page=${item}`);
    }
  });

  test('HR views do not reference dashboard.php in links', () => {
    const hrFiles = [
      'hr_payroll.php', 'hr_time_tracking.php', 'hr_complaints.php',
      'hr_employee_contracts.php', 'hr_onboarding.php', 'termination.php',
      'admin_staff_scheduling.php'
    ];
    for (const file of hrFiles) {
      const content = readPwaFile(file);
      expect(content).not.toMatch(/href\s*=\s*["']dashboard\.php/);
      expect(content).not.toMatch(/fetch\(\s*["']dashboard\.php/);
    }
  });
});

// ── ADMINISTRATION SECTION ─────────────────────────────────────────

test.describe('Administration PWA views', () => {

  test('all Administration PWA view files exist', () => {
    expect(pwaFileExists('all_users.php')).toBe(true);
    expect(pwaFileExists('categories.php')).toBe(true);
    expect(pwaFileExists('eval_framework.php')).toBe(true);
    expect(pwaFileExists('system_notification.php')).toBe(true);
    expect(pwaFileExists('admin_security.php')).toBe(true);
    expect(pwaFileExists('system_tools.php')).toBe(true);
    expect(pwaFileExists('marketing.php')).toBe(true);
    expect(pwaFileExists('admin_wishlist.php')).toBe(true);
  });

  test('all_users.php uses isAdmin', () => {
    const content = readPwaFile('all_users.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('categories.php uses isAdmin', () => {
    const content = readPwaFile('categories.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('eval_framework.php uses isAdmin', () => {
    const content = readPwaFile('eval_framework.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('system_notification.php uses isAdmin', () => {
    const content = readPwaFile('system_notification.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('admin_security.php uses isAdmin', () => {
    const content = readPwaFile('admin_security.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('system_tools.php uses isAdmin', () => {
    const content = readPwaFile('system_tools.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('marketing.php uses isAdmin', () => {
    const content = readPwaFile('marketing.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('admin_wishlist.php uses isAdmin', () => {
    const content = readPwaFile('admin_wishlist.php');
    expect(content).toMatch(/\$isAdmin/);
  });

  test('Admin views show "Admin access required" error', () => {
    const adminViews = [
      'all_users.php', 'categories.php', 'eval_framework.php',
      'system_notification.php', 'admin_security.php', 'system_tools.php',
      'marketing.php', 'admin_wishlist.php'
    ];
    for (const file of adminViews) {
      const content = readPwaFile(file);
      expect(content).toContain('Admin access required');
    }
  });

  test('all_users.php has CSRF for user management', () => {
    const content = readPwaFile('all_users.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('categories.php has CSRF for resource management', () => {
    const content = readPwaFile('categories.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('eval_framework.php has CSRF for eval management', () => {
    const content = readPwaFile('eval_framework.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('marketing.php has CSRF for business cards', () => {
    const content = readPwaFile('marketing.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('admin_wishlist.php has CSRF for wishlist management', () => {
    const content = readPwaFile('admin_wishlist.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('all_users.php is mobile-native', () => {
    const content = readPwaFile('all_users.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('categories.php is mobile-native', () => {
    const content = readPwaFile('categories.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('eval_framework.php is mobile-native', () => {
    const content = readPwaFile('eval_framework.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('system_notification.php is mobile-native', () => {
    const content = readPwaFile('system_notification.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('admin_security.php is mobile-native', () => {
    const content = readPwaFile('admin_security.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('system_tools.php is mobile-native', () => {
    const content = readPwaFile('system_tools.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('marketing.php is mobile-native', () => {
    const content = readPwaFile('marketing.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('admin_wishlist.php is mobile-native', () => {
    const content = readPwaFile('admin_wishlist.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('pwa_more_menu.php has Administration section with isAdmin', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toMatch(/isAdmin.*Administration/s);
    expect(content).toContain('page=all_users');
    expect(content).toContain('page=categories');
    expect(content).toContain('page=eval_framework');
    expect(content).toContain('page=system_notification');
    expect(content).toContain('page=admin_security');
    expect(content).toContain('page=system_tools');
    expect(content).toContain('page=marketing');
    expect(content).toContain('page=admin_wishlist');
  });

  test('desktop dashboard has same Administration menu items as PWA', () => {
    const desktop = readFile('dashboard.php');
    const pwa = readFile('pwa_more_menu.php');
    const adminItems = [
      'all_users', 'categories', 'eval_framework', 'system_notification',
      'admin_security', 'system_tools', 'marketing', 'admin_wishlist'
    ];
    for (const item of adminItems) {
      expect(desktop).toContain(`page=${item}`);
      expect(pwa).toContain(`page=${item}`);
    }
  });

  test('Admin views do not reference dashboard.php in links', () => {
    const adminFiles = [
      'all_users.php', 'categories.php', 'eval_framework.php',
      'system_notification.php', 'admin_security.php', 'system_tools.php',
      'marketing.php', 'admin_wishlist.php'
    ];
    for (const file of adminFiles) {
      const content = readPwaFile(file);
      expect(content).not.toMatch(/href\s*=\s*["']dashboard\.php/);
      expect(content).not.toMatch(/fetch\(\s*["']dashboard\.php/);
    }
  });
});

// ── HR ROUTING COVERAGE ─────────────────────────────────────────────

test.describe('PWA routing covers HR and Administration pages', () => {

  test('pwa.php routes HR pages', () => {
    const content = readFile('pwa.php');
    expect(content).toContain("'admin_staff_scheduling'");
    expect(content).toContain("'termination'");
    expect(content).toContain("'payroll'");
    expect(content).toContain("'onboarding'");
    expect(content).toContain("'employee_contracts'");
    expect(content).toContain("'hr_time_tracking'");
    expect(content).toContain("'complaints'");
  });

  test('pwa.php routes Administration pages', () => {
    const content = readFile('pwa.php');
    expect(content).toContain("'all_users'");
    expect(content).toContain("'categories'");
    expect(content).toContain("'eval_framework'");
    expect(content).toContain("'system_notification'");
    expect(content).toContain("'system_tools'");
    expect(content).toContain("'cron_jobs'");
  });

  test('pwa_tablet.php routes HR pages', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain("'admin_staff_scheduling'");
    expect(content).toContain("'termination'");
    expect(content).toContain("'payroll'");
    expect(content).toContain("'onboarding'");
    expect(content).toContain("'employee_contracts'");
    expect(content).toContain("'hr_time_tracking'");
    expect(content).toContain("'complaints'");
  });

  test('pwa_tablet.php routes Administration pages', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain("'all_users'");
    expect(content).toContain("'categories'");
    expect(content).toContain("'eval_framework'");
    expect(content).toContain("'system_notification'");
    expect(content).toContain("'system_tools'");
    expect(content).toContain("'cron_jobs'");
  });

  test('pwa_tablet.php sidebar has HR section', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toMatch(/canAccessHR.*HR/s);
    expect(content).toContain('page=admin_staff_scheduling');
    expect(content).toContain('page=hr_time_tracking');
    expect(content).toContain('page=payroll');
    expect(content).toContain('page=onboarding');
    expect(content).toContain('page=employee_contracts');
    expect(content).toContain('page=complaints');
    expect(content).toContain('page=termination');
  });

  test('pwa_tablet.php sidebar has Administration section', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toMatch(/isAdmin.*Administration/s);
    expect(content).toContain('page=all_users');
    expect(content).toContain('page=categories');
    expect(content).toContain('page=eval_framework');
    expect(content).toContain('page=system_notification');
    expect(content).toContain('page=admin_security');
    expect(content).toContain('page=system_tools');
    expect(content).toContain('page=marketing');
    expect(content).toContain('page=admin_wishlist');
  });
});

// ── BUTTON HANDLER VERIFICATION ─────────────────────────────────────

test.describe('PWA HR/Admin views have all handlers defined', () => {

  function extractOnclickFunctions(content) {
    const matches = content.matchAll(/onclick="([^"]+)"/g);
    const fns = new Set();
    for (const m of matches) {
      const calls = m[1].matchAll(/(?<![.\w])([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/g);
      for (const c of calls) {
        const fn = c[1];
        // Skip built-in/common JS functions and PHP functions that appear in <?= ?> tags
        if (!['event', 'this', 'if', 'return', 'confirm', 'alert', 'window', 'document',
              'location', 'history', 'parseInt', 'parseFloat', 'encodeURIComponent',
              'decodeURIComponent', 'String', 'Number', 'JSON', 'Array', 'Object',
              'console', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval',
              'showToast', 'showConfirmModal', 'stopPropagation', 'preventDefault',
              // DOM methods that appear in inline handlers
              'closest', 'querySelector', 'querySelectorAll', 'getAttribute', 'setAttribute',
              'classList', 'remove', 'appendChild', 'insertBefore', 'replaceChild',
              // PHP functions that appear inside <?= ?> within onclick attributes
              'htmlspecialchars', 'json_encode', 'urlencode', 'intval', 'number_format',
              'date', 'time', 'trim', 'strtolower', 'strtoupper', 'ucfirst', 'ucwords',
              'isset', 'empty', 'is_null', 'is_array', 'is_string', 'is_int', 'is_float',
              'count', 'strlen', 'substr', 'strpos', 'str_replace', 'preg_replace',
              'nl2br', 'sprintf', 'round', 'floor', 'ceil', 'abs', 'max', 'min',
              'array_key_exists', 'in_array', 'array_merge', 'array_map'
        ].includes(fn)) {
          fns.add(fn);
        }
      }
    }
    return fns;
  }

  function verifyHandlers(filename) {
    const content = readPwaFile(filename);
    const fns = extractOnclickFunctions(content);
    for (const fn of fns) {
      // Check for multiple function definition patterns:
      // function name(  |  window.name = function  |  window.name = async function
      // var/let/const name = function  |  async function name(
      const patterns = [
        `function ${fn}(`,
        `function ${fn} (`,
        `window.${fn} = function`,
        `window.${fn} = async function`,
        `window.${fn}= function`,
        `window.${fn}=function`,
        `window.${fn}=async function`,
        `async function ${fn}(`,
      ];
      const defined = patterns.some(p => content.includes(p)) ||
                      new RegExp(`(var|let|const)\\s+${fn}\\s*=\\s*(async\\s+)?function`).test(content);
      expect(defined, `${filename}: onclick handler '${fn}' not defined`).toBe(true);
    }
  }

  // HR views with onclick handlers
  test('hr_time_tracking.php all onclick handlers defined', () => {
    verifyHandlers('hr_time_tracking.php');
  });

  test('hr_complaints.php all onclick handlers defined', () => {
    verifyHandlers('hr_complaints.php');
  });

  test('hr_employee_contracts.php all onclick handlers defined', () => {
    verifyHandlers('hr_employee_contracts.php');
  });

  test('hr_onboarding.php all onclick handlers defined', () => {
    verifyHandlers('hr_onboarding.php');
  });

  test('termination.php all onclick handlers defined', () => {
    verifyHandlers('termination.php');
  });

  // Admin views with onclick handlers
  test('all_users.php all onclick handlers defined', () => {
    verifyHandlers('all_users.php');
  });

  test('categories.php all onclick handlers defined', () => {
    verifyHandlers('categories.php');
  });

  test('eval_framework.php all onclick handlers defined', () => {
    verifyHandlers('eval_framework.php');
  });

  test('marketing.php all onclick handlers defined', () => {
    verifyHandlers('marketing.php');
  });

  test('admin_wishlist.php all onclick handlers defined', () => {
    verifyHandlers('admin_wishlist.php');
  });
});

// ── COMPREHENSIVE CSRF AUDIT ─────────────────────────────────────

test.describe('HR/Admin CSRF comprehensive audit', () => {

  test('every POST form in HR/Admin PWA views has CSRF protection', () => {
    const views = [
      'hr_payroll.php', 'hr_time_tracking.php', 'hr_complaints.php',
      'hr_employee_contracts.php', 'hr_onboarding.php', 'termination.php',
      'admin_staff_scheduling.php', 'all_users.php', 'categories.php',
      'eval_framework.php', 'system_notification.php', 'marketing.php',
      'admin_wishlist.php'
    ];
    for (const file of views) {
      const content = readPwaFile(file);
      // Find all POST forms
      const formRegex = /<form[^>]*method\s*=\s*["']POST["'][^>]*>/gi;
      const forms = content.match(formRegex) || [];
      if (forms.length > 0) {
        // Must have at least one CSRF token mechanism
        const hasCSRF = content.includes('csrfTokenInput()') ||
                       content.includes('csrf_token') ||
                       content.includes('csrf-token');
        expect(hasCSRF, `${file} has POST forms but no CSRF protection`).toBe(true);
      }
      // Check fetch calls also have CSRF
      const fetchRegex = /fetch\s*\([^)]*,\s*\{[^}]*method\s*:\s*['"]POST['"]/gi;
      const fetches = content.match(fetchRegex) || [];
      if (fetches.length > 0) {
        const hasCSRF = content.includes('csrf_token') || content.includes('csrf-token');
        expect(hasCSRF, `${file} has fetch POST calls but no CSRF protection`).toBe(true);
      }
    }
  });
});

// ── ONCLICK HANDLER FUNCTIONALITY DEEP CHECKS ───────────────────────

test.describe('HR/Admin onclick handler functionality', () => {

  test('eval_framework.php FAB switches to mEvalAddSkill on Skills tab', () => {
    const content = readPwaFile('eval_framework.php');
    // The mEvalTab function must update FAB to call mEvalAddSkill for skills tab
    expect(content).toContain("fab.setAttribute('onclick', 'mEvalAddSkill()')");
    expect(content).toContain("fab.setAttribute('title', 'Add Skill')");
  });

  test('eval_framework.php has mEvalAddSkill function defined', () => {
    const content = readPwaFile('eval_framework.php');
    expect(content).toMatch(/function mEvalAddSkill\s*\(/);
  });

  test('eval_framework.php mEvalAddSkill sets action to create_skill', () => {
    const content = readPwaFile('eval_framework.php');
    // Extract the mEvalAddSkill function body
    const match = content.match(/function mEvalAddSkill\s*\(\)\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/);
    expect(match).not.toBeNull();
    const fnBody = match[1];
    expect(fnBody).toContain("'create_skill'");
    expect(fnBody).toContain('mEvalSkillAction');
    expect(fnBody).toContain('mEvalSkillSheet');
  });

  test('eval_framework.php mEvalEditSkill sets action to update_skill', () => {
    const content = readPwaFile('eval_framework.php');
    const match = content.match(/function mEvalEditSkill\s*\(s\)\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/);
    expect(match).not.toBeNull();
    const fnBody = match[1];
    expect(fnBody).toContain("'update_skill'");
    expect(fnBody).toContain('mEvalSkillAction');
  });

  test('eval_framework.php skill sheet has category selector', () => {
    const content = readPwaFile('eval_framework.php');
    // The skill sheet should have a select for category_id
    expect(content).toMatch(/<select[^>]*name="category_id"[^>]*id="mEvalSkillCatId"/);
  });

  test('eval_framework.php skill sheet action is dynamic (not hardcoded)', () => {
    const content = readPwaFile('eval_framework.php');
    // The action field should have an id so JS can change it
    expect(content).toMatch(/id="mEvalSkillAction"/);
  });

  test('eval_framework.php mEvalTab passes button reference (no implicit event)', () => {
    const content = readPwaFile('eval_framework.php');
    // Onclick should pass 'this' to the function
    expect(content).toMatch(/onclick="mEvalTab\('categories',\s*this\)"/);
    expect(content).toMatch(/onclick="mEvalTab\('skills',\s*this\)"/);
    // Function should accept btn parameter
    expect(content).toMatch(/function mEvalTab\(tab,\s*btn\)/);
    // Should use btn instead of event.currentTarget
    expect(content).not.toContain('event.currentTarget');
  });

  test('admin_permissions.php mPermsTab passes button reference (no implicit event)', () => {
    const content = readPwaFile('admin_permissions.php');
    // Onclick should pass 'this' to the function
    expect(content).toMatch(/onclick="mPermsTab\('roles',\s*this\)"/);
    expect(content).toMatch(/onclick="mPermsTab\('manage',\s*this\)"/);
    // Function should accept btn parameter
    expect(content).toMatch(/function mPermsTab\(tab,\s*btn\)/);
    // Should use btn instead of event.currentTarget
    expect(content).not.toContain('event.currentTarget');
  });

  test('eval_framework.php tab buttons pass this as second arg', () => {
    const content = readPwaFile('eval_framework.php');
    // Both tab buttons should pass this
    const tabButtonMatches = content.match(/onclick="mEvalTab\([^"]+\)"/g) || [];
    expect(tabButtonMatches.length).toBeGreaterThanOrEqual(2);
    for (const match of tabButtonMatches) {
      expect(match).toContain(', this)');
    }
  });

  test('admin_permissions.php tab buttons pass this as second arg', () => {
    const content = readPwaFile('admin_permissions.php');
    // Both tab buttons should pass this
    const tabButtonMatches = content.match(/onclick="mPermsTab\([^"]+\)"/g) || [];
    expect(tabButtonMatches.length).toBeGreaterThanOrEqual(2);
    for (const match of tabButtonMatches) {
      expect(match).toContain(', this)');
    }
  });
});

// ── EXPANDED HANDLER VERIFICATION FOR ADDITIONAL ADMIN VIEWS ────────

test.describe('PWA additional admin views handler verification', () => {

  function extractOnclickFunctions(content) {
    const matches = content.matchAll(/onclick="([^"]+)"/g);
    const fns = new Set();
    for (const m of matches) {
      const calls = m[1].matchAll(/(?<![.\w])([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/g);
      for (const c of calls) {
        const fn = c[1];
        if (!['event', 'this', 'if', 'return', 'confirm', 'alert', 'window', 'document',
              'location', 'history', 'parseInt', 'parseFloat', 'encodeURIComponent',
              'decodeURIComponent', 'String', 'Number', 'JSON', 'Array', 'Object',
              'console', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval',
              'showToast', 'showConfirmModal', 'stopPropagation', 'preventDefault',
              'closest', 'querySelector', 'querySelectorAll', 'getAttribute', 'setAttribute',
              'classList', 'remove', 'appendChild', 'insertBefore', 'replaceChild',
              'htmlspecialchars', 'json_encode', 'urlencode', 'intval', 'number_format',
              'date', 'time', 'trim', 'strtolower', 'strtoupper', 'ucfirst', 'ucwords',
              'isset', 'empty', 'is_null', 'is_array', 'is_string', 'is_int', 'is_float',
              'count', 'strlen', 'substr', 'strpos', 'str_replace', 'preg_replace',
              'nl2br', 'sprintf', 'round', 'floor', 'ceil', 'abs', 'max', 'min',
              'array_key_exists', 'in_array', 'array_merge', 'array_map'
        ].includes(fn)) {
          fns.add(fn);
        }
      }
    }
    return fns;
  }

  function verifyHandlers(filename) {
    const content = readPwaFile(filename);
    const fns = extractOnclickFunctions(content);
    for (const fn of fns) {
      const patterns = [
        `function ${fn}(`,
        `function ${fn} (`,
        `window.${fn} = function`,
        `window.${fn} = async function`,
        `window.${fn}= function`,
        `window.${fn}=function`,
        `window.${fn}=async function`,
        `async function ${fn}(`,
      ];
      const defined = patterns.some(p => content.includes(p)) ||
                      new RegExp(`(var|let|const)\\s+${fn}\\s*=\\s*(async\\s+)?function`).test(content);
      expect(defined, `${filename}: onclick handler '${fn}' not defined`).toBe(true);
    }
  }

  test('admin_discounts.php all onclick handlers defined', () => {
    verifyHandlers('admin_discounts.php');
  });

  test('admin_locations.php all onclick handlers defined', () => {
    verifyHandlers('admin_locations.php');
  });

  test('admin_packages.php all onclick handlers defined', () => {
    verifyHandlers('admin_packages.php');
  });

  test('admin_session_types.php all onclick handlers defined', () => {
    verifyHandlers('admin_session_types.php');
  });

  test('admin_plan_categories.php all onclick handlers defined', () => {
    verifyHandlers('admin_plan_categories.php');
  });

  test('admin_team_coaches.php all onclick handlers defined', () => {
    verifyHandlers('admin_team_coaches.php');
  });

  test('admin_theme_settings.php all onclick handlers defined', () => {
    verifyHandlers('admin_theme_settings.php');
  });

  test('admin_coach_termination.php all onclick handlers defined', () => {
    verifyHandlers('admin_coach_termination.php');
  });

  test('admin_business_partners.php all onclick handlers defined', () => {
    verifyHandlers('admin_business_partners.php');
  });

  test('admin_permissions.php all onclick handlers defined', () => {
    verifyHandlers('admin_permissions.php');
  });

  test('merchandise_categories.php all onclick handlers defined', () => {
    verifyHandlers('merchandise_categories.php');
  });
});

// ── NO IMPLICIT EVENT GLOBAL IN ANY VIEW ────────────────────────────

test.describe('No implicit event global usage in HR/Admin views', () => {

  const viewFiles = [
    'eval_framework.php', 'admin_permissions.php', 'categories.php',
    'all_users.php', 'hr_complaints.php', 'hr_time_tracking.php',
    'hr_employee_contracts.php', 'hr_onboarding.php', 'termination.php',
    'admin_discounts.php', 'admin_locations.php', 'admin_packages.php',
    'admin_session_types.php', 'admin_plan_categories.php',
    'admin_team_coaches.php', 'admin_theme_settings.php',
    'admin_wishlist.php', 'marketing.php', 'admin_coach_termination.php',
    'admin_business_partners.php', 'admin_staff_scheduling.php',
  ];

  test('no view uses implicit event.currentTarget', () => {
    for (const file of viewFiles) {
      const content = readPwaFile(file);
      // Check script blocks don't use event.currentTarget outside of addEventListener callbacks
      const scriptMatch = content.match(/<script>([\s\S]*?)<\/script>/gi) || [];
      for (const script of scriptMatch) {
        // If there's event.currentTarget, it must be inside an addEventListener callback, not a named function
        if (script.includes('event.currentTarget')) {
          // This is only OK inside addEventListener(... function(event) { ... event.currentTarget ... })
          // Named functions called via onclick should NOT use event.currentTarget
          const namedFnRegex = /function\s+\w+\s*\([^)]*\)\s*\{[^}]*event\.currentTarget/g;
          const badMatches = script.match(namedFnRegex) || [];
          expect(badMatches.length, `${file}: named function uses implicit event.currentTarget`).toBe(0);
        }
      }
    }
  });
});
