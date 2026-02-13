# Crash Hockey Training Platform - Deployment Guide

Complete step-by-step deployment instructions for Docker setup using **linuxserver.io nginx** and **official MariaDB** containers.

---

## Step 1: Setup Fedora Server and Update via SSH

```bash
# Connect to your server
ssh root@your-server-ip

# Update the system
sudo dnf update -y
sudo dnf upgrade -y

# Install basic tools
sudo dnf install -y git curl wget nano
```

---

## Step 2: Install Docker

```bash
# Install Docker on Fedora
sudo dnf install -y dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Verify Docker installation
docker --version
docker compose version

# Add your user to docker group (optional, requires re-login)
sudo usermod -aG docker $USER
```

---

## Step 3: Install Portainer (Container Management UI)

```bash
# Create Portainer volume
docker volume create portainer_data

# Run Portainer
docker run -d \
  -p 8000:8000 \
  -p 9443:9443 \
  --name portainer \
  --restart=always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  portainer/portainer-ce:latest

# Access Portainer at https://your-server-ip:9443
echo "Access Portainer at: https://$(hostname -I | awk '{print $1}'):9443"
```

---

## Step 4: Setup MariaDB Database

```bash
# Create MariaDB container
docker run -d \
  --name mariadb \
  --restart=always \
  -e MYSQL_ROOT_PASSWORD=your_secure_root_password \
  -e MYSQL_DATABASE=arcticwolves \
  -e MYSQL_USER=chroot \
  -e MYSQL_PASSWORD=your_secure_password \
  -v mariadb_data:/var/lib/mysql \
  -p 3306:3306 \
  mariadb:latest

# Wait for MariaDB to start (about 30 seconds)
sleep 30

# Verify MariaDB is running
docker ps | grep mariadb

# Test connection
docker exec -it mariadb mysql -u awroot -p arcticwolves
# Enter the password when prompted, then type 'EXIT;' to quit
```

**Important:** Note down these credentials:
- **Database**: `arcticwolves`
- **Username**: `chroot`
- **Password**: `your_secure_password`
- **Host**: `mariadb` (when accessed from other containers) or `localhost` (from host)

---

## Step 5: Setup linuxserver.io NGINX Container

```bash
# Create config directory on HOST
sudo mkdir -p /portainer/nginx

# Run linuxserver/nginx container with bind mount
# Host path: /portainer/nginx maps to Container path: /config
docker run -d \
  --name=nginx \
  --restart=always \
  -e PUID=911 \
  -e PGID=911 \
  -e TZ=America/Toronto \
  -p 80:80 \
  -p 443:443 \
  -v /portainer/nginx:/config \
  --link mariadb:mariadb \
  lscr.io/linuxserver/nginx:latest

# Verify NGINX is running
docker ps | grep nginx

# Check NGINX logs
docker logs nginx
```

**Important Directory Structure (on HOST at /portainer/nginx):**
- `/portainer/nginx/www/` - Web root directory (maps to `/config/www` in container)
- `/portainer/nginx/nginx/site-confs/` - NGINX site configurations
- `/portainer/nginx/php/` - PHP configuration files
- `/portainer/nginx/log/` - Log files

**Critical Understanding:**
- **Host path**: `/portainer/nginx` (where files actually exist on Fedora)
- **Container path**: `/config` (internal container view via bind mount)
- **Set permissions on HOST** at `/portainer/nginx` paths

---

## Step 6: Clone Repository to WWW Folder

```bash
# Navigate to NGINX www directory ON HOST
cd /portainer/nginx/www

# Remove default files if they exist
sudo rm -rf html

# Clone the Crash Hockey repository
[sudo git clone https://github.com/CrashMediaIT/Arctic_Wolves.git

# Navigate into the directory
cd Arctic_Wolves

# Verify files are present
ls -la
```

---

## Step 7: Setup Permissions

**IMPORTANT:** The setup wizard (`setup.php`) **automatically creates directories and sets permissions** when you first run it. This is the recommended approach.

### Option 1: Automatic Setup (Recommended)

Simply navigate to `http://your-domain.com/setup.php` and the permission setup happens automatically in the background.

### Option 2: Manual Setup Script

If you prefer to set up permissions before running the setup wizard, use the provided script:

```bash
# Run the automated permission setup script
bash /portainer/nginx/www/Arctic_Wolves/deployment/setup_permissions.sh
```

### Option 3: Manual Commands

For advanced users who want full control, run these commands manually:

**IMPORTANT:** All permissions must be set **INSIDE the container** since that's what PHP-FPM sees.

