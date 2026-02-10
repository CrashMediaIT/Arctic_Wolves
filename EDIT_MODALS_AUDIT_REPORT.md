# Edit Modals Audit Report
## Comprehensive Field Analysis - Arctic Wolves Platform

**Date:** December 2024  
**Purpose:** Identify missing fields in edit modals compared to their corresponding create/add modals

---

## Executive Summary

This audit examined 5 critical files containing 13 modal pairs across the views/ directory. The analysis reveals significant field gaps in edit modals that prevent users from modifying important data after initial creation.

### Critical Findings:
- **Session Edit Modal**: Missing 5 critical fields (42% incomplete)
- **Package Edit Modal**: Missing 5 fields (56% incomplete)  
- **Merchandise Edit Modal**: Missing size/stock management (critical inventory feature)
- **HR Payroll Edit Modal**: Missing 12 fields including banking and address information (60% incomplete)
- **User Edit Modal**: Missing status toggle and password reset capability

---

## Priority 1: CRITICAL - Immediate Action Required

These modals are missing essential business functionality that significantly impacts operations.

### 1.1 Accounting Products - Session Edit Modal ⚠️ HIGH PRIORITY

**File:** `views/accounting_products.php`  
**Modal IDs:** `add-session-modal` (line 1064) vs `edit-session-modal` (line 1448)  
**Severity:** CRITICAL - Core product management broken

#### Missing Fields (5):

| Field Name | Purpose | Business Impact |
|------------|---------|-----------------|
| **Session Type** | Dropdown: On Ice, Off Ice, Nutrition, Meeting, Other | Cannot change session category after creation |
| **Skill Types** | Multi-select checkboxes (skill_ids[]) | Cannot modify training focus areas |
| **Session Dates** | Dynamic rows with datetime & team assignments | Cannot reschedule or add new dates |
| **Show on Landing Page** | Boolean checkbox (show_on_landing) | Cannot toggle public visibility |
| **Save as Template** | Boolean checkbox (is_template) | Cannot convert to/from template |

#### Fields Present in Edit (10):
- ✅ Session Name
- ✅ Description
- ✅ Price
- ✅ Duration (minutes)
- ✅ Max Participants
- ✅ Coach
- ✅ Location
- ✅ Practice Plan
- ✅ Session Type Category
- ✅ Status (is_active)

#### Recommended Fix:
Add the 5 missing fields to the JavaScript function `populateEditModal()` (lines 2134-2390). The session dates section will require special handling for dynamic row generation.

#### Implementation Complexity: **MEDIUM**
- Estimated Time: 4-6 hours
- Requires: JavaScript modifications, backend endpoint updates for fetching session dates

---

### 1.2 Accounting Products - Package Edit Modal ⚠️ HIGH PRIORITY

**File:** `views/accounting_products.php`  
**Modal IDs:** `add-package-modal` (line 1249) vs `edit-package-modal` (line 1464)  
**Severity:** HIGH - Cannot modify package structure

#### Missing Fields (5):

| Field Name | Purpose | Business Impact |
|------------|---------|-----------------|
| **Package Type** | Selector: credits/dollar_value/bundled | Cannot change package business model |
| **Number of Sessions** | Integer input for credits type | Cannot adjust session count |
| **Store Credit Value** | Dollar amount for dollar_value type | Cannot modify credit amount |
| **Age Group** | Dropdown selection | Cannot change target demographics |
| **Skill Level** | Dropdown selection | Cannot adjust difficulty targeting |

#### Fields Present in Edit (6):
- ✅ Package Name
- ✅ Description
- ✅ Price
- ✅ Credits/Sessions (limited)
- ✅ Valid Days
- ✅ Status (is_active)

#### Recommended Fix:
The edit form needs conditional field display based on `package_type` similar to the add modal. Update JavaScript population function at lines 2231-2273.

#### Implementation Complexity: **MEDIUM-HIGH**
- Estimated Time: 5-7 hours
- Requires: Conditional field rendering, package type detection

---

### 1.3 Accounting Products - Merchandise Product Edit Modal ⚠️ CRITICAL

**File:** `views/accounting_products.php`  
**Modal IDs:** `add-merchandise-product-modal` (line 1528) vs `edit-merchandise-product-modal` (line 1614)  
**Severity:** CRITICAL - Inventory management broken

#### Missing Fields (1 major):

| Field Name | Purpose | Business Impact |
|------------|---------|-----------------|
| **Size & Stock Options** | Dynamic rows: sizes[] and quantities[] | CANNOT manage inventory levels or sizes after creation - must delete and recreate |

#### Fields Present in Edit (9):
- ✅ Product Name
- ✅ SKU
- ✅ Category
- ✅ Description
- ✅ Price
- ✅ Cost Price (bonus field not in add)
- ✅ Product Image
- ✅ Status (is_active)
- ✅ Track Inventory checkbox

