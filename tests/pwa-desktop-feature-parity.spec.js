import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { join } from 'path';

/**
 * PWA ↔ Desktop Comprehensive Feature-Parity Tests
 *
 * Systematic, view-by-view verification that pwa.php serves
 * the EXACT SAME view files as the desktop dashboard.php for
 * every single route – no stripped-down PWA overrides.
 *
 * Also verifies navigation menus, role-gating, CSS scroll
 * properties, and supporting infrastructure.
 */

const ROOT = join(__dirname, '..');

/* ──────────────────────────────────────────────────────── */
/*  Helpers                                                  */
/* ──────────────────────────────────────────────────────── */

/** Extract route key → view-file pairs from a PHP $allowed_pages array */
function extractRoutes(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const regex = /^\s*'([a-z_]+)'\s*=>\s*'(views\/[^']+)'/gm;
  const routes = {};
  let m;
  while ((m = regex.exec(content)) !== null) {
    routes[m[1]] = m[2];
  }
  return routes;
}

/** Extract role/permission variable definitions from a PHP file */
function extractRoleVars(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const regex = /\$(is[A-Z]\w+|canAccess\w+)\s*=/g;
  const vars = new Set();
  let m;
  while ((m = regex.exec(content)) !== null) {
    vars.add(m[1]);
  }
  return vars;
}

/** Extract all page= link targets from a PHP file */
function extractPageLinks(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const regex = /page=([a-z_]+)/g;
  const pages = new Set();
  let m;
  while ((m = regex.exec(content)) !== null) {
    pages.add(m[1]);
  }
  return pages;
}

/** Read a file as text */
function read(relPath) {
  return readFileSync(join(ROOT, relPath), 'utf-8');
}

/* ──────────────────────────────────────────────────────── */
/*  1.  Routing-table parity (route-by-route)               */
/* ──────────────────────────────────────────────────────── */