```bash
# Create required directories INSIDE container (this ensures PHP sees correct permissions)
docker exec nginx mkdir -p /config/www/Arctic_Wolves/uploads
docker exec nginx mkdir -p /config/www/Arctic_Wolves/sessions
docker exec nginx mkdir -p /config/www/Arctic_Wolves/cache

# Set ownership to 'abc' user INSIDE container (what PHP-FPM runs as)
docker exec nginx chown -R abc:abc /config/www/Arctic_Wolves

# CRITICAL: Set root directory to 775 (allows PHP to write Arctic_Wolves.env during setup)
docker exec nginx chmod 775 /config/www/Arctic_Wolves

# Set upload/session/cache directories to 775 (web server needs write access)
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/uploads
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/sessions
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/cache

# Set standard permissions for other directories and files
docker exec nginx find /config/www/Arctic_Wolves -type d -exec chmod 755 {} \;
docker exec nginx find /config/www/Arctic_Wolves -type f -exec chmod 644 {} \;

# Re-apply critical permissions (find command may have reset them)
docker exec nginx chmod 775 /config/www/Arctic_Wolves
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/uploads
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/sessions
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/cache

# Verify permissions from inside container (what PHP actually sees)
docker exec nginx ls -ld /config/www/Arctic_Wolves
# Should show: drwxrwxr-x ... abc abc ... /config/www/Arctic_Wolves

# Test if directory is writable by PHP
docker exec nginx sh -c '[ -w /config/www/Arctic_Wolves ] && echo "✅ Directory IS writable by PHP" || echo "❌ Directory NOT writable by PHP"'

# Test write access from inside container
docker exec nginx touch /config/www/Arctic_Wolves/test.txt && \
docker exec nginx rm /config/www/Arctic_Wolves/test.txt && \
echo "✅ Write access verified - setup can proceed" || \
echo "❌ Write access FAILED"

# OPTIONAL: If you still have issues on Fedora, configure SELinux on host
# (Usually NOT needed if permissions are set inside container as shown above)
# sudo chcon -R -t container_file_t /portainer/nginx/www/Arctic_Wolves
# sudo semanage fcontext -a -t container_file_t "/portainer/nginx/www/Arctic_Wolves(/.*)?"
# sudo restorecon -R /portainer/nginx/www/Arctic_Wolves
```

**Permission Summary:**
- **Root directory**: `775` (CRITICAL - allows PHP to write config file during setup)
- **Uploads/Sessions/Cache**: `775` (web server needs write access)
- **Regular files**: `644` (readable by web server, not writable)
- **Regular directories**: `755` (traversable by web server)
- **Owner**: `911:911` (abc user in linuxserver container)
- **SELinux context**: `container_file_t` (allows container to write)

**If test fails, temporarily disable SELinux for troubleshooting:**
```bash
# Check SELinux status
sestatus

# Temporarily disable (resets on reboot)
sudo setenforce 0

# Try setup wizard again
# If it works, SELinux was the issue

# Re-enable SELinux
sudo setenforce 1

# Then apply proper context as shown above
```

---

## Step 8: Move Configuration Files to Correct Directories

```bash
# Copy NGINX configuration
sudo cp /portainer/nginx/www/Arctic_Wolves/deployment/arctic_wolves.conf \
  /portainer/nginx/nginx/site-confs/arctic_wolves.conf

# Copy PHP configuration
sudo cp /portainer/nginx/www/Arctic_Wolves/deployment/php-config.ini \
  /portainer/nginx/php/php-config.ini

# Set correct ownership
sudo chown 911:911 /portainer/nginx/nginx/site-confs/arctic_wolves.conf
sudo chown 911:911 /portainer/nginx/php/php-config.ini

# Verify files are in place
ls -la /portainer/nginx/nginx/site-confs/
ls -la /portainer/nginx/php/
```

**What Each File Does:**
- **arctic_wolves.conf**: NGINX server configuration (domain, PHP, upload limits)
- **php-config.ini**: PHP settings (upload limits, memory, timeouts)

---

## Step 9: Restart NGINX Container

```bash
# Restart NGINX to apply configurations
docker restart nginx

# Wait for container to restart
sleep 10

# Check NGINX status
docker ps | grep nginx

# View NGINX logs for any errors
docker logs nginx --tail 50

# Test NGINX configuration
docker exec nginx nginx -t

# Check that the site is accessible
curl -I http://localhost
# Should return "HTTP/1.1 200 OK" or redirect to HTTPS
```

**Troubleshooting:**
- If NGINX won't start, check logs: `docker logs nginx`
- Test config syntax: `docker exec nginx nginx -t`
- Check file permissions: `ls -la /portainer/nginx/nginx/site-confs/`

