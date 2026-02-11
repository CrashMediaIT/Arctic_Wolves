import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - NDI Cameras Tab Tests
 * Tests to verify the NDI Camera management functionality in System Tools
 */

// Get base URL from environment or use default
const BASE_URL = process.env.BASE_URL || 'http://localhost/Arctic_Wolves';

test.describe('System Tools - NDI Cameras Tab', () => {
  
  // Login as admin before each test
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/login.php`);
    
    // Login with admin credentials
    await page.fill('input[name="email"]', 'admin@test.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Wait for navigation to dashboard
    await page.waitForURL('**/dashboard.php*');
    
    // Navigate to system tools NDI cameras tab
    await page.goto(`${BASE_URL}/dashboard.php?page=system_tools&tab=ndi_cameras`);
    await page.waitForLoadState('networkidle');
  });
  
  test('NDI Cameras tab is accessible and visible', async ({ page }) => {
    // Verify the NDI Cameras tab link exists and is active
    const ndiTabLink = page.locator('a.page-tab:has-text("NDI Cameras")');
    await expect(ndiTabLink).toBeVisible();
    await expect(ndiTabLink).toHaveClass(/active/);
  });
  
  test('NDI Cameras tab contains the camera management card', async ({ page }) => {
    // Verify the NDI Camera Management card header exists
    const cardHeader = page.locator('.card-header:has-text("NDI Camera Management")');
    await expect(cardHeader).toBeVisible();
    
    // Verify description text is present
    const description = page.locator('text=Add and manage NDI (Network Device Interface) cameras');
    await expect(description).toBeVisible();
  });
  
  test('NDI Cameras tab contains the Add New Camera form', async ({ page }) => {
    // Verify "Add New NDI Camera" section exists
    const addHeader = page.locator('h4:has-text("Add New NDI Camera")');
    await expect(addHeader).toBeVisible();
    
    // Verify form inputs exist
    const nameInput = page.locator('input[name="ndi_camera_name"]');
    await expect(nameInput).toBeVisible();
    await expect(nameInput).toHaveAttribute('placeholder', 'Main Rink Camera');
    
    const ipInput = page.locator('input[name="ndi_camera_ip"]');
    await expect(ipInput).toBeVisible();
    await expect(ipInput).toHaveAttribute('placeholder', '192.168.1.100');
    
    const portInput = page.locator('input[name="ndi_camera_port"]');
    await expect(portInput).toBeVisible();
    await expect(portInput).toHaveValue('5960');
    
    const ndiNameInput = page.locator('input[name="ndi_camera_ndi_name"]');
    await expect(ndiNameInput).toBeVisible();
    
    const locationInput = page.locator('input[name="ndi_camera_location"]');
    await expect(locationInput).toBeVisible();
    
    // Verify submit button exists
    const addBtn = page.locator('button:has-text("Add Camera")');
    await expect(addBtn).toBeVisible();
  });
  
  test('NDI Cameras tab contains the Configured Cameras section', async ({ page }) => {
    // Verify "Configured Cameras" section exists
    const configuredHeader = page.locator('h4:has-text("Configured Cameras")');
    await expect(configuredHeader).toBeVisible();
  });
  
  test('NDI Cameras tab has correct form action', async ({ page }) => {
    // Verify form posts to process_settings.php
    const form = page.locator('#add-ndi-camera-form');
    await expect(form).toHaveAttribute('action', 'process_settings.php');
    await expect(form).toHaveAttribute('method', 'POST');
    
    // Verify hidden action field
    const actionInput = page.locator('#add-ndi-camera-form input[name="action"]');
    await expect(actionInput).toHaveValue('add_ndi_camera');
  });
  
  test('NDI Cameras tab has CSRF token in form', async ({ page }) => {
    const csrfInput = page.locator('#add-ndi-camera-form input[name="csrf_token"]');
    await expect(csrfInput).toBeAttached();
    const csrfValue = await csrfInput.getAttribute('value');
    expect(csrfValue).toBeTruthy();
    expect(csrfValue.length).toBeGreaterThan(0);
  });
  
  test('NDI Cameras tab JavaScript functions are defined', async ({ page }) => {
    // Check if JavaScript functions exist
    const functionsExist = await page.evaluate(() => {
      return {
        toggleNdiCamera: typeof window.toggleNdiCamera === 'function',
        editNdiCamera: typeof window.editNdiCamera === 'function',
        closeNdiEditModal: typeof window.closeNdiEditModal === 'function',
        saveNdiCamera: typeof window.saveNdiCamera === 'function',
        deleteNdiCamera: typeof window.deleteNdiCamera === 'function'
      };
    });
    
    expect(functionsExist.toggleNdiCamera).toBe(true);
    expect(functionsExist.editNdiCamera).toBe(true);
    expect(functionsExist.closeNdiEditModal).toBe(true);
    expect(functionsExist.saveNdiCamera).toBe(true);
    expect(functionsExist.deleteNdiCamera).toBe(true);
  });
  
  test('NDI Cameras edit modal exists but is hidden by default', async ({ page }) => {
    const modal = page.locator('#ndi-camera-edit-modal');
    await expect(modal).toBeAttached();
    
    // Modal should not be visible by default
    await expect(modal).not.toBeVisible();
  });
  
  test('Tab switching works correctly with NDI Cameras tab', async ({ page }) => {
    // NDI Cameras tab should be active
    const ndiTabLink = page.locator('a.page-tab:has-text("NDI Cameras")');
    await expect(ndiTabLink).toHaveClass(/active/);
    
    // Click Settings tab
    await page.click('a.page-tab:has-text("Settings")');
    await page.waitForTimeout(300);
    
    // Settings tab should now be active (via URL change)
    await expect(page).toHaveURL(/tab=settings/);
    
    // Click back to NDI Cameras tab
    await page.click('a.page-tab:has-text("NDI Cameras")');
    await page.waitForTimeout(300);
    
    // NDI Cameras tab should be active again
    await expect(page).toHaveURL(/tab=ndi_cameras/);
  });
});
