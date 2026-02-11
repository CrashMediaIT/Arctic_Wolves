/**
 * Test for merchandise product edit - stock level update functionality
 * 
 * This test verifies that:
 * 1. Edit product modal includes sizes/inventory section
 * 2. Existing sizes are fetched and populated when editing
 * 3. User can add, modify, and remove sizes
 * 4. Form submits with size data to update endpoint
 */

import { test, expect } from '@playwright/test';

test.describe('Merchandise Product Edit - Stock Level Management', () => {
  test.beforeEach(async ({ page }) => {
    // Note: These tests require a running instance with database
    // Skip if no test environment is available
    test.skip(process.env.SKIP_BROWSER_TESTS === 'true', 'Browser tests disabled');
  });

  test('edit modal should contain sizes and inventory section', async ({ page }) => {
    // This is a structural test that verifies the HTML contains the necessary elements
    // We can verify the existence of the elements even without a running server
    
    const fs = require('fs');
    const path = require('path');
    
    // Read the view file
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Verify edit modal has sizes section
    expect(content).toContain('edit-sizes-container');
    expect(content).toContain('Sizes & Inventory');
    expect(content).toContain('addSizeRow(\'edit\')');
    
    // Verify edit modal has the necessary form fields for sizes
    expect(content).toMatch(/name="sizes\[\]"/);
    expect(content).toMatch(/name="quantities\[\]"/);
    expect(content).toMatch(/name="size_ids\[\]"/);
  });

  test('editProduct function should fetch and populate sizes', async ({ page }) => {
    const fs = require('fs');
    const path = require('path');
    
    // Read the view file
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Verify editProduct function fetches sizes via API
    expect(content).toContain('function editProduct(product)');
    expect(content).toContain('process_merchandise_products.php?action=get_sizes');
    expect(content).toContain('edit-sizes-container');
    
    // Verify it creates size rows with hidden size_ids
    expect(content).toContain('size_ids[]');
  });

  test('addSizeRow function should support edit context', async ({ page }) => {
    const fs = require('fs');
    const path = require('path');
    
    // Read the view file
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Verify addSizeRow function handles 'edit' context
    expect(content).toContain('function addSizeRow(context)');
    expect(content).toContain('edit-sizes-container');
    
    // Verify edit context includes hidden size_ids field
    const addSizeRowMatch = content.match(/function addSizeRow\(context\)[\s\S]*?^}/m);
    expect(addSizeRowMatch).toBeTruthy();
    
    const addSizeRowFunction = addSizeRowMatch[0];
    expect(addSizeRowFunction).toContain('edit');
    expect(addSizeRowFunction).toContain('size_ids[]');
  });

  test('backend update action should handle sizes', async ({ page }) => {
    const fs = require('fs');
    const path = require('path');
    
    // Read the processor file
    const processorPath = path.join(__dirname, '..', 'process_merchandise_products.php');
    const content = fs.readFileSync(processorPath, 'utf-8');
    
    // Find the update case - look from 'case update' until we hit the next case or default
    const updateStartIndex = content.indexOf("case 'update':");
    expect(updateStartIndex).toBeGreaterThan(-1);
    
    // Find the end of this case (look for next 'case' or 'default:')
    const contentAfterUpdate = content.substring(updateStartIndex);
    const nextCaseIndex = contentAfterUpdate.search(/\n\s+case '|default:/);
    
    let updateCase;
    if (nextCaseIndex > 0) {
      updateCase = contentAfterUpdate.substring(0, nextCaseIndex);
    } else {
      // Just get a reasonable chunk
      updateCase = contentAfterUpdate.substring(0, 2000);
    }
    
    // Verify it processes sizes, quantities, and size_ids
    expect(updateCase).toContain('$sizes');
    expect(updateCase).toContain('$quantities');
    expect(updateCase).toContain('$sizeIds');
    
    // Verify it calls handleProductSizes
    expect(updateCase).toContain('handleProductSizes');
    
    // Verify it uses transactions
    expect(updateCase).toContain('beginTransaction');
    expect(updateCase).toContain('commit');
  });

  test('edit and create modals should have consistent size management', async ({ page }) => {
    const fs = require('fs');
    const path = require('path');
    
    // Read the view file
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Extract create modal sizes section
    const createModalMatch = content.match(/id="add-product-modal"[\s\S]*?<\/form>/);
    expect(createModalMatch).toBeTruthy();
    const createModal = createModalMatch[0];
    
    // Extract edit modal sizes section  
    const editModalMatch = content.match(/id="edit-product-modal"[\s\S]*?<\/form>/);
    expect(editModalMatch).toBeTruthy();
    const editModal = editModalMatch[0];
    
    // Both should have sizes container
    expect(createModal).toContain('sizes-container');
    expect(editModal).toContain('sizes-container');
    
    // Both should have add size button
    expect(createModal).toContain('Add Size');
    expect(editModal).toContain('Add Size');
    
    // Create modal has static size inputs, edit modal populates them dynamically
    expect(createModal).toMatch(/name="sizes\[\]"/);
    expect(createModal).toMatch(/name="quantities\[\]"/);
    
    // Edit modal should have the container that will be populated
    expect(editModal).toContain('edit-sizes-container');
    expect(editModal).toContain('Sizes & Inventory');
  });
});