---

## Step 10: Run the Setup Wizard

### Access the Setup Wizard

1. Open your browser and navigate to:
   ```
   http://your-domain.com/setup.php
   ```
   Or use your server IP:
   ```
   http://your-server-ip/setup.php
   ```

### Step-by-Step Setup Process

#### **Page 1: Database Configuration**
- **Database Host**: `mariadb` (Docker container name)
- **Database Name**: `arcticwolves`
- **Database Username**: `awroot`
- **Database Password**: `your_secure_password` (from Step 4)
- **Encryption Key**: Click "Generate" or enter your own 32-character key
- Click **"Save Configuration"**

#### **Page 2: Initialize Database**
- Review the schema that will be created
- Click **"Initialize Database"**
- Wait for all tables to be created (may take 30-60 seconds)
- You should see success messages for each table created

#### **Page 3: SMTP Configuration**
- **SMTP Host**: Your email provider (e.g., `smtp.gmail.com`)
- **SMTP Port**: Usually `587` for TLS or `465` for SSL
- **SMTP Username**: Your full email address
- **SMTP Password**: App password (not your regular email password)
- **Encryption**: Select `TLS` (recommended) or `SSL`
- **From Name**: `Crash Hockey`
- **From Email**: `noreply@your-domain.com`
- Click **"Test SMTP Connection"** to verify
- Click **"Save SMTP Settings"**

**SMTP Providers:**
- **Gmail**: Use App Password (not your regular password)
  - Generate at: https://myaccount.google.com/apppasswords
  - SMTP Host: `smtp.gmail.com`, Port: `587`, Encryption: `TLS`
- **Office365**: Use your email and password
  - SMTP Host: `smtp.office365.com`, Port: `587`, Encryption: `TLS`
- **Custom SMTP**: Contact your email provider for settings

#### **Page 4: Create Admin Account**
- **Full Name**: Your name
- **Email Address**: Your admin email (will receive verification email)
- **Username**: Admin username for login
- **Password**: Strong password (minimum 8 characters)
- **Confirm Password**: Re-enter password
- Click **"Create Admin Account"**

### Post-Setup

After completing the setup wizard:
1. **Check your email** for the admin verification email
2. **Login** with your admin credentials at: `http://your-domain.com/login.php`
3. **Verify SMTP** by checking the Email Logs page (Dashboard → System Admin → Email Logs)
4. **Setup is complete!** You can now configure your training platform

---

## Verification Checklist

After deployment, verify everything is working:

- [ ] **NGINX Container Running**: `docker ps | grep nginx`
- [ ] **MariaDB Container Running**: `docker ps | grep mariadb`
- [ ] **Website Accessible**: Visit `http://your-domain.com`
- [ ] **Setup Wizard Completed**: All 4 steps finished
- [ ] **Admin Login Works**: Can login at `/login.php`
- [ ] **Email Sending Works**: Check Email Logs page
- [ ] **File Uploads Work**: Try uploading a profile picture
- [ ] **Permissions Correct**: Check `/portainer/nginx/www/Arctic_Wolves` ownership is `911:911`

---

## Common Issues and Solutions

### Issue: "Failed to write configuration file"

**Symptom:** Setup wizard shows "Failed to write configuration file" but manual write test works.

**Diagnosis Steps:**
```bash
# 1. Check current permissions
ls -ld /portainer/nginx/www/Arctic_Wolves
# Should show: drwxrwxr-x  911 911

# 2. Test manual write from container (should work)
docker exec nginx touch /config/www/Arctic_Wolves/test.txt && \
docker exec nginx rm /config/www/Arctic_Wolves/test.txt && \
echo "✅ Container can write" || echo "❌ Container cannot write"

# 3. Check PHP user
docker exec nginx ps aux | grep php-fpm
# Should show processes running as user 'abc' (UID 911)

# 4. Check web server user from inside container
docker exec nginx whoami
# Should show 'abc'
```

**Solutions:**

1. **PRIMARY FIX - Set permissions INSIDE the container:**
```bash
# This fixes the issue where PHP sees directory as "Writable: No"
docker exec nginx chown -R abc:abc /config/www/Arctic_Wolves
docker exec nginx chmod 775 /config/www/Arctic_Wolves
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/uploads
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/sessions
docker exec nginx chmod -R 775 /config/www/Arctic_Wolves/cache

# Verify directory is now writable by PHP
docker exec nginx sh -c '[ -w /config/www/Arctic_Wolves ] && echo "✅ Fixed" || echo "❌ Still not writable"'
```

