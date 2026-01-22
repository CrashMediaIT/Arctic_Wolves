# DEPLOYMENT TESTING CHECKLIST
**Date:** 2026-01-21  
**Branch:** copilot/add-health-coach-role  
**Status:** Ready for Deployment Testing  
**Feature Parity:** ✅ 100% Complete

---

## OVERVIEW

This checklist provides a comprehensive testing procedure for deploying the CRASH HOCKEY application after achieving 100% feature parity. Follow each section in order to ensure a successful deployment.

---

## PHASE 1: PRE-DEPLOYMENT TESTS ✅

### 1.1 Code Quality
- [x] **PHP Syntax Validation** - 186/186 files passed
- [x] **File Structure** - All directories present
- [x] **Security Components** - All present
- [x] **Documentation** - Complete (26 files)
- [x] **Feature Parity** - 100% complete (+138 files)

### 1.2 Repository Status
- [x] All changes committed
- [x] Branch up to date
- [ ] Tags created for release
- [ ] Changelog updated

### 1.3 Configuration Files
- [x] `database_schema.sql` - 650 lines, 44 tables
- [x] `.htaccess` - Present
- [x] `db_config.php` - Template ready
- [x] `security.php` - Complete
- [x] Deployment configs in `deployment/`

---

## PHASE 2: ENVIRONMENT SETUP

### 2.1 Server Requirements
- [ ] **Operating System:** Linux (Ubuntu 20.04+ recommended)
- [ ] **Web Server:** Apache 2.4+ or Nginx 1.18+
- [ ] **PHP Version:** 8.1+ (tested with 8.3.6)
- [ ] **Database:** MySQL 5.7+ or MariaDB 10.3+
- [ ] **SSL Certificate:** Valid certificate installed

### 2.2 PHP Extensions Required
```bash
php -m | grep -E '(pdo|pdo_mysql|mysqli|json|curl|zip|gd|mbstring|xml|openssl|fileinfo)'
```
- [ ] `pdo`
- [ ] `pdo_mysql`
- [ ] `mysqli`
- [ ] `json`
- [ ] `curl`
- [ ] `zip`
- [ ] `gd`
- [ ] `mbstring`
- [ ] `xml`
- [ ] `openssl`
- [ ] `fileinfo`

### 2.3 Directory Permissions
```bash
# Set ownership (adjust as needed)
sudo chown -R www-data:www-data /var/www/crashhockey

# Set directory permissions
find /var/www/crashhockey -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/crashhockey -type f -exec chmod 644 {} \;

# Writable directories
chmod 777 /var/www/crashhockey/uploads
chmod 777 /var/www/crashhockey/cache
chmod 777 /var/www/crashhockey/logs
chmod 777 /var/www/crashhockey/tmp
chmod 777 /var/www/crashhockey/backups
chmod 777 /var/www/crashhockey/receipts
chmod 777 /var/www/crashhockey/videos
```

- [ ] Web root owned by web server user
- [ ] Files: 644 permissions
- [ ] Directories: 755 permissions
- [ ] Writable directories: 777 (uploads, cache, logs, tmp, backups)

### 2.4 Apache/Nginx Configuration
**Apache:**
```bash
# Copy config
sudo cp deployment/crashhockey.conf /etc/apache2/sites-available/

# Enable site
sudo a2ensite crashhockey.conf

# Enable required modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Test config
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```
- [ ] Virtual host configured
- [ ] mod_rewrite enabled
- [ ] SSL configured
- [ ] Config tested
- [ ] Server restarted

**Nginx:**
- [ ] Server block configured
- [ ] PHP-FPM configured
- [ ] SSL configured
- [ ] Config tested
- [ ] Server restarted

### 2.5 PHP Configuration
```bash
# Copy recommended config
sudo cp deployment/php-config.ini /etc/php/8.3/apache2/conf.d/99-crashhockey.ini

# Restart PHP-FPM (if using Nginx)
sudo systemctl restart php8.3-fpm
```

**Check Settings:**
- [ ] `upload_max_filesize = 50M`
- [ ] `post_max_size = 50M`
- [ ] `memory_limit = 256M`
- [ ] `max_execution_time = 300`
- [ ] `session.gc_maxlifetime = 7200`
- [ ] `date.timezone` set correctly

