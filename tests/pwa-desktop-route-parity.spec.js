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