2. **Alternative - Set permissions on host (less reliable):**
```bash
sudo chmod 775 /portainer/nginx/www/www/Arctic_Wolves
sudo chown 911:911 /portainer/nginx/www/www/Arctic_Wolves
```

3. **View detailed error from setup wizard:**
   - The setup wizard shows: file path, error message, writable status, and web server user
   - This helps identify the exact issue

4. **Check PHP error log for details:**
```bash
docker exec nginx tail -50 /config/log/php-error.log
```

5. **Verify directory exists and is accessible:**
```bash
docker exec nginx ls -la /config/www/www/Arctic_Wolves/
```

### Issue: "502 Bad Gateway"
**Solutions:**
1. Check PHP-FPM is running:
   ```bash
   docker exec nginx ps aux | grep php-fpm
   ```
2. Verify NGINX config:
   ```bash
   docker exec nginx nginx -t
   ```
3. Check logs:
   ```bash
   docker logs nginx
   tail -50 /portainer/nginx/log/php-error.log
   ```

### Issue: "Can't connect to database"
**Solutions:**
1. Verify MariaDB is running:
   ```bash
   docker ps | grep mariadb
   ```
2. Test connection:
   ```bash
   docker exec -it mariadb mysql -uchroot -p www/Arctic_Wolves
   ```
3. Check Docker link:
   ```bash
   docker exec nginx ping -c 3 mariadb
   ```

### Issue: "Email not sending"
**Solutions:**
1. Check Email Logs page (Dashboard → System Admin → Email Logs)
2. View error messages in the logs table
3. Verify SMTP settings are correct
4. For Gmail, ensure you're using an App Password, not your regular password
5. Check PHP error log:
   ```bash
   tail -50 /portainer/nginx/log/php-error.log | grep -i mail
   ```

### Issue: "Permission denied" errors
**Solution:**
```bash
# Reset all permissions
cd /portainer/nginx/www/www/Arctic_Wolves
sudo chown -R 911:911 .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;
sudo chmod 775 .
sudo chmod -R 775 uploads sessions cache
```

---

## SSL/HTTPS Setup (Optional but Recommended)

For production deployment, enable HTTPS:

### Option 1: Using Let's Encrypt (Recommended)

```bash
# Install certbot in NGINX container
docker exec -it nginx apk add certbot certbot-nginx

# Obtain certificate (replace with your domain)
docker exec -it nginx certbot --nginx -d your-domain.com -d www.your-domain.com

# Certificates will be auto-renewed
```

### Option 2: Using Existing Certificates

```bash
# Copy your certificates to NGINX
sudo mkdir -p /config/nginx/ssl
sudo cp your-certificate.crt /config/nginx/ssl/www/Arctic_Wolves.ca.crt
sudo cp your-private-key.key /config/nginx/ssl/www/Arctic_Wolves.ca.key
sudo chown -R 911:911 /config/nginx/ssl

# The www/Arctic_Wolves.conf is already configured to use these paths
# Just restart NGINX
docker restart nginx
```

---

## Maintenance Commands

### View Logs
```bash
# NGINX access log
tail -f /portainer/nginx/log/www/Arctic_Wolves_access.log

# NGINX error log
tail -f /portainer/nginx/log/www/Arctic_Wolves_error.log

# PHP error log
tail -f /portainer/nginx/log/php-error.log

# Docker container logs
docker logs nginx --tail 100 -f
docker logs mariadb --tail 100 -f
```

### Backup Database
```bash
# Backup to file
docker exec mariadb mysqldump -uchroot -p www/Arctic_Wolves > backup_$(date +%Y%m%d).sql

# Restore from backup
docker exec -i mariadb mysql -uchroot -p www/Arctic_Wolves < backup_20260120.sql
```

### Update Application
```bash
cd /portainer/nginx/www/www/Arctic_Wolves
sudo git pull origin main
sudo chown -R 911:911 .
docker restart nginx
```

### Restart Services
```bash
# Restart NGINX
docker restart nginx

# Restart MariaDB
docker restart mariadb

# Restart both
docker restart nginx mariadb
```

---

## Security Best Practices

1. **Change default passwords** - Never use example passwords in production
2. **Enable HTTPS** - Use Let's Encrypt or purchase SSL certificate
3. **Regular backups** - Automate daily database backups
4. **Keep updated** - Regularly update Docker containers and application
5. **Firewall rules** - Only expose ports 80, 443, and 9443 (Portainer)
6. **Strong encryption key** - Use a random 32-character encryption key
7. **Monitor logs** - Regularly check logs for suspicious activity

---