### 2.6 Database Setup
```sql
-- Create database
CREATE DATABASE crashhockey CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'crashhockey_user'@'localhost' IDENTIFIED BY 'SECURE_PASSWORD_HERE';

-- Grant privileges
GRANT ALL PRIVILEGES ON crashhockey.* TO 'crashhockey_user'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;
```

- [ ] Database created
- [ ] Database user created
- [ ] Strong password generated
- [ ] Privileges granted
- [ ] Connection tested

---

## PHASE 3: SETUP WIZARD TESTING

### 3.1 Access Setup Wizard
```
https://your-domain.com/setup.php
```
- [ ] Setup wizard loads
- [ ] No PHP errors displayed
- [ ] Page displays correctly
- [ ] SSL working (https)

### 3.2 Step 1: Database Configuration
**Test Items:**
- [ ] Database host input (localhost)
- [ ] Database name input
- [ ] Database username input
- [ ] Database password input (masked)
- [ ] Port input (3306)
- [ ] Connection test button works
- [ ] Error messages displayed for invalid credentials
- [ ] Success message for valid credentials
- [ ] Proceed to next step

**Validation:**
```bash
# Test database connection manually
mysql -h localhost -u crashhockey_user -p crashhockey
```

### 3.3 Step 2: Database Schema Installation
**Test Items:**
- [ ] Schema installation starts
- [ ] Progress indicator displays
- [ ] All 44 tables created
- [ ] No SQL errors
- [ ] Success confirmation
- [ ] Proceed to next step

**Validation:**
```sql
-- Check table count
USE crashhockey;
SHOW TABLES;
SELECT COUNT(*) FROM information_schema.tables 
WHERE table_schema = 'crashhockey';
-- Should return 44
```

### 3.4 Step 3: Admin Account Creation
**Test Items:**
- [ ] First name input
- [ ] Last name input
- [ ] Email input (with validation)
- [ ] Username input (unique check)
- [ ] Password input (strength meter)
- [ ] Password confirmation
- [ ] Role selection (should be Administrator)
- [ ] Form validation works
- [ ] Admin user created successfully
- [ ] Proceed to next step

**Validation:**
```sql
-- Check admin user
SELECT id, username, email, role FROM users WHERE role = 'Administrator';
```

### 3.5 Step 4: System Settings
**Test Items:**
- [ ] Organization name input
- [ ] Timezone selection
- [ ] Currency selection
- [ ] Date format selection
- [ ] Email configuration (optional)
- [ ] SMTP settings (if applicable)
- [ ] Test email button (if configured)
- [ ] Settings saved
- [ ] Setup completion message

### 3.6 Setup Completion
**Test Items:**
- [ ] Setup completion page displays
- [ ] Success message shown
- [ ] "Go to Login" button present
- [ ] `config.php` file created
- [ ] Database config written
- [ ] Setup wizard locked (cannot rerun)
- [ ] Redirect to login page

**Validation:**
```bash
# Check config file created
ls -la config.php

# Verify setup lock
cat config.php | grep "define('SETUP_COMPLETE'"
```

---

## PHASE 4: AUTHENTICATION TESTING

### 4.1 Admin Login
- [ ] Navigate to login page (`/login.php`)
- [ ] Page loads correctly
- [ ] Username field present
- [ ] Password field present (masked)
- [ ] "Remember Me" checkbox
- [ ] Login button present
- [ ] Register link present
- [ ] Forgot password link present

**Test Cases:**
1. [ ] Login with correct credentials → Success
2. [ ] Login with wrong password → Error message
3. [ ] Login with non-existent user → Error message
4. [ ] Login with empty fields → Validation errors
5. [ ] Test "Remember Me" functionality
6. [ ] Test rate limiting (5+ failed attempts)

### 4.2 Session Management
- [ ] Session created on login
- [ ] Session ID stored in cookie
- [ ] Session timeout works (default: 2 hours)
- [ ] Session persists across pages
- [ ] Logout destroys session
- [ ] Cannot access dashboard after logout

**Validation:**
```sql
-- Check session table
SELECT * FROM sessions WHERE user_id = [ADMIN_USER_ID];
```

### 4.3 Dashboard Access
After successful login:
- [ ] Dashboard loads (`/dashboard.php`)
- [ ] User name displayed
- [ ] Role displayed (Administrator)
- [ ] Navigation menu present
- [ ] No PHP errors
- [ ] Quick stats displayed (if applicable)

