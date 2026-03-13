/**
 * PWA Health, Accounting & POS Section Tests
 *
 * Validates that all Health Management, Accounting & Reports, and POS PWA views:
 * - Have correct mobile-native implementations
 * - Use proper permission variables ($canAccessHealthManagement, $canAccessAccounting, $canAccessPOS)
 * - Include CSRF tokens where needed
 * - Don't use dashboard.php in links/fetch
 * - Have all onclick handlers defined
 * - Match the desktop sidebar menu items
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

// ── HEALTH SECTION ─────────────────────────────────────────────────────

test.describe('Health Management PWA views', () => {

  test('all Health PWA view files exist', () => {
    expect(pwaFileExists('health.php')).toBe(true);
    expect(pwaFileExists('library_workouts.php')).toBe(true);
    expect(pwaFileExists('library_nutrition.php')).toBe(true);
    expect(pwaFileExists('health_coach_roster.php')).toBe(true);
    // Aliases
    expect(pwaFileExists('strength_conditioning.php')).toBe(true);
    expect(pwaFileExists('nutrition.php')).toBe(true);
    expect(pwaFileExists('workouts.php')).toBe(true);
  });

  test('health.php is mobile-native with styling', () => {
    const content = readPwaFile('health.php');
    expect(content).toContain('<style>');
    expect(content).toContain('m-health');
    expect(content).toContain('font-family: Inter');
  });

  test('library_workouts.php uses canAccessHealthManagement', () => {
    const content = readPwaFile('library_workouts.php');
    expect(content).toMatch(/\$canAccessHealthManagement/);
    expect(content).not.toMatch(/in_array\(\$user_role/);
  });

  test('library_nutrition.php uses canAccessHealthManagement', () => {
    const content = readPwaFile('library_nutrition.php');
    expect(content).toMatch(/\$canAccessHealthManagement/);
    expect(content).not.toMatch(/in_array\(\$user_role/);
  });

  test('health_coach_roster.php uses canAccessHealthManagement', () => {
    const content = readPwaFile('health_coach_roster.php');
    expect(content).toMatch(/\$canAccessHealthManagement/);
  });

  test('health.php has CSRF token for workout/nutrition CRUD', () => {
    const content = readPwaFile('health.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('library_workouts.php is mobile-native', () => {
    const content = readPwaFile('library_workouts.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('library_nutrition.php is mobile-native', () => {
    const content = readPwaFile('library_nutrition.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('strength_conditioning.php aliases to health.php', () => {
    const content = readPwaFile('strength_conditioning.php');
    expect(content).toMatch(/include.*health\.php/);
  });

  test('nutrition.php aliases to health.php', () => {
    const content = readPwaFile('nutrition.php');
    expect(content).toMatch(/include.*health\.php/);
  });

  test('pwa_more_menu.php has Health section with canAccessHealthManagement', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toMatch(/canAccessHealthManagement.*Health/s);
    expect(content).toContain('page=library_workouts');
    expect(content).toContain('page=library_nutrition');
  });

  test('desktop dashboard has same Health menu items as PWA', () => {
    const desktop = readFile('dashboard.php');
    const pwa = readFile('pwa_more_menu.php');
    // Desktop has library_workouts, library_nutrition, roster
    expect(desktop).toContain('page=library_workouts');
    expect(desktop).toContain('page=library_nutrition');
    // PWA also has them
    expect(pwa).toContain('page=library_workouts');
    expect(pwa).toContain('page=library_nutrition');
  });

  test('health views do not reference dashboard.php in links', () => {
    for (const file of ['health.php', 'library_workouts.php', 'library_nutrition.php', 'health_coach_roster.php']) {
      const content = readPwaFile(file);
      expect(content).not.toMatch(/href\s*=\s*["']dashboard\.php/);
      expect(content).not.toMatch(/fetch\(\s*["']dashboard\.php/);
    }
  });
});

// ── ACCOUNTING SECTION ─────────────────────────────────────────────────

test.describe('Accounting & Reports PWA views', () => {

  test('all Accounting PWA view files exist', () => {
    expect(pwaFileExists('finance_dashboard.php')).toBe(true);
    expect(pwaFileExists('financial_reports.php')).toBe(true);
    expect(pwaFileExists('reports_user.php')).toBe(true);
    expect(pwaFileExists('reports_income.php')).toBe(true);
    expect(pwaFileExists('accounting_credits.php')).toBe(true);
    expect(pwaFileExists('accounting_expenses.php')).toBe(true);
    expect(pwaFileExists('accounting_products.php')).toBe(true);
    expect(pwaFileExists('accounts_payable.php')).toBe(true);
    // Aliases
    expect(pwaFileExists('credits_refunds.php')).toBe(true);
    expect(pwaFileExists('expenses.php')).toBe(true);
    expect(pwaFileExists('products.php')).toBe(true);
    expect(pwaFileExists('accounting_dashboard.php')).toBe(true);
    expect(pwaFileExists('billing_dashboard.php')).toBe(true);
  });

  test('finance_dashboard.php uses canAccessAccounting', () => {
    const content = readPwaFile('finance_dashboard.php');
    expect(content).toMatch(/\$canAccessAccounting/);
  });

  test('reports_user.php uses canAccessAccounting (not raw session check)', () => {
    const content = readPwaFile('reports_user.php');
    expect(content).toMatch(/\$canAccessAccounting/);
    expect(content).not.toMatch(/\$_SESSION\['user_role'\]/);
  });

  test('accounts_payable.php uses canAccessAccounting (not isAdmin)', () => {
    const content = readPwaFile('accounts_payable.php');
    expect(content).toMatch(/\$canAccessAccounting/);
    // Should not use $isAdmin as the only check
    const lines = content.split('\n').slice(0, 15);
    const permBlock = lines.join('\n');
    expect(permBlock).not.toMatch(/if\s*\(\s*!\s*\$isAdmin\s*\)/);
  });

  test('accounting_credits.php uses canAccessAccounting', () => {
    const content = readPwaFile('accounting_credits.php');
    expect(content).toMatch(/\$canAccessAccounting/);
  });

  test('accounting_expenses.php uses canAccessAccounting', () => {
    const content = readPwaFile('accounting_expenses.php');
    expect(content).toMatch(/\$canAccessAccounting/);
  });

  test('accounting_products.php uses canAccessAccounting', () => {
    const content = readPwaFile('accounting_products.php');
    expect(content).toMatch(/\$canAccessAccounting/);
  });

  test('reports_income.php uses canAccessAccounting', () => {
    const content = readPwaFile('reports_income.php');
    expect(content).toMatch(/\$canAccessAccounting/);
  });

  test('financial_reports.php uses canAccessAccounting', () => {
    const content = readPwaFile('financial_reports.php');
    expect(content).toMatch(/\$canAccessAccounting/);
  });

  test('accounting_credits.php has CSRF for forms/fetch', () => {
    const content = readPwaFile('accounting_credits.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('accounting_expenses.php has CSRF for forms/fetch', () => {
    const content = readPwaFile('accounting_expenses.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('accounting_products.php has CSRF for forms/fetch', () => {
    const content = readPwaFile('accounting_products.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('financial_reports.php has CSRF for form', () => {
    const content = readPwaFile('financial_reports.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('accounting alias files redirect properly', () => {
    expect(readPwaFile('credits_refunds.php')).toMatch(/include.*accounting_credits\.php/);
    expect(readPwaFile('expenses.php')).toMatch(/include.*accounting_expenses\.php/);
    expect(readPwaFile('products.php')).toMatch(/include.*accounting_products\.php/);
    expect(readPwaFile('accounting_dashboard.php')).toMatch(/include.*finance_dashboard\.php/);
    expect(readPwaFile('billing_dashboard.php')).toMatch(/include.*finance_dashboard\.php/);
  });

  test('finance_dashboard.php is mobile-native', () => {
    const content = readPwaFile('finance_dashboard.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('pwa_more_menu.php has Accounting section with canAccessAccounting', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toMatch(/canAccessAccounting.*Accounting/s);
    expect(content).toContain('page=finance_dashboard');
    expect(content).toContain('page=financial_reports');
    expect(content).toContain('page=reports_user');
    expect(content).toContain('page=credits_refunds');
    expect(content).toContain('page=expenses');
    expect(content).toContain('page=products');
  });

  test('desktop dashboard has same Accounting menu items as PWA', () => {
    const desktop = readFile('dashboard.php');
    const pwa = readFile('pwa_more_menu.php');
    const desktopItems = ['finance_dashboard', 'financial_reports', 'reports_user', 'credits_refunds', 'expenses', 'products'];
    for (const item of desktopItems) {
      expect(desktop).toContain(`page=${item}`);
      expect(pwa).toContain(`page=${item}`);
    }
  });

  test('accounting views do not reference dashboard.php in links', () => {
    const accountingFiles = [
      'finance_dashboard.php', 'financial_reports.php', 'reports_user.php',
      'reports_income.php', 'accounting_credits.php', 'accounting_expenses.php',
      'accounting_products.php', 'accounts_payable.php'
    ];
    for (const file of accountingFiles) {
      const content = readPwaFile(file);
      expect(content).not.toMatch(/href\s*=\s*["']dashboard\.php/);
      expect(content).not.toMatch(/fetch\(\s*["']dashboard\.php/);
    }
  });
});

// ── POS SECTION ─────────────────────────────────────────────────────

test.describe('POS (Point of Sale) PWA views', () => {

  test('all POS PWA view files exist', () => {
    expect(pwaFileExists('pos_terminal.php')).toBe(true);
    expect(pwaFileExists('inventory_management.php')).toBe(true);
    expect(pwaFileExists('pos_online_orders.php')).toBe(true);
    expect(pwaFileExists('pos_time_tracking.php')).toBe(true);
    expect(pwaFileExists('pos_schedule.php')).toBe(true);
    expect(pwaFileExists('sip_settings.php')).toBe(true);
    expect(pwaFileExists('pos_transactions.php')).toBe(true);
    expect(pwaFileExists('shop_orders.php')).toBe(true);
    expect(pwaFileExists('staff_time_history.php')).toBe(true);
  });

  test('pos_terminal.php uses canAccessPOS', () => {
    const content = readPwaFile('pos_terminal.php');
    expect(content).toMatch(/\$canAccessPOS/);
  });

  test('inventory_management.php uses canAccessPOS', () => {
    const content = readPwaFile('inventory_management.php');
    expect(content).toMatch(/\$canAccessPOS/);
  });

  test('pos_online_orders.php uses canAccessPOS', () => {
    const content = readPwaFile('pos_online_orders.php');
    expect(content).toMatch(/\$canAccessPOS/);
  });

  test('pos_time_tracking.php uses canAccessPOS', () => {
    const content = readPwaFile('pos_time_tracking.php');
    expect(content).toMatch(/\$canAccessPOS/);
  });

  test('pos_schedule.php uses canAccessPOS', () => {
    const content = readPwaFile('pos_schedule.php');
    expect(content).toMatch(/\$canAccessPOS/);
  });

  test('pos_terminal.php has CSRF for transactions', () => {
    const content = readPwaFile('pos_terminal.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('pos_online_orders.php has CSRF for order updates', () => {
    const content = readPwaFile('pos_online_orders.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('pos_schedule.php has CSRF for shift management', () => {
    const content = readPwaFile('pos_schedule.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('pos_terminal.php is mobile-native', () => {
    const content = readPwaFile('pos_terminal.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
    expect(content).toContain('min-height');
  });

  test('inventory_management.php is mobile-native', () => {
    const content = readPwaFile('inventory_management.php');
    expect(content).toContain('<style>');
    expect(content).toContain('font-family');
  });

  test('pos_online_orders.php uses pwa.php for fetch (not dashboard.php)', () => {
    const content = readPwaFile('pos_online_orders.php');
    if (content.match(/fetch\s*\(/)) {
      expect(content).not.toMatch(/fetch\(\s*["']dashboard\.php/);
    }
  });

  test('pwa_more_menu.php has POS section with canAccessPOS', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toMatch(/canAccessPOS.*Point of Sale/s);
    expect(content).toContain('page=pos_terminal');
    expect(content).toContain('page=inventory_management');
    expect(content).toContain('page=pos_online_orders');
    expect(content).toContain('page=pos_time_tracking');
    expect(content).toContain('page=pos_schedule');
    expect(content).toContain('page=sip_settings');
  });

  test('desktop dashboard has same POS menu items as PWA', () => {
    const desktop = readFile('dashboard.php');
    const pwa = readFile('pwa_more_menu.php');
    const posItems = ['pos_terminal', 'inventory_management', 'pos_online_orders', 'pos_time_tracking', 'pos_schedule', 'sip_settings'];
    for (const item of posItems) {
      expect(desktop).toContain(`page=${item}`);
      expect(pwa).toContain(`page=${item}`);
    }
  });

  test('POS views do not reference dashboard.php in links', () => {
    const posFiles = [
      'pos_terminal.php', 'inventory_management.php', 'pos_online_orders.php',
      'pos_time_tracking.php', 'pos_schedule.php', 'sip_settings.php',
      'pos_transactions.php', 'shop_orders.php'
    ];
    for (const file of posFiles) {
      const content = readPwaFile(file);
      expect(content).not.toMatch(/href\s*=\s*["']dashboard\.php/);
      expect(content).not.toMatch(/fetch\(\s*["']dashboard\.php/);
    }
  });

  test('shop_orders.php has CSRF for order status updates', () => {
    const content = readPwaFile('shop_orders.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });
});

// ── ROUTING COVERAGE ─────────────────────────────────────────────────

test.describe('PWA routing covers Health, Accounting, and POS pages', () => {

  test('pwa.php routes Health pages', () => {
    const content = readFile('pwa.php');
    expect(content).toContain("'library_workouts'");
    expect(content).toContain("'library_nutrition'");
    expect(content).toContain("'health_coach_roster'");
  });

  test('pwa.php routes Accounting pages', () => {
    const content = readFile('pwa.php');
    expect(content).toContain("'finance_dashboard'");
    expect(content).toContain("'financial_reports'");
    expect(content).toContain("'credits_refunds'");
    expect(content).toContain("'expenses'");
    expect(content).toContain("'products'");
    expect(content).toContain("'accounts_payable'");
  });

  test('pwa.php routes POS pages', () => {
    const content = readFile('pwa.php');
    expect(content).toContain("'pos_terminal'");
    expect(content).toContain("'inventory_management'");
    expect(content).toContain("'pos_online_orders'");
    expect(content).toContain("'pos_time_tracking'");
    expect(content).toContain("'pos_schedule'");
  });

  test('pwa_tablet.php routes same pages', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain("'library_workouts'");
    expect(content).toContain("'library_nutrition'");
    expect(content).toContain("'finance_dashboard'");
    expect(content).toContain("'pos_terminal'");
    expect(content).toContain("'inventory_management'");
  });

  test('pwa_tablet.php sidebar has Health section', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toMatch(/canAccessHealthManagement.*Health/s);
    expect(content).toContain('page=library_workouts');
    expect(content).toContain('page=library_nutrition');
  });

  test('pwa_tablet.php sidebar has Accounting section', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toMatch(/canAccessAccounting.*Accounting/s);
    expect(content).toContain('page=finance_dashboard');
    expect(content).toContain('page=financial_reports');
    expect(content).toContain('page=credits_refunds');
    expect(content).toContain('page=expenses');
    expect(content).toContain('page=products');
  });

  test('pwa_tablet.php sidebar has POS section', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toMatch(/canAccessPOS.*Point of Sale/s);
    expect(content).toContain('page=pos_terminal');
    expect(content).toContain('page=inventory_management');
    expect(content).toContain('page=pos_online_orders');
    expect(content).toContain('page=pos_time_tracking');
    expect(content).toContain('page=pos_schedule');
  });
});

// ── BUTTON HANDLER VERIFICATION ─────────────────────────────────────

test.describe('PWA Health/Accounting/POS views have all handlers defined', () => {

  function extractOnclickFunctions(content) {
    const matches = content.matchAll(/onclick="([^"]+)"/g);
    const fns = new Set();
    for (const m of matches) {
      const calls = m[1].matchAll(/([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/g);
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

  test('health.php all onclick handlers defined', () => {
    verifyHandlers('health.php');
  });

  test('accounting_credits.php all onclick handlers defined', () => {
    verifyHandlers('accounting_credits.php');
  });

  test('accounting_expenses.php all onclick handlers defined', () => {
    verifyHandlers('accounting_expenses.php');
  });

  test('accounting_products.php all onclick handlers defined', () => {
    verifyHandlers('accounting_products.php');
  });

  test('pos_terminal.php all onclick handlers defined', () => {
    verifyHandlers('pos_terminal.php');
  });

  test('pos_online_orders.php all onclick handlers defined', () => {
    verifyHandlers('pos_online_orders.php');
  });

  test('pos_schedule.php all onclick handlers defined', () => {
    verifyHandlers('pos_schedule.php');
  });

  test('reports_income.php all onclick handlers defined', () => {
    verifyHandlers('reports_income.php');
  });

  test('financial_reports.php all onclick handlers defined', () => {
    verifyHandlers('financial_reports.php');
  });
});
