import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { join } from 'path';

/**
 * PWA Desktop Route Parity Tests
 *
 * Validates that pwa.php and pwa_tablet.php routing tables and navigation
 * menus stay in sync with the desktop dashboard.php.
 */

const ROOT = join(__dirname, '..');

/** Extract route keys from an $allowed_pages PHP array in a file */
function extractRouteKeys(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  // Match lines like:   'route_name'  => 'views/something.php',
  const regex = /^\s*'([a-z_]+)'\s*=>\s*'views\//gm;
  const keys = new Set();
  let match;
  while ((match = regex.exec(content)) !== null) {
    keys.add(match[1]);
  }
  return keys;
}

/** Extract role variable definitions ($isHR, $canAccessHR, etc.) from a PHP file */
function extractRoleVariables(filePath) {
  const content = readFileSync(filePath, 'utf-8');
  const regex = /\$(is[A-Z]\w+|canAccess\w+)\s*=/g;
  const vars = new Set();
  let match;
  while ((match = regex.exec(content)) !== null) {
    vars.add(match[1]);
  }
  return vars;
}

test.describe('PWA Route Parity with Desktop', () => {

  test('pwa.php contains all routes from dashboard.php', () => {
    const dashboardRoutes = extractRouteKeys(join(ROOT, 'dashboard.php'));
    const pwaRoutes = extractRouteKeys(join(ROOT, 'pwa.php'));

    const missing = [...dashboardRoutes].filter(r => !pwaRoutes.has(r));
    expect(missing, `pwa.php is missing routes: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa_tablet.php contains all routes from dashboard.php', () => {
    const dashboardRoutes = extractRouteKeys(join(ROOT, 'dashboard.php'));
    const tabletRoutes = extractRouteKeys(join(ROOT, 'pwa_tablet.php'));

    const missing = [...dashboardRoutes].filter(r => !tabletRoutes.has(r));
    expect(missing, `pwa_tablet.php is missing routes: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa.php defines the same role variables as dashboard.php', () => {
    const dashboardVars = extractRoleVariables(join(ROOT, 'dashboard.php'));
    const pwaVars = extractRoleVariables(join(ROOT, 'pwa.php'));

    const missing = [...dashboardVars].filter(v => !pwaVars.has(v));
    expect(missing, `pwa.php is missing role variables: ${missing.join(', ')}`).toEqual([]);
  });

  test('pwa_tablet.php defines the same role variables as dashboard.php', () => {
    const dashboardVars = extractRoleVariables(join(ROOT, 'dashboard.php'));
    const tabletVars = extractRoleVariables(join(ROOT, 'pwa_tablet.php'));

    const missing = [...dashboardVars].filter(v => !tabletVars.has(v));
    expect(missing, `pwa_tablet.php is missing role variables: ${missing.join(', ')}`).toEqual([]);
  });

});

test.describe('PWA Navigation Completeness', () => {

  test('pwa_more_menu.php includes inventory_management link', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    expect(content).toContain('page=inventory_management');
  });

  test('pwa_more_menu.php includes reports_user link', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    expect(content).toContain('page=reports_user');
  });

  test('pwa_more_menu.php includes sip_settings link', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    expect(content).toContain('page=sip_settings');
  });

  test('pwa_tablet.php sidebar includes inventory_management link', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    expect(content).toContain('page=inventory_management');
  });

  test('pwa_tablet.php sidebar includes reports_user link', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    expect(content).toContain('page=reports_user');
  });

  test('pwa_tablet.php sidebar includes sip_settings link', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    expect(content).toContain('page=sip_settings');
  });

  test('pwa_more_menu.php uses canAccessAccounting for finance section', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    // Should use $canAccessAccounting, not just $isAdmin for the Accounting section
    expect(content).toContain('$canAccessAccounting');
  });

  test('pwa_more_menu.php uses canAccessHR for HR section', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    expect(content).toContain('$canAccessHR');
  });

  test('pwa_tablet.php uses canAccessAccounting for accounting section', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    expect(content).toContain('$canAccessAccounting');
  });

  test('pwa_tablet.php uses canAccessHR for HR section', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    expect(content).toContain('$canAccessHR');
  });

});

test.describe('PWA Navigation Label Parity with Desktop', () => {

  /** Extract nav link labels from dashboard.php sidebar */
  function extractNavLabels(filePath) {
    const content = readFileSync(filePath, 'utf-8');
    // Match text after icon class like: <i class="..."></i> Label Text
    const regex = /<i class="[^"]*"><\/i>\s*([^<\n]+)/g;
    const labels = new Set();
    let match;
    while ((match = regex.exec(content)) !== null) {
      labels.add(match[1].trim());
    }
    return labels;
  }

  test('pwa_more_menu.php uses "Video Reviews" not "Coach Video Reviews"', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    expect(content).toContain('Video Reviews');
    expect(content).not.toContain('Coach Video Reviews');
  });

  test('pwa_tablet.php uses "Video Reviews" not "Coach Video Reviews"', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    expect(content).toContain('Video Reviews');
    expect(content).not.toContain('Coach Video Reviews');
  });

  test('pwa_more_menu.php uses "Financial Reports Hub" matching desktop', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    expect(content).toContain('Financial Reports Hub');
  });

  test('pwa_more_menu.php Health section label matches desktop', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    // Should say "Health" not "Health Management"
    expect(content).not.toContain('>Health Management<');
  });

  test('pwa_more_menu.php Team section uses "Roster" not "Team Roster"', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    // The team section link should say "Roster" (matching desktop) not "Team Roster"
    const teamSectionMatch = content.match(/page=team_roster[^>]*>[\s\S]*?<\/a>/);
    expect(teamSectionMatch).toBeTruthy();
    expect(teamSectionMatch[0]).not.toContain('Team Roster');
  });

  test('pwa_more_menu.php has Company Directory in Account section for staff', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    // Company Directory should be in Account section (after isStaff check)
    expect(content).toContain('$isStaff');
    expect(content).toContain('Company Directory');
  });

  test('pwa_more_menu.php Coaches Corner order matches desktop', () => {
    const content = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');
    // Desktop order: Stopwatch comes before Session Evaluations
    const stopwatchIdx = content.indexOf('page=coach_stopwatch');
    const evalIdx = content.indexOf('page=coach_session_evaluations');
    expect(stopwatchIdx).toBeGreaterThan(-1);
    expect(evalIdx).toBeGreaterThan(-1);
    expect(stopwatchIdx).toBeLessThan(evalIdx);
  });

  test('pwa_tablet.php Video nav active state includes record_video and video_review_detail', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    // The video link active check should include both record_video and video_review_detail
    const videoLine = content.match(/page=video".*?in_array\(\$page,\s*\[([^\]]+)\]/);
    expect(videoLine).toBeTruthy();
    expect(videoLine[1]).toContain('record_video');
    expect(videoLine[1]).toContain('video_review_detail');
  });

});
