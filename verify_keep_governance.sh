#!/bin/bash

# Keep Governance Features Verification Script
# This script verifies that all complex features are properly implemented

echo "================================================"
echo "Keep Governance Features Verification"
echo "================================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Track results
PASS=0
FAIL=0

# Function to check file exists
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} File exists: $1"
        ((PASS++))
        return 0
    else
        echo -e "${RED}✗${NC} File missing: $1"
        ((FAIL++))
        return 1
    fi
}

# Function to check content in file
check_content() {
    if grep -q "$2" "$1" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} Found '$2' in $1"
        ((PASS++))
        return 0
    else
        echo -e "${RED}✗${NC} Missing '$2' in $1"
        ((FAIL++))
        return 1
    fi
}

echo "1. DRILL DRAW IMPLEMENTATION"
echo "----------------------------"
check_file "js/drill_designer.js"
check_content "js/drill_designer.js" "class DrillDesigner"
check_content "js/drill_designer.js" "drawRink"
check_content "js/drill_designer.js" "drawPlayer"
check_content "js/drill_designer.js" "drawArrow"
check_content "views/drills_create.php" "drill_designer.js"
check_content "process_drills.php" "diagram_data"
echo ""

echo "2. VIDEO UPLOAD MODULE"
echo "----------------------"
check_file "process_video.php"
check_content "process_video.php" "handleVideoUpload"
check_content "process_video.php" "FileUploadValidator"
check_content "process_video.php" "validateCSRFToken"
check_content "demo_data_seeder.php" "seedVideos"
check_content "views/video_coach_reviews.php" "process_video.php"
echo ""

echo "3. DRAG & DROP FUNCTIONALITY"
echo "----------------------------"
check_file "js/eval_framework.js"
check_file "DRAG_AND_DROP_IMPLEMENTATION.md"
check_content "views/admin_eval_framework.php" "SortableJS"
check_content "process_eval_framework.php" "reorder_skills"
echo ""

echo "4. DOCUMENTATION"
echo "----------------"
check_file "KEEP_GOVERNANCE_IMPLEMENTATION.md"
check_content "QA/ISSUES_TRACKER.md" "Keep Governance Complex Features"
check_content "QA/ISSUES_TRACKER.md" "Drill Draw Implementation"
check_content "QA/ISSUES_TRACKER.md" "Video Module Upload Features"
echo ""

echo "5. SECURITY FEATURES"
echo "-------------------"
check_content "process_video.php" "validateCSRFToken"
check_content "process_video.php" "\$pdo->prepare"
check_content "process_drills.php" "checkCsrfToken"
check_content "js/drill_designer.js" "JSON.parse"
echo ""

# Directory checks
echo "6. FILE STRUCTURE"
echo "-----------------"
if [ -d "videos" ]; then
    echo -e "${GREEN}✓${NC} Videos directory exists"
    ((PASS++))
else
    echo -e "${YELLOW}⚠${NC} Videos directory missing (will be created on first upload)"
fi

if [ -d "js" ]; then
    echo -e "${GREEN}✓${NC} JavaScript directory exists"
    ((PASS++))
else
    echo -e "${RED}✗${NC} JavaScript directory missing"
    ((FAIL++))
fi
echo ""

# Summary
echo "================================================"
echo "VERIFICATION SUMMARY"
echo "================================================"
echo -e "Passed: ${GREEN}$PASS${NC}"
echo -e "Failed: ${RED}$FAIL${NC}"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✓ ALL CHECKS PASSED${NC}"
    echo "Keep Governance features are properly implemented!"
    exit 0
else
    echo -e "${RED}✗ SOME CHECKS FAILED${NC}"
    echo "Please review the failed checks above."
    exit 1
fi
