import { test, expect } from '@playwright/test';

/**
 * Arctic Wolves - PWA Landing Page & Scroll Tests
 * 
 * Validates:
 * 1. Non-authenticated users see the landing page (not PWA login) on mobile
 * 2. PWA manifest start_url points to index.php (landing page)
 * 3. pwa.php redirects unauthenticated users to index.php (landing page)
 * 4. PWA content area has proper touch scrolling CSS properties
 * 5. "Athlete Login" link is available on the landing page
 */

test.describe('PWA Landing Page for Non-Authenticated Users', () => {

  test('manifest.json start_url points to index.php', async ({ page }) => {
    const response = await page.goto('/manifest.json', { waitUntil: 'networkidle' });
    expect(response.ok()).toBe(true);

    const manifest = await response.json();
    expect(manifest.start_url).toBe('/index.php');
  });

  test('index.php shows landing page with Athlete Login link when not signed in', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });

    // Should show the landing/marketing page, not a login form
    const pageContent = await page.content();
    expect(pageContent).not.toMatch(/Fatal error|SQLSTATE|PDOException/);

    // Landing page should contain "Athlete Login" link
    const athleteLoginLink = page.locator('a:has-text("Athlete Login")');
    await expect(athleteLoginLink).toBeVisible();
  });

  test('pwa.php redirects to index.php when not authenticated', async ({ page }) => {
    // Navigate to pwa.php without being logged in
    await page.goto('/pwa.php', { waitUntil: 'networkidle' });

    // Should be redirected to index.php (landing page), not pwa_login.php
    const url = page.url();
    expect(url).toContain('index.php');
    expect(url).not.toContain('pwa_login.php');
  });

  test('pwa_tablet.php redirects to index.php when not authenticated', async ({ page }) => {
    await page.goto('/pwa_tablet.php', { waitUntil: 'networkidle' });

    const url = page.url();
    expect(url).toContain('index.php');
    expect(url).not.toContain('pwa_login.php');
  });

});

test.describe('PWA Touch Scrolling CSS', () => {

  test('pwa.css has correct body height and overflow for scrolling', async ({ page }) => {
    // Load the CSS file directly and check its content
    const response = await page.goto('/css/pwa.css', { waitUntil: 'networkidle' });
    const cssText = await response.text();

    // body.pwa-body should use height (not min-height) for proper flex scrolling
    expect(cssText).toContain('height: 100dvh');
    expect(cssText).toContain('overflow: hidden');

    // .pwa-content should have touch-action: pan-y for touch scrolling
    expect(cssText).toContain('touch-action: pan-y');
    expect(cssText).toContain('-webkit-overflow-scrolling: touch');
    expect(cssText).toContain('overflow-y: auto');
  });

  test('pwa-tablet.css has touch-action: pan-y on content area', async ({ page }) => {
    const response = await page.goto('/css/pwa-tablet.css', { waitUntil: 'networkidle' });
    const cssText = await response.text();

    expect(cssText).toContain('touch-action: pan-y');
    expect(cssText).toContain('-webkit-overflow-scrolling: touch');
  });

});
