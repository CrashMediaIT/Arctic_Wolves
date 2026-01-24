#!/bin/bash

# Arctic Wolves - Test Setup Verification Script
# This script verifies that the Playwright test infrastructure is properly set up

echo "================================================"
echo "Arctic Wolves - Test Setup Verification"
echo "================================================"
echo ""

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if npm is installed
echo -n "Checking if npm is installed... "
if command -v npm &> /dev/null; then
    echo -e "${GREEN}✓ Found ($(npm --version))${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    echo "Please install Node.js and npm first"
    exit 1
fi

# Check if package.json exists
echo -n "Checking if package.json exists... "
if [ -f "package.json" ]; then
    echo -e "${GREEN}✓ Found${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    echo "Please run this script from the project root directory"
    exit 1
fi

# Check if node_modules exists
echo -n "Checking if node_modules exists... "
if [ -d "node_modules" ]; then
    echo -e "${GREEN}✓ Found${NC}"
else
    echo -e "${YELLOW}⚠ Not found${NC}"
    echo "Running 'npm install' to install dependencies..."
    npm install
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Dependencies installed${NC}"
    else
        echo -e "${RED}✗ Failed to install dependencies${NC}"
        exit 1
    fi
fi

# Check if @playwright/test is installed
echo -n "Checking if @playwright/test is installed... "
if npm list @playwright/test &> /dev/null; then
    VERSION=$(npm list @playwright/test --depth=0 2>/dev/null | grep @playwright/test | awk '{print $2}')
    echo -e "${GREEN}✓ Installed ($VERSION)${NC}"
else
    echo -e "${RED}✗ Not installed${NC}"
    echo "Running 'npm install' to fix..."
    npm install
fi

# Check if playwright.config.js exists
echo -n "Checking if playwright.config.js exists... "
if [ -f "playwright.config.js" ]; then
    echo -e "${GREEN}✓ Found${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    echo "Playwright configuration file is missing"
    exit 1
fi

# Check if tests directory exists
echo -n "Checking if tests directory exists... "
if [ -d "tests" ]; then
    TEST_COUNT=$(find tests -name "*.spec.js" | wc -l)
    echo -e "${GREEN}✓ Found ($TEST_COUNT test files)${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    echo "Tests directory is missing"
    exit 1
fi

# Try to list tests
echo -n "Trying to list tests... "
if npx playwright test --list &> /dev/null; then
    TOTAL_TESTS=$(npx playwright test --list 2>&1 | grep "Total:" | awk '{print $2}')
    echo -e "${GREEN}✓ Success ($TOTAL_TESTS tests found)${NC}"
else
    echo -e "${RED}✗ Failed${NC}"
    echo "There might be an issue with the Playwright configuration"
    exit 1
fi

# Check if .gitignore includes necessary patterns
echo -n "Checking .gitignore configuration... "
if [ -f ".gitignore" ]; then
    if grep -q "node_modules/" .gitignore && \
       grep -q "test-results/" .gitignore && \
       grep -q "playwright-report/" .gitignore; then
        echo -e "${GREEN}✓ Properly configured${NC}"
    else
        echo -e "${YELLOW}⚠ Missing some patterns${NC}"
        echo "  Consider adding: node_modules/, test-results/, playwright-report/"
    fi
else
    echo -e "${YELLOW}⚠ .gitignore not found${NC}"
fi

# Summary
echo ""
echo "================================================"
echo "Summary"
echo "================================================"
echo -e "${GREEN}✓ Test infrastructure is properly set up!${NC}"
echo ""
echo "Next steps:"
echo "  1. Ensure the application is running at the configured BASE_URL"
echo "  2. Run tests: npm test"
echo "  3. View results: npx playwright show-report"
echo ""
echo "For more information, see tests/README.md"
echo ""
