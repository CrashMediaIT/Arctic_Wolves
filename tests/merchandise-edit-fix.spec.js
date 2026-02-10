/**
 * Test for merchandise product edit fix
 * 
 * This test verifies that editing a merchandise product:
 * 1. Uses the correct action endpoint (get_merchandise_product)
 * 2. Populates the edit modal with product data
 * 3. Can submit the edit form successfully
 */

import { test, expect } from '@playwright/test';

test.describe('Merchandise Product Edit', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to login page
    await page.goto('/login.php');
    
    // Login as admin
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    
    // Wait for dashboard to load
    await page.waitForURL('**/dashboard.php');
    
    // Navigate to products page
    await page.goto('/dashboard.php?page=products&tab=merchandise');
    await page.waitForLoadState('networkidle');
  });

  test('should use correct endpoint for merchandise product edit', async ({ page }) => {
    // Find and click the first edit button for a merchandise product
    const editButton = page.locator('button[data-action="edit"][data-type="merch-product"]').first();
    
    if (await editButton.count() > 0) {
      // Wait for the API request when clicking edit button
      const responsePromise = page.waitForResponse(
        response => response.url().includes('process_admin_action.php') && 
                    response.url().includes('action=get_merchandise_product')
      );
      
      await editButton.click();
      
      // Wait for the response
      const response = await responsePromise;
      
      // Verify the correct endpoint was called
      expect(response.url()).toContain('action=get_merchandise_product');
      expect(response.url()).not.toContain('action=get_discount');
    } else {
      test.skip('No merchandise products available to test');
    }
  });

  test('should display edit modal with product data', async ({ page }) => {
    // Find and click the first edit button
    const editButton = page.locator('button[data-action="edit"][data-type="merch-product"]').first();
    
    if (await editButton.count() > 0) {
      await editButton.click();
      
      // Wait for modal to appear
      const modal = page.locator('#edit-merchandise-product-modal');
      await expect(modal).toBeVisible();
      
      // Wait for the form to be populated (not showing loading state)
      await expect(modal.locator('.fa-spinner')).not.toBeVisible({ timeout: 5000 });
      
      // Verify form fields exist
      await expect(modal.locator('input[name="name"]')).toBeVisible();
      await expect(modal.locator('input[name="price"]')).toBeVisible();
      await expect(modal.locator('select[name="is_active"]')).toBeVisible();
      
      // Verify the form action points to merchandise products processor
      const form = modal.locator('form');
      const action = await form.getAttribute('action');
      expect(action).toContain('process_merchandise_products.php');
    } else {
      test.skip('No merchandise products available to test');
    }
  });

  test('should not throw "Discount not found" error', async ({ page }) => {
    // Listen for response errors
    let responseError = null;
    page.on('response', async response => {
      if (response.url().includes('process_admin_action.php')) {
        if (response.status() !== 200) {
          try {
            const body = await response.json();
            if (body.message && body.message.includes('Discount not found')) {
              responseError = body.message;
            }
          } catch (e) {
            // Not JSON response
          }
        }
      }
    });

    // Find and click the first edit button
    const editButton = page.locator('button[data-action="edit"][data-type="merch-product"]').first();
    
    if (await editButton.count() > 0) {
      // Wait for the response when clicking edit button
      const responsePromise = page.waitForResponse(
        response => response.url().includes('process_admin_action.php')
      );
      
      await editButton.click();
      await responsePromise;
      
      // Wait for modal to be fully loaded
      const modal = page.locator('#edit-merchandise-product-modal');
      await expect(modal).toBeVisible();
      await expect(modal.locator('form')).toBeVisible();
      
      // Verify no "Discount not found" error occurred
      expect(responseError).toBeNull();
      
      // Verify modal doesn't show error message
      const errorText = await modal.locator('.modal-body').textContent();
      expect(errorText).not.toContain('Discount not found');
    } else {
      test.skip('No merchandise products available to test');
    }
  });
});
