# Docker Setup Guide for Arctic Wolves

This guide provides Docker deployment instructions using the linuxserver containers.

## Docker Containers Used

- **Database**: [linuxserver/mariadb](https://hub.docker.com/r/linuxserver/mariadb)
- **Web Server**: [linuxserver/nginx](https://hub.docker.com/r/linuxserver/nginx)

## Initial Setup Commands

### 1. Automated Setup (Recommended)

The setup wizard (`setup.php`) **automatically creates directories and sets permissions** when you first run it. This is the recommended approach for most installations.

Simply navigate to `http://your-domain.com/setup.php` and follow the wizard. The permission setup happens automatically in the background.

### 2. Manual Setup (Optional)

If you prefer to set up permissions manually before running the setup wizard, you can use the provided script:

```bash
# Run the automated permission setup script
bash deployment/setup_permissions.sh
```

Or run the commands manually:

#### Create Required Directories

```bash
# Create application directories
docker exec nginx mkdir -p /config/www/arctic_wolves/uploads
docker exec nginx mkdir -p /config/www/arctic_wolves/sessions  
docker exec nginx mkdir -p /config/www/arctic_wolves/cache
docker exec nginx mkdir -p /config/www/arctic_wolves/logs
docker exec nginx mkdir -p /config/www/arctic_wolves/backups
docker exec nginx mkdir -p /config/www/arctic_wolves/receipts
docker exec nginx mkdir -p /config/www/arctic_wolves/videos
docker exec nginx mkdir -p /config/www/arctic_wolves/tmp
```

#### Set Ownership

The linuxserver/nginx container runs PHP-FPM as the `abc` user (UID 911, GID 911).

```bash
# Set ownership to 'abc' user INSIDE container (what PHP-FPM runs as)
docker exec nginx chown -R abc:abc /config/www/arctic_wolves
```

#### Set Permissions

```bash
# CRITICAL: Set root directory to 775 (allows PHP to write arctic_wolves.env during setup)
docker exec nginx chmod 775 /config/www/arctic_wolves

# Set upload/session/cache directories to 775 (web server needs write access)
docker exec nginx chmod -R 775 /config/www/arctic_wolves/uploads
docker exec nginx chmod -R 775 /config/www/arctic_wolves/sessions
docker exec nginx chmod -R 775 /config/www/arctic_wolves/cache
docker exec nginx chmod -R 775 /config/www/arctic_wolves/logs
docker exec nginx chmod -R 775 /config/www/arctic_wolves/backups
docker exec nginx chmod -R 775 /config/www/arctic_wolves/receipts
docker exec nginx chmod -R 775 /config/www/arctic_wolves/videos
docker exec nginx chmod -R 775 /config/www/arctic_wolves/tmp

# Set standard permissions for other directories and files
docker exec nginx find /config/www/arctic_wolves -type d -exec chmod 755 {} \;
docker exec nginx find /config/www/arctic_wolves -type f -exec chmod 644 {} \;

# Re-apply critical permissions (find command may have reset them)
docker exec nginx chmod 775 /config/www/arctic_wolves
docker exec nginx chmod -R 775 /config/www/arctic_wolves/uploads
docker exec nginx chmod -R 775 /config/www/arctic_wolves/sessions
docker exec nginx chmod -R 775 /config/www/arctic_wolves/cache
docker exec nginx chmod -R 775 /config/www/arctic_wolves/logs
docker exec nginx chmod -R 775 /config/www/arctic_wolves/backups
docker exec nginx chmod -R 775 /config/www/arctic_wolves/receipts
docker exec nginx chmod -R 775 /config/www/arctic_wolves/videos
docker exec nginx chmod -R 775 /config/www/arctic_wolves/tmp
```

### 3. Verify Permissions

```bash
# Verify permissions from inside container (what PHP actually sees)
docker exec nginx ls -ld /config/www/arctic_wolves
# Should show: drwxrwxr-x ... abc abc ... /config/www/arctic_wolves

# Test if directory is writable by PHP
docker exec nginx sh -c '[ -w /config/www/arctic_wolves ] && echo "✅ Directory IS writable by PHP" || echo "❌ Directory NOT writable by PHP"'

# Test if uploads directory is writable
docker exec nginx sh -c '[ -w /config/www/arctic_wolves/uploads ] && echo "✅ Uploads directory IS writable" || echo "❌ Uploads directory NOT writable"'
```

## Docker Compose Configuration

### MariaDB Container

```yaml
version: "3.8"
services:
  mariadb:
    image: linuxserver/mariadb:latest
    container_name: mariadb
    environment:
      - PUID=911
      - PGID=911
      - TZ=America/Toronto
      - MYSQL_ROOT_PASSWORD=your_secure_root_password
      - MYSQL_DATABASE=arctic_wolves
      - MYSQL_USER=arctic_wolves_user
      - MYSQL_PASSWORD=your_secure_password
    volumes:
      - /path/to/mariadb/config:/config
    ports:
      - 3306:3306
    restart: unless-stopped
```

### NGINX Container

```yaml
  nginx:
    image: linuxserver/nginx:latest
    container_name: nginx
    environment:
      - PUID=911
      - PGID=911
      - TZ=America/Toronto
    volumes:
      - /path/to/nginx/config:/config
      - /path/to/arctic_wolves:/config/www/arctic_wolves
    ports:
      - 80:80
      - 443:443
    depends_on:
      - mariadb
    restart: unless-stopped
```

## NGINX Configuration

Copy the `deployment/arctic_wolves.conf` file to your NGINX container:

```bash
# Copy nginx config
docker cp deployment/arctic_wolves.conf nginx:/config/nginx/site-confs/default.conf

# Restart nginx to apply changes
docker restart nginx
```

## Database Import

```bash
# Import the database schema
docker exec -i mariadb mysql -uarctic_wolves_user -pyour_secure_password arctic_wolves < database_schema.sql
```

## Environment File Setup

The application will create `/config/www/arctic_wolves/arctic_wolves.env` automatically during the setup wizard. Ensure the directory is writable (775 permissions).

Alternatively, create it manually:

```bash
docker exec nginx sh -c 'cat > /config/www/arctic_wolves/arctic_wolves.env << EOF
DB_HOST=mariadb
DB_NAME=arctic_wolves
DB_USER=arctic_wolves_user
DB_PASS=your_secure_password
EOF'

# Set proper ownership and permissions
docker exec nginx chown abc:abc /config/www/arctic_wolves/arctic_wolves.env
docker exec nginx chmod 640 /config/www/arctic_wolves/arctic_wolves.env
```

## First-Time Setup

1. Navigate to `http://your-domain.com/setup.php`
2. Follow the setup wizard
3. After setup, restrict access to setup.php by uncommenting the deny block in `arctic_wolves.conf`

## Troubleshooting

### Check PHP-FPM Status

```bash
# Check PHP-FPM is running on TCP port 9000
docker exec nginx netstat -tuln | grep 9000
# Should show: tcp        0      0 127.0.0.1:9000          0.0.0.0:*               LISTEN

# Check PHP-FPM process
docker exec nginx ps aux | grep php-fpm
```

### Check Database Connection

```bash
# Test database connection from nginx container
docker exec nginx php -r "try { new PDO('mysql:host=mariadb;dbname=arctic_wolves', 'arctic_wolves_user', 'your_secure_password'); echo 'Connected successfully\n'; } catch(PDOException \$e) { echo 'Connection failed: ' . \$e->getMessage() . '\n'; }"
```

### Check Permissions

```bash
# Check file ownership
docker exec nginx ls -la /config/www/arctic_wolves/

# Check specific directory permissions
docker exec nginx stat /config/www/arctic_wolves/uploads
```

### View Logs

```bash
# NGINX error log
docker exec nginx tail -f /config/log/arctic_wolves_error.log

# NGINX access log  
docker exec nginx tail -f /config/log/arctic_wolves_access.log

# PHP-FPM error log
docker exec nginx tail -f /config/log/php/error.log
```

## Security Notes

1. Change all default passwords before deploying to production
2. Use strong passwords for database users
3. Enable HTTPS/SSL for production deployments
4. Restrict setup.php access after initial setup
5. Regularly backup the database and uploaded files
6. Keep Docker images updated: `docker pull linuxserver/nginx:latest && docker pull linuxserver/mariadb:latest`

## Maintenance Commands

### Backup Database

```bash
# Create backup
docker exec mariadb mysqldump -uarctic_wolves_user -pyour_secure_password arctic_wolves | gzip > arctic_wolves_backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Restore Database

```bash
# Restore from backup
gunzip < arctic_wolves_backup_20260122_120000.sql.gz | docker exec -i mariadb mysql -uarctic_wolves_user -pyour_secure_password arctic_wolves
```

### Update Application Files

```bash
# Pull latest changes
git pull

# Sync files to container
docker cp /path/to/local/arctic_wolves/. nginx:/config/www/arctic_wolves/

# Set permissions again
docker exec nginx chown -R abc:abc /config/www/arctic_wolves
docker exec nginx chmod 775 /config/www/arctic_wolves
```
