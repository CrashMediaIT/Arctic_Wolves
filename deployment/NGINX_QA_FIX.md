# NGINX Configuration Fix for QA Folder Access

## Problem
When accessing the QA governance folder through the web server, nginx was returning a 403 Forbidden error.

## Root Cause
The nginx configuration did not have a specific location block to allow access to the QA folder and its documentation files (markdown files and screenshots).

## Solution
Added a location block in `arctic_wolves.conf` to explicitly allow access to the QA folder while maintaining security:

```nginx
# Allow access to QA governance documentation
location ^~ /QA/ {
    # Allow access to markdown files and images in QA folder
    location ~* \.(md|png|jpg|jpeg|gif)$ {
        add_header Content-Type text/plain;
        add_header X-Content-Type-Options "nosniff" always;
    }
    # Prevent execution of any scripts
    location ~ \.php$ {
        deny all;
    }
}
```

## What This Does
1. **`location ^~ /QA/`** - Creates a priority location block for the /QA directory
2. **Allows markdown and image files** - Permits access to .md, .png, .jpg, .jpeg, and .gif files
3. **Sets Content-Type to text/plain** - Prevents browser from executing markdown files as HTML
4. **Adds X-Content-Type-Options** - Prevents MIME type sniffing for security
5. **Denies PHP execution** - Blocks any PHP files in the QA folder from executing

## Security Considerations
- Markdown files are served as plain text, not HTML (prevents XSS)
- PHP execution is explicitly denied in the QA folder
- Only specific file types are allowed (.md, .png, .jpg, .jpeg, .gif)
- All other security headers from the main configuration still apply

## Testing
After applying this configuration:
1. Restart nginx: `sudo systemctl restart nginx` (or appropriate command for your setup)
2. Test access to QA folder: `curl http://yourdomain.com/QA/`
3. Test markdown file: `curl http://yourdomain.com/QA/README.md`
4. Test screenshot: `curl http://yourdomain.com/QA/screenshots/Screenshot%202026-01-23%20212206.png`

## Files Changed
- `deployment/arctic_wolves.conf` - Added QA folder location block (lines 102-113)

## Date Applied
January 24, 2026