---

## PHASE 5: CORE FEATURE TESTING

### 5.1 User Management
**Admin Panel → Users:**
- [ ] Create new user (all roles)
- [ ] Edit user profile
- [ ] Change user password
- [ ] Enable/disable user
- [ ] Delete user
- [ ] View user list
- [ ] Search users
- [ ] Filter by role
- [ ] Export user list

**Test Each Role:**
- [ ] Administrator (full access)
- [ ] Health Coach (coach-specific features)
- [ ] Coach (basic coaching features)
- [ ] Parent (view-only)
- [ ] Athlete (limited access)

### 5.2 Athlete Management
**Features to Test:**
- [ ] Create athlete profile
- [ ] Edit athlete details
- [ ] Upload athlete photo
- [ ] View athlete list
- [ ] Search athletes
- [ ] Filter by team/age/skill
- [ ] Assign athlete to coach
- [ ] Athlete statistics
- [ ] Athlete progress tracking
- [ ] Parent assignment
- [ ] Delete athlete

**Validation:**
```sql
SELECT COUNT(*) FROM athletes;
SELECT * FROM athletes WHERE id = [TEST_ATHLETE_ID];
```

### 5.3 Session Management
**Create Session:**
- [ ] Session creation form
- [ ] Date/time picker
- [ ] Location selection
- [ ] Session type selection
- [ ] Capacity setting
- [ ] Price setting
- [ ] Description
- [ ] Create session successfully

**Manage Sessions:**
- [ ] View session list
- [ ] Edit session details
- [ ] Cancel session
- [ ] View registrations
- [ ] Check-in attendees
- [ ] Session feedback
- [ ] Session history

### 5.4 Booking & Registration
- [ ] Public session listing
- [ ] Session details view
- [ ] Registration form
- [ ] Payment processing (if enabled)
- [ ] Registration confirmation
- [ ] Email notification
- [ ] Cancel registration
- [ ] Waitlist functionality

### 5.5 Goals System ⭐ NEW
**Goal Management:**
- [ ] Create goal
- [ ] Assign goal to athlete
- [ ] Set goal deadline
- [ ] Goal categories
- [ ] Goal templates
- [ ] Goal progress tracking
- [ ] Update goal status
- [ ] Goal completion
- [ ] Goal history

**Goal Evaluation:**
- [ ] Create goal evaluation
- [ ] Multi-criteria assessment
- [ ] Progress notes
- [ ] Goal approval workflow
- [ ] Coach feedback
- [ ] Parent view of goals

**Validation:**
```sql
SELECT COUNT(*) FROM goals;
SELECT * FROM goal_evaluations WHERE goal_id = [TEST_GOAL_ID];
```

### 5.6 Skills Evaluation ⭐ NEW
**Skills Assessment:**
- [ ] Create skill evaluation
- [ ] Select evaluation template
- [ ] Multi-criteria scoring
- [ ] Skill level assignment
- [ ] Progress tracking
- [ ] Historical comparison
- [ ] Evaluation reports
- [ ] Coach comments
- [ ] Parent notifications

**Evaluation Framework:**
- [ ] Create evaluation template
- [ ] Define criteria
- [ ] Set scoring ranges
- [ ] Age/skill level configuration
- [ ] Template management

**Validation:**
```sql
SELECT COUNT(*) FROM evaluation_templates;
SELECT * FROM skill_evaluations WHERE athlete_id = [TEST_ATHLETE_ID];
```

### 5.7 Package Management ⭐ NEW
**Package Administration:**
- [ ] Create package
- [ ] Set package pricing
- [ ] Define session credits
- [ ] Set expiration
- [ ] Package visibility
- [ ] Edit package
- [ ] Disable package

**Package Purchasing:**
- [ ] Browse packages
- [ ] View package details
- [ ] Add to cart
- [ ] Apply discount code
- [ ] Complete purchase
- [ ] Payment confirmation
- [ ] Credits applied to account
- [ ] Purchase history

**Refund Processing:**
- [ ] Refund request
- [ ] Admin refund approval
- [ ] Partial refunds
- [ ] Credit restoration
- [ ] Refund history

**Validation:**
```sql
SELECT COUNT(*) FROM packages;
SELECT COUNT(*) FROM package_purchases;
SELECT * FROM user_credits WHERE user_id = [TEST_USER_ID];
```

