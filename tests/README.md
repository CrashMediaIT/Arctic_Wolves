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

### 2. Style Guide Compliance
Tests for:
- Button colors (#6B46C1)
- Button heights (45px)
- Icon presence (fa-plus for Add buttons)

### 3. Functionality Tests
Tests for:
- No unexpected redirects to home
- CRUD operations working
- Form submissions successful

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
