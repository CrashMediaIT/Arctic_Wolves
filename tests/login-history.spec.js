import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - Login History Tests
 * Validates that login attempts are recorded and displayed in the security panel
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

test.describe('Login History Recording', () => {

  test('successful login is recorded in security panel login history', async ({ page }) => {
    // Step 1: Login as admin
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    // Step 2: Navigate to admin security panel (login history tab)
    await page.goto('/dashboard.php?page=admin&section=security&tab=login_history', {
      waitUntil: 'networkidle'
    });

    // Step 3: Check for no SQL/PHP errors
    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // Step 4: Verify login history table is visible and contains entries
    // The login we just performed should appear in the history
    const loginRows = page.locator('table tbody tr, .login-history-row, [class*="login"]');
    const rowCount = await loginRows.count();
    expect(rowCount).toBeGreaterThan(0);
  });

  test('login history tab displays on security page without errors', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin&section=security&tab=login_history', {
      waitUntil: 'networkidle'
    });

    // Verify page loaded without errors
    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // The page should contain login history content (not be empty)
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toContain('Login');
  });

  test('failed login attempt is recorded in login history', async ({ page }) => {
    // Step 1: Attempt login with wrong password
    await page.goto('/login.php', { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', TEST_USER.admin.email);
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');

    // Should stay on login page with an error
    await page.waitForTimeout(2000);
    const currentUrl = page.url();
    expect(currentUrl).toContain('login.php');

    // Step 2: Now login successfully as admin to check the history
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    // Step 3: Navigate to security panel
    await page.goto('/dashboard.php?page=admin&section=security&tab=login_history', {
      waitUntil: 'networkidle'
    });

    // Step 4: The page should show login history entries including failed ones
    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // Look for the failed status indicator in the history
    const hasFailedEntry = await page.locator('text=/failed/i').count();
    expect(hasFailedEntry).toBeGreaterThan(0);
  });
});
