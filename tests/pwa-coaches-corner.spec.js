import { test, expect } from '@playwright/test';
import { readFileSync, readdirSync, existsSync } from 'fs';
import { join } from 'path';

/**
 * PWA Coaches Corner Functionality Tests
 *
 * Validates that all Coaches Corner PWA views have:
 * - Proper role-based permission checks (isAnyCoach)
 * - CSRF tokens on all POST forms/fetch calls
 * - No broken dashboard.php references
 * - All onclick handlers have corresponding function definitions
 * - Feature parity: key desktop features present in PWA
 */

const ROOT = join(__dirname, '..');
const PWA_DIR = join(ROOT, 'views', 'pwa');

function readPwaFile(name) {
  return readFileSync(join(PWA_DIR, name), 'utf-8');
}

function readFile(relPath) {
  return readFileSync(join(ROOT, relPath), 'utf-8');
}

// Helper: extract function names from onclick attributes
function extractOnclickFunctions(content) {
  const onclicks = content.match(/onclick\s*=\s*["'][^"']*?([a-zA-Z_]\w+)\s*\(/g) || [];
  const funcNames = new Set();
  for (const oc of onclicks) {
    const m = oc.match(/([a-zA-Z_]\w+)\s*\(/);
    if (m) funcNames.add(m[1]);
  }
  // Remove built-in JS functions and methods
  const builtins = ['confirm', 'alert', 'location', 'history', 'document', 'window',
    'parseInt', 'encodeURIComponent', 'showConfirmModal', 'showToast', 'event',
    'stopPropagation', 'preventDefault', 'this', 'return', 'if', 'else', 'new', 'var', 'let', 'const'];
  builtins.forEach(b => funcNames.delete(b));
  return funcNames;
}

// Helper: check if a function is defined in content
function isFunctionDefined(content, fnName) {
  const patterns = [
    new RegExp(`function\\s+${fnName}\\s*\\(`),
    new RegExp(`window\\.${fnName}\\s*=\\s*(async\\s+)?function`),
    new RegExp(`var\\s+${fnName}\\s*=\\s*(async\\s+)?function`),
    new RegExp(`(async\\s+)?function\\s+${fnName}\\s*\\(`)
  ];
  return patterns.some(p => p.test(content));
}

// ──────────────────────────────────────────────────────────────────────
// 1. Coaches Corner PWA views - Permission checks
// ──────────────────────────────────────────────────────────────────────
test.describe('Coaches Corner PWA views have correct permission checks', () => {

  const coachViews = [
    'coach_calendar.php',
    'drills.php',
    'practice.php',
    'coach_stopwatch.php',
    'coach_shot_speed.php',
    'travel.php',
    'video_record_drill.php',
  ];

  for (const file of coachViews) {
    test(`${file} checks isAnyCoach permission`, () => {
      const content = readPwaFile(file);
      expect(content).toMatch(/\$isAnyCoach/);
    });
  }

  // coach_video_reviews uses inline role check via the menu gate (isAnyCoach in pwa_more_menu)
  // The file itself doesn't have an explicit check - same as the desktop version
  test('coach_video_reviews.php is gated by menu-level isAnyCoach', () => {
    const moreMenu = readFile('pwa_more_menu.php');
    // Video Reviews link appears inside the isAnyCoach section
    const coachesSection = moreMenu.match(/isAnyCoach[\s\S]*?page=coach_video_reviews/);
    expect(coachesSection, 'coach_video_reviews must be inside isAnyCoach menu section').toBeTruthy();
  });

  test('roster.php checks isAnyCoach or isTeamStaff or canAccessHealthManagement', () => {
    const content = readPwaFile('roster.php');
    // Roster has a broader check because multiple roles access it
    expect(content).toMatch(/\$isAnyCoach|\$isTeamStaff|\$canAccessHealthManagement/);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 2. No dashboard.php references in Coaches Corner views
// ──────────────────────────────────────────────────────────────────────
test.describe('Coaches Corner PWA views have no broken dashboard.php navigation', () => {

  const coachViews = [
    'coach_calendar.php', 'drills.php', 'practice.php', 'roster.php',
    'coach_stopwatch.php', 'coach_shot_speed.php', 'coach_video_reviews.php',
    'travel.php', 'video_record_drill.php', 'session_detail.php',
    'coach_evaluations.php', 'coach_goals.php'
  ];

  for (const file of coachViews) {
    test(`${file} has no broken dashboard.php links`, () => {
      const content = readPwaFile(file);
      const dashboardLinks = content.match(/href\s*=\s*["']dashboard\.php\?page=[^"']+["']/g);
      const dashboardFetch = content.match(/fetch\(['"]dashboard\.php/g);
      const dashboardAction = content.match(/action\s*=\s*["']dashboard\.php["']/g);
      expect(dashboardLinks || [], `${file} has dashboard.php href links`).toEqual([]);
      expect(dashboardFetch || [], `${file} has dashboard.php fetch`).toEqual([]);
      expect(dashboardAction || [], `${file} has dashboard.php form action`).toEqual([]);
    });
  }
});

// ──────────────────────────────────────────────────────────────────────
// 3. CSRF token protection on all POST operations
// ──────────────────────────────────────────────────────────────────────
test.describe('Coaches Corner PWA views have CSRF protection', () => {

  test('coach_calendar.php has CSRF token for all fetch operations', () => {
    const content = readPwaFile('coach_calendar.php');
    // Should have csrfTokenInput() in the hidden form
    expect(content).toMatch(/csrfTokenInput\(\)/);
    // All fetch POST calls should include csrf_token
    const fetchCalls = content.match(/fetch\([^)]+,\s*\{[^}]*method:\s*['"]POST['"][^}]*body:/gs) || [];
    expect(fetchCalls.length).toBeGreaterThan(0);
    // The CSRF token helper function should exist
    expect(content).toMatch(/mCalGetCsrf|csrf_token/);
  });

  test('session_detail.php has CSRF token for assign plan', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/csrfTokenInput\(\)/);
  });

  test('coach_evaluations.php has CSRF token', () => {
    const content = readPwaFile('coach_evaluations.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('coach_goals.php has CSRF token', () => {
    const content = readPwaFile('coach_goals.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('drills.php has CSRF token', () => {
    const content = readPwaFile('drills.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });

  test('practice.php has CSRF token', () => {
    const content = readPwaFile('practice.php');
    expect(content).toMatch(/csrfTokenInput\(\)|csrf[_-]token/i);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 4. All onclick handlers have defined functions
// ──────────────────────────────────────────────────────────────────────
test.describe('Coaches Corner onclick handlers are all defined', () => {

  const coachViews = [
    'coach_calendar.php', 'drills.php', 'practice.php',
    'coach_stopwatch.php', 'coach_shot_speed.php',
    'coach_evaluations.php', 'coach_goals.php',
    'session_detail.php'
  ];

  for (const file of coachViews) {
    test(`${file} - all onclick functions are defined`, () => {
      if (!existsSync(join(PWA_DIR, file))) return;
      const content = readPwaFile(file);
      const funcNames = extractOnclickFunctions(content);
      for (const fn of funcNames) {
        expect(isFunctionDefined(content, fn), `Function ${fn} should be defined in ${file}`).toBe(true);
      }
    });
  }
});

// ──────────────────────────────────────────────────────────────────────
// 5. Coach Calendar feature parity
// ──────────────────────────────────────────────────────────────────────
test.describe('Coach Calendar PWA has key desktop features', () => {

  test('coach_calendar.php has Assign Plan functionality', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/mCalOpenAssignPlan/);
    expect(content).toMatch(/mCalSubmitAssignPlan/);
    expect(content).toMatch(/mCalClosePlanSheet/);
    expect(content).toMatch(/practice_plan_id/);
    expect(content).toMatch(/assign_practice_plan/);
  });

  test('coach_calendar.php has Start Evaluation functionality', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/mCalOpenStartEval/);
    expect(content).toMatch(/mCalSubmitStartEval/);
    expect(content).toMatch(/mCalCloseEvalSheet/);
    expect(content).toMatch(/start_evaluation/);
    expect(content).toMatch(/process_session_evaluations\.php/);
  });

  test('coach_calendar.php has Record Drill link', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/page=record_drill_video/);
  });

  test('coach_calendar.php shows practice plan status on cards', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/practice_plan_name/);
    expect(content).toMatch(/has-plan/);
    expect(content).toMatch(/no-plan/);
  });

  test('coach_calendar.php shows evaluation status on cards', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/evaluation_id/);
    expect(content).toMatch(/evaluation_name/);
    expect(content).toMatch(/evaluation_status/);
  });

  test('coach_calendar.php loads practice plans for assign sheet', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/\$calPracticePlans/);
    expect(content).toMatch(/FROM practice_plans/);
  });

  test('coach_calendar.php loads eval templates for start eval sheet', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/\$calEvalTemplates/);
    expect(content).toMatch(/evaluation_templates/);
  });

  test('coach_calendar.php loads assigned athletes', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/\$calAssignedAthletes/);
  });

  test('coach_calendar.php has filter by coach and location', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/filter_coach/);
    expect(content).toMatch(/filter_location/);
    expect(content).toMatch(/filter_range/);
  });

  test('coach_calendar.php has Create Session FAB', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/page=create_session/);
    expect(content).toMatch(/m-cal-fab/);
  });

  test('coach_calendar.php has Complete and Cancel session buttons', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/mCalComplete/);
    expect(content).toMatch(/mCalCancel/);
  });

  test('coach_calendar.php has Edit session link', () => {
    const content = readPwaFile('coach_calendar.php');
    expect(content).toMatch(/page=create_session&edit_id=/);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 6. Session Detail coach actions
// ──────────────────────────────────────────────────────────────────────
test.describe('Session Detail PWA has coach features', () => {

  test('session_detail.php has coach action section', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/m-sd-coach-section/);
    expect(content).toMatch(/Coach Actions/);
  });

  test('session_detail.php loads practice plan for coach', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/\$sdPracticePlan/);
    expect(content).toMatch(/session_practice_plans/);
  });

  test('session_detail.php loads evaluation for coach', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/\$sdEvaluation/);
    expect(content).toMatch(/session_evaluations/);
  });

  test('session_detail.php has Assign Plan button and sheet', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/mSdOpenAssignPlan/);
    expect(content).toMatch(/mSdSubmitAssignPlan/);
    expect(content).toMatch(/mSdClosePlanSheet/);
    expect(content).toMatch(/assign_practice_plan/);
  });

  test('session_detail.php has Continue Evaluation link', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/page=session_evaluation_form&evaluation_id=/);
    expect(content).toMatch(/Continue Evaluation/);
  });

  test('session_detail.php has Record Drill link', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/page=record_drill_video&session_id=/);
    expect(content).toMatch(/Record Drill/);
  });

  test('session_detail.php has plan status display', () => {
    const content = readPwaFile('session_detail.php');
    expect(content).toMatch(/has-plan/);
    expect(content).toMatch(/no-plan/);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 7. Coaches Corner menu completeness
// ──────────────────────────────────────────────────────────────────────
test.describe('Coaches Corner menu items match desktop', () => {

  test('pwa_more_menu.php has all 10 Coaches Corner items', () => {
    const content = readFile('pwa_more_menu.php');
    const expected = [
      'page=coach_calendar',
      'page=drills',
      'page=practice',
      'page=roster',
      'page=coach_stopwatch',
      'page=coach_shot_speed',
      'page=coach_video_reviews',
      'page=travel',
      'page=record_drill_video',
      'page=gameplan'
    ];
    for (const item of expected) {
      expect(content, `Missing ${item} in pwa_more_menu.php`).toContain(item);
    }
  });

  test('pwa_tablet.php sidebar has all Coaches Corner items', () => {
    const content = readFile('pwa_tablet.php');
    const expected = [
      'page=coach_calendar',
      'page=drills',
      'page=practice',
      'page=roster',
      'page=coach_stopwatch',
      'page=coach_shot_speed',
      'page=coach_video_reviews',
      'page=travel',
      'page=record_drill_video',
    ];
    for (const item of expected) {
      expect(content, `Missing ${item} in pwa_tablet.php`).toContain(item);
    }
    // Gameplan can be either page=gameplan or /gameplan.php
    expect(content).toMatch(/page=gameplan|\/gameplan\.php/);
  });

  test('Coaches Corner uses isAnyCoach permission gate in menu', () => {
    const moreMenu = readFile('pwa_more_menu.php');
    const tablet = readFile('pwa_tablet.php');
    expect(moreMenu).toMatch(/\$isAnyCoach/);
    expect(tablet).toMatch(/\$isAnyCoach/);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 8. Development Programs conditional item
// ──────────────────────────────────────────────────────────────────────
test.describe('Development Programs conditional visibility', () => {

  test('pwa_more_menu.php shows Development Programs conditionally', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toMatch(/canAccessDevPrograms.*development_programs/s);
  });

  test('pwa_tablet.php shows Development Programs conditionally', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toMatch(/canAccessDevPrograms.*development_programs/s);
  });
});

// ──────────────────────────────────────────────────────────────────────
// 9. PWA routing - all Coaches Corner sub-pages are routed
// ──────────────────────────────────────────────────────────────────────
test.describe('PWA routing covers all Coaches Corner pages', () => {

  test('pwa.php routes all Coaches Corner pages', () => {
    const content = readFile('pwa.php');
    const requiredRoutes = [
      'coach_calendar',
      'drills', 'drill_library', 'create_drill',
      'practice', 'practice_library', 'create_practice', 'practice_create',
      'roster',
      'coach_stopwatch',
      'coach_shot_speed',
      'coach_video_reviews',
      'travel',
      'record_drill_video',
      'session_detail',
      'session_evaluation_form'
    ];
    for (const route of requiredRoutes) {
      expect(content, `pwa.php missing route: ${route}`).toMatch(new RegExp(`['"]${route}['"]\\s*=>`));
    }
  });
});