#### Recommended Fix:
Add dynamic size/stock row management in the edit modal. This is critical for retail operations - staff cannot currently adjust inventory without deleting products.

#### Implementation Complexity: **MEDIUM**
- Estimated Time: 4-5 hours
- Requires: Dynamic row generation, fetch existing sizes from backend

---

### 1.4 HR Payroll - Edit Payroll Modal ⚠️ HIGH PRIORITY

**File:** `views/hr_payroll.php`  
**Modal ID:** `editPayrollModal`  
**Severity:** HIGH - Critical HR data cannot be updated

#### Missing Fields (12):

| Category | Fields | Business Impact |
|----------|--------|-----------------|
| **Employment** | Start Date | Cannot correct start dates |
| **Pension** | Employee Pension Rate (%), Employer Match (%) | Cannot adjust pension contributions |
| **Address** | Street, Unit, City, Province, Postal Code | Cannot update employee addresses |
| **Banking** | Institution Number, Transit Number, Account Number | Cannot update direct deposit info - must recreate employee |
| **Notes** | Free-text notes | Cannot add/update administrative notes |

#### Fields Present in Edit (8):
- ✅ Employee (readonly)
- ✅ Employee Type
- ✅ Pay Rate
- ✅ Pay Frequency
- ✅ Tax Province
- ✅ CPP Contributions checkbox
- ✅ EI Premiums checkbox
- ✅ Company Pension checkbox

#### Recommended Fix:
The edit modal should include collapsible sections for Address and Banking information. Banking info is especially critical as it's required for direct deposit.

#### Implementation Complexity: **MEDIUM-HIGH**
- Estimated Time: 5-8 hours
- Requires: Form sections, conditional pension fields, backend validation

---

## Priority 2: MEDIUM - Should Be Fixed Soon

### 2.1 Accounting Products - Discount Edit Modal

**File:** `views/accounting_products.php`  
**Modal IDs:** `add-discount-modal` (line 1381) vs `edit-discount-modal` (line 1480)  
**Severity:** MEDIUM - Date management missing

#### Missing Fields (2):

| Field Name | Purpose | Business Impact |
|------------|---------|-----------------|
| **Start Date** | Date input | Cannot adjust promotion start |
| **End Date** | Date input | Cannot extend or shorten promotion period |

#### Fields Present in Edit (6):
- ✅ Discount Code
- ✅ Description (not in add modal)
- ✅ Discount Type (percentage/fixed)
- ✅ Discount Value
- ✅ Max Uses (usage limit)
- ✅ Status (is_active)

#### Recommended Fix:
Add start_date and end_date fields to edit modal JavaScript population (lines 2274-2319).

#### Implementation Complexity: **LOW**
- Estimated Time: 2-3 hours
- Requires: Date field additions

---

### 2.2 Admin Users - Edit User Modal

**File:** `views/admin_users.php`  
**Modal IDs:** `add-user-modal` (line 1076) vs `edit-user-modal` (line 1197)  
**Severity:** MEDIUM - Status and password management missing

#### Missing Fields (2 critical capabilities):

| Field Name | Purpose | Business Impact |
|------------|---------|-----------------|
| **Status (is_verified)** | Active/Inactive dropdown | Cannot deactivate users in edit form - must use separate action |
| **Password** | Password change field | Cannot reset passwords through edit interface |

#### Fields Present in Edit (6):
- ✅ First Name
- ✅ Last Name
- ✅ Email
- ✅ Phone
- ✅ Role (Primary Role)
- ✅ Birth Date

#### Note on Assignments:
The add modal uses inline typeahead for coach/team assignments, while the edit modal has a separate "Assignments" tab. This is acceptable as it's a different but functional approach.

#### Recommended Fix:
Add an "Account Status" field and a "Change Password" section to the edit modal. Password should be optional in edit mode.

#### Implementation Complexity: **LOW-MEDIUM**
- Estimated Time: 3-4 hours
- Requires: Status toggle, optional password field with confirmation

---

## Priority 3: LOW - Nice to Have

These are either working as intended or have acceptable workarounds.

### 3.1 Admin Categories - All Category Modals ✅ GOOD

**File:** `views/admin_categories.php`  
**Modals Analyzed:** Skills, Drill Types, Merchandise Categories, Teams, Locations

#### Status: **NO ISSUES FOUND**

All modal pairs have complete field parity:
- ✅ Skill Modal: Name, Description (complete)
- ✅ Drill Type Modal: Name, Position Category, Description (complete)
- ✅ Merchandise Category Modal: Name, Description (complete)
- ✅ Team Modal: Name, Age Group, Skill Level, Division, Seasons, Coaches, Status (complete)
- ✅ Location Modal: Search, Arena Name, City, Image (complete)

