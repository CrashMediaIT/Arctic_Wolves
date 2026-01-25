# GitHub Updates Feature Documentation

## Overview
The GitHub Updates feature allows administrators to check for and apply updates directly from the Arctic Wolves GitHub repository. This feature supports both public and private repositories through GitHub authentication.

## Location
The Updates feature is available in the Admin Settings page under the "Updates" tab:
- **Menu:** Admin → System Settings
- **Tab:** Updates (rightmost tab)

## Features

### 1. GitHub Authentication
- Support for private repositories using GitHub Personal Access Token (PAT)
- Test connection functionality to verify repository access
- Secure token storage in system settings

### 2. Update Checking
- Check for available updates from the repository
- View latest commit information (message, date, author)
- Compare current installation version with latest repository version

### 3. Update Application
- Download and update changed files from repository
- **Automatically delete files that were removed from repository**
- Preserve configuration files (db_config.php, .env, etc.)
- Atomic updates with error reporting

## Usage

### Setup GitHub Authentication (For Private Repositories)

1. Navigate to Admin Settings → Updates tab
2. Click "Generate token here" link or visit: https://github.com/settings/tokens/new
3. Create a new Personal Access Token with the following settings:
   - **Description:** Arctic Wolves Updater
   - **Scope:** Select `repo` (Full control of private repositories)
4. Copy the generated token (starts with `ghp_`)
5. Paste the token into the "GitHub Personal Access Token" field
6. Click "Test Connection" to verify access
7. Click "Save GitHub Settings"

### Checking for Updates

1. Navigate to Admin Settings → Updates tab
2. Click "Check for Updates" button
3. Review the update status:
   - **Up to date:** Your system is running the latest version
   - **Updates Available:** Shows latest commit details

### Applying Updates

1. **IMPORTANT:** Backup your database before applying updates
2. Backup any custom configuration files
3. Click "Apply Updates" button
4. Confirm the warning dialog
5. Wait for the update process to complete (may take several minutes)
6. Review the results:
   - Number of files updated
   - Number of files deleted
   - Any errors encountered
7. Reload the page to see changes

## File Handling

### Files Updated
All files in the repository are synchronized with your local installation, including:
- PHP application files
- JavaScript and CSS files
- View templates
- Library files
- Documentation

### Files Excluded from Updates
The following files/directories are never modified by the updater to preserve your configuration:
- `db_config.php` - Database configuration
- `uploads/` - User uploaded files
- `.git/` - Git repository data
- `.env` - Environment configuration
- `config.php` - Custom configuration
- `vendor/` - Composer dependencies
- `node_modules/` - NPM dependencies

### Files Deleted
**NEW:** Files that exist in your local installation but were removed from the repository will be automatically deleted. This ensures your installation stays in sync with the repository structure.

## Security Considerations

1. **Access Control:** Only administrators can access the Updates feature
2. **Token Security:** GitHub tokens are stored encrypted in the database
3. **CSRF Protection:** All update actions are protected against CSRF attacks
4. **File Validation:** Only files from the authorized repository can be updated
5. **Backup Required:** Always backup before applying updates

## Troubleshooting

### "Repository not found or access denied"
- Verify your GitHub token has the correct permissions
- Check if the token has expired
- Ensure the token has access to the CrashMediaIT/Arctic_Wolves repository

### "Failed to connect to GitHub"
- Check your internet connection
- Verify your server can access GitHub.com
- Check for firewall or proxy restrictions

### Update Failed with Errors
- Review the error messages displayed
- Check file permissions on the server
- Ensure sufficient disk space
- Verify PHP has write access to application directories

### Files Not Updating
- Check the excluded paths list
- Verify the files exist in the repository
- Check server file permissions

## Technical Details

### Implementation Files
- **Library:** `/lib/github_updater.php` - Core update logic
- **UI:** `/views/admin_settings.php` - Updates tab interface
- **Controller:** `/process_settings.php` - Update action handler

### Database Settings
The following settings are stored in the `system_settings` table:
- `github_token` - GitHub Personal Access Token
- `current_commit_sha` - Current installation version

### API Endpoints
The updater uses GitHub's API:
- Repository info: `https://api.github.com/repos/{owner}/{repo}`
- Commits: `https://api.github.com/repos/{owner}/{repo}/commits/{branch}`
- File tree: `https://api.github.com/repos/{owner}/{repo}/git/trees/{branch}?recursive=1`
- Raw files: `https://raw.githubusercontent.com/{owner}/{repo}/{branch}/{path}`

### Update Process Flow
1. Authenticate with GitHub using stored token
2. Fetch repository file tree
3. Compare with local file list
4. Download and update changed files
5. Delete files not present in repository (excluding protected paths)
6. Update current commit SHA
7. Report results

## Best Practices

1. **Test in Staging:** Always test updates in a staging environment first
2. **Backup First:** Create database and file backups before updating
3. **Review Changes:** Check the GitHub repository for recent changes before updating
4. **Monitor Errors:** Review any error messages carefully
5. **Verify Functionality:** Test critical features after applying updates

## Governance
This feature supports the Arctic Wolves governance model by:
- Ensuring all installations can stay up-to-date with the latest fixes
- Providing audit trail through commit history
- Enabling centralized update management
- Supporting automated deployment workflows

## Version Information
- **Feature Added:** January 2026
- **Repository:** CrashMediaIT/Arctic_Wolves
- **Default Branch:** main
