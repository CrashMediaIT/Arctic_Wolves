# Quick Start Guide: QA Governance Checks with Playwright

This guide helps QA team members quickly set up and run automated tests as governance checks.

## What Was Fixed

**Problem**: Tests failed with "Cannot find module '@playwright/test'" error when trying to run QA governance checks.

**Solution**: Installed Playwright dependencies and documented the setup process.

## Quick Setup (One Time Only)

1. **Verify Setup** - Run the verification script:
   ```bash
   ./verify-test-setup.sh
   ```

2. **If verification fails**, install dependencies:
   ```bash
   npm install
   ```

3. **Verify again**:
   ```bash
   ./verify-test-setup.sh
   ```

## Running Tests as Governance Checks

### Option 1: Quick Verification (No Server Required)

Check that test configuration is valid:
```bash
# List all available tests
npx playwright test --list

# Shows: "Total: 25 tests in 2 files"
```

### Option 2: Full Test Run (Requires Running Server)

1. **Start the application** (if not already running):
   ```bash
   # Example: Using PHP built-in server
   php -S localhost:8000
   ```

2. **Run tests**:
   ```bash
   # Use default BASE_URL (http://localhost/Arctic_Wolves)
   npm test

   # OR specify custom BASE_URL
   BASE_URL=http://192.168.1.100/Arctic_Wolves npm test
   ```

3. **View results with screenshots**:
   ```bash
   npx playwright show-report
   ```

## What Gets Captured on Failures

When a test fails, Playwright automatically captures:

- **Screenshots**: Visual proof of the failure state
- **Video**: Recording of the test execution (on failure)
- **Trace**: Detailed execution log for debugging

All stored in: `test-results/` directory

## Test Categories

### 1. Button Fixes Tests (`button-fixes.spec.js`)
- Tests button attributes (data-action, data-page, data-modal)
- Verifies no inline onclick handlers where not needed
- Validates JavaScript event handlers

### 2. Database Fixes Tests (`database-fixes.spec.js`)
- Tests for SQL errors (missing columns, wrong table names)
- Validates style guide compliance
- Tests for unexpected redirects

## Common Issues and Solutions

### Issue: "Cannot find module '@playwright/test'"
**Solution**: Run `npm install`

### Issue: "Browser installation failed"
**Reason**: Network restrictions blocking downloads  
**Solutions**:
- Install on a different network
- Use system Chrome/Chromium
- Skip automated tests and use manual QA

### Issue: Tests timeout or fail to connect
**Solution**: 
- Verify application is running
- Check BASE_URL is correct
- Ensure test users exist in database

## Integration with QA Workflow

### Before Making Changes
1. Run verification: `./verify-test-setup.sh`
2. Run tests: `npm test`
3. Document baseline results

### After Making Changes
1. Run tests: `npm test`
2. Check for new failures
3. Review screenshots of any failures
4. Fix issues and re-test

### For Documentation
1. Take manual screenshots for QA/screenshots/ directory
2. Run automated tests for validation
3. Include both in QA reports

## Documentation Links

- **Detailed Setup**: [tests/README.md](tests/README.md)
- **Test Plan**: [QA/BROWSER_TESTING_PLAN.md](QA/BROWSER_TESTING_PLAN.md)
- **Validation Checklist**: [QA/VALIDATION_CHECKLIST.md](QA/VALIDATION_CHECKLIST.md)

## Questions?

See the full documentation in `tests/README.md` for:
- Writing new tests
- Debugging tests
- CI/CD integration
- Troubleshooting tips
