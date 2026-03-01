import { test, expect } from '@playwright/test';
import { readFileSync, existsSync, readdirSync } from 'fs';
import { join, basename } from 'path';

/**
 * PWA ↔ Desktop Comprehensive Feature-Parity Tests
 *
 * Systematic, view-by-view verification that every PWA view includes
 * its desktop counterpart so the PWA has 100 % feature parity.
 *
 * Architecture:
 *   pwa.php  →  resolves $view_file to views/pwa/<page>.php (when it exists)
 *   views/pwa/<page>.php  →  includes views/<desktop_file>.php
 *
 * This guarantees the PWA renders the exact same markup and logic as the
 * desktop dashboard while keeping PWA view files as the routing layer.
 */

const ROOT = join(__dirname, '..');
const read = (rel) => readFileSync(join(ROOT, rel), 'utf-8');

/* ── helpers ─────────────────────────────────────────────── */

/** Extract route key → desktop view-file from an $allowed_pages array */
function extractRoutes(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const regex = /^\s*'([a-z_]+)'\s*=>\s*'(views\/[^']+)'/gm;
  const routes = {};
  let m;
  while ((m = regex.exec(content)) !== null) routes[m[1]] = m[2];
  return routes;
}

/** Extract role / permission variable definitions */
function extractRoleVars(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const regex = /\$(is[A-Z]\w+|canAccess\w+)\s*=/g;
  const vars = new Set();
  let m;
  while ((m = regex.exec(content)) !== null) vars.add(m[1]);
  return vars;
}

/** Extract all page= link targets */
function extractPageLinks(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const regex = /page=([a-z_]+)/g;
  const pages = new Set();
  let m;
  while ((m = regex.exec(content)) !== null) pages.add(m[1]);
  return pages;
}

/** Resolve the desktop view file that a PWA view ultimately includes */
function resolveDesktopInclude(pwaViewContent) {
  // Matches:  include __DIR__ . "/../some_file.php";
  //           include __DIR__ . '/../some_file.php';
  const m = pwaViewContent.match(/include\s+__DIR__\s*\.\s*["']\/\.\.\/([^"']+)["']/);
  return m ? m[1] : null;
}

/* ── data ────────────────────────────────────────────────── */
const desktopRoutes = extractRoutes(join(ROOT, 'dashboard.php'));
const pwaRoutes     = extractRoutes(join(ROOT, 'pwa.php'));
const pwaViewDir    = join(ROOT, 'views/pwa');
const pwaViewFiles  = readdirSync(pwaViewDir).filter(f => f.endsWith('.php'));

/* ══════════════════════════════════════════════════════════
   1.  Routing-table parity
   ══════════════════════════════════════════════════════════ */

