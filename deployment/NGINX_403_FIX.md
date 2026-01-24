# NGINX 403 Error Fix for Governance Documentation Access

## Issue
Nginx was returning 403 Forbidden errors when accessing governance documentation in /QA and /deployment directories.

## Root Cause
The nginx configuration had no location blocks to allow access to documentation directories (/QA and /deployment), causing 403 Forbidden errors when trying to view governance documents like MAINTENANCE_PROCESS.md, UPDATES.md, etc.

## Solution
Added location block to allow access to documentation files in /QA and /deployment directories (lines 105-109):

```nginx
# Allow access to QA and deployment documentation directories
# Only allows .md, .txt, and .json files (.sql already denied above)
location ~ ^/(QA|deployment)/.*\.(md|txt|json)$ {
    add_header Content-Type "text/plain; charset=utf-8";
    add_header X-Content-Type-Options "nosniff" always;
}
```

**Security Note**: SQL files in these directories are protected by the global SQL deny rule at lines 100-103. Since nginx evaluates regex location blocks in order and stops at the first match, any `.sql` file (regardless of directory) matches the deny rule first and is blocked before reaching the documentation allow block.

## Why This Fixes the 403 Errors

### Documentation Directories - Before the Fix
Without specific location blocks for /QA and /deployment:
- Nginx had no explicit rule to serve files from these directories
- Requests fell through to default deny behavior
- Governance documents were inaccessible (403 Forbidden)
- QA team could not review documentation files

### Documentation Directories - After the Fix
With dedicated location blocks for documentation:
1. **Explicit access granted** to .md, .txt, and .json files in /QA and /deployment
2. **Proper content type** headers set for text files
3. **SQL and config files protected** from being served directly (via global deny rules)
4. **Governance documents accessible** for review and updates

## Security Benefits
1. **Documentation Access Control**: Allows governance docs while protecting sensitive SQL files
2. **Proper Content Types**: Sets appropriate headers for security
3. **Maintains Protection**: Existing global deny rules continue to protect .sql, .env, and backup files

## Pre-existing Security Features
The nginx configuration also includes these security features (added in previous updates):
- **PHP File Verification** (line 66): `try_files $uri =404;` prevents processing non-existent PHP files
- **Global SQL Protection** (lines 100-103): Denies access to all .sql files
- **Backup File Protection** (lines 130-132): Denies access to .bak, .backup, .old, .tmp files

## Testing
After applying this fix:
```bash
# Restart nginx
sudo systemctl restart nginx

# Test governance documentation access (should return 200)
curl -I http://yourdomain.com/QA/MAINTENANCE_PROCESS.md
curl -I http://yourdomain.com/deployment/UPDATES.md
curl -I http://yourdomain.com/deployment/NGINX_403_FIX.md

# Test SQL file protection (should return 404)
curl -I http://yourdomain.com/QA/test.sql
curl -I http://yourdomain.com/deployment/schema.sql
```

## Related Documentation
- nginx location matching: https://nginx.org/en/docs/http/ngx_http_core_module.html#location
- nginx add_header directive: https://nginx.org/en/docs/http/ngx_http_headers_module.html#add_header

## Date Applied
January 24, 2026

## Files Modified
- `deployment/arctic_wolves.conf` - Lines 99-109: Added comments and location block for QA and deployment documentation access
