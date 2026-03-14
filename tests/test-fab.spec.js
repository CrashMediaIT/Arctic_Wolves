const { test, expect } = require('@playwright/test');

test('FAB click test in PWA layout', async ({ page }) => {
  await page.goto('file:///tmp/test-fab.html');
  await page.setViewportSize({ width: 390, height: 844 }); // iPhone 14

  // Check the FAB is visible
  const fab = page.locator('#mAthFab');
  await expect(fab).toBeVisible();

  // Check the result div is hidden
  const result = page.locator('#clickResult');
  await expect(result).toBeHidden();
  
  // Try clicking the FAB
  await fab.click({ timeout: 5000 });
  
  // Check if the click was received
  await expect(result).toBeVisible({ timeout: 2000 });
  
  console.log('FAB click SUCCESS - the click was received');
});

test('FAB click test - check what element receives click at FAB position', async ({ page }) => {
  await page.goto('file:///tmp/test-fab.html');
  await page.setViewportSize({ width: 390, height: 844 });
  
  const fab = page.locator('#mAthFab');
  const fabBox = await fab.boundingBox();
  console.log('FAB bounding box:', JSON.stringify(fabBox));
  
  // Check what element is at the center of where the FAB should be
  const centerX = fabBox.x + fabBox.width / 2;
  const centerY = fabBox.y + fabBox.height / 2;
  
  const elementAtPoint = await page.evaluate(({x, y}) => {
    const el = document.elementFromPoint(x, y);
    return {
      tagName: el?.tagName,
      id: el?.id,
      className: el?.className,
      textContent: el?.textContent?.substring(0, 50)
    };
  }, { x: centerX, y: centerY });
  
  console.log('Element at FAB center:', JSON.stringify(elementAtPoint));
  
  // The element at the FAB's position should be the FAB itself (or its child)
  expect(elementAtPoint.id === 'mAthFab' || elementAtPoint.tagName === 'BUTTON').toBeTruthy();
});

test('FAB z-index vs tab-bar z-index', async ({ page }) => {
  await page.goto('file:///tmp/test-fab.html');
  await page.setViewportSize({ width: 390, height: 844 });
  
  const fabZIndex = await page.evaluate(() => {
    const fab = document.getElementById('mAthFab');
    return window.getComputedStyle(fab).zIndex;
  });
  
  const tabBarZIndex = await page.evaluate(() => {
    const tabBar = document.querySelector('.pwa-tab-bar');
    return window.getComputedStyle(tabBar).zIndex;
  });
  
  const fabPosition = await page.evaluate(() => {
    const fab = document.getElementById('mAthFab');
    return window.getComputedStyle(fab).position;
  });
  
  console.log('FAB z-index:', fabZIndex, 'position:', fabPosition);
  console.log('Tab bar z-index:', tabBarZIndex);
  
  // Check FAB is actually fixed positioned
  expect(fabPosition).toBe('fixed');
  expect(parseInt(fabZIndex)).toBeGreaterThan(parseInt(tabBarZIndex));
});
