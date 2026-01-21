# Crash Hockey - Complete Deployment Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation Methods](#installation-methods)
3. [Database Setup](#database-setup)
4. [File Permissions](#file-permissions)
5. [Setup Wizard](#setup-wizard)
6. [Docker Deployment](#docker-deployment)
7. [Production Deployment](#production-deployment)
8. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Requirements
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL/MariaDB**: 5.7+ / 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Disk Space**: 500MB minimum
- **Memory**: 512MB RAM minimum

### PHP Extensions Required
```bash
php-mysql (or php-mysqli)
php-pdo
php-mbstring
php-json
php-session
```

### Optional for Enhanced Features
```bash
php-gd (for image processing)
php-curl (for external API calls)
php-zip (for backup/restore)
```

---

## Installation Methods

### Method 1: Standard Installation (Recommended)

#### 1. Clone or Download Repository
```bash
# Clone repository
git clone https://github.com/CrashMediaIT/crashhockey.git
cd crashhockey

# Or download and extract
wget https://github.com/CrashMediaIT/crashhockey/archive/refs/heads/main.zip
unzip main.zip
cd crashhockey-main
```

#### 2. Set Up Web Server

**For Apache:**
```apache
<VirtualHost *:80>
    ServerName crashhockey.local
    DocumentRoot /var/www/crashhockey
    
    <Directory /var/www/crashhockey>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/crashhockey_error.log
    CustomLog ${APACHE_LOG_DIR}/crashhockey_access.log combined
</VirtualHost>
```

**For Nginx:**
```nginx
server {
    listen 80;
    server_name crashhockey.local;
    root /var/www/crashhockey;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

#### 3. Create Database
```bash
mysql -u root -p
```

```sql
CREATE DATABASE crashhockey CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crashhockey_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON crashhockey.* TO 'crashhockey_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Database Setup

### Import Database Schema
```bash
mysql -u crashhockey_user -p crashhockey < database_schema.sql
```

### Verify Tables Were Created
```bash
mysql -u crashhockey_user -p crashhockey -e "SHOW TABLES;"
```

You should see 40+ tables including:
- users
- teams
- sessions
- drills
- practice_plans
- workout_plans
- nutrition_plans
- and more...

---

## File Permissions

### Standard Linux/Unix Permissions

```bash
# Set ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /var/www/crashhockey

# Set directory permissions
sudo find /var/www/crashhockey -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/crashhockey -type f -exec chmod 644 {} \;

# Make specific files writable by web server
sudo chmod 666 /var/www/crashhockey/crashhockey.env
sudo chmod 666 /var/www/crashhockey/.setup_complete
```

### Docker Permissions

If running in Docker, ensure proper permissions inside the container:

```bash
# Connect to running container
docker exec -it crashhockey_web bash

# Inside container, set permissions
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
chmod 666 /var/www/html/crashhockey.env
chmod 666 /var/www/html/.setup_complete

# Exit container
exit
```

### Directory Structure Permissions
```bash
# Create necessary directories if they don't exist
mkdir -p uploads videos receipts logs cache backups
chmod 755 uploads videos receipts logs cache backups
chown -R www-data:www-data uploads videos receipts logs cache backups
```

---

## Setup Wizard

### Access Setup Wizard
After deploying files and setting permissions:

1. **Navigate to**: `http://your-domain.com/setup.php`
2. **Complete 4 Steps**:

#### Step 1: Database Configuration
- Host: `localhost` (or your database host)
- Database Name: `crashhockey`
- Username: `crashhockey_user`
- Password: Your database password

#### Step 2: Admin User
- First Name
- Last Name  
- Email Address
- Password (minimum 8 characters)
- Confirm Password

#### Step 3: SMTP Configuration
- SMTP Host: `smtp.gmail.com` (or your provider)
- SMTP Port: `587` (or `465` for SSL)
- SMTP Username
- SMTP Password
- From Email Address

#### Step 4: Finalization
- Click "Complete Setup"
- Redirected to login page

### Post-Setup Security

**IMPORTANT**: After completing setup:

```bash
# Option 1: Delete setup file
rm /var/www/crashhockey/setup.php

# Option 2: Restrict access via .htaccess (if using Apache)
cat > /var/www/crashhockey/.htaccess << 'EOF'
<Files "setup.php">
    Require ip 127.0.0.1
    Require ip YOUR_IP_ADDRESS
</Files>
EOF
```

---

## Docker Deployment

### Using Docker Compose (Recommended)

#### 1. Create docker-compose.yml
```yaml
version: '3.8'

services:
  web:
    image: php:8.0-apache
    container_name: crashhockey_web
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=crashhockey
      - DB_USER=crashhockey_user
      - DB_PASS=secure_password
    networks:
      - crashhockey_network

  db:
    image: mysql:8.0
    container_name: crashhockey_db
    environment:
      - MYSQL_ROOT_PASSWORD=root_password
      - MYSQL_DATABASE=crashhockey
      - MYSQL_USER=crashhockey_user
      - MYSQL_PASSWORD=secure_password
    volumes:
      - db_data:/var/lib/mysql
      - ./database_schema.sql:/docker-entrypoint-initdb.d/schema.sql
    networks:
      - crashhockey_network

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: crashhockey_phpmyadmin
    ports:
      - "8081:80"
    environment:
      - PMA_HOST=db
      - PMA_USER=crashhockey_user
      - PMA_PASSWORD=secure_password
    depends_on:
      - db
    networks:
      - crashhockey_network

volumes:
  db_data:

networks:
  crashhockey_network:
    driver: bridge
```

#### 2. Install PHP Extensions in Container

Create `Dockerfile`:
```dockerfile
FROM php:8.0-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
```

Update docker-compose.yml to use custom Dockerfile:
```yaml
services:
  web:
    build: .
    # ... rest of configuration
```

#### 3. Deploy with Docker Compose
```bash
# Start services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f web

# Import database (if not auto-imported)
docker exec -i crashhockey_db mysql -ucrashhockey_user -psecure_password crashhockey < database_schema.sql

# Set permissions inside container
docker exec crashhockey_web chown -R www-data:www-data /var/www/html
docker exec crashhockey_web find /var/www/html -type d -exec chmod 755 {} \;
docker exec crashhockey_web find /var/www/html -type f -exec chmod 644 {} \;
```

#### 4. Access Application
- **Main Site**: http://localhost:8080
- **Setup**: http://localhost:8080/setup.php
- **PHPMyAdmin**: http://localhost:8081

### Using Docker Without Compose

```bash
# Create network
docker network create crashhockey_network

# Run MySQL
docker run -d \
  --name crashhockey_db \
  --network crashhockey_network \
  -e MYSQL_ROOT_PASSWORD=root_password \
  -e MYSQL_DATABASE=crashhockey \
  -e MYSQL_USER=crashhockey_user \
  -e MYSQL_PASSWORD=secure_password \
  -v crashhockey_db:/var/lib/mysql \
  mysql:8.0

# Run PHP Apache
docker run -d \
  --name crashhockey_web \
  --network crashhockey_network \
  -p 8080:80 \
  -v $(pwd):/var/www/html \
  -e DB_HOST=crashhockey_db \
  -e DB_NAME=crashhockey \
  -e DB_USER=crashhockey_user \
  -e DB_PASS=secure_password \
  php:8.0-apache

# Install PHP extensions
docker exec crashhockey_web docker-php-ext-install pdo pdo_mysql mysqli

# Set permissions
docker exec crashhockey_web chown -R www-data:www-data /var/www/html
docker exec crashhockey_web find /var/www/html -type d -exec chmod 755 {} \;
docker exec crashhockey_web find /var/www/html -type f -exec chmod 644 {} \;

# Restart web container
docker restart crashhockey_web
```

---

## Production Deployment

### Pre-Deployment Checklist

- [ ] Database backup created
- [ ] All sensitive credentials updated
- [ ] SSL certificate installed
- [ ] Environment file secured
- [ ] Setup.php removed or restricted
- [ ] Error reporting disabled in production
- [ ] Firewall rules configured
- [ ] Regular backups scheduled

### Security Hardening

#### 1. Update PHP Configuration
```ini
# /etc/php/8.0/apache2/php.ini or php-fpm config
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
expose_php = Off
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 300
```

#### 2. Secure Environment File
```bash
# Restrict .env file access
chmod 600 /var/www/crashhockey/crashhockey.env
chown www-data:www-data /var/www/crashhockey/crashhockey.env

# Add to .htaccess (Apache)
<Files "crashhockey.env">
    Require all denied
</Files>

# Or in Nginx config
location ~ /crashhockey\.env {
    deny all;
    return 404;
}
```

#### 3. Enable HTTPS
```bash
# Install Certbot for Let's Encrypt SSL
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal
sudo certbot renew --dry-run
```

#### 4. Configure Firewall
```bash
# UFW (Ubuntu)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# Or firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### Backup Strategy

#### Database Backup Script
```bash
#!/bin/bash
# /usr/local/bin/backup_crashhockey.sh

BACKUP_DIR="/var/backups/crashhockey"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="crashhockey"
DB_USER="crashhockey_user"
DB_PASS="your_password"

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/crashhockey

# Delete backups older than 30 days
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup completed: $DATE"
```

#### Schedule with Cron
```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * /usr/local/bin/backup_crashhockey.sh >> /var/log/crashhockey_backup.log 2>&1
```

---

## Troubleshooting

### Database Connection Issues

**Problem**: "Database Connection Failed"

**Solutions**:
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u crashhockey_user -p -h localhost crashhockey

# Check credentials in crashhockey.env
cat /var/www/crashhockey/crashhockey.env

# Verify user permissions
mysql -u root -p
```
```sql
SHOW GRANTS FOR 'crashhockey_user'@'localhost';
```

### Permission Errors

**Problem**: "Permission denied" or "Unable to write file"

**Solutions**:
```bash
# Check current ownership
ls -la /var/www/crashhockey

# Fix ownership
sudo chown -R www-data:www-data /var/www/crashhockey

# Fix permissions
sudo find /var/www/crashhockey -type d -exec chmod 755 {} \;
sudo find /var/www/crashhockey -type f -exec chmod 644 {} \;
```

### 404 Errors on Navigation

**Problem**: Navigation links return 404

**Solutions**:
```bash
# Enable mod_rewrite (Apache)
sudo a2enmod rewrite
sudo systemctl restart apache2

# Check .htaccess exists and contains rewrite rules
cat /var/www/crashhockey/.htaccess

# For Nginx, ensure try_files is configured correctly
```

### Session Issues

**Problem**: Logged out unexpectedly

**Solutions**:
```bash
# Check session directory permissions
ls -la /var/lib/php/sessions

# Fix if needed
sudo chmod 1733 /var/lib/php/sessions

# Or in php.ini
session.save_path = "/var/www/crashhockey/sessions"
```

### Docker Issues

**Problem**: Container won't start or crashes

**Solutions**:
```bash
# View container logs
docker logs crashhockey_web
docker logs crashhockey_db

# Check container status
docker ps -a

# Restart containers
docker-compose restart

# Rebuild if needed
docker-compose down
docker-compose up --build -d

# Clear everything and start fresh
docker-compose down -v
docker-compose up -d
```

### Performance Issues

**Problem**: Slow page loads

**Solutions**:
```bash
# Enable PHP OpCache
# In php.ini:
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000

# Optimize MySQL
# In my.cnf:
[mysqld]
innodb_buffer_pool_size = 256M
query_cache_size = 64M
query_cache_limit = 2M

# Restart services
sudo systemctl restart apache2
sudo systemctl restart mysql
```

---

## Maintenance

### Regular Tasks

**Daily**:
- Monitor error logs
- Check disk space
- Verify backups completed

**Weekly**:
- Review security logs
- Test backup restoration
- Update dependencies if needed

**Monthly**:
- Security audit
- Performance review
- User feedback review

### Log Locations

```bash
# Apache logs
/var/log/apache2/crashhockey_error.log
/var/log/apache2/crashhockey_access.log

# Nginx logs
/var/log/nginx/error.log
/var/log/nginx/access.log

# PHP logs
/var/log/php/error.log

# Application logs (if implemented)
/var/www/crashhockey/logs/

# MySQL logs
/var/log/mysql/error.log
```

### Monitoring Commands

```bash
# Check disk usage
df -h

# Check memory usage
free -m

# Check MySQL processes
mysqladmin -u root -p processlist

# Check Apache/Nginx status
sudo systemctl status apache2
sudo systemctl status nginx

# Monitor resource usage
top
htop
```

---

## Support

### Documentation Files
- **NAVIGATION_REFERENCE.md** - Navigation structure and database schema reference
- **SECURITY_AUDIT.md** - Security considerations
- **README.md** - Project overview

### Getting Help
1. Check this deployment guide
2. Review error logs
3. Check GitHub issues
4. Contact system administrator

---

## Quick Reference Commands

### Start/Stop Services
```bash
# Apache
sudo systemctl start apache2
sudo systemctl stop apache2
sudo systemctl restart apache2

# Nginx
sudo systemctl start nginx
sudo systemctl stop nginx
sudo systemctl restart nginx

# MySQL
sudo systemctl start mysql
sudo systemctl stop mysql
sudo systemctl restart mysql

# Docker
docker-compose up -d
docker-compose down
docker-compose restart
```

### Database Operations
```bash
# Backup database
mysqldump -u crashhockey_user -p crashhockey > backup.sql

# Restore database
mysql -u crashhockey_user -p crashhockey < backup.sql

# Access MySQL console
mysql -u crashhockey_user -p crashhockey
```

### File Operations
```bash
# View live error log
tail -f /var/log/apache2/crashhockey_error.log

# Search for errors
grep "error" /var/log/apache2/crashhockey_error.log

# Check PHP version
php -v

# Test PHP syntax
php -l filename.php
```

---

**Last Updated**: January 21, 2026
**Version**: 1.0
