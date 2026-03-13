import { test, expect } from '@playwright/test';
import { readFileSync, existsSync } from 'fs';
import { join } from 'path';

/**
 * PWA Main Menu Functionality Tests
 *
 * Validates that all PWA views have:
 * - Proper role-based permission checks matching desktop
 * - CSRF tokens in all POST forms and fetch calls
 * - No broken dashboard.php references
 * - Working button definitions (onclick handlers reference defined functions)
 */

const ROOT = join(__dirname, '..');
const PWA_DIR = join(ROOT, 'views', 'pwa');

function readPwaFile(name) {
  return readFileSync(join(PWA_DIR, name), 'utf-8');
}

function readFile(relPath) {
  return readFileSync(join(ROOT, relPath), 'utf-8');
}

// ──────────────────────────────────────────────────────────────────────
// 1. No dashboard.php references in PWA views (except permitted ones)
// ──────────────────────────────────────────────────────────────────────
test.describe('PWA views have no broken dashboard.php references', () => {

  test('athletes.php filter form uses pwa.php not dashboard.php', () => {
    const content = readPwaFile('athletes.php');
    // Should NOT have action="dashboard.php"
    expect(content).not.toMatch(/action\s*=\s*["']dashboard\.php["']/);
    // Should NOT have href="dashboard.php?page=athletes"
    expect(content).not.toMatch(/href\s*=\s*["']dashboard\.php\?page=athletes["']/);
    // Should have pwa-friendly links
    expect(content).toMatch(/action\s*=\s*["']pwa\.php["']/);
    expect(content).toMatch(/href\s*=\s*["']\?page=athletes["']/);
  });

  test('coach_evaluations.php admin link uses ?page= not dashboard.php', () => {
    const content = readPwaFile('coach_evaluations.php');
    expect(content).not.toMatch(/href\s*=\s*["']dashboard\.php\?page=/);
    expect(content).toMatch(/href\s*=\s*["']\?page=eval_framework["']/);
  });

  test('pos_online_orders.php fetch uses pwa.php not dashboard.php', () => {
    const content = readPwaFile('pos_online_orders.php');
    expect(content).not.toMatch(/fetch\(['"]dashboard\.php/);
    expect(content).toMatch(/fetch\(['"]pwa\.php\?page=pos_online_orders/);
  });

  test('shop_orders.php fetch uses pwa.php not dashboard.php', () => {
    const content = readPwaFile('shop_orders.php');
    expect(content).not.toMatch(/fetch\(['"]dashboard\.php/);
    expect(content).toMatch(/fetch\(['"]pwa\.php\?page=shop_orders/);
  });

  test('no PWA view has broken dashboard.php navigation links', () => {
    const { readdirSync } = require('fs');
    const pwaFiles = readdirSync(PWA_DIR).filter(f => f.endsWith('.php'));
    const issues = [];

    for (const file of pwaFiles) {
      const content = readPwaFile(file);
      // Check for href="dashboard.php?page=..." patterns (navigation links)
      const matches = content.match(/href\s*=\s*["']dashboard\.php\?page=[^"']+["']/g);
      if (matches) {
        issues.push(`${file}: ${matches.join(', ')}`);
      }
      // Check for form action="dashboard.php" patterns
      const formMatches = content.match(/action\s*=\s*["']dashboard\.php["']/g);
      if (formMatches) {
        issues.push(`${file}: form ${formMatches.join(', ')}`);
      }
    }

    // gameplan.php has a rewriter for dashboard links, so it's allowed
    const filteredIssues = issues.filter(i => !i.startsWith('gameplan.php'));
    expect(filteredIssues, `PWA views with dashboard.php refs: ${filteredIssues.join('; ')}`).toEqual([]);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 2. Role-based permission checks
// ──────────────────────────────────────────────────────────────────────
test.describe('PWA views have correct permission checks', () => {

  test('athletes.php checks for coach/admin role', () => {
    const content = readPwaFile('athletes.php');
    expect(content).toMatch(/in_array\(\$user_role.*\[.*'coach'.*'admin'.*\]\)/s);
  });

  test('library_nutrition.php checks for canAccessHealthManagement permission', () => {
    const content = readPwaFile('library_nutrition.php');
    expect(content).toMatch(/\$canAccessHealthManagement/);
  });

  test('library_workouts.php checks for canAccessHealthManagement permission', () => {
    const content = readPwaFile('library_workouts.php');
    expect(content).toMatch(/\$canAccessHealthManagement/);
  });

  test('session_templates.php checks for coach/admin role', () => {
    const content = readPwaFile('session_templates.php');
    expect(content).toMatch(/in_array\(\$user_role.*\[.*'coach'.*'admin'.*\]\)/s);
  });

  test('staff_time_history.php checks canAccessPOS', () => {
    const content = readPwaFile('staff_time_history.php');
    expect(content).toMatch(/\$canAccessPOS/);
  });

  test('testing.php checks isAdmin', () => {
    const content = readPwaFile('testing.php');
    expect(content).toMatch(/!\$isAdmin/);
  });

  test('packages.php checks for logged-in user', () => {
    const content = readPwaFile('packages.php');
    expect(content).toMatch(/\$_SESSION\['user_id'\]/);
  });

  // HR views should use canAccessHR
  const hrViews = [
    'hr_payroll.php', 'hr_onboarding.php', 'hr_employee_contracts.php',
    'hr_complaints.php', 'hr_time_tracking.php', 'termination.php',
    'admin_staff_scheduling.php'
  ];
  for (const file of hrViews) {
    test(`${file} uses canAccessHR permission gate`, () => {
      const content = readPwaFile(file);
      expect(content).toMatch(/\$canAccessHR/);
    });
  }

  // Accounting views should use canAccessAccounting
  const acctViews = [
    'accounting_credits.php', 'accounting_expenses.php', 'accounting_products.php',
    'finance_dashboard.php', 'financial_reports.php', 'reports_income.php'
  ];
  for (const file of acctViews) {
    test(`${file} uses canAccessAccounting permission gate`, () => {
      const content = readPwaFile(file);
      expect(content).toMatch(/\$canAccessAccounting/);
    });
  }
});

// ──────────────────────────────────────────────────────────────────────
// 3. CSRF token presence in POST forms
// ──────────────────────────────────────────────────────────────────────
test.describe('PWA views include CSRF tokens in POST forms', () => {

  test('camp_checkin.php forms include CSRF token', () => {
    const content = readPwaFile('camp_checkin.php');
    // Count POST forms
    const postForms = content.match(/<form[^>]*method\s*=\s*["']post["'][^>]*>/gi) || [];
    // Count CSRF token inputs
    const csrfInputs = content.match(/csrfTokenInput\(\)|name\s*=\s*["']csrf_token["']/gi) || [];
    expect(csrfInputs.length).toBeGreaterThanOrEqual(postForms.length);
  });

  test('notifications.php mark-all-read form includes CSRF token', () => {
    const content = readPwaFile('notifications.php');
    const postForms = content.match(/<form[^>]*method\s*=\s*["']post["'][^>]*>/gi) || [];
    const csrfInputs = content.match(/csrfTokenInput\(\)|name\s*=\s*["']csrf_token["']/gi) || [];
    expect(csrfInputs.length).toBeGreaterThanOrEqual(postForms.length);
  });

  test('mileage_tracker.php form includes CSRF token', () => {
    const content = readPwaFile('mileage_tracker.php');
    const postForms = content.match(/<form[^>]*method\s*=\s*["']post["'][^>]*>/gi) || [];
    const csrfInputs = content.match(/csrfTokenInput\(\)|name\s*=\s*["']csrf_token["']/gi) || [];
    expect(csrfInputs.length).toBeGreaterThanOrEqual(postForms.length);
  });

  test('session_evaluation_form.php form includes CSRF token', () => {
    const content = readPwaFile('session_evaluation_form.php');
    const postForms = content.match(/<form[^>]*method\s*=\s*["']post["'][^>]*>/gi) || [];
    const csrfInputs = content.match(/csrfTokenInput\(\)|name\s*=\s*["']csrf_token["']/gi) || [];
    expect(csrfInputs.length).toBeGreaterThanOrEqual(postForms.length);
  });

  test('create_session.php form includes CSRF token', () => {
    const content = readPwaFile('create_session.php');
    const postForms = content.match(/<form[^>]*method\s*=\s*["']post["'][^>]*>/gi) || [];
    const csrfInputs = content.match(/csrfTokenInput\(\)|name\s*=\s*["']csrf_token["']/gi) || [];
    expect(csrfInputs.length).toBeGreaterThanOrEqual(postForms.length);
  });

  test('shop.php add-to-cart fetch includes CSRF token', () => {
    const content = readPwaFile('shop.php');
    // The mShopAddToCart function should append csrf_token
    expect(content).toMatch(/csrf[_-]token/i);
    // Specifically check it's in the add to cart function
    const addToCartSection = content.match(/function mShopAddToCart[\s\S]*?^}/m);
    if (addToCartSection) {
      expect(addToCartSection[0]).toMatch(/csrf/i);
    }
  });
});

// ──────────────────────────────────────────────────────────────────────
// 4. Route parity - athlete_notes route
// ──────────────────────────────────────────────────────────────────────
test.describe('Route parity fixes', () => {

  test('pwa.php includes athlete_notes route', () => {
    const content = readFile('pwa.php');
    expect(content).toMatch(/'athlete_notes'\s*=>\s*'views\/athlete_notes\.php'/);
  });

  test('pwa_tablet.php includes athlete_notes route', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toMatch(/'athlete_notes'\s*=>\s*'views\/athlete_notes\.php'/);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 5. Button definitions - onclick handlers have corresponding functions
// ──────────────────────────────────────────────────────────────────────
test.describe('PWA view buttons have defined handlers', () => {

  test('categories.php all onclick functions are defined', () => {
    const content = readPwaFile('categories.php');
    // Extract onclick function names
    const onclicks = content.match(/onclick\s*=\s*["'][^"']*?([a-zA-Z_]\w+)\s*\(/g) || [];
    const funcNames = new Set();
    for (const oc of onclicks) {
      const m = oc.match(/([a-zA-Z_]\w+)\s*\(/);
      if (m) funcNames.add(m[1]);
    }
    // Check each is defined as a function
    for (const fn of funcNames) {
      // Skip built-in functions
      if (['confirm', 'alert', 'location', 'history', 'document', 'window', 'parseInt', 'encodeURIComponent'].includes(fn)) continue;
      const fnDef = new RegExp(`function\\s+${fn}\\s*\\(`);
      expect(content, `Function ${fn} should be defined`).toMatch(fnDef);
    }
  });

  test('eval_framework.php all onclick functions are defined', () => {
    const content = readPwaFile('eval_framework.php');
    const onclicks = content.match(/onclick\s*=\s*["'][^"']*?([a-zA-Z_]\w+)\s*\(/g) || [];
    const funcNames = new Set();
    for (const oc of onclicks) {
      const m = oc.match(/([a-zA-Z_]\w+)\s*\(/);
      if (m) funcNames.add(m[1]);
    }
    for (const fn of funcNames) {
      if (['confirm', 'alert', 'location', 'history', 'document', 'window', 'parseInt', 'encodeURIComponent', 'showConfirmModal'].includes(fn)) continue;
      const fnDef = new RegExp(`function\\s+${fn}\\s*\\(`);
      expect(content, `Function ${fn} should be defined`).toMatch(fnDef);
    }
  });

  test('marketing.php all onclick functions are defined', () => {
    const content = readPwaFile('marketing.php');
    const onclicks = content.match(/onclick\s*=\s*["'][^"']*?([a-zA-Z_]\w+)\s*\(/g) || [];
    const funcNames = new Set();
    for (const oc of onclicks) {
      const m = oc.match(/([a-zA-Z_]\w+)\s*\(/);
      if (m) funcNames.add(m[1]);
    }
    for (const fn of funcNames) {
      if (['confirm', 'alert', 'location', 'history', 'document', 'window', 'parseInt', 'encodeURIComponent', 'showConfirmModal'].includes(fn)) continue;
      const fnDef = new RegExp(`(function\\s+${fn}\\s*\\(|window\\.${fn}\\s*=\\s*(async\\s+)?function|var\\s+${fn}\\s*=\\s*(async\\s+)?function)`);
      expect(content, `Function ${fn} should be defined`).toMatch(fnDef);
    }
  });
});

// ──────────────────────────────────────────────────────────────────────
// 6. All PWA POST forms have CSRF protection (comprehensive scan)
// ──────────────────────────────────────────────────────────────────────
test.describe('Comprehensive CSRF audit of all PWA views', () => {

  test('every POST form in PWA views has CSRF protection', () => {
    const { readdirSync } = require('fs');
    const pwaFiles = readdirSync(PWA_DIR).filter(f => f.endsWith('.php'));
    const issues = [];

    for (const file of pwaFiles) {
      const content = readPwaFile(file);
      // Find all <form method="post"> blocks
      const formRegex = /<form[^>]*method\s*=\s*["']post["'][^>]*>([\s\S]*?)<\/form>/gi;
      let match;
      while ((match = formRegex.exec(content)) !== null) {
        const formContent = match[1];
        const hasCSRF = /csrfTokenInput\(\)|name\s*=\s*["']csrf_token["']|csrf_token/i.test(formContent);
        if (!hasCSRF) {
          issues.push(file);
          break;
        }
      }
    }

    expect(issues, `PWA views with POST forms missing CSRF: ${issues.join(', ')}`).toEqual([]);
  });
});
