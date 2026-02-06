import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - Team Seasons & Roster Management Tests
 * Tests to verify multi-season team support and per-season athlete assignment
 */

// Test configuration
const TEST_USER = {
  admin: {
    email: 'admin@test.com',
    password: 'password123'
  }
};

/**
 * Helper function to login
 */
async function login(page, role = 'admin') {
  try {
    const user = TEST_USER[role];
    await page.goto('/login.php', { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard\.php/, { timeout: 10000 });
    return await page.url().includes('dashboard.php');
  } catch (error) {
    console.error(`Login failed for ${role}:`, error.message);
    return false;
  }
}

/**
 * Helper function to check for SQL errors
 */
async function expectNoSqlError(page) {
  const hasError = await page.locator('text=/Fatal error|SQLSTATE|PDOException/').isVisible().catch(() => false);
  return !hasError;
}

test.describe('Team Seasons & Roster Management', () => {

  test('Team Coach Management page loads without SQL errors', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin_team_coaches');

    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);

    // Page title should be visible
    await expect(page.locator('.page-title')).toContainText(/Team Coach Management/i);
  });

  test('Team Seasons section is visible on admin page', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin_team_coaches');

    // Should have the Team Seasons section
    await expect(page.locator('text=Team Seasons')).toBeVisible();
    await expect(page.locator('text=Add Season to Team')).toBeVisible();
  });

  test('Team Roster section is visible on admin page', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin_team_coaches');

    // Should have the Athlete Roster section
    await expect(page.locator('text=Team Roster')).toBeVisible();
    await expect(page.locator('text=Assign Athletes')).toBeVisible();
  });

  test('Season creation form has required fields', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin_team_coaches');

    // Check season creation form fields
    await expect(page.locator('input[name="season_name"]')).toBeVisible();
    await expect(page.locator('input[name="start_date"]')).toBeVisible();
    await expect(page.locator('input[name="end_date"]')).toBeVisible();
    await expect(page.locator('select[name="is_active"]')).toBeVisible();
  });

  test('Team season assignment form has team and season selects', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=admin_team_coaches');

    // The "add_team_season" form should have team and season selects
    const teamSeasonForm = page.locator('form:has(input[value="add_team_season"])');
    await expect(teamSeasonForm.locator('select[name="team_id"]')).toBeVisible();
    await expect(teamSeasonForm.locator('select[name="season_id"]')).toBeVisible();
  });
});
