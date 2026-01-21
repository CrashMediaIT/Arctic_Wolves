# Crash Hockey Platform - Updates Log

This file tracks all major updates and changes to the Crash Hockey platform. Each update is organized by date with a clear title describing the primary change.

---

## January 20, 2026 (Latest) - Fix: Permissions Set INSIDE Container

**Primary Changes:**
- **Permission fix**: Changed deployment to set permissions INSIDE container instead of on host
- **Root cause**: PHP sees directory as "Writable: No" even when host permissions are correct
- **Solution**: Using `docker exec nginx chmod/chown` ensures PHP-FPM can write to directory
- **Verification**: Added PHP writability test to confirm directory is writable from PHP's perspective

**Step 7 Updated - Now Sets Permissions Inside Container:**
```bash
docker exec nginx chown -R abc:abc /config/www/crashhockey
docker exec nginx chmod 775 /config/www/crashhockey
docker exec nginx chmod -R 775 /config/www/crashhockey/{uploads,sessions,cache}
```

**Why This Works:**
- Setting permissions on host at `/portainer/nginx` may not reflect correctly inside container at `/config`
- PHP-FPM runs inside container as 'abc' user - needs to see directory as writable
- Setting permissions from inside container ensures correct user/group and writability
- Verified with: `docker exec nginx sh -c '[ -w /config/www/crashhockey ] && echo "Writable"'`

**Troubleshooting Updated:**
- PRIMARY FIX now emphasizes container-based permission setting
- Shows how to verify directory is writable by PHP
- SELinux configuration marked as OPTIONAL (usually not needed with container permissions)

**User Error Output That Led to This Fix:**
```
Failed to write configuration file to: /config/www/crashhockey/crashhockey.env
Error: file_put_contents(...): Failed to open stream: Permission denied
Directory: /config/www/crashhockey
Writable: No  <-- Key issue
Web server user: abc
```

Manual write test worked (`docker exec nginx touch`) but PHP saw directory as "Writable: No". Setting permissions inside container fixed this.

---

## January 20, 2026 - Enhanced Error Reporting for Setup

**Primary Changes:**
- **Detailed error messages**: Setup wizard now shows actual PHP errors instead of generic message
- **Diagnostic information**: Added directory path, writable status, and web server user to error output
- **Better troubleshooting**: Error messages now include specific details to help diagnose permission issues

**Setup.php Changes:**
- Added error capturing with `error_get_last()`
- Display file path being written
- Show if directory is writable
- Display web server user (UID 911 = abc user)
- Helps identify if issue is permissions, SELinux, or something else

**Deployment Guide Updated:**
- Enhanced troubleshooting section for "Failed to write configuration file"
- Added diagnostic steps to verify permissions from inside container
- Clear distinction between manual write test vs. PHP write
- Troubleshooting covers cases where manual test works but setup fails

**Use Case:**
When user reports "Failed to write configuration file" but manual write test works (`docker exec nginx touch` succeeds), the enhanced error message will show:
- Exact file path PHP is trying to write
- Whether directory is writable from PHP's perspective
- Which user PHP is running as
- Actual PHP error message

---

## January 20, 2026 - Complete Orange Elimination

**Primary Changes:**
- **All orange removed**: Eliminated every instance of orange color across entire site (0 remaining)
- **Setup hover states**: Changed button hover from `#ff6a00` orange to `#5a0080` darker purple
- **Consistent purple**: All pages, views, and components now use deep purple theme
- **Verification**: Confirmed 0 orange hex codes, 58 instances of purple color

**Files Updated (9 files):**
- setup.php - Button hover states changed to purple
- process_login.php - Orange error text changed to purple
- public_sessions.php - Capacity warning changed from orange to purple
- views/practice_plans.php - Orange background removed
- views/admin_permissions.php - Orange background removed
- views/ihs_import.php - Orange background removed
- views/drills.php - Orange background removed
- views/settings.php - Pink error messages changed to purple
- views/admin_age_skill.php - Pink error messages changed to purple

**Color Replacements:**
- `#ff6a00` (orange) → `#5a0080` (darker purple for hover)
- `#ffa500` (orange) → `#7000a4` (deep purple)
- `#ff6b7a` (pink/red) → `#7000a4` (deep purple)
- `color:orange` → `color:#7000a4`

---

## January 20, 2026 - SELinux Fix & /portainer/nginx Path

