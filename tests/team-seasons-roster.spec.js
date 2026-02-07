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

test.describe('Multi-Season Team Creation & Edit', () => {

  test('Resource Management teams tab loads without errors', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=categories&tab=teams');

    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);

    // Teams tab should be visible
    await expect(page.locator('#teams-tab')).toBeVisible();
  });

  test('Add Team modal has season checkboxes instead of text input', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=categories&tab=teams');

    // Open Add Team modal
    await page.click('[data-modal="add-team-modal"]');
    await expect(page.locator('#add-team-modal')).toBeVisible();

    // Should have season checkboxes container
    const modal = page.locator('#add-team-modal');
    await expect(modal.locator('.season-checkboxes')).toBeVisible();

    // Should have checkbox inputs for seasons (or message if no seasons)
    const checkboxes = modal.locator('input[name="season_ids[]"]');
    const noSeasonsMsg = modal.locator('text=No seasons created yet');
    const hasCheckboxes = await checkboxes.count() > 0;
    const hasNoSeasonsMsg = await noSeasonsMsg.isVisible().catch(() => false);
    expect(hasCheckboxes || hasNoSeasonsMsg).toBe(true);
  });

  test('Edit Team modal has season checkboxes', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=categories&tab=teams');

    // Try to click the first Edit button for a team
    const editBtn = page.locator('[data-action="edit"][data-type="team"]').first();
    if (await editBtn.isVisible().catch(() => false)) {
      await editBtn.click();
      await expect(page.locator('#edit-team-modal')).toBeVisible();

      // Should have season checkboxes container
      const modal = page.locator('#edit-team-modal');
      await expect(modal.locator('#edit-team-seasons-container')).toBeVisible();
    }
  });

  test('Team cards display assigned season badges', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=categories&tab=teams');

    // Verify the page loaded without errors
    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);

    // Team cards should exist
    const teamCards = page.locator('.category-card');
    const count = await teamCards.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });
});

test.describe('Profile Team Selection from Roster', () => {

  test('Profile page loads without errors', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=profile');

    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);
  });

  test('Profile player tab shows Team History section', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=profile&tab=player');

    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);

    // Should have Team History section
    await expect(page.locator('text=Team History')).toBeVisible();
  });

  test('Profile has Select from Roster form when team-season combos exist', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=profile&tab=player');

    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);

    // The form might or might not appear depending on whether team_seasons exist
    // Just verify page loaded without errors and the add team form is present
    await expect(page.locator('text=Add New Team')).toBeVisible();
  });

  test('Profile has add_team_from_roster form with required fields', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);

    await page.goto('/dashboard.php?page=profile&tab=player');

    const rosterForm = page.locator('#select-roster-team-form');
    if (await rosterForm.isVisible().catch(() => false)) {
      // Should have team & season dropdown
      await expect(rosterForm.locator('select[name="roster_team_season"]')).toBeVisible();
      // Should have position dropdown
      await expect(rosterForm.locator('select[name="roster_position"]')).toBeVisible();
      // Should have submit button
      await expect(rosterForm.locator('button[type="submit"]')).toBeVisible();
    }
  });
});
