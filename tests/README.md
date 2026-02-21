# Arctic Wolves - Playwright Testing

This directory contains browser automation tests using Playwright.

## Setup

### 1. Install Dependencies

```bash
npm install
```

### 2. Install Playwright Browsers

```bash
npx playwright install chromium
```

**Note:** If browser installation fails due to network restrictions (e.g., corporate firewalls, proxy restrictions), you can:
- Install browsers on a different network
- Use system-installed Chrome/Chromium
- Skip browser tests and rely on manual QA with screenshots

### 3. Verify Installation

```bash
# Check if Playwright is installed
npm list @playwright/test

# List available tests without running them
npx playwright test --list
```

## Running Tests

### Run All Tests
```bash
npm test
```

### Run Tests in Headed Mode (See Browser)
```bash
npm run test:headed
```

### Run Tests with UI Mode (Interactive)
```bash
npm run test:ui
```

### Run Tests in Debug Mode
```bash
npm run test:debug
```

### Run Specific Test File
```bash
npx playwright test tests/database-fixes.spec.js
```

## Test Configuration

The tests expect the Arctic Wolves application to be running at:
- **Default URL:** `http://localhost/Arctic_Wolves`
- **Override:** Set `BASE_URL` environment variable

```bash
BASE_URL=http://192.168.1.100/Arctic_Wolves npm test
```

## Test Users

The tests use these default test accounts:
- **Admin:** admin@test.com / password123
- **Coach:** coach@test.com / password123
- **Athlete:** athlete@test.com / password123

Make sure these accounts exist in your test database.

## Test Categories

### 1. Database Fixes (`database-fixes.spec.js`)
Validates the 6 critical SQL column fixes:
- Video drill review (d.name → d.title)
- Health workouts (exercises → exercise_library)
- Drills import (d.source → d.ihs_source_url)
- Athletes roster (removed booked_for_user_id)
- Travel mileage (settings → system_settings)
- Reports generation (removed format column)

### 2. Style Guide Compliance (`button-fixes.spec.js`)
Tests for:
- Button colors (#6B46C1)
- Button heights (45px)
- Icon presence (fa-plus for Add buttons)

### 3. Booking Cancellation (`booking-cancellation.spec.js`)
Tests the booking cancellation (DELETE) operation in process_booking.php.

### 4. Admin Updates Tab (`admin-updates-tab.spec.js`)
Tests the Feature Importer functionality in System Tools → Updates tab.

### 5. Backup Nextcloud Destination (`backup-nextcloud-destination.spec.js`)
Verifies Quick Backup and Force Nextcloud Backup use correct destinations.

### 6. Encryption Consolidation (`encryption-consolidation.spec.js`)
Validates encryptPassword/decryptPassword consolidation into security.php.

### 7. FusionPBX/SIP Integration (`fusionpbx-sip-integration.spec.js`)
Verifies FusionPBX removal and Company Directory migration.

### 8. Inventory Tracking System (`inventory-tracking-system.spec.js`)
Tests merchandise inventory tracking schema, shipment/audit modals, and stock history.

### 9. Login History (`login-history.spec.js`)
Validates login attempt recording and display in Security Center.

### 10. Merchandise Edit Fix (`merchandise-edit-fix.spec.js`)
Tests the merchandise product edit endpoint correction.

### 11. Merchandise Stock Level (`merchandise-edit-stock-level.spec.js`)
Tests stock level editing within product edit modals.

### 12. NDI Cameras Tab (`ndi-cameras-tab.spec.js`)
Tests NDI camera management in System Tools.

### 13. OCR Line Items & Payee (`ocr-line-items-payee.spec.js`)
Tests OCR line item extraction and payee input enhancement for expenses.

### 14. Paperless-NGX API Versioning (`paperless-ngx-api-versioning.spec.js`)
Validates Paperless-NGX API versioned Accept header and endpoint fixes.

### 15. Programs & Camps Feature (`programs-camps-feature.spec.js`)
Tests Programs & Camps feature, email export fix, and registration flow.

### 16. PWA Landing & Scroll (`pwa-landing-and-scroll.spec.js`)
Validates PWA landing page for non-authenticated users and touch scrolling.

### 17. SIP Phone/Company Directory (`sip-phone-logging.spec.js`)
Verifies Company Directory view, SIP config removal, and directory search.

### 18. SIP WSS & Setup Migration (`sip-wss-and-setup-migration.spec.js`)
Tests SIP WSS port configuration and production setup with existing databases.

### 19. Stallion Express Integration (`stallion-express-integration.spec.js`)
Validates Stallion Express shipping integration schema and fulfillment features.

### 20. Team Seasons Roster (`team-seasons-roster.spec.js`)
Tests multi-season team support and per-season athlete assignment.

## Test Results

After running tests, view the HTML report:
```bash
npx playwright show-report
```

## Writing New Tests

1. Create a new file in `tests/` directory with `.spec.js` extension
2. Import test utilities:
```javascript
import { test, expect } from '@playwright/test';
```
3. Write test cases using Playwright API
4. Run tests to validate

## Debugging Tests

### Take Screenshots
```javascript
await page.screenshot({ path: 'screenshot.png' });
```

### View Browser Console
```javascript
page.on('console', msg => console.log('Browser:', msg.text()));
```

### Pause Execution
```javascript
await page.pause(); // Opens Playwright Inspector
```

## CI/CD Integration

Tests are configured to run in CI environments. Set the `CI` environment variable:
```bash
CI=true npm test
```

This enables:
- Automatic retries (2 attempts)
- Single-threaded execution
- Fail-fast on test.only

## QA Governance Checks

The test infrastructure supports QA governance checks by:

1. **Automated Screenshots**: Tests automatically capture screenshots on failure
   - Configuration: `screenshot: 'only-on-failure'` in playwright.config.js
   - Location: `test-results/` directory
   - Format: PNG files with test names

2. **Test Reports**: HTML reports with visual evidence
   - View with: `npx playwright show-report`
   - Includes screenshots, traces, and videos of failures

3. **Prerequisites for QA Checks**:
   - Dependencies installed: `npm install`
   - Application running at configured BASE_URL
   - Test database with required test users

4. **Common Issues**:
   - **MODULE_NOT_FOUND error**: Run `npm install` to install @playwright/test
   - **Browser installation fails**: Network restrictions may block downloads
   - **Tests won't run**: Check that dependencies are installed with `npm list`

### Running Tests as Governance Checks

```bash
# 1. Ensure dependencies are installed
npm install

# 2. Verify setup
npm list @playwright/test
npx playwright test --list

# 3. Run tests (requires running application)
BASE_URL=http://your-server/Arctic_Wolves npm test

# 4. View results with screenshots
npx playwright show-report
```

## Troubleshooting

### Tests Timeout
- Increase timeout in `playwright.config.js`
- Check if application is running
- Verify BASE_URL is correct

### Login Fails
- Verify test users exist in database
- Check credentials in test file
- Ensure session handling works

### Element Not Found
- Add `await page.waitForSelector('.element')`
- Check if element is in viewport
- Verify element selector is correct

## Resources

- [Playwright Documentation](https://playwright.dev)
- [Arctic Wolves Testing Guide](../QA/BROWSER_TESTING_GUIDE.md)
- [Test Plan](../QA/BROWSER_TESTING_PLAN.md)
