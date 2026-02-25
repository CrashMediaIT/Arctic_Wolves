# Upload Migration Progress: Local Storage → RustFS

## Status: Tests Need Updating

## What Was Completed ✅

### 1. Core Upload Flow Changes (process_expenses.php)
- **OCR flow**: Uses `$_FILES['tmp_name']` directly for Paperless OCR (no local file paths)
- **`ocr_receipt_url` handling**: Accepts RustFS URLs (http/https) only — removed local `realpath()` validation
- **Post-create**: Removed redundant `uploadReceiptToNextcloud` call (receipt already in RustFS), passes `$_FILES['tmp_name']` for Paperless
- **Update action**: Removed local `mkdir`, uses `$_FILES['tmp_name']` for Paperless
- **Delete action**: Deletes from RustFS via `deleteFromRustFS()` instead of local `unlink()`
- **Receipt size limit**: Increased from 10MB to 100MB, UI text updated

### 2. Dead Code Removed
- `uploadReceiptToNextcloud()` function — was a redundant double-upload
- `uploadContractToNextcloud()` in `process_recurring_expenses.php` — never called
- `getPersistentStoragePath()` in `cloud_config.php` — no longer needed
- `saveToPersistentStorage()` in `cloud_config.php` — no longer needed  
- `restoreFromPersistentStorage()` in `cloud_config.php` — no longer needed
- `restoreImageFromNextcloud()` in `cloud_config.php` — no longer needed
- `restoreThemeImagesFromPersistentStorage()` in `cloud_config.php` — no longer needed
- `restoreAllFilesFromPersistentStorage()` in `cloud_config.php` — no longer needed

### 3. Local mkdir/uploads Directories Removed
- `process_expenses.php`: Removed `mkdir('uploads/receipts/')`
- `process_recurring_expenses.php`: Removed `mkdir('uploads/contracts')`
- `process_admin_action.php`: Removed `mkdir('uploads/profiles/')` + `.htaccess`, `mkdir('uploads/team_logos/')` x2
- `process_drills.php`: Removed `mkdir('uploads/drill_videos/')`, removed fallback `copy()` to `uploads/drills/`
- `process_practice_plans.php`: Removed fallback `copy()` to `uploads/drills/`

### 4. All Local File Restore/Fallback Removed
- `lib/image_helper.php`: Functions only accept RustFS URLs (http/https), zero local restore logic
- `dashboard.php`: Removed `restoreAllFilesFromPersistentStorage()` call
- `pwa.php`: Removed `restoreAllFilesFromPersistentStorage()` call
- `process_profile_update.php`: RustFS delete for old profile images, no local fallback
- `process_admin_action.php`: RustFS delete for old profile images, no local fallback

### 5. View Files Updated for RustFS URLs
- `views/admin_users.php`: Profile image validation accepts RustFS URLs only
- `views/phone_directory.php`: Profile image validation accepts RustFS URLs only
- `views/sip_settings.php`: Profile image validation accepts RustFS URLs only
- `views/profile.php`: Removed local restore block entirely
- `views/view_drill.php`: Removed local restore block entirely
- `views/view_practice_plan.php`: Removed local restore block entirely

### 6. Comments Updated
- All "persist to /config/persistent_uploads, upload to Nextcloud, cache locally" → "Upload to RustFS"

## What Still Needs Work ❌

### Test Files Need Updating
The following test files have assertions checking for removed functions/patterns. They need updating to match the new RustFS-only approach:

**Tests checking for removed functions (these test for code we intentionally removed):**
- `tests/all-uploads-persistent-storage.spec.js` — Tests for `uploadReceiptToNextcloud`, `uploadContractToNextcloud`, `getPersistentStoragePath`, `saveToPersistentStorage`, `restoreFromPersistentStorage`, `tryRestoreFromPersistent`, `resolveProfileImage`, `resolveEvaluationMedia`, `resolveDrillImage`
- `tests/all-images-nextcloud-persistence.spec.js` — Tests for local fallback copy in process_drills.php and process_practice_plans.php
- `tests/expense-ocr-receipt-attachment.spec.js` — Tests for `realpath` local validation and `uploadReceiptToNextcloud` call
- `tests/persistent-images-settings.spec.js` — Tests for `isValidImagePath` checking `uploads/` prefix
- `tests/persistent-storage-nextcloud-refactor.spec.js` — Tests for restore functions
- `tests/rustfs-s3-storage-integration.spec.js` — Tests for restore/persistent functions
- `tests/business-card-front-bg-fix.spec.js` — Tests for restore function
- `tests/redirect-and-persistence-fixes.spec.js` — Tests for restore function, `restoreAllFilesFromPersistentStorage`
- `tests/stripe-packages-ocr-nextcloud-fixes.spec.js` — Tests for `uploadReceiptToNextcloud` / `ocr_scan` Paperless
- `tests/drill-delete-and-credential-fix.spec.js` — Tests for local `uploads/` mkdir
- `tests/pwa-landing-and-scroll.spec.js` — Tests for `restoreAllFilesFromPersistentStorage`
- `tests/programs-camps-view-fixes.spec.js` — Tests for `restoreAllFilesFromPersistentStorage`

**Pre-existing failures (NOT caused by our changes):**
- 68 tests: `chromium_headless_shell` not installed (CI environment issue)
- 7 tests: `ECONNREFUSED` (API server not running)
- 2 tests: `ENOENT` for `docs/OCR_SETUP.md` (file doesn't exist)

### Fix Strategy for Tests
Each test file that references removed functions needs to be updated to:
1. Remove assertions that check for local `uploads/` directory creation
2. Remove assertions that check for `restoreFromPersistentStorage`, `saveToPersistentStorage`, etc.
3. Update assertions about `isValidImagePath` to expect URL validation (http/https) instead of `uploads/` prefix
4. Update OCR receipt tests to verify RustFS URL acceptance instead of local `realpath` validation
5. Remove tests for `uploadReceiptToNextcloud`/`uploadContractToNextcloud` (dead code)

## Files Modified (Committed)
- `cloud_config.php`
- `cron_receipt_scanner.php`
- `dashboard.php`
- `lib/image_helper.php`
- `process_admin_action.php`
- `process_drills.php`
- `process_eval_goals.php`
- `process_eval_skills.php`
- `process_expenses.php`
- `process_merchandise_categories.php`
- `process_merchandise_products.php`
- `process_practice_plans.php`
- `process_profile_update.php`
- `process_recurring_expenses.php`
- `process_theme.php`
- `process_video.php`
- `process_workout.php`
- `pwa.php`
- `views/accounting_expenses.php`
- `views/admin_users.php`
- `views/phone_directory.php`
- `views/profile.php`
- `views/sip_settings.php`
- `views/view_drill.php`
- `views/view_practice_plan.php`

## Key Architecture Decisions
- **Only OCR (Paperless-NGX) and DocuSeal (signatures) need direct file access** — they use PHP `$_FILES['tmp_name']` (the temporary upload)
- **All permanent storage goes to RustFS** via `persistUploadedFile()`
- **No local `uploads/` directory** — zero `mkdir`, zero `file_exists` on uploads paths, zero `unlink` on local files
- **`receipt_url` in DB is always a RustFS URL** (https://...)
- **Receipt size limit: 100MB** (was 10MB)