test.describe('1 · Routing-table parity', () => {

  test('pwa.php has every route key from dashboard.php', () => {
    const missing = Object.keys(desktopRoutes).filter(k => !(k in pwaRoutes));
    expect(missing, `pwa.php missing routes: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa.php maps every route to the same desktop view as dashboard.php', () => {
    const mismatched = [];
    for (const [key, desktopView] of Object.entries(desktopRoutes)) {
      if (pwaRoutes[key] && pwaRoutes[key] !== desktopView) {
        mismatched.push(`${key}: dashboard=${desktopView}  pwa=${pwaRoutes[key]}`);
      }
    }
    expect(mismatched, `Mismatches:\n${mismatched.join('\n')}`).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════
   2.  PWA view override: pwa.php loads views/pwa/ when they exist
   ══════════════════════════════════════════════════════════ */

test.describe('2 · pwa.php loads PWA views when they exist', () => {

  test('pwa.php contains the views/pwa override logic', () => {
    const content = read('pwa.php');
    expect(content).toContain("views/pwa/");
    expect(content).toContain("$pwa_view_file");
    expect(content).toMatch(/if\s*\(\s*file_exists/);
  });
});

/* ══════════════════════════════════════════════════════════
   3.  SYSTEMATIC VIEW-BY-VIEW: every PWA view includes its
       desktop counterpart (the core parity guarantee)
   ══════════════════════════════════════════════════════════ */

test.describe('3 · Every PWA view includes its desktop counterpart', () => {

  for (const pwaFile of pwaViewFiles) {
    const pwaPath = join(pwaViewDir, pwaFile);

    test(`views/pwa/${pwaFile} includes a desktop view`, () => {
      const content = readFileSync(pwaPath, 'utf-8').trim();

      // Every PWA view must include a desktop view via __DIR__ . "/../..."
      const desktopFile = resolveDesktopInclude(content);
      expect(desktopFile,
        `views/pwa/${pwaFile} does not include a desktop view via __DIR__/../`
      ).not.toBeNull();

      // The included desktop file must actually exist
      const desktopPath = join(ROOT, 'views', desktopFile);
      expect(existsSync(desktopPath),
        `views/pwa/${pwaFile} includes views/${desktopFile} which does not exist`
      ).toBe(true);
    });
  }
});

/* ══════════════════════════════════════════════════════════
   4.  For every route, the PWA view chain resolves to the
       same desktop file that dashboard.php would use.
   ══════════════════════════════════════════════════════════ */

test.describe('4 · Route → PWA view → desktop view chain matches dashboard routing', () => {

  for (const [routeKey, desktopViewPath] of Object.entries(desktopRoutes)) {
    const expectedDesktopFile = desktopViewPath.replace('views/', '');
    const pwaFile = routeKey + '.php';
    const pwaPath = join(pwaViewDir, pwaFile);

    // Only test routes that have a PWA view file
    if (!existsSync(pwaPath)) continue;

    test(`route "${routeKey}" → views/pwa/${pwaFile} → views/${expectedDesktopFile}`, () => {
      const content = readFileSync(pwaPath, 'utf-8').trim();
      const resolvedDesktop = resolveDesktopInclude(content);

      expect(resolvedDesktop,
        `views/pwa/${pwaFile} does not include any desktop view`
      ).not.toBeNull();

      expect(resolvedDesktop,
        `views/pwa/${pwaFile} includes ${resolvedDesktop} but dashboard uses ${expectedDesktopFile}`
      ).toBe(expectedDesktopFile);
    });
  }
});

/* ══════════════════════════════════════════════════════════
   5.  Role / permission variable parity
   ══════════════════════════════════════════════════════════ */

test.describe('5 · Role-variable parity', () => {

  test('pwa.php defines every role variable from dashboard.php', () => {
    const desktopVars = extractRoleVars(join(ROOT, 'dashboard.php'));
    const pwaVars     = extractRoleVars(join(ROOT, 'pwa.php'));
    const missing = [...desktopVars].filter(v => !pwaVars.has(v));
    expect(missing, `pwa.php missing: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa_tablet.php defines every role variable from dashboard.php', () => {
    const desktopVars = extractRoleVars(join(ROOT, 'dashboard.php'));
    const tabletVars  = extractRoleVars(join(ROOT, 'pwa_tablet.php'));
    const missing = [...desktopVars].filter(v => !tabletVars.has(v));
    expect(missing, `pwa_tablet.php missing: ${missing.join(', ')}`).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════
   6.  Navigation completeness
   ══════════════════════════════════════════════════════════ */

test.describe('6 · Navigation completeness — every desktop sidebar link reachable in PWA', () => {

  const desktopNavLinks = extractPageLinks(join(ROOT, 'dashboard.php'));
  const pwaMoreLinks    = extractPageLinks(join(ROOT, 'pwa_more_menu.php'));
  const pwaShellLinks   = extractPageLinks(join(ROOT, 'pwa.php'));
  const allPwaLinks     = new Set([...pwaMoreLinks, ...pwaShellLinks]);

  test('every desktop sidebar page= link is reachable from PWA navigation', () => {
    const missing = [...desktopNavLinks]
      .filter(p => p !== 'pwa_more' && !allPwaLinks.has(p));
    expect(missing, `Desktop nav pages not in PWA: ${missing.join(', ')}`).toEqual([]);
  });

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

/* ══════════════════════════════════════════════════════════
   7.  Role-gating consistency between desktop and PWA menus
   ══════════════════════════════════════════════════════════ */

test.describe('7 · Role-gating consistency', () => {

  const pwaMenu = read('pwa_more_menu.php');

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

/* ══════════════════════════════════════════════════════════
   8.  CSS scrolling — PWA content area scrollable
   ══════════════════════════════════════════════════════════ */

test.describe('8 · CSS scrolling — PWA content area must be scrollable', () => {

  test('pwa.css has overflow-y: auto', () => {
    expect(read('css/pwa.css')).toContain('overflow-y: auto');
  });

  test('pwa.css has touch-action: pan-y', () => {
    expect(read('css/pwa.css')).toContain('touch-action: pan-y');
  });

  test('pwa.css has -webkit-overflow-scrolling: touch', () => {
    expect(read('css/pwa.css')).toContain('-webkit-overflow-scrolling: touch');
  });
});

/* ══════════════════════════════════════════════════════════
   9.  Feature-specific regression guards — desktop features
       exist in the view files the PWA now includes
   ══════════════════════════════════════════════════════════ */

test.describe('9 · Feature-specific regression guards', () => {

  const homeView      = read('views/home.php');
  const frontDeskView = read('views/front_desk_home.php');
  const parentView    = read('views/parent_home.php');
  const profileView   = read('views/profile.php');
  const statsView     = read('views/stats.php');
  const messagesView  = read('views/messages.php');

  // Home
  test('home.php contains pending payments section', () => {
    expect(homeView).toMatch(/pending.*payment|payment.*pending/i);
  });
  test('home.php contains performance metrics', () => {
    expect(homeView).toMatch(/performance|metrics|stats/i);
  });
  test('home.php contains notification dismiss', () => {
    expect(homeView).toMatch(/dismiss|notification/i);
  });

  // Front desk
  test('front_desk_home.php contains shift/time tracking', () => {
    expect(frontDeskView).toMatch(/shift|clock|time.?track/i);
  });
  test('front_desk_home.php contains POS terminal link', () => {
    expect(frontDeskView).toMatch(/pos_terminal|POS Terminal/i);
  });

  // Parent home
  test('parent_home.php contains athlete management', () => {
    expect(parentView).toMatch(/manage.*athlete|athlete.*manage/i);
  });
  test('parent_home.php contains invitation system', () => {
    expect(parentView).toMatch(/invite|invitation/i);
  });
  test('parent_home.php contains camp check-in', () => {
    expect(parentView).toMatch(/camp.*check|check.*in/i);
  });
  test('parent_home.php contains book session', () => {
    expect(parentView).toMatch(/book.*session|session.*book/i);
  });

  // Profile
  test('profile.php contains avatar/photo upload', () => {
    expect(profileView).toMatch(/avatar|photo|upload.*image|image.*upload/i);
  });
  test('profile.php contains password change', () => {
    expect(profileView).toMatch(/change.*password|password.*change|new.*password/i);
  });

  // Stats
  test('stats.php contains chart/analytics', () => {
    expect(statsView).toMatch(/chart|analytics|graph|canvas/i);
  });
  test('stats.php contains athlete selector for coaches', () => {
    expect(statsView).toMatch(/athlete_id|athlete.*select/i);
  });

  // Messages
  test('messages.php contains conversation threading', () => {
    expect(messagesView).toMatch(/conversation|thread|reply/i);
  });
  test('messages.php contains message sending', () => {
    expect(messagesView).toMatch(/send.*message|submit.*message|process_messages/i);
  });
});
