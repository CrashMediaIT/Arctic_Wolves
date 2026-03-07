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

test.describe('PWA View Override and Feature Parity', () => {

  test('pwa_tablet.php has PWA view override logic like pwa.php', () => {
    const tabletContent = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    // Must have the PWA view override logic
    expect(tabletContent).toContain("views/pwa/' . $page . '.php'");
    expect(tabletContent).toContain('$pwa_view_file');
    expect(tabletContent).toContain('$skipPwaOverride');
    expect(tabletContent).toContain('file_exists');
  });

  test('pwa.php and pwa_tablet.php both skip PWA override for system_tools with tab param', () => {
    const pwaContent = readFileSync(join(ROOT, 'pwa.php'), 'utf-8');
    const tabletContent = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');

    // Both should skip PWA override when system_tools has a tab parameter
    expect(pwaContent).toContain("$page === 'system_tools' && isset($_GET['tab'])");
    expect(tabletContent).toContain("$page === 'system_tools' && isset($_GET['tab'])");
  });

  test('pwa/system_tools.php does not reference deprecated audit_log page', () => {
    const content = readFileSync(join(ROOT, 'views', 'pwa', 'system_tools.php'), 'utf-8');
    expect(content).not.toContain("'audit_log'");
    expect(content).not.toContain('page=audit_log');
  });

  test('pwa/system_tools.php includes all desktop system_tools tabs', () => {
    const content = readFileSync(join(ROOT, 'views', 'pwa', 'system_tools.php'), 'utf-8');
    const desktopTabs = [
      'settings', 'mileage', 'smtp', 'rustfs', 'docuseal', 'payments',
      'stallion', 'paperless', 'encryption', 'landing', 'updates',
      'api_keys', 'ndi_cameras', 'gameplan'
    ];
    for (const tab of desktopTabs) {
      // Check the PHP array values: 'tab' => 'tabname'
      expect(content, `Missing system_tools tab: ${tab}`).toContain(`'tab' => '${tab}'`);
    }
  });

  test('pwa/system_tools.php includes separate-page tools', () => {
    const content = readFileSync(join(ROOT, 'views', 'pwa', 'system_tools.php'), 'utf-8');
    const pages = [
      'admin_database_tools', 'admin_system_check', 'admin_database_backup',
      'admin_database_restore', 'admin_security', 'cron_jobs',
      'admin_feature_import', 'admin_theme_settings'
    ];
    for (const page of pages) {
      expect(content, `Missing tool page: ${page}`).toContain(page);
    }
  });

  test('pwa_tablet.php sidebar includes Parent Camp Check-in section', () => {
    const content = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    expect(content).toContain('$isParent');
    expect(content).toContain('page=camp_checkin');
    expect(content).toContain('Camp Check-in');
  });

  test('pwa_tablet.php and pwa_more_menu.php both have Parent section matching', () => {
    const tabletContent = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    const mobileContent = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');

    // Both should have parent check and camp_checkin link
    expect(tabletContent).toContain('$isParent');
    expect(tabletContent).toContain('page=camp_checkin');
    expect(mobileContent).toContain('$isParent');
    expect(mobileContent).toContain('page=camp_checkin');
  });

  test('pwa/video.php links to video_review_detail not video&id', () => {
    const content = readFileSync(join(ROOT, 'views', 'pwa', 'video.php'), 'utf-8');
    // Video cards should link to the detail page, not back to the video list
    expect(content).toContain('page=video_review_detail&video_id=');
    expect(content).not.toContain('page=video&id=');
  });

  test('pwa/admin_wishlist.php exists and has full CRUD', () => {
    const content = readFileSync(join(ROOT, 'views', 'pwa', 'admin_wishlist.php'), 'utf-8');
    // Should have admin-only check
    expect(content).toContain('$isAdmin');
    // Should have CRUD operations
    expect(content).toContain('create_item');
    expect(content).toContain('update_item');
    expect(content).toContain('delete_item');
    expect(content).toContain('toggle_purchased');
    // Should reference the backend process file
    expect(content).toContain('process_wishlist.php');
    // Should have mobile-native bottom sheet UI
    expect(content).toContain('m-bs-sheet');
  });

  test('orphaned audit_log.php PWA view is removed', () => {
    const { existsSync } = require('fs');
    const auditLogPath = join(ROOT, 'views', 'pwa', 'audit_log.php');
    expect(existsSync(auditLogPath)).toBe(false);
  });

  test('dashboard.php has Parent Camp Check-in section matching pwa_tablet.php and pwa_more_menu.php', () => {
    const desktopContent = readFileSync(join(ROOT, 'dashboard.php'), 'utf-8');
    const tabletContent = readFileSync(join(ROOT, 'pwa_tablet.php'), 'utf-8');
    const mobileContent = readFileSync(join(ROOT, 'pwa_more_menu.php'), 'utf-8');

    // All three should have parent check and camp_checkin link
    expect(desktopContent).toContain('$isParent');
    expect(desktopContent).toContain('page=camp_checkin');
    expect(desktopContent).toContain('Camp Check-in');
    expect(tabletContent).toContain('$isParent');
    expect(tabletContent).toContain('page=camp_checkin');
    expect(mobileContent).toContain('$isParent');
    expect(mobileContent).toContain('page=camp_checkin');
  });

  test('admin_wishlist link URLs are protocol-validated for safety', () => {
    const pwaContent = readFileSync(join(ROOT, 'views', 'pwa', 'admin_wishlist.php'), 'utf-8');
    const desktopContent = readFileSync(join(ROOT, 'views', 'admin_wishlist.php'), 'utf-8');

    // Both should validate link scheme before rendering href
    expect(pwaContent).toContain("['http', 'https', '']");
    expect(pwaContent).toContain('$isSafeLink');
    expect(desktopContent).toContain("['http', 'https', '']");
    expect(desktopContent).toContain('$isSafeLink');
  });

});
