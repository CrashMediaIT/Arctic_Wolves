# NGINX 403 Error Fix for PHP Files

## Issue
Nginx was returning 403 Forbidden errors when accessing index.php and setup.php.

## Root Cause
The PHP-FPM location block was missing a critical security check that verifies the requested PHP file exists before attempting to execute it. This is a common nginx security issue.

## Solution
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

## Why This Fixes the 403 Error

### Before the Fix
Without `try_files`, nginx would pass ALL requests matching `\.php$` to PHP-FPM, even if:
- The file doesn't exist
- nginx worker doesn't have permission to read it
- The path is invalid or malformed

This could result in:
- 403 Forbidden errors (when permissions are wrong)
- Security vulnerabilities (path traversal attacks)
- Confusing error messages

### After the Fix
With `try_files $uri =404;`, nginx:
1. **Checks if the file exists** before passing to PHP-FPM
2. **Returns 404** for non-existent files (clearer error)
3. **Only processes valid PHP files** that exist and are readable
4. **Prevents security issues** from processing invalid paths

## Security Benefits
1. **Path Traversal Protection**: Prevents attempts to execute PHP files outside the document root
2. **Clear Error Messages**: Returns 404 for missing files instead of 403
3. **Reduced Attack Surface**: PHP-FPM only processes legitimate, existing files
4. **Standard Best Practice**: Recommended by nginx documentation for all PHP setups

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
```

## Related Documentation
- nginx PHP-FPM configuration: https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/
- nginx location matching: https://nginx.org/en/docs/http/ngx_http_core_module.html#location
- try_files directive: https://nginx.org/en/docs/http/ngx_http_core_module.html#try_files

## Date Applied
January 24, 2026

## Files Modified
- `deployment/arctic_wolves.conf` - Added try_files security check to PHP location block (lines 65-66)