### 5.8 Financial Tracking ⭐ NEW
**Expense Management:**
- [ ] Record expense
- [ ] Categorize expense
- [ ] Attach receipt
- [ ] Edit expense
- [ ] Delete expense
- [ ] Export expenses

**Mileage Tracking:**
- [ ] Log mileage
- [ ] Calculate reimbursement
- [ ] Mileage reports
- [ ] Export mileage data

**Financial Reports:**
- [ ] Income dashboard
- [ ] Expense summary
- [ ] Billing dashboard
- [ ] Payment history
- [ ] Accounts payable
- [ ] Export financial data

**Validation:**
```sql
SELECT SUM(amount) FROM expenses;
SELECT SUM(distance * rate) FROM mileage;
```

### 5.9 Content & Training ⭐ NEW
**Drill Library:**
- [ ] Create drill
- [ ] Categorize drill
- [ ] Add drill description
- [ ] Upload drill video
- [ ] Edit drill
- [ ] Delete drill
- [ ] Search drills
- [ ] Filter by category

**Practice Plans:**
- [ ] Create practice plan
- [ ] Add drills to plan
- [ ] Set duration
- [ ] Plan categories
- [ ] Copy existing plan
- [ ] Edit plan
- [ ] Delete plan
- [ ] Assign to session

**Content Libraries:**
- [ ] Workout library
- [ ] Nutrition library
- [ ] Session library
- [ ] Search content
- [ ] Filter by category

### 5.10 Reporting & Analytics ⭐ NEW
**Generate Reports:**
- [ ] Income report
- [ ] Athlete progress report
- [ ] Session attendance report
- [ ] Financial summary
- [ ] Custom date range
- [ ] Export to PDF
- [ ] Export to CSV
- [ ] Schedule recurring reports

**Report Viewer:**
- [ ] View saved reports
- [ ] Filter reports
- [ ] Delete old reports

---

## PHASE 6: SECURITY VALIDATION

### 6.1 CSRF Protection ⭐ NEW
- [ ] CSRF tokens generated on forms
- [ ] Tokens validated on submission
- [ ] Invalid token rejected
- [ ] Token rotation works
- [ ] No token bypass possible

**Test:**
```bash
# Try to submit form without token
curl -X POST https://your-domain.com/process_*.php -d "param=value"
# Should be rejected
```

### 6.2 SQL Injection Prevention
**Test Cases:**
- [ ] Try `' OR '1'='1` in login
- [ ] Try SQL injection in search fields
- [ ] Try injection in URL parameters
- [ ] Verify prepared statements used
- [ ] Check error messages don't reveal SQL

**Example Tests:**
```
Username: admin' OR '1'='1'--
Search: '; DROP TABLE users; --
ID: 1 OR 1=1
```
All should be safely escaped or rejected.

### 6.3 XSS Prevention
**Test Cases:**
- [ ] Try `<script>alert('XSS')</script>` in text fields
- [ ] Try injection in profile fields
- [ ] Try injection in comments
- [ ] Verify output escaping
- [ ] Check for reflected XSS

### 6.4 File Upload Security ⭐ NEW
**Test Cases:**
- [ ] Upload valid image → Success
- [ ] Upload PHP file → Rejected
- [ ] Upload oversized file → Rejected
- [ ] Upload with fake extension → Rejected
- [ ] Verify file type checking
- [ ] Verify filename sanitization
- [ ] Check upload directory permissions

**Validation:**
```bash
# Try to upload malicious file
file uploads/test.jpg  # Should be actual image, not PHP
```

### 6.5 Authentication Security
- [ ] Passwords hashed (bcrypt)
- [ ] No plaintext passwords
- [ ] Password strength enforced
- [ ] Account lockout after 5 failed attempts
- [ ] Session timeout works
- [ ] Session hijacking prevention
- [ ] Force password change works

**Validation:**
```sql
-- Check password hashing
SELECT username, password FROM users LIMIT 5;
-- Should see bcrypt hashes ($2y$...)
```

### 6.6 Authorization & Permissions ⭐ NEW
**Role-Based Access Control:**
- [ ] Admin can access all features
- [ ] Coach cannot access admin panel
- [ ] Parent has view-only access
- [ ] Athlete has limited access
- [ ] Permission checks work
- [ ] Direct URL access blocked

**Test:**
```bash
# Try to access admin panel as coach
# Should be redirected or show access denied
```