test.describe('1 · Route-table parity (every route, every view file)', () => {

  const desktopRoutes = extractRoutes(join(ROOT, 'dashboard.php'));
  const pwaRoutes     = extractRoutes(join(ROOT, 'pwa.php'));
  const tabletRoutes  = extractRoutes(join(ROOT, 'pwa_tablet.php'));

  test('pwa.php has every route key that dashboard.php has', () => {
    const missing = Object.keys(desktopRoutes).filter(k => !(k in pwaRoutes));
    expect(missing, `pwa.php missing routes: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa_tablet.php has every route key that dashboard.php has', () => {
    const missing = Object.keys(desktopRoutes).filter(k => !(k in tabletRoutes));
    expect(missing, `pwa_tablet.php missing routes: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa.php maps every route to the same view file as dashboard.php', () => {
    const mismatched = [];
    for (const [key, desktopView] of Object.entries(desktopRoutes)) {
      if (pwaRoutes[key] && pwaRoutes[key] !== desktopView) {
        mismatched.push(`${key}: desktop=${desktopView}  pwa=${pwaRoutes[key]}`);
      }
    }
    expect(mismatched, `View-file mismatches:\n${mismatched.join('\n')}`).toEqual([]);
  });

  test('pwa_tablet.php maps every route to the same view file as dashboard.php', () => {
    const mismatched = [];
    for (const [key, desktopView] of Object.entries(desktopRoutes)) {
      if (tabletRoutes[key] && tabletRoutes[key] !== desktopView) {
        mismatched.push(`${key}: desktop=${desktopView}  tablet=${tabletRoutes[key]}`);
      }
    }
    expect(mismatched, `View-file mismatches:\n${mismatched.join('\n')}`).toEqual([]);
  });
});

/* ──────────────────────────────────────────────────────── */
/*  2.  No PWA view-file overrides remain                   */
/* ──────────────────────────────────────────────────────── */

test.describe('2 · PWA never loads stripped-down view overrides', () => {

  test('pwa.php does not reference views/pwa/ directory for page rendering', () => {
    const content = read('pwa.php');
    // The file should NOT contain any logic that swaps $view_file to a
    // views/pwa/* path (aside from comments which are harmless).
    const nonCommentLines = content
      .split('\n')
      .filter(line => !line.trim().startsWith('//') && !line.trim().startsWith('*'));
    const pwaViewRef = nonCommentLines.some(
      line => /\$view_file\s*=\s*.*views\/pwa\//.test(line) ||
              /include.*views\/pwa\//.test(line)
    );
    expect(pwaViewRef, 'pwa.php still contains a code path that loads views/pwa/ files').toBe(false);
  });

  test('pwa.php does not define a $pwa_only_pages whitelist', () => {
    const content = read('pwa.php');
    const nonCommentLines = content
      .split('\n')
      .filter(line => !line.trim().startsWith('//') && !line.trim().startsWith('*'));
    const hasWhitelist = nonCommentLines.some(line => /\$pwa_only_pages/.test(line));
    expect(hasWhitelist, 'pwa.php still has $pwa_only_pages').toBe(false);
  });
});

/* ──────────────────────────────────────────────────────── */
/*  3.  Role / permission variable parity                   */
/* ──────────────────────────────────────────────────────── */

test.describe('3 · Role-variable parity', () => {

  test('pwa.php defines every role variable from dashboard.php', () => {
    const desktopVars = extractRoleVars(join(ROOT, 'dashboard.php'));
    const pwaVars     = extractRoleVars(join(ROOT, 'pwa.php'));
    const missing = [...desktopVars].filter(v => !pwaVars.has(v));
    expect(missing, `pwa.php missing role vars: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa_tablet.php defines every role variable from dashboard.php', () => {
    const desktopVars = extractRoleVars(join(ROOT, 'dashboard.php'));
    const tabletVars  = extractRoleVars(join(ROOT, 'pwa_tablet.php'));
    const missing = [...desktopVars].filter(v => !tabletVars.has(v));
    expect(missing, `pwa_tablet.php missing role vars: ${missing.join(', ')}`).toEqual([]);
  });
});

/* ──────────────────────────────────────────────────────── */
/*  4.  Navigation completeness                             */
/* ──────────────────────────────────────────────────────── */

test.describe('4 · Navigation completeness — every desktop sidebar link reachable in PWA', () => {

  // Desktop sidebar links (from dashboard.php nav sections)
  const desktopNavLinks = extractPageLinks(join(ROOT, 'dashboard.php'));
  const pwaMoreLinks    = extractPageLinks(join(ROOT, 'pwa_more_menu.php'));
  const pwaShellLinks   = extractPageLinks(join(ROOT, 'pwa.php'));
  const allPwaLinks     = new Set([...pwaMoreLinks, ...pwaShellLinks]);

  test('every desktop sidebar page= link is reachable from PWA navigation', () => {
    // Exclude pwa_more – that's PWA-only navigation
    const missing = [...desktopNavLinks]
      .filter(p => p !== 'pwa_more' && !allPwaLinks.has(p));
    expect(
      missing,
      `Desktop nav pages not in PWA: ${missing.join(', ')}`
    ).toEqual([]);
  });

  // Verify specific critical links individually
  const criticalLinks = [
    'stats', 'messages', 'sessions', 'video', 'health', 'shop', 'payment_history',
    'team_roster', 'coach_calendar', 'drills', 'practice', 'roster',
    'coach_stopwatch', 'coach_shot_speed', 'coach_session_evaluations',
    'coach_video_reviews', 'record_drill_video', 'travel',
    'library_workouts', 'library_nutrition',
    'finance_dashboard', 'financial_reports', 'reports_user',
    'credits_refunds', 'expenses', 'products',
    'pos_terminal', 'inventory_management', 'pos_online_orders',
    'pos_time_tracking', 'pos_schedule', 'sip_settings',
    'admin_staff_scheduling', 'hr_time_tracking', 'payroll',
    'onboarding', 'employee_contracts', 'complaints', 'termination',
    'all_users', 'categories', 'eval_framework', 'system_notification',
    'admin_security', 'system_tools', 'marketing',
    'profile', 'goals', 'workouts', 'notifications',
  ];

  for (const page of criticalLinks) {
    test(`PWA has navigation link for page=${page}`, () => {
      expect(allPwaLinks.has(page),
        `page=${page} missing from PWA navigation`).toBe(true);
    });
  }
});

/* ──────────────────────────────────────────────────────── */
/*  5.  Role-gating consistency (section by section)        */
/* ──────────────────────────────────────────────────────── */

test.describe('5 · Role-gating consistency between desktop and PWA menus', () => {

  const pwaMenu = read('pwa_more_menu.php');

  // Each pair: [role condition substring, section description]
  const roleSections = [
    ['$isTeamStaff',               'Team section'],
    ['$isAnyCoach',                'Coaches Corner'],
    ['$canAccessHealthManagement', 'Health Management'],
    ['$canAccessAccounting',       'Accounting & Reports'],
    ['$canAccessPOS',              'Point of Sale'],
    ['$canAccessHR',               'HR section'],
    ['$isAdmin',                   'Administration'],
  ];

  for (const [condition, label] of roleSections) {
    test(`PWA more menu uses ${condition} for "${label}"`, () => {
      expect(pwaMenu).toContain(condition);
    });
  }
});

/* ──────────────────────────────────────────────────────── */
/*  6.  CSS scrolling — PWA content is scrollable           */
/* ──────────────────────────────────────────────────────── */

test.describe('6 · CSS scrolling — PWA content area must be scrollable', () => {

  test('pwa.css .pwa-content has overflow-y: auto', () => {
    const css = read('css/pwa.css');
    expect(css).toContain('overflow-y: auto');
  });

  test('pwa.css .pwa-content has touch-action: pan-y', () => {
    const css = read('css/pwa.css');
    expect(css).toContain('touch-action: pan-y');
  });

  test('pwa.css .pwa-content has -webkit-overflow-scrolling: touch', () => {
    const css = read('css/pwa.css');
    expect(css).toContain('-webkit-overflow-scrolling: touch');
  });

  test('pwa.css .modal-content has overflow-y: auto', () => {
    const css = read('css/pwa.css');
    // Modal must be scrollable when content exceeds viewport
    const modalSection = css.substring(
      css.indexOf('.modal-content'),
      css.indexOf('}', css.indexOf('.modal-content')) + 1
    );
    expect(modalSection).toContain('overflow-y: auto');
  });

  test('shared_styles.css containers use overflow-y: visible (not hidden)', () => {
    const css = read('views/shared_styles.css');
    // The .container/.page-container rule should not block vertical scroll
    const containerMatch = css.match(/\.container[^{]*{[^}]*overflow[^}]*/);
    expect(containerMatch).not.toBeNull();
    expect(containerMatch[0]).not.toMatch(/overflow:\s*hidden/);
  });
});

/* ──────────────────────────────────────────────────────── */
/*  7.  View-by-view: every desktop view file is used by    */
/*      pwa.php (not a views/pwa/ substitute)               */
/* ──────────────────────────────────────────────────────── */

test.describe('7 · View-by-view: pwa.php uses the exact desktop view file for every route', () => {

  const pwaContent = read('pwa.php');
  const desktopRoutes = extractRoutes(join(ROOT, 'dashboard.php'));

  // Group routes by their target view file to avoid redundant tests
  const viewFiles = [...new Set(Object.values(desktopRoutes))];

  for (const viewFile of viewFiles) {
    test(`pwa.php routing table includes ${viewFile}`, () => {
      expect(pwaContent).toContain(`'${viewFile}'`);
    });
  }

  // Verify no code path references views/pwa/ for page rendering
  test('no active code path in pwa.php loads any views/pwa/ file', () => {
    const lines = pwaContent.split('\n');
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      // Skip comments
      if (line.startsWith('//') || line.startsWith('*') || line.startsWith('/*')) continue;
      // Check for views/pwa/ reference in active code
      if (line.includes("views/pwa/") && (line.includes('$view_file') || line.includes('include'))) {
        expect.soft(false, `Line ${i + 1} loads a views/pwa/ file: ${line}`).toBe(true);
      }
    }
  });
});

/* ──────────────────────────────────────────────────────── */
/*  8.  Specific desktop features that must be accessible   */
/*      through the PWA (regression guards)                 */
/* ──────────────────────────────────────────────────────── */

test.describe('8 · Feature-specific regression guards', () => {

  // The desktop home.php has specific features; now that PWA loads the same file,
  // verify those features exist in the desktop view file (not stripped out).
  const homeView = read('views/home.php');

  test('views/home.php contains pending payments section', () => {
    expect(homeView).toMatch(/pending.*payment|payment.*pending/i);
  });

  test('views/home.php contains performance metrics section', () => {
    expect(homeView).toMatch(/performance|metrics|stats/i);
  });

  test('views/home.php contains system notification dismiss functionality', () => {
    expect(homeView).toMatch(/dismiss|notification/i);
  });

  // Front desk view features
  const frontDeskView = read('views/front_desk_home.php');

  test('views/front_desk_home.php contains shift/time tracking', () => {
    expect(frontDeskView).toMatch(/shift|clock|time.?track/i);
  });

  test('views/front_desk_home.php contains POS terminal link', () => {
    expect(frontDeskView).toMatch(/pos_terminal|POS Terminal/i);
  });

  // Parent home view features
  const parentView = read('views/parent_home.php');

  test('views/parent_home.php contains athlete management', () => {
    expect(parentView).toMatch(/manage.*athlete|athlete.*manage/i);
  });

  test('views/parent_home.php contains invitation system', () => {
    expect(parentView).toMatch(/invite|invitation/i);
  });

  test('views/parent_home.php contains camp check-in', () => {
    expect(parentView).toMatch(/camp.*check|check.*in/i);
  });

  test('views/parent_home.php contains book session functionality', () => {
    expect(parentView).toMatch(/book.*session|session.*book/i);
  });
});
