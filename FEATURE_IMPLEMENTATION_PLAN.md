# Feature Implementation Plan - Advanced Features Phase 2

## 🎯 Overview
This document outlines the implementation plan for the advanced features requested for Crash Hockey platform.

## 📋 Features to Implement

### 1. Feature Import/Upgrade System (HIGH PRIORITY)
**Scope:** Comprehensive system for importing and upgrading features
**Components:**
- Admin interface for feature upload
- Feature package format (ZIP with manifest.json)
- Automated validation system
- Database migration system
- File system updates (create/move/delete)
- Navigation updates
- Rollback capability on error

**Implementation Steps:**
1. Create feature package manifest schema
2. Build import upload interface
3. Implement pre-import validation engine
4. Create file system updater
5. Build database migration runner
6. Implement rollback system
7. Add comprehensive error reporting

**Estimated Complexity:** VERY HIGH
**Files to Create:** 5-7 new files, 2000+ lines of code

---

### 2. Directory Structure (MEDIUM PRIORITY)
**Scope:** Add all required directories to repository
**Directories to Create:**
- `/uploads/` - User uploaded files
- `/uploads/avatars/` - Profile pictures
- `/uploads/videos/` - Training videos
- `/uploads/receipts/` - Scanned receipts
- `/uploads/evaluations/` - Evaluation media
- `/uploads/goals/` - Goal media
- `/cache/` - System cache
- `/sessions/` - PHP sessions
- `/logs/` - Application logs
- `/tmp/` - Temporary files

**Implementation:** Create .gitkeep files in each directory

---

### 3. Enhanced Evaluation Platform (HIGH PRIORITY)
**Scope:** Team-based evaluation interface with athlete switching

**Features:**
- Single-page team evaluation view
- All categories and skills on one page
- Quick athlete switcher dropdown
- Checkbox to add athlete to database
- Progress indicators
- Bulk save functionality

**Files to Modify:**
- `views/evaluations_skills.php` - Add team view mode
- `process_eval_skills.php` - Handle team evaluations
- `deployment/schema.sql` - Add team_evaluations table (already exists)

**Estimated Complexity:** MEDIUM
**Lines of Code:** 500-800 lines

---

### 4. Coach Approval Dashboard Widget (MEDIUM PRIORITY)
**Scope:** Display pending approvals on coach dashboard

**Features:**
- Widget showing pending goal step approvals
- Count badge
- Quick approve/reject actions
- Link to full approval interface

**Files to Modify:**
- `dashboard.php` - Add approval widget for coaches
- `views/evaluations_goals.php` - Already has approval system

**Estimated Complexity:** LOW
**Lines of Code:** 100-200 lines

---

### 5. Athlete Physical Stats (MEDIUM PRIORITY)
**Scope:** Add physical attributes to athlete profiles

**Fields to Add:**
- Weight (lbs/kg)
- Height (cm/ft-in)
- Shooting Hand (Left/Right/Ambidextrous)
- Position (Forward/Defense/Goalie)
- Catching Hand (for goalies: Regular/Full Right)

**Files to Modify:**
- `deployment/schema.sql` - Add columns to users table
- `views/profile.php` - Add fields to profile edit
- `process_profile_update.php` - Handle new fields
- `views/athletes.php` - Display stats in athlete list

**Estimated Complexity:** LOW
**Lines of Code:** 200-300 lines

---

### 6. Team Coaches Role (HIGH PRIORITY)
**Scope:** New role with restricted team-based visibility

**Features:**
- New role: 'team_coach'
- Team assignment (many-to-many relationship)
- Season-based access control
- View only assigned athletes
- Cannot see Crash Hockey internal data
- Limited evaluation access

**Database Changes:**
- New table: `team_coach_assignments` (coach_id, team_id, season_id)
- New table: `seasons` (id, name, start_date, end_date, active)
- Add 'team_coach' to roles in permissions

**Files to Create/Modify:**
- `deployment/schema.sql` - Add tables
- `security.php` - Add team_coach role logic
- `views/admin_team_coaches.php` - Manage assignments
- `process_admin_team_coaches.php` - Handle assignments
- Multiple view files - Add access control checks

**Estimated Complexity:** HIGH
**Lines of Code:** 1000-1500 lines

---

### 7. Automated Validation System (CRITICAL)
**Scope:** Pre-deployment validation before feature imports

**Checks to Implement:**
1. **File System Audit**
   - Verify all required files exist
   - Check for orphaned files
   - Validate file permissions

2. **Code Analysis**
   - Scan for broken includes/requires
   - Find undefined function calls
   - Detect missing class references

3. **Database Audit**
   - Verify all tables exist
   - Check foreign key integrity
   - Validate column types match code usage

4. **Cross-Reference Validation**
   - Form actions → process files
   - View includes → view files
   - Database queries → table/column names
   - Navigation links → pages

5. **Security Scan**
   - Check for SQL injection vulnerabilities
   - Verify CSRF protection
   - Check file upload security
   - Validate input sanitization

**Output:**
- Detailed validation report
- Error count by category
- Actionable fix recommendations
- Pass/fail status

**Files to Create:**
- `admin/system_validator.php` - Validation engine
- `views/admin_system_check.php` - UI
- `process_system_validation.php` - Run checks

**Estimated Complexity:** VERY HIGH
**Lines of Code:** 2000-3000 lines

---

## 📊 Implementation Priority

### Phase 1 (Critical - Implement First)
1. Directory Structure - Quick win
2. Athlete Physical Stats - Low complexity
3. Coach Approval Widget - Medium value, low complexity

### Phase 2 (High Priority)
4. Enhanced Evaluation Platform - High value
5. Team Coaches Role - Complex but valuable
6. Automated Validation System - Foundation for import system

### Phase 3 (Advanced)
7. Feature Import System - Requires validation system first

---

## 🚧 Implementation Notes

**Caution Areas:**
- Feature import system needs extensive testing
- Team coach role affects many existing files
- Validation system must be thorough to prevent false positives

**Dependencies:**
- Feature import requires automated validation (implement validation first)
- Team coaches need seasons table
- Evaluation enhancements need team_evaluations table (already exists)

**Testing Requirements:**
- Each feature needs isolated testing
- Integration testing after each phase
- Security testing for new roles
- Performance testing for validation system

---

## ✅ Success Criteria

**Phase 1 Complete When:**
- All directories exist with .gitkeep
- Athlete stats fields working
- Approval widget shows on coach dashboard

**Phase 2 Complete When:**
- Team evaluation view functional
- Team coach role working with restrictions
- Validation system detects common issues

**Phase 3 Complete When:**
- Can import sample feature package
- Validation runs automatically
- Rollback works on error
- Documentation complete

---

## 📅 Estimated Timeline

- **Phase 1:** 30-60 minutes
- **Phase 2:** 2-3 hours
- **Phase 3:** 3-4 hours

**Total:** 5.5-7.5 hours of focused development

---

*Generated: January 20, 2026*