### 6.7 Rate Limiting
- [ ] Login rate limiting (5 attempts)
- [ ] API rate limiting
- [ ] IP-based throttling
- [ ] Lockout duration (15 minutes)
- [ ] Lockout notification

---

## PHASE 7: DATABASE MANAGEMENT TESTING ⭐ NEW

### 7.1 Database Backup
**Admin Panel → Database Tools → Backup:**
- [ ] Backup interface loads
- [ ] Backup options displayed
- [ ] Start backup
- [ ] Progress indicator
- [ ] Backup completes
- [ ] Backup file created in `/backups`
- [ ] Backup file downloadable
- [ ] Backup includes all tables
- [ ] Backup compressed (gzip)

**Validation:**
```bash
ls -lh backups/
zcat backups/backup_*.sql.gz | head -50
```

### 7.2 Database Restore
**Admin Panel → Database Tools → Restore:**
- [ ] Restore interface loads
- [ ] List available backups
- [ ] Select backup file
- [ ] Preview backup info
- [ ] Confirm restore
- [ ] Restore in progress
- [ ] Restore completes
- [ ] Data restored correctly
- [ ] No data corruption

**Validation:**
```sql
-- After restore, verify data
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM athletes;
-- Compare with pre-restore counts
```

### 7.3 Automated Backups (Cron)
**Configure Cron Job:**
```bash
# Add to crontab
0 2 * * * /usr/bin/php /var/www/crashhockey/cron_database_backup.php
```

- [ ] Cron job configured
- [ ] Test manual execution: `php cron_database_backup.php`
- [ ] Backup created automatically
- [ ] Old backups cleaned up (retention policy)
- [ ] Error logging works
- [ ] Email notification (if configured)

---

## PHASE 8: FEATURE IMPORT TESTING ⭐ NEW

### 8.1 Feature Importer
**Admin Panel → System Tools → Feature Import:**
- [ ] Feature import interface loads
- [ ] Upload feature package (ZIP)
- [ ] Package validation
- [ ] Dependency checking
- [ ] Preview changes
- [ ] Conflict detection
- [ ] Import confirmation
- [ ] Import progress
- [ ] Import success
- [ ] Files extracted correctly
- [ ] Database tables updated
- [ ] No system breakage

**Validation:**
```bash
# Check imported files
ls -la [imported_feature_directory]

# Check logs
tail -f logs/feature_import.log
```

### 8.2 System Validator
**Admin Panel → System Tools → Validator:**
- [ ] System check interface loads
- [ ] Run validation
- [ ] Check PHP version
- [ ] Check PHP extensions
- [ ] Check directory permissions
- [ ] Check database connection
- [ ] Check file integrity
- [ ] Generate report
- [ ] Display warnings/errors
- [ ] Recommendations shown

---

## PHASE 9: AUTOMATED TASKS (CRON) ⭐ NEW

### 9.1 Cron Job Configuration
**Setup All Cron Jobs:**
```bash
# Edit crontab
crontab -e

# Add these lines:
0 2 * * * /usr/bin/php /var/www/crashhockey/cron_database_backup.php
0 3 * * * /usr/bin/php /var/www/crashhockey/cron_security_scan.php
*/15 * * * * /usr/bin/php /var/www/crashhockey/cron_notifications.php
0 1 * * * /usr/bin/php /var/www/crashhockey/cron_audit_cleanup.php
0 0 * * * /usr/bin/php /var/www/crashhockey/cron_stats_snapshot.php
30 8 * * * /usr/bin/php /var/www/crashhockey/cron_session_reminders.php
0 4 * * 0 /usr/bin/php /var/www/crashhockey/cron_receipt_scanner.php
```

### 9.2 Test Each Cron Job
**Database Backup:**
```bash
php cron_database_backup.php
# Check: backups/ directory for new file
```
- [ ] Executes without errors
- [ ] Creates backup file
- [ ] Logs activity

**Security Scan:**
```bash
php cron_security_scan.php
```
- [ ] Scans for vulnerabilities
- [ ] Logs findings
- [ ] Sends alerts (if configured)

**Notification Delivery:**
```bash
php cron_notifications.php
```
- [ ] Sends pending notifications
- [ ] Updates notification status
- [ ] Logs delivery