## Additional Configuration

### Setup Cron Jobs (Automated Tasks)

Create a cron script on your host:

```bash
# Create cron script
sudo nano /usr/local/bin/www/Arctic_Wolves-cron.sh
```

Add the following:
```bash
#!/bin/bash
# Crash Hockey Cron Jobs

# Receipt scanner (every 5 minutes)
docker exec nginx /usr/bin/php /portainer/nginx/www/www/Arctic_Wolves/cron_receipt_scanner.php

# Notifications (every 10 minutes)
docker exec nginx /usr/bin/php /portainer/nginx/www/www/Arctic_Wolves/cron_notifications.php

# Credit expiry check (daily at 2 AM)
docker exec nginx /usr/bin/php /portainer/nginx/www/www/Arctic_Wolves/cron_credit_expiry.php
```

Make executable and add to crontab:
```bash
sudo chmod +x /usr/local/bin/www/Arctic_Wolves-cron.sh

# Edit root crontab
sudo crontab -e

# Add these lines:
*/5 * * * * /usr/local/bin/www/Arctic_Wolves-cron.sh >> /var/log/www/Arctic_Wolves-cron.log 2>&1
```

---

## Support and Documentation

- **Project Updates**: See `deployment/UPDATES.md` for change history
- **Quick Reference**: See `/README.md` in repository root
- **Email Debugging**: Dashboard → System Admin → Email Logs
- **Error Logs**: `/portainer/nginx/log/` directory

For issues during deployment, check:
1. Docker container status: `docker ps -a`
2. NGINX logs: `docker logs nginx`
3. PHP error log: `tail -f /portainer/nginx/log/php-error.log`
4. Database connectivity: `docker exec -it mariadb mysql -uchroot -p www/Arctic_Wolves`
5. File permissions: `ls -la /portainer/nginx/www/www/Arctic_Wolves`

---

**Deployment complete!** Your Crash Hockey Training Platform should now be fully operational.

---

## Game Plan & Video Companion Server

The Arctic Wolves Game Plan (Video Review) system runs on `gameplan.arcticwolves.ca` as a subdomain. The Game Plan code has been merged into the Arctic_Wolves repository, so both the main site and Game Plan share the same web root. An optional companion server provides hardware-accelerated video processing.

### Deploy Game Plan Subdomain

The Game Plan server block is included in `arctic_wolves.conf`. If you already deployed the main site config, the Game Plan subdomain is automatically configured.

```bash
# 1. Ensure DNS for gameplan.arcticwolves.ca points to the same server

# 2. The gameplan server block is already in arctic_wolves.conf
#    If you need to deploy it separately instead:
docker cp Arctic_Wolves/deployment/gameplan.conf nginx:/config/nginx/site-confs/gameplan.conf

# 3. Restart NGINX to pick up the config
docker restart nginx

# 4. Verify: http://gameplan.arcticwolves.ca should now load
```

### Deploy Video Companion Server

The companion server handles hardware-accelerated video encoding, decoding, and clip extraction.

```bash
# 1. Navigate to companion directory
cd /portainer/nginx/www/Arctic_Wolves/companion

# 2. Copy and configure environment
cp .env.example .env
nano .env  # Set API_KEY and VIDEO_BASE_PATH

# 3a. CPU-only deployment
docker compose up -d

# 3b. With NVIDIA GPU support (requires nvidia-container-toolkit)
docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d
```

### Configure Video Storage (NFS/SMB)

Both the Game Plan app and the companion server need access to the same video files. Mount the network share on both servers:

```bash
# NFS Mount (on both Game Plan and Companion hosts)
mkdir -p /videos
mount -t nfs nas.local:/volume1/videos /videos

# Or add to /etc/fstab for persistent mount:
# nas.local:/volume1/videos /videos nfs rw,sync,no_subtree_check 0 0
```

```bash
# SMB/CIFS Mount (alternative)
mkdir -p /videos
mount -t cifs //nas.local/videos /videos -o username=user,password=pass,uid=911,gid=911

# Or add to /etc/fstab for persistent mount:
# //nas.local/videos /videos cifs username=user,password=pass,uid=911,gid=911 0 0
```

For Docker, mount the same path in `docker-compose.yml`:
```yaml
volumes:
  - /videos:/videos
```

### Configure in Admin Dashboard

1. Go to **Administration → Game Plan Settings** in the main Arctic Wolves dashboard
2. Enter the companion server URL (e.g. `http://companion:5100`)
3. Set the shared API key
4. Enable hardware acceleration and select the method
5. Configure the video storage type (Local, NFS, or SMB) and mount path
