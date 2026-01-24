import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - Database Fix Validation Tests
 * Tests to verify the 6 critical database column fixes
 */

// Test configuration
const TEST_USER = {
  admin: {
    email: 'admin@test.com',
    password: 'password123'
  },
  coach: {
    email: 'coach@test.com',
    password: 'password123'
  },
  athlete: {
    email: 'athlete@test.com',
    password: 'password123'
  }
};

/**
 * Helper function to login
 * @param {Page} page - Playwright page object
 * @param {string} role - User role (admin, coach, athlete)
 * @returns {Promise<boolean>} - Returns true if login successful
 */
async function login(page, role = 'admin') {
  try {
    const user = TEST_USER[role];
    await page.goto('/login.php', { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    
    // Wait for redirect to dashboard with timeout
    await page.waitForURL(/dashboard\.php/, { timeout: 10000 });
    
    // Verify we're actually logged in
    const isDashboard = await page.url().includes('dashboard.php');
    return isDashboard;
  } catch (error) {
    console.error(`Login failed for ${role}:`, error.message);
    return false;
  }
}

/**
 * Helper function to check for SQL errors
 * @param {Page} page - Playwright page object
 * @returns {Promise<boolean>} - Returns true if no SQL errors found
 */
async function expectNoSqlError(page) {
  const hasError = await page.locator('text=/Fatal error|SQLSTATE|PDOException/').isVisible().catch(() => false);
  return !hasError;
}

test.describe('Database Column Fix Validation', () => {
  
  test('1. Video - Drill Review loads without d.name SQL error', async ({ page }) => {
    const loggedIn = await login(page, 'athlete');
    expect(loggedIn).toBe(true);
    
    // Navigate to Video > Drill Review
    await page.goto('/dashboard.php?page=drill_review');
    
    // Should NOT see fatal error
    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);
    
    // Should see page title
    await expect(page.locator('.page-title')).toContainText(/drill/i);
  });
  
  test('2. Health - Workouts loads without category SQL error', async ({ page }) => {
    const loggedIn = await login(page, 'athlete');
    expect(loggedIn).toBe(true);
    
    // Navigate to Health > Strength & Conditioning
    await page.goto('/dashboard.php?page=health_workouts');
    
    // Should NOT see fatal error
    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);
    
    // Should see page content
    await expect(page.locator('.page-header')).toBeVisible();
  });
  
  test('3. Drills - Import loads without d.source SQL error', async ({ page }) => {
    const loggedIn = await login(page, 'coach');
    expect(loggedIn).toBe(true);
    
    // Navigate to Drills > Import from IHS
    await page.goto('/dashboard.php?page=drills_import');
    
    // Should NOT see fatal error
    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);
    
    // Should see import interface
    await expect(page.locator('.page-title')).toContainText(/import/i);
  });
  
  test('4. Roster - Athletes loads without booked_for_user_id SQL error', async ({ page }) => {
    const loggedIn = await login(page, 'coach');
    expect(loggedIn).toBe(true);
    
    // Navigate to Roster
    await page.goto('/dashboard.php?page=athletes');
    
    // Should NOT see fatal error
    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);
    
    // Should see roster interface
    await expect(page.locator('.page-header')).toBeVisible();
  });
  
  test('5. Travel - Mileage loads without settings table error', async ({ page }) => {
    const loggedIn = await login(page, 'coach');
    expect(loggedIn).toBe(true);
    
    // Navigate to Travel > Mileage
    await page.goto('/dashboard.php?page=travel_mileage');
    
    // Should NOT see fatal error
    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);
    
    // Should see mileage interface
    await expect(page.locator('.page-header')).toBeVisible();
  });
  
  test('6. Reports - Generate report without format column error', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);
    
    // Navigate to Reports
    await page.goto('/dashboard.php?page=reports');
    
    // Should see reports interface without error
    const noError = await expectNoSqlError(page);
    expect(noError).toBe(true);
    
    // Try to generate a report (if form exists)
    const generateBtn = page.locator('button:has-text("Generate")');
    if (await generateBtn.isVisible()) {
      // Select report type by visible text instead of index
      const reportTypeSelect = page.locator('select[name="report_type"]');
      if (await reportTypeSelect.isVisible()) {
        const firstOption = await reportTypeSelect.locator('option').first().textContent();
        if (firstOption) {
          await reportTypeSelect.selectOption({ label: firstOption });
          await generateBtn.click();
          
          // Check response
          await page.waitForTimeout(1000);
          
          // Should not show format column error
          const noErrorAfter = await expectNoSqlError(page);
          expect(noErrorAfter).toBe(true);
        }
      }
    }
  });
});

test.describe('Style Guide Compliance - Sample Tests', () => {
  
  test('Book Session button follows style guide', async ({ page }) => {
    const loggedIn = await login(page, 'athlete');
    expect(loggedIn).toBe(true);
    
    // Navigate to Sessions > Booking
    await page.goto('/dashboard.php?page=sessions_booking');
    
    // Find book session button
    const bookBtn = page.locator('button:has-text("Book")').first();
    
    if (await bookBtn.isVisible()) {
      // Check button has icon
      const hasIcon = await bookBtn.locator('i.fa').isVisible();
      expect(hasIcon).toBe(true);
      
      // Check button style (height should be 45px)
      const box = await bookBtn.boundingBox();
      if (box) {
        // Allow some tolerance for rendering differences
        expect(box.height).toBeGreaterThanOrEqual(40);
        expect(box.height).toBeLessThanOrEqual(50);
      }
    }
  });
  
  test('Add buttons use primary color', async ({ page }) => {
    const loggedIn = await login(page, 'admin');
    expect(loggedIn).toBe(true);
    
    // Navigate to admin page with Add buttons
    await page.goto('/dashboard.php?page=admin_categories');
    
    // Find Add buttons
    const addBtns = page.locator('button:has-text("Add")');
    const count = await addBtns.count();
    
    if (count > 0) {
      // Check first Add button
      const btn = addBtns.first();
      
      // Should have fa-plus icon
      const hasIcon = await btn.locator('i.fa-plus').isVisible();
      expect(hasIcon).toBe(true);
    }
  });
});

test.describe('Functionality - Redirect Issues', () => {
  
  test('Contact coach button should not redirect to home', async ({ page }) => {
    const loggedIn = await login(page, 'athlete');
    expect(loggedIn).toBe(true);
    
    // Navigate to Nutrition
    await page.goto('/dashboard.php?page=nutrition');
    
    // Find contact coach button
    const contactBtn = page.locator('button:has-text("Contact Coach")');
    
    if (await contactBtn.isVisible()) {
      const currentUrl = page.url();
      await contactBtn.click();
      
      // Wait a moment for any redirect
      await page.waitForTimeout(500);
      
      const newUrl = page.url();
      
      // Should NOT redirect to home/dashboard
      expect(newUrl).not.toContain('page=home');
      expect(newUrl).not.toBe(currentUrl.replace(/\?.*/, '')); // Not stripped to base dashboard
    }
  });
});
