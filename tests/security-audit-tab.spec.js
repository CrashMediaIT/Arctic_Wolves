import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - Security & Dependency Audit Tab Tests
 * Validates that the Security & Dependency Audit tab renders correctly
 * in the admin security center.
 */

const TEST_USER = {
  admin: {
    email: 'admin@test.com',
    password: 'password123'
  }
};

/**
 * Helper function to login as admin
 */
async function login(page, role = 'admin') {
  try {
    const user = TEST_USER[role];
    await page.goto('/login.php', { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard\.php/, { timeout: 10000 });
    return page.url().includes('dashboard.php');
  } catch (error) {
    console.error(`Login failed for ${role}:`, error.message);
    return false;
  }
}

test.describe('Security & Dependency Audit Tab', () => {

  test('security audit tab link is visible in the security center navigation', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    // Navigate to the security center
    await page.goto('/dashboard.php?page=admin_security', {
      waitUntil: 'networkidle'
    });

    // Check for no PHP/SQL errors
    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // Verify the Security & Dependency Audit tab link exists
    const auditTabLink = page.locator('a.page-tab[href*="tab=security_audit"]');
    await expect(auditTabLink).toBeVisible();
    const tabText = await auditTabLink.innerText();
    expect(tabText).toContain('Security');
    expect(tabText).toContain('Audit');
  });

  test('security audit tab loads without errors and shows expected sections', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    // Navigate directly to the security audit tab
    await page.goto('/dashboard.php?page=admin_security&tab=security_audit', {
      waitUntil: 'networkidle'
    });

    // Check for no PHP/SQL errors
    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // Verify the tab is active
    const activeTab = page.locator('a.page-tab.active');
    const activeTabText = await activeTab.innerText();
    expect(activeTabText).toContain('Audit');

    // Verify the page body contains key sections
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toContain('Overall Status');
    expect(bodyText).toContain('NPM Dependencies');
    expect(bodyText).toContain('Composer');
    expect(bodyText).toContain('Security Scan History');
  });

  test('security audit tab shows dependency vulnerability summary cards', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin_security&tab=security_audit', {
      waitUntil: 'networkidle'
    });

    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // Verify summary cards exist
    const summaryCards = page.locator('.audit-summary-card');
    const cardCount = await summaryCards.count();
    expect(cardCount).toBe(4);

    // Verify the expected labels
    const bodyText = await page.locator('.audit-summary-grid').innerText();
    expect(bodyText).toContain('Overall Status');
    expect(bodyText).toContain('Dependency Vulnerabilities');
    expect(bodyText).toContain('Critical / High');
    expect(bodyText).toContain('Outdated Packages');
  });

  test('all six security tabs are present in navigation', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin_security', {
      waitUntil: 'networkidle'
    });

    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // Verify all 6 tabs are present
    const tabs = page.locator('.page-tabs a.page-tab');
    const tabCount = await tabs.count();
    expect(tabCount).toBe(6);

    // Verify tab names
    const tabTexts = await tabs.allInnerTexts();
    const combinedText = tabTexts.join(' ');
    expect(combinedText).toContain('Login History');
    expect(combinedText).toContain('Audit Log');
    expect(combinedText).toContain('Error Log');
    expect(combinedText).toContain('Registration Restrictions');
    expect(combinedText).toContain('POS IP Whitelist');
    expect(combinedText).toContain('Dependency Audit');
  });
});