**Audit Cleanup:**
```bash
php cron_audit_cleanup.php
```
- [ ] Cleans old audit logs
- [ ] Respects retention policy
- [ ] Logs cleanup activity

**Stats Snapshot:**
```bash
php cron_stats_snapshot.php
```
- [ ] Captures daily statistics
- [ ] Stores in database
- [ ] Available for reports

**Session Reminders:**
```bash
php cron_session_reminders.php
```
- [ ] Identifies upcoming sessions
- [ ] Sends reminder emails
- [ ] Logs reminders sent

**Receipt Scanner:**
```bash
php cron_receipt_scanner.php
```
- [ ] Scans receipts directory
- [ ] Processes new receipts
- [ ] Updates expense records

### 9.3 Cron Job Management
**Admin Panel → System → Cron Jobs:**
- [ ] View all cron jobs
- [ ] Last run time
- [ ] Next run time
- [ ] Execution status
- [ ] View logs
- [ ] Enable/disable jobs
- [ ] Manual trigger

---

## PHASE 10: INTEGRATION TESTING

### 10.1 Email Functionality
**Test Email Delivery:**
- [ ] User registration email
- [ ] Email verification
- [ ] Password reset email
- [ ] Session reminder
- [ ] Booking confirmation
- [ ] Goal notification
- [ ] Evaluation notification
- [ ] System notification

**Check Email Logs:**
- [ ] View email log interface
- [ ] See sent emails
- [ ] Delivery status
- [ ] Error messages

### 10.2 File Upload Testing
**Test Upload Types:**
- [ ] Profile photos
- [ ] Athlete photos
- [ ] Drill videos
- [ ] Receipt images
- [ ] Document uploads
- [ ] File size limits enforced
- [ ] File type restrictions work
- [ ] Thumbnails generated (if applicable)

### 10.3 API Integrations (if configured)
**Google API:**
- [ ] Test Google Calendar integration
- [ ] Test Google Drive integration
- [ ] Authentication works
- [ ] Data sync works

**Nextcloud:**
- [ ] Test Nextcloud connection
- [ ] File upload to Nextcloud
- [ ] File retrieval
- [ ] Authentication works

**IHS Import:**
- [ ] IHS data import interface
- [ ] Upload IHS data file
- [ ] Parse data
- [ ] Import to database
- [ ] Validation

---

## PHASE 11: PERFORMANCE TESTING

### 11.1 Page Load Times
**Test Key Pages:**
- [ ] Homepage: < 2 seconds
- [ ] Dashboard: < 3 seconds
- [ ] Athlete list: < 4 seconds
- [ ] Session list: < 3 seconds
- [ ] Reports: < 5 seconds

### 11.2 Database Performance
**Check Query Performance:**
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;

