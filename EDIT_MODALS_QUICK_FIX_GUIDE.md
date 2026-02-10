# Edit Modals - Quick Fix Guide

This is a concise action guide derived from the comprehensive EDIT_MODALS_AUDIT_REPORT.md

## 🚨 Fix Priority Order

### Priority 1: CRITICAL (Fix Immediately)
1. **Merchandise Product - Size/Stock** - Inventory completely broken
2. **Session Edit - Dates & Skills** - Cannot reschedule or change training focus
3. **HR Payroll - Banking Info** - Cannot update direct deposit

### Priority 2: HIGH (Fix This Week)
4. **Package Edit - Complete Fields** - Cannot modify package structure
5. **User Edit - Status & Password** - No account management

### Priority 3: MEDIUM (Fix Next Week)
6. **Discount Edit - Date Range** - Cannot adjust promotion periods

---

## 🔧 Quick Fix Checklist

### 1. Merchandise Product Edit (4-5 hours)
**File:** `views/accounting_products.php`
- [ ] Line 1614: Add size/stock container to edit modal HTML
- [ ] Line 2328-2387: Update `populateEditModal()` to load existing sizes
- [ ] Add dynamic row generation for sizes (copy from add modal lines 1569-1581)
- [ ] Test: Edit product → Add/remove sizes → Save → Verify inventory

### 2. Session Edit (4-6 hours)
**File:** `views/accounting_products.php`
- [ ] Line 1448: Expand edit modal HTML to include 5 missing sections
- [ ] Line 2170-2229: Update `populateEditModal()` function
  - [ ] Add session_type dropdown
  - [ ] Add skill_ids[] checkboxes (copy from add modal 1166-1182)
  - [ ] Add session_dates dynamic rows (copy from add modal 1184-1211)
  - [ ] Add show_on_landing checkbox
  - [ ] Add is_template checkbox
- [ ] Backend: Update `get_session` endpoint to return session_dates
- [ ] Test: Edit session → Change type → Add dates → Toggle visibility

### 3. HR Payroll Edit (5-8 hours)
**File:** `views/hr_payroll.php`
- [ ] Restructure `editPayrollModal` to add 3 sections:
  - [ ] Employment section (start_date)
  - [ ] Address section (street, unit, city, province, postal)
  - [ ] Banking section (institution, transit, account numbers)
  - [ ] Pension section (employee rate, employer match - conditional)
  - [ ] Notes textarea
- [ ] Create/update JavaScript population function
- [ ] Backend: Ensure payroll endpoint returns all fields
- [ ] Test: Edit payroll → Update banking → Save → Verify

### 4. Package Edit (5-7 hours)
**File:** `views/accounting_products.php`
- [ ] Line 1464: Add conditional sections to edit modal
- [ ] Line 2231-2273: Update `populateEditModal()` function
  - [ ] Add package_type selector with change handler
  - [ ] Add number_of_sessions (show if type=credits)
  - [ ] Add store_credit_value (show if type=dollar_value)
  - [ ] Add age_group dropdown
  - [ ] Add skill_level dropdown
- [ ] Test: Edit package → Change type → Verify conditional fields

### 5. User Edit (3-4 hours)
**File:** `views/admin_users.php`
- [ ] Line 1197+: Add to edit modal:
  - [ ] Status dropdown (is_verified: Active/Inactive)
  - [ ] Optional password section with confirmation
- [ ] Update JavaScript population
- [ ] Backend: Handle optional password in update endpoint
- [ ] Test: Edit user → Change status → Set new password

### 6. Discount Edit (2-3 hours)
**File:** `views/accounting_products.php`
- [ ] Line 1480: Add date fields to edit modal
- [ ] Line 2274-2319: Update `populateEditModal()` function
  - [ ] Add start_date field
  - [ ] Add end_date field
- [ ] Test: Edit discount → Change dates → Save

---

## 📋 Testing Checklist (After Each Fix)

For each modal fixed:
- [ ] Open add/create modal → Fill all fields → Save
- [ ] Open edit modal → Verify ALL fields populated
- [ ] Modify each field → Save
- [ ] Reload → Verify changes persisted
- [ ] Compare side-by-side with add modal → Confirm field parity

---

## 🔍 Common Code Patterns

### Adding a Simple Field to Edit Modal

**1. Add HTML to modal (in edit modal structure):**
```html
<div class="form-group">
    <label class="form-label">Field Name</label>
    <input type="text" id="edit-field-name" name="field_name" class="form-input">
</div>
```

**2. Populate in JavaScript (in populateEditModal function):**
```javascript
document.getElementById('edit-field-name').value = data.field_name || '';
```

### Adding Dynamic Rows (e.g., sizes, dates)

**1. Create container in edit modal:**
```html
<div id="edit-dynamic-rows-container"></div>
<button onclick="addEditRow()">Add Row</button>
```

**2. Populate from data:**
```javascript
data.rows.forEach((row, index) => {
    const rowHtml = `<div class="row-${index}">...</div>`;
    container.innerHTML += rowHtml;
});
```

### Adding Conditional Fields

**1. Add all fields with hidden states:**
```html
<div id="field-1" style="display: none;">...</div>
<div id="field-2" style="display: none;">...</div>
```

**2. Show/hide based on type:**
```javascript
const type = data.type;
document.getElementById('field-1').style.display = type === 'option1' ? 'block' : 'none';
```

---

## ⚠️ Common Pitfalls to Avoid

1. **Forgetting CSRF token** - Always include `<?= csrfTokenInput() ?>` in forms
2. **Missing backend updates** - Edit endpoints must return ALL fields needed
3. **No field validation** - Add required attributes and JS validation
4. **Incomplete testing** - Test create → edit → save → reload cycle
5. **Breaking existing functionality** - Ensure original fields still work

---

## 📊 Progress Tracking

| Modal | Status | Est. Time | Actual Time | Completed |
|-------|--------|-----------|-------------|-----------|
| Merchandise Product | 🔴 Not Started | 4-5 hrs | - | ☐ |
| Session Edit | 🔴 Not Started | 4-6 hrs | - | ☐ |
| HR Payroll | 🔴 Not Started | 5-8 hrs | - | ☐ |
| Package Edit | 🔴 Not Started | 5-7 hrs | - | ☐ |
| User Edit | 🔴 Not Started | 3-4 hrs | - | ☐ |
| Discount Edit | 🔴 Not Started | 2-3 hrs | - | ☐ |

**Total Estimated Time:** 23-33 hours
**Total Actual Time:** _____

---

## 🎯 Success Criteria

- [ ] All 32 missing fields added across 7 modals
- [ ] 100% field parity between add/create and edit modals
- [ ] All fields populate correctly from backend
- [ ] All fields save correctly to database
- [ ] No regression in existing functionality
- [ ] User can modify ANY data without deleting/recreating records

---

**Quick Reference:** See full analysis in EDIT_MODALS_AUDIT_REPORT.md
