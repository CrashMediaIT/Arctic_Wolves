import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - Admin Updates Tab Tests
 * Tests to verify the Feature Import functionality in System Tools Updates tab
 */

// Get base URL from environment or use default
const BASE_URL = process.env.BASE_URL || 'http://localhost/Arctic_Wolves';

test.describe('System Tools - Updates Tab', () => {
  
  // Login as admin before each test
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/login.php`);
    
    // Login with admin credentials
    await page.fill('input[name="email"]', 'admin@test.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Wait for navigation to dashboard
    await page.waitForURL('**/dashboard.php*');
    
    // Navigate to system tools updates tab
    await page.goto(`${BASE_URL}/dashboard.php?page=system_tools&tab=updates`);
    await page.waitForLoadState('networkidle');
  });
  
  test('Updates tab is accessible and shows Feature Importer', async ({ page }) => {
    // Verify we're on the updates tab
    const updatesTabLink = page.locator('a.page-tab:has-text("Updates")');
    await expect(updatesTabLink).toBeVisible();
    await expect(updatesTabLink).toHaveClass(/active/);
  });
  
  test('Updates tab contains system updates section', async ({ page }) => {
    // Verify the System Updates card exists
    const systemUpdatesCard = page.locator('.card-header:has-text("System Updates")');
    await expect(systemUpdatesCard).toBeVisible();
    
    // Verify the info box with instructions is visible
    const infoBox = page.locator('.info-box:has-text("Upload and import update packages")');
    await expect(infoBox).toBeVisible();
  });
  
  test('Updates tab contains file upload section', async ({ page }) => {
    // Verify upload section exists
    const uploadSection = page.locator('#uploadSection');
    await expect(uploadSection).toBeVisible();
    
    // Verify file input exists
    const fileInput = page.locator('#updateFileInput');
    await expect(fileInput).toHaveAttribute('accept', '.zip');
    
    // Verify Browse Files button exists
    const browseBtn = page.locator('button:has-text("Browse Files")');
    await expect(browseBtn).toBeVisible();
  });
  
  test('Updates tab contains import button (initially disabled)', async ({ page }) => {
    // Verify Import button exists and is disabled
    const importBtn = page.locator('#importUpdateBtn');
    await expect(importBtn).toBeVisible();
    await expect(importBtn).toBeDisabled();
    await expect(importBtn).toContainText('Import Update Package');
  });
  
  test('Updates tab JavaScript functions are defined', async ({ page }) => {
    // Check if JavaScript functions exist
    const functionsExist = await page.evaluate(() => {
      return {
        handleUpdateFileSelect: typeof window.handleUpdateFileSelect === 'function',
        handleUpdateFile: typeof window.handleUpdateFile === 'function',
        removeUpdateFile: typeof window.removeUpdateFile === 'function',
        startUpdateImport: typeof window.startUpdateImport === 'function',
        formatUpdateFileSize: typeof window.formatUpdateFileSize === 'function',
        addUpdateLogEntry: typeof window.addUpdateLogEntry === 'function'
      };
    });
    
    expect(functionsExist.handleUpdateFileSelect).toBe(true);
    expect(functionsExist.handleUpdateFile).toBe(true);
    expect(functionsExist.removeUpdateFile).toBe(true);
    expect(functionsExist.startUpdateImport).toBe(true);
    expect(functionsExist.formatUpdateFileSize).toBe(true);
    expect(functionsExist.addUpdateLogEntry).toBe(true);
  });
  
  test('Updates tab has proper styling and layout', async ({ page }) => {
    // Verify settings cards exist
    const settingsCards = page.locator('#updates-tab .card');
    const cardCount = await settingsCards.count();
    expect(cardCount).toBeGreaterThanOrEqual(1); // At least 1 card (System updates)
    
    // Verify the upload section has proper styling
    const uploadSection = page.locator('#uploadSection');
    const borderStyle = await uploadSection.evaluate(el => getComputedStyle(el).borderStyle);
    expect(borderStyle).toBe('dashed');
  });
  
  test('Tab switching works correctly with Updates tab', async ({ page }) => {
    // Updates tab should be active
    const updatesTabLink = page.locator('a.page-tab:has-text("Updates")');
    await expect(updatesTabLink).toHaveClass(/active/);
    
    // Click Settings tab
    await page.click('a.page-tab:has-text("Settings")');
    await page.waitForTimeout(300); // Wait for navigation
    
    // Settings tab should now be active (via URL change)
    await expect(page).toHaveURL(/tab=settings/);
    
    // Click back to Updates tab
    await page.click('a.page-tab:has-text("Updates")');
    await page.waitForTimeout(300);
    
    // Updates tab should be active again
    await expect(page).toHaveURL(/tab=updates/);
  });
});