-- Monitor slow queries
SHOW GLOBAL STATUS LIKE 'Slow_queries';
```
- [ ] No slow queries (< 1 second)
- [ ] Indexes optimized
- [ ] Query cache enabled

### 11.3 Concurrent Users
**Load Testing:**
- [ ] 10 concurrent users
- [ ] 50 concurrent users
- [ ] 100 concurrent users
- [ ] No timeout errors
- [ ] Response times acceptable
- [ ] No database locks

### 11.4 File Upload Performance
- [ ] Upload 10MB file: < 30 seconds
- [ ] Upload 50MB file: < 2 minutes
- [ ] Multiple concurrent uploads
- [ ] No memory exhaustion

---

## PHASE 12: BROWSER COMPATIBILITY

### 12.1 Desktop Browsers
- [ ] Google Chrome (latest)
- [ ] Mozilla Firefox (latest)
- [ ] Safari (latest)
- [ ] Microsoft Edge (latest)

### 12.2 Mobile Browsers
- [ ] Mobile Chrome (Android)
- [ ] Mobile Safari (iOS)
- [ ] Mobile Firefox

### 12.3 Responsive Design
- [ ] Desktop (1920x1080)
- [ ] Laptop (1366x768)
- [ ] Tablet (768x1024)
- [ ] Mobile (375x667)

---

## PHASE 13: USER ACCEPTANCE TESTING

### 13.1 Administrator Workflow
- [ ] Complete system setup
- [ ] Create users (all roles)
- [ ] Configure system settings
- [ ] Set up packages
- [ ] Create session types
- [ ] Configure locations
- [ ] Set up evaluation templates
- [ ] Test all admin features

### 13.2 Coach Workflow
- [ ] Login as coach
- [ ] View assigned athletes
- [ ] Create session
- [ ] Take attendance
- [ ] Create goal for athlete
- [ ] Conduct skills evaluation
- [ ] View reports
- [ ] Log expense
- [ ] Log mileage

### 13.3 Parent Workflow
- [ ] Login as parent
- [ ] View athlete(s)
- [ ] See upcoming sessions
- [ ] View athlete goals
- [ ] View evaluation results
- [ ] Book session
- [ ] Purchase package
- [ ] View payment history

### 13.4 Athlete Workflow (if applicable)
- [ ] Login as athlete
- [ ] View own profile
- [ ] View goals
- [ ] View evaluation results
- [ ] View schedule
- [ ] Update profile

---

## PHASE 14: PRODUCTION READINESS

### 14.1 Security Hardening
- [ ] Change default passwords
- [ ] Remove test accounts
- [ ] Disable error display
- [ ] Enable error logging
- [ ] Configure firewall
- [ ] SSL certificate valid
- [ ] HTTP→HTTPS redirect
- [ ] Security headers configured
- [ ] Remove setup wizard access

### 14.2 Backup Strategy
- [ ] Automated daily backups
- [ ] Off-site backup storage
- [ ] Backup retention policy (30 days)
- [ ] Test restore procedure
- [ ] Document backup process

### 14.3 Monitoring Setup
- [ ] Server monitoring
- [ ] Application monitoring
- [ ] Database monitoring
- [ ] Error logging
- [ ] Uptime monitoring
- [ ] Alert notifications

### 14.4 Documentation
- [ ] Admin documentation
- [ ] User documentation
- [ ] Deployment documentation
- [ ] Backup/restore procedures
- [ ] Troubleshooting guide
- [ ] Support contact information

---

## PHASE 15: GO-LIVE CHECKLIST

### 15.1 Final Verification
- [ ] All tests passed
- [ ] No critical issues
- [ ] Performance acceptable
- [ ] Security validated
- [ ] Backups configured
- [ ] Monitoring active
- [ ] Documentation complete

### 15.2 Launch Preparation
- [ ] Maintenance page ready
- [ ] DNS updated (if needed)
- [ ] SSL certificate verified
- [ ] Email configured
- [ ] Support team briefed
- [ ] Rollback plan documented

### 15.3 Launch Execution
- [ ] Enable maintenance mode
- [ ] Deploy final code
- [ ] Run database migrations
- [ ] Verify deployment
- [ ] Run smoke tests
- [ ] Disable maintenance mode
- [ ] Monitor for issues

### 15.4 Post-Launch
- [ ] Monitor error logs
- [ ] Monitor performance
- [ ] Monitor user feedback
- [ ] Address urgent issues
- [ ] Schedule follow-up review (7 days)

---

## ISSUE TRACKING

### Critical Issues
| # | Issue | Status | Assigned | Notes |
|---|-------|--------|----------|-------|
| 1 | | | | |

### High Priority Issues
| # | Issue | Status | Assigned | Notes |
|---|-------|--------|----------|-------|
| 1 | | | | |

### Medium Priority Issues
| # | Issue | Status | Assigned | Notes |
|---|-------|--------|----------|-------|
| 1 | | | | |

### Low Priority Issues
| # | Issue | Status | Assigned | Notes |
|---|-------|--------|----------|-------|
| 1 | | | | |

---

## TESTING NOTES

### Test Environment
- **Server:** 
- **PHP Version:** 
- **Database:** 
- **Domain:** 
- **Tested By:** 
- **Date Started:** 
- **Date Completed:** 

### Known Issues
1. 

### Deferred Items
1. 

### Recommendations
1. 

---

## SIGN-OFF

### Testing Team
- [ ] **Lead Tester:** __________________ Date: ______
- [ ] **Security Tester:** __________________ Date: ______
- [ ] **Performance Tester:** __________________ Date: ______

### Approval
- [ ] **Project Manager:** __________________ Date: ______
- [ ] **Technical Lead:** __________________ Date: ______
- [ ] **Client/Stakeholder:** __________________ Date: ______

---

**Checklist Version:** 1.0  
**Last Updated:** 2026-01-21  
**Total Test Items:** 400+  
**Estimated Testing Time:** 40-60 hours  
**Status:** Ready for Use ✅