**Primary Changes:**
- **Path correction**: Updated all deployment docs from `/config` to `/portainer/nginx` (actual host path)
- **SELinux handling**: Added critical SELinux context configuration for Fedora
- **Permission clarity**: Clarified that permissions must be set on HOST (not in container)
- **Write test added**: Container write access test to verify permissions before setup

**Key Understanding:**
- **Host path**: `/portainer/nginx` (where files actually exist on Fedora server)
- **Container path**: `/config` (internal container view via bind mount `-v /portainer/nginx:/config`)
- **Set permissions on HOST**: All chmod/chown commands run on `/portainer/nginx` paths

**SELinux Fix (Critical for Fedora):**
```bash
sudo chcon -R -t container_file_t /portainer/nginx/www/crashhockey
sudo semanage fcontext -a -t container_file_t "/portainer/nginx/www/crashhockey(/.*)?"
sudo restorecon -R /portainer/nginx/www/crashhockey
```

**Files Updated:**
- deployment/DEPLOYMENT.md - All paths corrected, SELinux instructions added
- Step 5: Docker command now shows `-v /portainer/nginx:/config`
- Step 7: All permission commands use `/portainer/nginx` paths
- All troubleshooting sections updated with correct paths

**Verification:**
- Write test: `docker exec nginx touch /config/www/crashhockey/test.txt` (uses container path)
- SELinux check: `sudo ausearch -m avc -ts recent` (shows SELinux denials)
- Permissions: `ls -ld /portainer/nginx/www/crashhockey` (should show 775 911:911)

---

## January 20, 2026 - Deep Purple Theme & Permission Fixes

**Primary Changes:**
- **Brand color updated**: Changed from royal purple (#6b46c1) to deep purple (#7000a4)
- **Consistent branding**: All 20+ files updated with new color across entire application
- **Permission fix enhanced**: Added comprehensive troubleshooting for "Failed to write configuration file" error
- **Setup wizard colors**: Updated setup.php from orange to purple theme
- **Silver accents**: Maintained silver (#c0c0c0) for contrast elements

**Files Updated:**
- setup.php - Full purple theme implementation
- style.css - CSS variables updated to #7000a4
- All views (20+ files) - Consistent purple branding
- dashboard.php, mailer.php, public_sessions.php - Theme updates
- deployment/DEPLOYMENT.md - Enhanced permission troubleshooting

**Permission Troubleshooting Added:**
- Explicit chmod 775 commands for root directory
- Container write access test command
- Detailed permission verification steps
- Owner/group confirmation (911:911)

**Theme:**
- Deep purple primary (#7000a4)
- Silver accents (#c0c0c0)
- Navy background (#06080b) - unchanged

---

## January 20, 2026 - Repository Reorganization & Royal Purple

**Primary Changes:**
- Created `/deployment` folder for server configs and documentation
- Consolidated all guides into single `deployment/DEPLOYMENT.md`
- NGINX config renamed: `nginx.conf` → `crashhockey.conf`
- Setup wizard fixed: Corrected schema path and added SMTP error display
- Email logs viewer added: New admin interface for debugging
- Theme changed: Orange → Royal purple (#6b46c1)

**Documentation:**
- All guides merged into comprehensive DEPLOYMENT.md
- Separate UPDATES.md created for change tracking
- Clean root README.md with quick start
- 10-step deployment process documented

**Fixes:**
- Schema file path corrected in setup wizard
- SMTP error messages now displayed during setup
- Database initialization order fixed

---

## January 20, 2026 - Initial Platform Implementation

**Primary Changes:**
- Complete hockey training platform implementation
- Drill library with categories, tags, and search functionality
- Practice plan builder with IHS JSON import
- Coach-athlete assignment system with video review
- Email notification system with in-app notifications
- Parent/manager accounts with multi-athlete booking
- Age groups and skill levels system
- HST tax configuration
- Package system (credit-based and bundled)
- Enhanced session types (group/private/semi-private)
- Accounting and reporting system
- Cloud receipt integration with Nextcloud OCR
- Mileage tracking with Google Maps
- Refund management system
- Comprehensive role-based permissions (5 roles, 30+ permissions)
- AES-256 database encryption
- CSRF protection and rate limiting
- Security headers and session management

**Database:**
- 28+ tables created
- Complete schema with relationships
- Encrypted configuration storage

**Theme:**
- Orange primary color (#ff4d00)
- Amber accent color (#ff9d00)
- Navy background (#06080b)

**Deployment:**
- Initial deployment guide created
- Docker compatibility with linuxserver/docker-nginx
- 10GB video upload support

---
