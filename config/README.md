# Configuration Directory

This directory is for storing configuration files that are not checked into version control.

## Files That May Be Stored Here

- Environment-specific configuration files
- Custom PHP configuration overrides
- Web server configuration snippets
- SSL certificates (development only)
- API keys and credentials (development only)

## Security Note

**DO NOT** commit sensitive credentials or production configuration to version control. Use environment variables or separate secure credential management systems for production deployments.

## For Production

In production, this directory should contain:
- Environment-specific settings
- Custom application configuration
- Cached configuration files

Ensure proper file permissions:
```bash
chmod 755 config/
chmod 644 config/*
```
