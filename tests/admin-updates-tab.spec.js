import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - Admin Updates Tab Tests
 * Tests to verify the GitHub Updates tab functionality in Admin Settings
 */

// Get base URL from environment or use default
const BASE_URL = process.env.BASE_URL || 'http://localhost/Arctic_Wolves';

test.describe('Admin Settings - Updates Tab', () => {
  
  // Login as admin before each test
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/login.php`);
    
    // Login with admin credentials
    await page.fill('input[name="email"]', 'admin@test.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Wait for navigation to dashboard
    await page.waitForURL('**/dashboard.php*');
    
    // Navigate to admin settings
    await page.goto(`${BASE_URL}/dashboard.php?page=admin_settings`);
    await page.waitForLoadState('networkidle');
  });
  
  test('Updates tab button exists and is clickable', async ({ page }) => {
    // Find the Updates tab button
    const updatesTabBtn = page.locator('button.tab:has-text("Updates")');
    
    // Verify it exists and is visible
    await expect(updatesTabBtn).toBeVisible();
    
    // Verify it has the correct icon
    const icon = updatesTabBtn.locator('i.fa-download');
    await expect(icon).toBeVisible();
    
    // Click the updates tab
    await updatesTabBtn.click();
    
    // Verify the updates tab content is visible
    const updatesTabContent = page.locator('#tab-updates');
    await expect(updatesTabContent).toHaveClass(/active/);
  });
  
  test('Updates tab contains GitHub authentication section', async ({ page }) => {
    // Click the Updates tab
    await page.click('button.tab:has-text("Updates")');
    
    // Wait for tab content to be visible
    await page.waitForSelector('#tab-updates.active');
    
    // Verify GitHub token input exists
    const tokenInput = page.locator('#tab-updates input[name="github_token"]');
    await expect(tokenInput).toBeVisible();
    await expect(tokenInput).toHaveAttribute('type', 'password');
    
    // Verify Test Connection button exists
    const testBtn = page.locator('#tab-updates button:has-text("Test Connection")');
    await expect(testBtn).toBeVisible();
    
    // Verify Save button exists
    const saveBtn = page.locator('#tab-updates button[type="submit"]:has-text("Save GitHub Settings")');
    await expect(saveBtn).toBeVisible();
  });
  
  test('Updates tab contains system updates section', async ({ page }) => {
    // Click the Updates tab
    await page.click('button.tab:has-text("Updates")');
    
    // Wait for tab content to be visible
    await page.waitForSelector('#tab-updates.active');
    
    // Verify Check for Updates button exists
    const checkBtn = page.locator('#tab-updates button:has-text("Check for Updates")');
    await expect(checkBtn).toBeVisible();
    
    // Verify Apply Updates button exists
    const applyBtn = page.locator('#tab-updates button:has-text("Apply Updates")');
    await expect(applyBtn).toBeVisible();
    
    // Verify warning message exists
    const warning = page.locator('#tab-updates .help-text:has-text("Important")');
    await expect(warning).toBeVisible();
    await expect(warning).toContainText('Files removed from the repository will be deleted');
  });
  
  test('Updates tab JavaScript functions are defined', async ({ page }) => {
    // Click the Updates tab
    await page.click('button.tab:has-text("Updates")');
    
    // Check if JavaScript functions exist
    const functionsExist = await page.evaluate(() => {
      return {
        testGitHubConnection: typeof window.testGitHubConnection === 'function',
        checkForUpdates: typeof window.checkForUpdates === 'function',
        applyUpdates: typeof window.applyUpdates === 'function'
      };
    });
    
    expect(functionsExist.testGitHubConnection).toBe(true);
    expect(functionsExist.checkForUpdates).toBe(true);
    expect(functionsExist.applyUpdates).toBe(true);
  });
  
  test('Updates tab has proper styling and layout', async ({ page }) => {
    // Click the Updates tab
    await page.click('button.tab:has-text("Updates")');
    
    // Wait for tab content to be visible
    await page.waitForSelector('#tab-updates.active');
    
    // Verify settings cards exist
    const settingsCards = page.locator('#tab-updates .settings-card');
    const cardCount = await settingsCards.count();
    expect(cardCount).toBeGreaterThanOrEqual(2); // At least 2 cards (GitHub auth + System updates)
    
    // Verify card headers exist
    const cardTitles = page.locator('#tab-updates .card-title');
    await expect(cardTitles.first()).toBeVisible();
    
    // Verify alert styles are present
    const styles = await page.evaluate(() => {
      const style = document.querySelector('style');
      if (!style) return '';
      return style.textContent;
    });
    
    expect(styles).toContain('.alert-info');
    expect(styles).toContain('.alert-warning');
    expect(styles).toContain('.alert-danger');
  });
  
  test('Tab switching works correctly with Updates tab', async ({ page }) => {
    // Initially, General tab should be active
    const generalTab = page.locator('#tab-general');
    await expect(generalTab).toHaveClass(/active/);
    
    // Click Updates tab
    await page.click('button.tab:has-text("Updates")');
    await page.waitForTimeout(300); // Wait for animation
    
    // Updates tab should now be active
    const updatesTab = page.locator('#tab-updates');
    await expect(updatesTab).toHaveClass(/active/);
    
    // General tab should no longer be active
    await expect(generalTab).not.toHaveClass(/active/);
    
    // Click back to General tab
    await page.click('button.tab:has-text("General")');
    await page.waitForTimeout(300);
    
    // General tab should be active again
    await expect(generalTab).toHaveClass(/active/);
    
    // Updates tab should no longer be active
    await expect(updatesTab).not.toHaveClass(/active/);
  });
});
