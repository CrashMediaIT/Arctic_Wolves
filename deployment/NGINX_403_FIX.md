# NGINX 403 Error Fix for PHP Files and Documentation Access

## Issue
Nginx was returning 403 Forbidden errors when:
1. Accessing PHP files (index.php and setup.php)
2. Accessing governance documentation in /QA and /deployment directories

## Root Causes

### Issue 1: Missing PHP File Verification
The PHP-FPM location block was missing a critical security check that verifies the requested PHP file exists before attempting to execute it.

### Issue 2: Missing Documentation Directory Access Rules
The nginx configuration had no location blocks to allow access to documentation directories (/QA and /deployment), causing 403 Forbidden errors when trying to view governance documents like MAINTENANCE_PROCESS.md, UPDATES.md, etc.

## Solutions

### Solution 1: PHP File Security Check
Added `try_files $uri =404;` to the PHP location block (line 65-66 in arctic_wolves.conf):

```nginx
location ~ \.php$ {
    # Security: Don't process non-existent files
    try_files $uri =404;
    
    include fastcgi_params;
    fastcgi_pass 127.0.0.1:9000;
    ...
}
```

### Solution 2: Documentation Directory Access (PRIMARY FIX)
Added location blocks to allow access to documentation files in /QA and /deployment directories (lines 105-116):

```nginx
# Allow access to QA and deployment documentation directories
location ~ ^/(QA|deployment)/.*\.(md|txt|json|conf)$ {
    default_type text/plain;
    add_header Content-Type "text/plain; charset=utf-8";
    add_header X-Content-Type-Options "nosniff" always;
}

# Deny SQL files in QA and deployment directories
location ~ ^/(QA|deployment)/.*\.sql$ {
    deny all;
    return 404;
}
```

## Why This Fixes the 403 Errors

### PHP Files - Before the Fix
Without `try_files`, nginx would pass ALL requests matching `\.php$` to PHP-FPM, even if:
- The file doesn't exist
- nginx worker doesn't have permission to read it
- The path is invalid or malformed

This could result in:
- 403 Forbidden errors (when permissions are wrong)
- Security vulnerabilities (path traversal attacks)
- Confusing error messages

### PHP Files - After the Fix
With `try_files $uri =404;`, nginx:
1. **Checks if the file exists** before passing to PHP-FPM
2. **Returns 404** for non-existent files (clearer error)
3. **Only processes valid PHP files** that exist and are readable
4. **Prevents security issues** from processing invalid paths

### Documentation Directories - Before the Fix
Without specific location blocks for /QA and /deployment:
- Nginx had no explicit rule to serve files from these directories
- Requests fell through to default deny behavior
- Governance documents were inaccessible (403 Forbidden)
- QA team could not review documentation files

### Documentation Directories - After the Fix
With dedicated location blocks for documentation:
1. **Explicit access granted** to .md, .txt, .json, and .conf files in /QA and /deployment
2. **Proper content type** headers set for text files
3. **SQL files still protected** from being served directly
4. **Governance documents accessible** for review and updates

## Security Benefits
1. **Path Traversal Protection**: Prevents attempts to execute PHP files outside the document root
2. **Clear Error Messages**: Returns 404 for missing files instead of 403
3. **Reduced Attack Surface**: PHP-FPM only processes legitimate, existing files
4. **Documentation Access Control**: Allows governance docs while protecting sensitive SQL files
5. **Standard Best Practice**: Recommended by nginx documentation for all PHP setups

## Testing
After applying this fix:
```bash
# Restart nginx
sudo systemctl restart nginx

# Test valid PHP files (should work)
curl -I http://yourdomain.com/index.php
curl -I http://yourdomain.com/setup.php

# Test non-existent PHP file (should return 404, not 403)
curl -I http://yourdomain.com/nonexistent.php

# Test governance documentation access (should return 200)
curl -I http://yourdomain.com/QA/MAINTENANCE_PROCESS.md
curl -I http://yourdomain.com/deployment/UPDATES.md
curl -I http://yourdomain.com/deployment/NGINX_403_FIX.md

# Test SQL file protection (should return 404)
curl -I http://yourdomain.com/QA/test.sql
curl -I http://yourdomain.com/deployment/schema.sql
```

## Related Documentation
- nginx PHP-FPM configuration: https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/
- nginx location matching: https://nginx.org/en/docs/http/ngx_http_core_module.html#location
- try_files directive: https://nginx.org/en/docs/http/ngx_http_core_module.html#try_files

## Date Applied
January 24, 2026

## Files Modified
- `deployment/arctic_wolves.conf` - Lines 65-66: Added try_files security check to PHP location block
- `deployment/arctic_wolves.conf` - Lines 105-116: Added location blocks for QA and deployment documentation access
