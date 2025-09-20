#!/bin/bash
# Deployment script for Filament Management System
# Production deployment with security hardening

set -e  # Exit on any error

# Configuration
DOMAIN="filament.neuhauser.cloud"
APP_PATH="/mnt/HC_Volume_101973258/filament.neuhauser.cloud"
NGINX_CONFIG_PATH="/etc/nginx/sites-available"
NGINX_ENABLED_PATH="/etc/nginx/sites-enabled"
PHP_VERSION="8.2"
WEB_USER="www-data"

echo "🚀 Starting deployment for $DOMAIN..."

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if command succeeded
check_success() {
    if [ $? -eq 0 ]; then
        log "✅ $1 completed successfully"
    else
        log "❌ $1 failed"
        exit 1
    fi
}

# 1. Update system packages
log "📦 Updating system packages..."
apt update && apt upgrade -y
check_success "System update"

# 2. Install required packages if not present
log "📋 Installing required packages..."
apt install -y nginx php${PHP_VERSION}-fpm php${PHP_VERSION}-mysql php${PHP_VERSION}-curl php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-zip certbot python3-certbot-nginx
check_success "Package installation"

# 3. Create application directory structure
log "📁 Setting up directory structure..."
mkdir -p $APP_PATH
mkdir -p $APP_PATH/storage/logs
mkdir -p $APP_PATH/storage/cache
mkdir -p $APP_PATH/storage/sessions
mkdir -p $APP_PATH/storage/backups
mkdir -p $APP_PATH/public/error
check_success "Directory creation"

# 4. Set proper permissions
log "🔐 Setting file permissions..."
chown -R $WEB_USER:$WEB_USER $APP_PATH
chmod -R 755 $APP_PATH
chmod -R 777 $APP_PATH/storage
check_success "Permission setup"

# 5. Configure PHP-FPM
log "⚙️ Configuring PHP-FPM..."
cat > /etc/php/${PHP_VERSION}/fpm/pool.d/${DOMAIN}.conf << EOF
[${DOMAIN}]
user = $WEB_USER
group = $WEB_USER
listen = /var/run/php/php${PHP_VERSION}-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.process_idle_timeout = 10s
pm.max_requests = 500
php_admin_value[error_log] = $APP_PATH/storage/logs/php-fpm.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = files
php_value[session.save_path] = $APP_PATH/storage/sessions
php_value[max_execution_time] = 300
php_value[max_input_time] = 300
php_value[memory_limit] = 256M
php_value[post_max_size] = 20M
php_value[upload_max_filesize] = 20M
php_admin_value[expose_php] = Off
EOF

systemctl restart php${PHP_VERSION}-fpm
check_success "PHP-FPM configuration"

# 6. Configure Nginx main config (rate limiting)
log "🌐 Configuring Nginx rate limiting..."
if ! grep -q "limit_req_zone.*api" /etc/nginx/nginx.conf; then
    sed -i '/http {/a\\n\t# Rate limiting zones\n\tlimit_req_zone $binary_remote_addr zone=api:10m rate=100r/h;\n\tlimit_req_zone $binary_remote_addr zone=admin:10m rate=30r/h;\n\tlimit_req_zone $binary_remote_addr zone=auth:10m rate=5r/h;\n' /etc/nginx/nginx.conf
fi
check_success "Nginx rate limiting setup"

# 7. Copy and enable Nginx site configuration
log "📝 Setting up Nginx virtual host..."
cp deployment/nginx/${DOMAIN}.conf $NGINX_CONFIG_PATH/
ln -sf $NGINX_CONFIG_PATH/${DOMAIN}.conf $NGINX_ENABLED_PATH/
rm -f $NGINX_ENABLED_PATH/default
check_success "Nginx virtual host setup"

# 8. Test Nginx configuration
log "✅ Testing Nginx configuration..."
nginx -t
check_success "Nginx configuration test"

# 9. Obtain SSL certificate
log "🔒 Setting up SSL certificate..."
if [ ! -f /etc/letsencrypt/live/${DOMAIN}/fullchain.pem ]; then
    certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@${DOMAIN}
    check_success "SSL certificate installation"
else
    log "✅ SSL certificate already exists"
fi

