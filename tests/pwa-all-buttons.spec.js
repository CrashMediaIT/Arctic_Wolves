/**
 * PWA Comprehensive Button Handler Tests
 *
 * Validates that ALL PWA views have properly functioning onclick handlers:
 * - Every onclick-referenced function is defined in the same file
 * - No implicit 'event' variable usage in named functions
 * - No form submissions to non-existent process files
 * - No dashboard.php references in links or fetch URLs
 * - CSRF tokens present where needed
 *
 * This covers all 147 PWA view files comprehensively.
 */

const { test, expect } = require('@playwright/test');
const { readFileSync, existsSync, readdirSync } = require('fs');
const { join } = require('path');

const ROOT = join(__dirname, '..');
const PWA_DIR = join(ROOT, 'views', 'pwa');

function readFile(name) {
  return readFileSync(join(ROOT, name), 'utf-8');
}

function readPwaFile(name) {
  return readFileSync(join(PWA_DIR, name), 'utf-8');
}

function pwaFileExists(name) {
  return existsSync(join(PWA_DIR, name));
}

// Get all PWA view files
const allPwaFiles = readdirSync(PWA_DIR).filter(f => f.endsWith('.php'));

// ── SHARED UTILITIES ─────────────────────────────────────────────────

const BUILTIN_SKIP_LIST = [
  'event', 'this', 'if', 'return', 'confirm', 'alert', 'window', 'document',
  'location', 'history', 'parseInt', 'parseFloat', 'encodeURIComponent',
  'decodeURIComponent', 'String', 'Number', 'JSON', 'Array', 'Object',
  'console', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval',
  'showToast', 'showConfirmModal', 'showPromptModal', 'persistToast',
  'showNotification', 'mShowToast',
  'stopPropagation', 'preventDefault',
  // DOM methods
  'closest', 'querySelector', 'querySelectorAll', 'getAttribute', 'setAttribute',
  'classList', 'remove', 'appendChild', 'insertBefore', 'replaceChild',
  'getElementById', 'getElementsByClassName', 'getElementsByTagName',
  'toggle', 'contains', 'add',
  // PHP functions that appear inside <?= ?> within onclick attributes
  'htmlspecialchars', 'json_encode', 'urlencode', 'intval', 'number_format',
  'date', 'time', 'trim', 'strtolower', 'strtoupper', 'ucfirst', 'ucwords',
  'isset', 'empty', 'is_null', 'is_array', 'is_string', 'is_int', 'is_float',
  'count', 'strlen', 'substr', 'strpos', 'str_replace', 'preg_replace',
  'nl2br', 'sprintf', 'round', 'floor', 'ceil', 'abs', 'max', 'min',
  'array_key_exists', 'in_array', 'array_merge', 'array_map',
  // Math/Number
  'Math', 'Date', 'Error', 'Promise', 'Map', 'Set', 'RegExp',
  'fetch', 'FormData', 'URLSearchParams', 'Blob',
  // Keywords
  'new', 'typeof', 'void', 'delete', 'async', 'await',
  'true', 'false', 'null', 'undefined',
];