**Note:** Add-only modals exist for Skill Level, Season, and Age Group (no edit counterpart), but these are likely intentional for data integrity.

---

### 3.2 Accounting Expenses - Edit Expense Modal ✅ COMPLETE

**File:** `views/accounting_expenses.php`  
**Modals:** `add-expense-card` vs `edit-expense-modal`

#### Status: **NO ISSUES FOUND**

All 9 fields present in both forms:
- ✅ Expense Date
- ✅ Vendor Name
- ✅ Category
- ✅ Subtotal
- ✅ Tax Amount
- ✅ Total Amount (calculated)
- ✅ Payment Method
- ✅ Description
- ✅ Receipt File

---

## Implementation Roadmap

### Phase 1: Critical Fixes (Week 1-2)
1. ✅ Merchandise Product - Size/Stock Management (1.3)
2. ✅ Session - Date Management & Skill Types (1.1)
3. ✅ HR Payroll - Banking & Address Fields (1.4)

### Phase 2: High Priority (Week 3)
4. ✅ Package - Type & Demographic Fields (1.2)
5. ✅ User - Status & Password Management (2.2)

### Phase 3: Medium Priority (Week 4)
6. ✅ Discount - Date Range Management (2.1)

---

## Technical Notes

### Common Patterns Observed:

1. **Dynamic Loading:** All edit modals in accounting_products.php use AJAX to load data dynamically via `populateEditModal()` function
2. **Empty Shells:** Edit modals are initially empty with loading spinners, populated by JavaScript
3. **Inconsistent Completeness:** Some modals are well-maintained (categories, expenses), others severely incomplete (sessions, packages)
4. **No Backend Issues:** The backend endpoints appear complete - the issue is purely frontend form generation

### Files Requiring Changes:

| File | Lines to Modify | Functions to Update |
|------|-----------------|---------------------|
| `views/accounting_products.php` | 1448-1627, 2134-2388 | populateEditModal() for sessions, packages, discounts, merchandise |
| `views/hr_payroll.php` | Modal structure & JS | editPayrollModal population |
| `views/admin_users.php` | 1197+, JS functions | Edit user modal structure |

---

## Success Metrics

After implementing fixes, verify:
- [ ] All edit modals have 100% field parity with their add/create counterparts (or documented exceptions)
- [ ] Users can modify all business-critical data without recreating records
- [ ] No data loss or workaround requirements
- [ ] Consistent user experience across all edit operations

---

## Appendix A: Modal Inventory

### Complete Modal List (13 pairs analyzed):

| File | Modal Pair | Status |
|------|------------|--------|
| accounting_products.php | Session | ⚠️ 5 fields missing |
| accounting_products.php | Package | ⚠️ 5 fields missing |
| accounting_products.php | Discount | ⚠️ 2 fields missing |
| accounting_products.php | Merchandise Product | ⚠️ 1 critical field missing |
| admin_users.php | User | ⚠️ 2 fields missing |
| admin_categories.php | Skill | ✅ Complete |
| admin_categories.php | Drill Type | ✅ Complete |
| admin_categories.php | Merchandise Category | ✅ Complete |
| admin_categories.php | Team | ✅ Complete |
| admin_categories.php | Location | ✅ Complete |
| accounting_expenses.php | Expense | ✅ Complete |
| hr_payroll.php | Payroll | ⚠️ 12 fields missing |

**Total Issues:** 7 of 13 modal pairs have missing fields (54% incomplete)  
**Fields Missing Across Platform:** 32 fields total

---

## Appendix B: Quick Reference - JavaScript Functions to Update

```javascript
// accounting_products.php - Session Edit
// Function: populateEditModal() - Lines 2170-2229
// Add: session_type, skill_ids[], session_dates[], show_on_landing, is_template

// accounting_products.php - Package Edit  
// Function: populateEditModal() - Lines 2232-2272
// Add: package_type, number_of_sessions, store_credit_value, age_group, skill_level

// accounting_products.php - Discount Edit
// Function: populateEditModal() - Lines 2275-2318  
// Add: start_date, end_date

// accounting_products.php - Merchandise Edit
// Function: populateEditModal() - Lines 2328-2387
// Add: Dynamic size/stock rows with existing data

// hr_payroll.php - Payroll Edit
// Needs major modal restructure + JS population function
// Add: All address fields, banking fields, pension fields, start_date, notes

// admin_users.php - User Edit  
// Needs status field and password section
// Add: is_verified dropdown, password change section
```

---

**End of Report**

Generated by: Arctic Wolves Development Team  
Report Version: 1.0  
Last Updated: December 2024