# 10. Setup automatic certificate renewal
log "🔄 Setting up automatic SSL renewal..."
(crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
check_success "SSL auto-renewal setup"

# 11. Configure firewall
log "🛡️ Configuring firewall..."
ufw allow 'Nginx Full'
ufw allow ssh
ufw --force enable
check_success "Firewall configuration"

# 12. Create systemd service for log rotation
log "📊 Setting up log rotation..."
cat > /etc/logrotate.d/${DOMAIN} << EOF
$APP_PATH/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    notifempty
    copytruncate
}

/var/log/nginx/${DOMAIN}.*.log {
    daily
    missingok
    rotate 52
    compress
    notifempty
    postrotate
        systemctl reload nginx > /dev/null 2>&1 || true
    endscript
}
EOF
check_success "Log rotation setup"

# 13. Create backup script
log "💾 Setting up backup system..."
cat > /usr/local/bin/backup-${DOMAIN}.sh << EOF
#!/bin/bash
BACKUP_DIR="$APP_PATH/storage/backups"
DATE=\$(date +%Y%m%d_%H%M%S)
DB_NAME="filament_management"

# Create database backup
mysqldump -u root \$DB_NAME | gzip > \$BACKUP_DIR/db_backup_\$DATE.sql.gz

# Create application backup
tar -czf \$BACKUP_DIR/app_backup_\$DATE.tar.gz -C $APP_PATH --exclude=storage/backups --exclude=storage/logs .

# Keep only last 7 days of backups
find \$BACKUP_DIR -name "*.gz" -mtime +7 -delete
find \$BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: \$DATE"
EOF

chmod +x /usr/local/bin/backup-${DOMAIN}.sh
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-${DOMAIN}.sh >> $APP_PATH/storage/logs/backup.log 2>&1") | crontab -
check_success "Backup system setup"

# 14. Setup monitoring script
log "📊 Setting up monitoring..."
cat > /usr/local/bin/monitor-${DOMAIN}.sh << EOF
#!/bin/bash
LOG_FILE="$APP_PATH/storage/logs/monitor.log"
DATE=\$(date '+%Y-%m-%d %H:%M:%S')

# Check if site is responding
if curl -f -s https://$DOMAIN > /dev/null; then
    echo "[\$DATE] Site is responding" >> \$LOG_FILE
else
    echo "[\$DATE] WARNING: Site is not responding" >> \$LOG_FILE
    systemctl restart nginx
    systemctl restart php${PHP_VERSION}-fpm
fi

# Check disk space
DISK_USAGE=\$(df $APP_PATH | tail -1 | awk '{print \$5}' | sed 's/%//')
if [ \$DISK_USAGE -gt 85 ]; then
    echo "[\$DATE] WARNING: Disk usage is \$DISK_USAGE%" >> \$LOG_FILE
fi
EOF

chmod +x /usr/local/bin/monitor-${DOMAIN}.sh
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/monitor-${DOMAIN}.sh") | crontab -
check_success "Monitoring setup"

# 15. Restart all services
log "🔄 Restarting services..."
systemctl restart nginx
systemctl restart php${PHP_VERSION}-fpm
systemctl enable nginx
systemctl enable php${PHP_VERSION}-fpm
check_success "Service restart"

# 16. Final verification
log "🔍 Running final verification..."
if curl -f -s https://$DOMAIN > /dev/null; then
    log "✅ Deployment successful! Site is accessible at https://$DOMAIN"
else
    log "⚠️ Deployment completed but site verification failed. Check logs."
    exit 1
fi

# 17. Display deployment summary
cat << EOF

🎉 DEPLOYMENT COMPLETED SUCCESSFULLY!

📋 Deployment Summary:
- Domain: https://$DOMAIN
- Application Path: $APP_PATH
- PHP Version: $PHP_VERSION
- SSL Certificate: Active (Let's Encrypt)
- Firewall: Enabled
- Backups: Automated (daily at 2 AM)
- Monitoring: Active (every 5 minutes)
- Log Rotation: Configured

🔧 Next Steps:
1. Update your database configuration in config/database.php
2. Run database migrations if needed
3. Test all application functionality
4. Monitor logs in $APP_PATH/storage/logs/

📊 Useful Commands:
- View logs: tail -f $APP_PATH/storage/logs/app.log
- Check Nginx: systemctl status nginx
- Check PHP-FPM: systemctl status php${PHP_VERSION}-fpm
- Run backup: /usr/local/bin/backup-${DOMAIN}.sh
- Check SSL: certbot certificates

EOF

log "✅ Deployment script completed successfully!"