function extractOnclickFunctions(content) {
  const matches = content.matchAll(/onclick="([^"]+)"/g);
  const fns = new Set();
  for (const m of matches) {
    const calls = m[1].matchAll(/(?<![.\w])([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/g);
    for (const c of calls) {
      const fn = c[1];
      if (!BUILTIN_SKIP_LIST.includes(fn)) {
        fns.add(fn);
      }
    }
  }
  // Also check single-quoted onclick
  const matchesSingle = content.matchAll(/onclick='([^']+)'/g);
  for (const m of matchesSingle) {
    const calls = m[1].matchAll(/(?<![.\w])([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/g);
    for (const c of calls) {
      const fn = c[1];
      if (!BUILTIN_SKIP_LIST.includes(fn)) {
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

// ── ALL PWA VIEWS: ONCLICK HANDLER VERIFICATION ─────────────────────

test.describe('All PWA views have onclick handlers defined', () => {

  // Get all files that have onclick handlers
  const filesWithOnclick = allPwaFiles.filter(f => {
    const filepath = join(PWA_DIR, f);
    const content = readFileSync(filepath, 'utf-8');
    // Skip files that just include other files
    if (content.trim().match(/^<\?php\s+include\s+/)) return false;
    return content.includes('onclick=');
  });

  for (const file of filesWithOnclick) {
    test(`${file} all onclick handlers defined`, () => {
      verifyHandlers(file);
    });
  }
});

// ── NO IMPLICIT EVENT USAGE ─────────────────────────────────────────

test.describe('No implicit event global usage in any PWA view', () => {

  test('no named function uses event.xxx without event parameter', () => {
    for (const file of allPwaFiles) {
      const filepath = join(PWA_DIR, file);
      const content = readFileSync(filepath, 'utf-8');
      if (content.trim().match(/^<\?php\s+include\s+/)) continue;

      const scriptBlocks = content.match(/<script[^>]*>([\s\S]*?)<\/script>/gi) || [];
      for (const script of scriptBlocks) {
        // Check named functions (not anonymous callbacks in addEventListener)
        const namedFuncs = script.matchAll(/function\s+(\w+)\s*\(([^)]*)\)\s*\{/g);
        for (const m of namedFuncs) {
          const name = m[1];
          const params = m[2];
          // If event/e/evt is a parameter, skip
          if (/\bevent\b|\be\b|\bevt\b/.test(params)) continue;

          // Extract approximate function body
          const startIdx = m.index + m[0].length;
          let depth = 1;
          let endIdx = startIdx;
          const scriptContent = script;
          while (endIdx < scriptContent.length && depth > 0) {
            if (scriptContent[endIdx] === '{') depth++;
            else if (scriptContent[endIdx] === '}') depth--;
            endIdx++;
          }
          const body = scriptContent.substring(startIdx, endIdx - 1);

          // Skip if event.xxx is inside a nested addEventListener callback
          const nestedCallback = /addEventListener\s*\([^)]*function\s*\([^)]*event/;
          if (nestedCallback.test(body)) continue;

          // Check for implicit event usage
          const implicitEvent = /\bevent\.(currentTarget|target|preventDefault|stopPropagation)/.test(body);
          expect(implicitEvent, `${file}: function ${name}() uses implicit event`).toBe(false);
        }
      }
    }
  });
});

// ── NO REFERENCES TO NON-EXISTENT PROCESS FILES ─────────────────────

test.describe('All process file references exist', () => {

  test('no form action references non-existent process file', () => {
    for (const file of allPwaFiles) {
      const filepath = join(PWA_DIR, file);
      const content = readFileSync(filepath, 'utf-8');
      if (content.trim().match(/^<\?php\s+include\s+/)) continue;

      // Find form action="process_xxx.php" references
      const formActions = content.matchAll(/action\s*=\s*["'](process_[^"'?]+\.php)/g);
      for (const m of formActions) {
        const endpoint = m[1];
        expect(existsSync(join(ROOT, endpoint)),
          `${file}: form action references ${endpoint} which does not exist`).toBe(true);
      }
    }
  });

  test('no fetch() references non-existent process file', () => {
    for (const file of allPwaFiles) {
      const filepath = join(PWA_DIR, file);
      const content = readFileSync(filepath, 'utf-8');
      if (content.trim().match(/^<\?php\s+include\s+/)) continue;

      // Find fetch('process_xxx.php') references
      const fetchCalls = content.matchAll(/fetch\s*\(\s*['"](?:\.\/)?(?:\.\.\/)?([^'"?]*process_[^'"?]+\.php)/g);
      for (const m of fetchCalls) {
        const endpoint = m[1];
        expect(existsSync(join(ROOT, endpoint)),
          `${file}: fetch references ${endpoint} which does not exist`).toBe(true);
      }
    }
  });
});

// ── NO DASHBOARD.PHP REFERENCES ─────────────────────────────────────

test.describe('No dashboard.php references in PWA views', () => {

  test('no view uses dashboard.php in links or fetch (except gameplan rewriter)', () => {
    for (const file of allPwaFiles) {
      const filepath = join(PWA_DIR, file);
      const content = readFileSync(filepath, 'utf-8');
      if (content.trim().match(/^<\?php\s+include\s+/)) continue;

      // gameplan.php has a known link rewriter - skip it
      if (file === 'gameplan.php') continue;

      // Check for dashboard.php in href, action, or fetch URLs
      const hasDashboardLink = /href\s*=\s*["']dashboard\.php/.test(content);
      const hasDashboardFetch = /fetch\s*\(\s*['"]dashboard\.php/.test(content);
      const hasDashboardAction = /action\s*=\s*["']dashboard\.php/.test(content);

      expect(hasDashboardLink, `${file}: has href to dashboard.php`).toBe(false);
      expect(hasDashboardFetch, `${file}: has fetch to dashboard.php`).toBe(false);
      expect(hasDashboardAction, `${file}: has form action to dashboard.php`).toBe(false);
    }
  });
});

// ── CSRF PROTECTION ─────────────────────────────────────────────────

test.describe('CSRF protection in all PWA views', () => {

  test('every POST form has CSRF protection', () => {
    for (const file of allPwaFiles) {
      const filepath = join(PWA_DIR, file);
      const content = readFileSync(filepath, 'utf-8');
      if (content.trim().match(/^<\?php\s+include\s+/)) continue;

      // Find all POST forms
      const postForms = content.match(/<form[^>]*method\s*=\s*["']POST["'][^>]*>/gi) || [];
      if (postForms.length > 0) {
        // Must have at least one CSRF token mechanism
        const hasCSRF = content.includes('csrfTokenInput()') ||
                       content.includes('csrf_token') ||
                       content.includes('csrf-token');
        expect(hasCSRF, `${file}: has POST forms but no CSRF protection`).toBe(true);
      }

      // Check fetch POST calls also have CSRF
      // (some fetch calls send FormData from a form that already has csrfTokenInput())
      const fetchPosts = content.match(/method\s*:\s*['"]POST['"]/g) || [];
      if (fetchPosts.length > 0) {
        const hasCSRF = content.includes('csrf_token') || content.includes('csrf-token') || content.includes('csrfTokenInput');
        expect(hasCSRF, `${file}: has fetch POST calls but no CSRF protection`).toBe(true);
      }
    }
  });
});

// ── NOTIFICATIONS SPECIFIC TESTS ────────────────────────────────────

test.describe('Notifications PWA view', () => {

  test('mark_all_read is handled inline (not posted to process file)', () => {
    const content = readPwaFile('notifications.php');
    // Should handle mark_all_read via GET parameter
    expect(content).toContain("isset($_GET['mark_all_read'])");
    // Should NOT reference process_notifications.php
    expect(content).not.toContain('process_notifications.php');
  });

  test('mark_read is handled inline', () => {
    const content = readPwaFile('notifications.php');
    expect(content).toContain("isset($_GET['mark_read'])");
  });

  test('mark_all_read updates the database', () => {
    const content = readPwaFile('notifications.php');
    // After mark_all_read check, should update notifications table
    const markAllSection = content.substring(
      content.indexOf("mark_all_read"),
      content.indexOf("mark_read")
    );
    expect(markAllSection).toContain('UPDATE notifications');
    expect(markAllSection).toContain('read_status');
  });

  test('mark_all_read redirects back to notifications', () => {
    const content = readPwaFile('notifications.php');
    const markAllSection = content.substring(
      content.indexOf("mark_all_read"),
      content.indexOf("mark_read")
    );
    expect(markAllSection).toContain('Location: pwa.php?page=notifications');
  });

  test('mark all read UI uses link (not form to non-existent file)', () => {
    const content = readPwaFile('notifications.php');
    // Should have a link/button that navigates, not a form to process_notifications.php
    expect(content).toContain('mark_all_read=1');
    expect(content).not.toContain('action="process_notifications.php"');
  });
});

// ── COMPREHENSIVE PER-VIEW HANDLER TESTS ────────────────────────────
// Views NOT already covered by pwa-hr-admin.spec.js or other test files

test.describe('Sessions & Booking onclick handlers', () => {
  test('sessions.php all onclick handlers defined', () => { verifyHandlers('sessions.php'); });
  test('session_detail.php all onclick handlers defined', () => { verifyHandlers('session_detail.php'); });
  test('session_templates.php all onclick handlers defined', () => { verifyHandlers('session_templates.php'); });
  test('session_history.php all onclick handlers defined', () => { verifyHandlers('session_history.php'); });
  test('create_session.php all onclick handlers defined', () => { if (pwaFileExists('create_session.php')) verifyHandlers('create_session.php'); });
  test('session_evaluation_form.php all onclick handlers defined', () => { if (pwaFileExists('session_evaluation_form.php')) verifyHandlers('session_evaluation_form.php'); });
});

test.describe('Coach view onclick handlers', () => {
  test('coach_calendar.php all onclick handlers defined', () => { verifyHandlers('coach_calendar.php'); });
  test('coach_evaluations.php all onclick handlers defined', () => { if (pwaFileExists('coach_evaluations.php')) verifyHandlers('coach_evaluations.php'); });
  test('coach_goals.php all onclick handlers defined', () => { verifyHandlers('coach_goals.php'); });
  test('coach_shot_speed.php all onclick handlers defined', () => { verifyHandlers('coach_shot_speed.php'); });
  test('coach_stopwatch.php all onclick handlers defined', () => { verifyHandlers('coach_stopwatch.php'); });
  test('coach_video_reviews.php all onclick handlers defined', () => { if (pwaFileExists('coach_video_reviews.php')) verifyHandlers('coach_video_reviews.php'); });
  test('coach_pending_reviews.php all onclick handlers defined', () => { if (pwaFileExists('coach_pending_reviews.php')) verifyHandlers('coach_pending_reviews.php'); });
});

test.describe('Athlete view onclick handlers', () => {
  test('athletes.php all onclick handlers defined', () => { if (pwaFileExists('athletes.php')) verifyHandlers('athletes.php'); });
  test('athlete_detail.php all onclick handlers defined', () => { if (pwaFileExists('athlete_detail.php')) verifyHandlers('athlete_detail.php'); });
  test('athlete_evaluations.php all onclick handlers defined', () => { verifyHandlers('athlete_evaluations.php'); });
  test('athlete_goals.php all onclick handlers defined', () => { verifyHandlers('athlete_goals.php'); });
  test('manage_athletes.php all onclick handlers defined', () => { if (pwaFileExists('manage_athletes.php')) verifyHandlers('manage_athletes.php'); });
});

test.describe('Goals & Stats onclick handlers', () => {
  test('goals.php all onclick handlers defined', () => { if (pwaFileExists('goals.php')) verifyHandlers('goals.php'); });
  test('stats.php all onclick handlers defined', () => { verifyHandlers('stats.php'); });
  test('evaluations_goals.php all onclick handlers defined', () => { verifyHandlers('evaluations_goals.php'); });
  test('evaluations_skills.php all onclick handlers defined', () => { verifyHandlers('evaluations_skills.php'); });
});

test.describe('Practice & Drills onclick handlers', () => {
  test('practice.php all onclick handlers defined', () => { verifyHandlers('practice.php'); });
  test('drills.php all onclick handlers defined', () => { verifyHandlers('drills.php'); });
  test('drill_library.php all onclick handlers defined', () => { if (pwaFileExists('drill_library.php')) verifyHandlers('drill_library.php'); });
  test('create_drill.php all onclick handlers defined', () => { if (pwaFileExists('create_drill.php')) verifyHandlers('create_drill.php'); });
  test('view_drill.php all onclick handlers defined', () => { if (pwaFileExists('view_drill.php')) verifyHandlers('view_drill.php'); });
  test('view_practice_plan.php all onclick handlers defined', () => { if (pwaFileExists('view_practice_plan.php')) verifyHandlers('view_practice_plan.php'); });
});

test.describe('Video onclick handlers', () => {
  test('video.php all onclick handlers defined', () => { verifyHandlers('video.php'); });
  test('video_review_detail.php all onclick handlers defined', () => { if (pwaFileExists('video_review_detail.php')) verifyHandlers('video_review_detail.php'); });
  test('video_record_drill.php all onclick handlers defined', () => { if (pwaFileExists('video_record_drill.php')) verifyHandlers('video_record_drill.php'); });
  test('record_video.php all onclick handlers defined', () => { if (pwaFileExists('record_video.php')) verifyHandlers('record_video.php'); });
  test('drill_review.php all onclick handlers defined', () => { if (pwaFileExists('drill_review.php')) verifyHandlers('drill_review.php'); });
});

test.describe('Health & Wellness onclick handlers', () => {
  test('library_workouts.php all onclick handlers defined', () => { verifyHandlers('library_workouts.php'); });
  test('library_nutrition.php all onclick handlers defined', () => { verifyHandlers('library_nutrition.php'); });
  test('workouts.php all onclick handlers defined', () => { verifyHandlers('workouts.php'); });
  test('nutrition.php all onclick handlers defined', () => { if (pwaFileExists('nutrition.php')) verifyHandlers('nutrition.php'); });
  test('strength_conditioning.php all onclick handlers defined', () => { if (pwaFileExists('strength_conditioning.php')) verifyHandlers('strength_conditioning.php'); });
});

test.describe('Finance & Billing onclick handlers', () => {
  test('finance_dashboard.php all onclick handlers defined', () => { verifyHandlers('finance_dashboard.php'); });
  test('payment_history.php all onclick handlers defined', () => { verifyHandlers('payment_history.php'); });
  test('accounts_payable.php all onclick handlers defined', () => { if (pwaFileExists('accounts_payable.php')) verifyHandlers('accounts_payable.php'); });
  test('refunds.php all onclick handlers defined', () => { if (pwaFileExists('refunds.php')) verifyHandlers('refunds.php'); });
  test('billing_dashboard.php all onclick handlers defined', () => { if (pwaFileExists('billing_dashboard.php')) verifyHandlers('billing_dashboard.php'); });
});

test.describe('Shop & Inventory onclick handlers', () => {
  test('shop.php all onclick handlers defined', () => { verifyHandlers('shop.php'); });
  test('shop_orders.php all onclick handlers defined', () => { verifyHandlers('shop_orders.php'); });
  test('inventory_management.php all onclick handlers defined', () => { verifyHandlers('inventory_management.php'); });
  test('merchandise_products.php all onclick handlers defined', () => { if (pwaFileExists('merchandise_products.php')) verifyHandlers('merchandise_products.php'); });
});

test.describe('Scheduling & Reports onclick handlers', () => {
  test('schedules.php all onclick handlers defined', () => { verifyHandlers('schedules.php'); });
  test('scheduled_reports.php all onclick handlers defined', () => { verifyHandlers('scheduled_reports.php'); });
  test('reports.php all onclick handlers defined', () => { if (pwaFileExists('reports.php')) verifyHandlers('reports.php'); });
  test('reports_user.php all onclick handlers defined', () => { if (pwaFileExists('reports_user.php')) verifyHandlers('reports_user.php'); });
  test('reports_athlete.php all onclick handlers defined', () => { if (pwaFileExists('reports_athlete.php')) verifyHandlers('reports_athlete.php'); });
});

test.describe('Communication onclick handlers', () => {
  test('messages.php all onclick handlers defined', () => { if (pwaFileExists('messages.php')) verifyHandlers('messages.php'); });
  test('notifications.php all onclick handlers defined', () => { if (pwaFileExists('notifications.php')) verifyHandlers('notifications.php'); });
  test('sip_settings.php all onclick handlers defined', () => { verifyHandlers('sip_settings.php'); });
  test('phone_directory.php all onclick handlers defined', () => { if (pwaFileExists('phone_directory.php')) verifyHandlers('phone_directory.php'); });
});

test.describe('Travel & Mileage onclick handlers', () => {
  test('travel.php all onclick handlers defined', () => { verifyHandlers('travel.php'); });
  test('mileage_tracker.php all onclick handlers defined', () => { if (pwaFileExists('mileage_tracker.php')) verifyHandlers('mileage_tracker.php'); });
});

test.describe('Development & Training onclick handlers', () => {
  test('personal_development.php all onclick handlers defined', () => { if (pwaFileExists('personal_development.php')) verifyHandlers('personal_development.php'); });
  test('development_programs.php all onclick handlers defined', () => { if (pwaFileExists('development_programs.php')) verifyHandlers('development_programs.php'); });
  test('personal_drills.php all onclick handlers defined', () => { if (pwaFileExists('personal_drills.php')) verifyHandlers('personal_drills.php'); });
});

test.describe('Roster & Team onclick handlers', () => {
  test('roster.php all onclick handlers defined', () => { if (pwaFileExists('roster.php')) verifyHandlers('roster.php'); });
  test('team_roster.php all onclick handlers defined', () => { verifyHandlers('team_roster.php'); });
  test('health_coach_roster.php all onclick handlers defined', () => { if (pwaFileExists('health_coach_roster.php')) verifyHandlers('health_coach_roster.php'); });
});

test.describe('Staff & Time tracking onclick handlers', () => {
  test('staff_time_history.php all onclick handlers defined', () => { verifyHandlers('staff_time_history.php'); });
  test('pos_time_tracking.php all onclick handlers defined', () => { if (pwaFileExists('pos_time_tracking.php')) verifyHandlers('pos_time_tracking.php'); });
  test('pos_transactions.php all onclick handlers defined', () => { if (pwaFileExists('pos_transactions.php')) verifyHandlers('pos_transactions.php'); });
});

test.describe('Settings & Misc onclick handlers', () => {
  test('settings.php all onclick handlers defined', () => { verifyHandlers('settings.php'); });
  test('gameplan.php all onclick handlers defined', () => { if (pwaFileExists('gameplan.php')) verifyHandlers('gameplan.php'); });
  test('gameplan_settings.php all onclick handlers defined', () => { verifyHandlers('gameplan_settings.php'); });
  test('booking.php all onclick handlers defined', () => { if (pwaFileExists('booking.php')) verifyHandlers('booking.php'); });
  test('session_payment.php all onclick handlers defined', () => { if (pwaFileExists('session_payment.php')) verifyHandlers('session_payment.php'); });
  test('packages.php all onclick handlers defined', () => { if (pwaFileExists('packages.php')) verifyHandlers('packages.php'); });
  test('camp_checkin.php all onclick handlers defined', () => { if (pwaFileExists('camp_checkin.php')) verifyHandlers('camp_checkin.php'); });
});

test.describe('Admin-specific additional onclick handlers', () => {
  test('admin_age_skill.php all onclick handlers defined', () => { if (pwaFileExists('admin_age_skill.php')) verifyHandlers('admin_age_skill.php'); });
  test('admin_database_tools.php all onclick handlers defined', () => { if (pwaFileExists('admin_database_tools.php')) verifyHandlers('admin_database_tools.php'); });
  test('admin_database_backup.php all onclick handlers defined', () => { if (pwaFileExists('admin_database_backup.php')) verifyHandlers('admin_database_backup.php'); });
  test('admin_database_restore.php all onclick handlers defined', () => { if (pwaFileExists('admin_database_restore.php')) verifyHandlers('admin_database_restore.php'); });
  test('admin_email_reports.php all onclick handlers defined', () => { if (pwaFileExists('admin_email_reports.php')) verifyHandlers('admin_email_reports.php'); });
  test('admin_feature_import.php all onclick handlers defined', () => { if (pwaFileExists('admin_feature_import.php')) verifyHandlers('admin_feature_import.php'); });
  test('admin_security.php all onclick handlers defined', () => { if (pwaFileExists('admin_security.php')) verifyHandlers('admin_security.php'); });
  test('admin_system_check.php all onclick handlers defined', () => { if (pwaFileExists('admin_system_check.php')) verifyHandlers('admin_system_check.php'); });
  test('system_notification.php all onclick handlers defined', () => { if (pwaFileExists('system_notification.php')) verifyHandlers('system_notification.php'); });
  test('cron_jobs.php all onclick handlers defined', () => { if (pwaFileExists('cron_jobs.php')) verifyHandlers('cron_jobs.php'); });
  test('user_permissions.php all onclick handlers defined', () => { if (pwaFileExists('user_permissions.php')) verifyHandlers('user_permissions.php'); });
});
