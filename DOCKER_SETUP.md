# Docker Setup Guide for Artic Wolves

This guide provides Docker deployment instructions using the linuxserver containers.

## Docker Containers Used

- **Database**: [linuxserver/mariadb](https://hub.docker.com/r/linuxserver/mariadb)
- **Web Server**: [linuxserver/nginx](https://hub.docker.com/r/linuxserver/nginx)

## Initial Setup Commands

### 1. Create Required Directories

```bash
# Create application directories
docker exec nginx mkdir -p /config/www/artic_wolves/uploads
docker exec nginx mkdir -p /config/www/artic_wolves/sessions  
docker exec nginx mkdir -p /config/www/artic_wolves/cache
docker exec nginx mkdir -p /config/www/artic_wolves/logs
docker exec nginx mkdir -p /config/www/artic_wolves/backups
docker exec nginx mkdir -p /config/www/artic_wolves/receipts
docker exec nginx mkdir -p /config/www/artic_wolves/videos
docker exec nginx mkdir -p /config/www/artic_wolves/tmp
```

### 2. Set Ownership

The linuxserver/nginx container runs PHP-FPM as the `abc` user (UID 911, GID 911).

```bash
# Set ownership to 'abc' user INSIDE container (what PHP-FPM runs as)
docker exec nginx chown -R abc:abc /config/www/artic_wolves
```

### 3. Set Permissions

```bash
# CRITICAL: Set root directory to 775 (allows PHP to write artic_wolves.env during setup)
docker exec nginx chmod 775 /config/www/artic_wolves

# Set upload/session/cache directories to 775 (web server needs write access)
docker exec nginx chmod -R 775 /config/www/artic_wolves/uploads
docker exec nginx chmod -R 775 /config/www/artic_wolves/sessions
docker exec nginx chmod -R 775 /config/www/artic_wolves/cache
docker exec nginx chmod -R 775 /config/www/artic_wolves/logs
docker exec nginx chmod -R 775 /config/www/artic_wolves/backups
docker exec nginx chmod -R 775 /config/www/artic_wolves/receipts
docker exec nginx chmod -R 775 /config/www/artic_wolves/videos
docker exec nginx chmod -R 775 /config/www/artic_wolves/tmp

# Set standard permissions for other directories and files
docker exec nginx find /config/www/artic_wolves -type d -exec chmod 755 {} \;
docker exec nginx find /config/www/artic_wolves -type f -exec chmod 644 {} \;

# Re-apply critical permissions (find command may have reset them)
docker exec nginx chmod 775 /config/www/artic_wolves
docker exec nginx chmod -R 775 /config/www/artic_wolves/uploads
docker exec nginx chmod -R 775 /config/www/artic_wolves/sessions
docker exec nginx chmod -R 775 /config/www/artic_wolves/cache
docker exec nginx chmod -R 775 /config/www/artic_wolves/logs
docker exec nginx chmod -R 775 /config/www/artic_wolves/backups
docker exec nginx chmod -R 775 /config/www/artic_wolves/receipts
docker exec nginx chmod -R 775 /config/www/artic_wolves/videos
docker exec nginx chmod -R 775 /config/www/artic_wolves/tmp
```

### 4. Verify Permissions

```bash
# Verify permissions from inside container (what PHP actually sees)
docker exec nginx ls -ld /config/www/artic_wolves
# Should show: drwxrwxr-x ... abc abc ... /config/www/artic_wolves

# Test if directory is writable by PHP
docker exec nginx sh -c '[ -w /config/www/artic_wolves ] && echo "✅ Directory IS writable by PHP" || echo "❌ Directory NOT writable by PHP"'

# Test if uploads directory is writable
docker exec nginx sh -c '[ -w /config/www/artic_wolves/uploads ] && echo "✅ Uploads directory IS writable" || echo "❌ Uploads directory NOT writable"'
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
      - MYSQL_DATABASE=artic_wolves
      - MYSQL_USER=artic_wolves_user
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
      - /path/to/artic_wolves:/config/www/artic_wolves
    ports:
      - 80:80
      - 443:443
    depends_on:
      - mariadb
    restart: unless-stopped
```

## NGINX Configuration

Copy the `deployment/artic_wolves.conf` file to your NGINX container:

```bash
# Copy nginx config
docker cp deployment/artic_wolves.conf nginx:/config/nginx/site-confs/default.conf

# Restart nginx to apply changes
docker restart nginx
```

## Database Import

```bash
# Import the database schema
docker exec -i mariadb mysql -uartic_wolves_user -pyour_secure_password artic_wolves < database_schema.sql
```

## Environment File Setup

The application will create `/config/www/artic_wolves/artic_wolves.env` automatically during the setup wizard. Ensure the directory is writable (775 permissions).

Alternatively, create it manually:

```bash
docker exec nginx sh -c 'cat > /config/www/artic_wolves/artic_wolves.env << EOF
DB_HOST=mariadb
DB_NAME=artic_wolves
DB_USER=artic_wolves_user
DB_PASS=your_secure_password
EOF'

# Set proper ownership and permissions
docker exec nginx chown abc:abc /config/www/artic_wolves/artic_wolves.env
docker exec nginx chmod 640 /config/www/artic_wolves/artic_wolves.env
```

## First-Time Setup

1. Navigate to `http://your-domain.com/setup.php`
2. Follow the setup wizard
3. After setup, restrict access to setup.php by uncommenting the deny block in `artic_wolves.conf`

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
docker exec nginx php -r "try { new PDO('mysql:host=mariadb;dbname=artic_wolves', 'artic_wolves_user', 'your_secure_password'); echo 'Connected successfully\n'; } catch(PDOException \$e) { echo 'Connection failed: ' . \$e->getMessage() . '\n'; }"
```

### Check Permissions

```bash
# Check file ownership
docker exec nginx ls -la /config/www/artic_wolves/

# Check specific directory permissions
docker exec nginx stat /config/www/artic_wolves/uploads
```

### View Logs

```bash
# NGINX error log
docker exec nginx tail -f /config/log/artic_wolves_error.log

# NGINX access log  
docker exec nginx tail -f /config/log/artic_wolves_access.log

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
docker exec mariadb mysqldump -uartic_wolves_user -pyour_secure_password artic_wolves | gzip > artic_wolves_backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Restore Database

```bash
# Restore from backup
gunzip < artic_wolves_backup_20260122_120000.sql.gz | docker exec -i mariadb mysql -uartic_wolves_user -pyour_secure_password artic_wolves
```

### Update Application Files

```bash
# Pull latest changes
git pull

# Sync files to container
docker cp /path/to/local/artic_wolves/. nginx:/config/www/artic_wolves/

# Set permissions again
docker exec nginx chown -R abc:abc /config/www/artic_wolves
docker exec nginx chmod 775 /config/www/artic_wolves
```
