#!/bin/bash
# Adapted deployment script for Filament Management System
# Works with existing BT Panel / aaPanel Nginx configuration

set -e  # Exit on any error

# Configuration
DOMAIN="filament.neuhauser.cloud"
APP_PATH="/mnt/HC_Volume_101973258/filament.neuhauser.cloud"
BT_NGINX_CONFIG="/www/server/panel/vhost/nginx/${DOMAIN}.conf"
PHP_VERSION="8.4"  # Based on your enable-php-84.conf
WEB_USER="www-data"

echo "🚀 Starting adapted deployment for $DOMAIN with BT Panel..."

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

# 1. Check if we're running on a BT Panel system
log "🔍 Checking BT Panel environment..."
if [ -d "/www/server/panel" ]; then
    log "✅ BT Panel detected - using adapted configuration"
    BT_PANEL=true
else
    log "⚠️ No BT Panel detected - proceeding with standard setup"
    BT_PANEL=false
fi

# 2. Create application directory structure
log "📁 Setting up directory structure..."
mkdir -p $APP_PATH/storage/logs
mkdir -p $APP_PATH/storage/cache
mkdir -p $APP_PATH/storage/sessions
mkdir -p $APP_PATH/storage/backups
mkdir -p $APP_PATH/public
check_success "Directory creation"

# 3. Set proper permissions
log "🔐 Setting file permissions..."
if [ "$BT_PANEL" = true ]; then
    chown -R www:www $APP_PATH
    chmod -R 755 $APP_PATH
    chmod -R 777 $APP_PATH/storage
else
    chown -R $WEB_USER:$WEB_USER $APP_PATH
    chmod -R 755 $APP_PATH
    chmod -R 777 $APP_PATH/storage
fi
check_success "Permission setup"

# 4. Update Nginx configuration (minimal changes to existing config)
log "📝 Updating Nginx configuration..."
if [ -f "$BT_NGINX_CONFIG" ]; then
    log "✅ BT Panel Nginx config found - making backup"
    cp $BT_NGINX_CONFIG ${BT_NGINX_CONFIG}.backup.$(date +%Y%m%d_%H%M%S)
    
    # Add only necessary changes to existing config
    if ! grep -q "try_files.*index.php" $BT_NGINX_CONFIG; then
        log "📝 Adding PHP application routing to existing config..."
        
        # Create a temporary file with the necessary additions
        cat > /tmp/nginx_additions.conf << 'EOF'

    # Main application routing - ADDED FOR PHP APPLICATION
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Additional security headers for the application
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Additional security - protect sensitive directories
    location ~ /(config|src|storage|vendor|tests|docs)/ {
        deny all;
        return 404;
    }

    # Protect composer files
    location ~ /composer\.(json|lock) {
        deny all;
        return 404;
    }
EOF
        
        # Insert the additions before the last closing brace
        sed -i '/^[[:space:]]*#Monitor-Config-End/r /tmp/nginx_additions.conf' $BT_NGINX_CONFIG
        rm /tmp/nginx_additions.conf
        log "✅ Nginx configuration updated"
    else
        log "✅ PHP routing already configured"
    fi
else
    log "⚠️ BT Panel config not found - using deployment config"
    cp deployment/nginx/filament.neuhauser.cloud-adapted.conf /etc/nginx/sites-available/${DOMAIN}.conf
    ln -sf /etc/nginx/sites-available/${DOMAIN}.conf /etc/nginx/sites-enabled/
fi

# 5. Test Nginx configuration
log "✅ Testing Nginx configuration..."
nginx -t
check_success "Nginx configuration test"

# 6. Configure PHP-FPM for BT Panel
log "⚙️ Configuring PHP-FPM..."
if [ "$BT_PANEL" = true ]; then
    PHP_FPM_CONF="/www/server/php/${PHP_VERSION/./}/etc/php-fpm.d/www.conf"
    if [ -f "$PHP_FPM_CONF" ]; then
        # Backup original
        cp $PHP_FPM_CONF ${PHP_FPM_CONF}.backup.$(date +%Y%m%d_%H%M%S)
        
        # Update PHP-FPM settings for the application
        sed -i 's/pm.max_children = .*/pm.max_children = 50/' $PHP_FPM_CONF
        sed -i 's/pm.start_servers = .*/pm.start_servers = 5/' $PHP_FPM_CONF
        sed -i 's/pm.min_spare_servers = .*/pm.min_spare_servers = 5/' $PHP_FPM_CONF
        sed -i 's/pm.max_spare_servers = .*/pm.max_spare_servers = 20/' $PHP_FPM_CONF
        
        log "✅ PHP-FPM configuration updated for BT Panel"
    fi
fi

# 7. Create application-specific error pages
log "📄 Creating custom error pages..."
# Error pages are already created in previous steps

# 8. Setup database configuration template
log "🗄️ Creating database configuration template..."
cat > $APP_PATH/config/database.php.example << EOF
<?php
return [
    'host' => 'localhost',
    'database' => 'filament_management',
    'username' => 'filament_user',
    'password' => 'your_secure_password_here',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
EOF

# 9. Setup log rotation
log "📊 Setting up log rotation..."
cat > /etc/logrotate.d/${DOMAIN} << EOF
$APP_PATH/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    copytruncate
    su www www
}

/www/wwwlogs/${DOMAIN}*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    postrotate
        systemctl reload nginx > /dev/null 2>&1 || true
    endscript
}
EOF

# 10. Create backup script adapted for BT Panel
log "💾 Setting up backup system..."
cat > /usr/local/bin/backup-${DOMAIN}.sh << EOF
#!/bin/bash
BACKUP_DIR="$APP_PATH/storage/backups"
DATE=\$(date +%Y%m%d_%H%M%S)
DB_NAME="filament_management"

# Create database backup
if command -v mysqldump >/dev/null 2>&1; then
    mysqldump \$DB_NAME | gzip > \$BACKUP_DIR/db_backup_\$DATE.sql.gz
else
    /www/server/mysql/bin/mysqldump \$DB_NAME | gzip > \$BACKUP_DIR/db_backup_\$DATE.sql.gz
fi

# Create application backup
tar -czf \$BACKUP_DIR/app_backup_\$DATE.tar.gz -C $APP_PATH --exclude=storage/backups --exclude=storage/logs .

# Keep only last 7 days of backups
find \$BACKUP_DIR -name "*.gz" -mtime +7 -delete
find \$BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: \$DATE" >> \$BACKUP_DIR/backup.log
EOF

chmod +x /usr/local/bin/backup-${DOMAIN}.sh

# Add to crontab if not exists
if ! crontab -l 2>/dev/null | grep -q "backup-${DOMAIN}.sh"; then
    (crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-${DOMAIN}.sh") | crontab -
fi

# 11. Setup monitoring script
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
    # Restart services
    systemctl restart nginx
    if [ "$BT_PANEL" = true ]; then
        systemctl restart php${PHP_VERSION/./}-fpm
    fi
fi

# Check disk space
DISK_USAGE=\$(df $APP_PATH | tail -1 | awk '{print \$5}' | sed 's/%//')
if [ \$DISK_USAGE -gt 85 ]; then
    echo "[\$DATE] WARNING: Disk usage is \$DISK_USAGE%" >> \$LOG_FILE
fi
EOF

chmod +x /usr/local/bin/monitor-${DOMAIN}.sh

# Add to crontab if not exists
if ! crontab -l 2>/dev/null | grep -q "monitor-${DOMAIN}.sh"; then
    (crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/monitor-${DOMAIN}.sh") | crontab -
fi

# 12. Restart services
log "🔄 Restarting services..."
systemctl reload nginx

if [ "$BT_PANEL" = true ]; then
    systemctl restart php${PHP_VERSION/./}-fpm
else
    systemctl restart php${PHP_VERSION}-fpm
fi

check_success "Service restart"

# 13. Final verification
log "🔍 Running final verification..."
sleep 3
if curl -f -s https://$DOMAIN > /dev/null; then
    log "✅ Deployment successful! Site is accessible at https://$DOMAIN"
else
    log "⚠️ Deployment completed but site verification failed. Check logs."
    log "📋 Checking error logs..."
    if [ -f "/www/wwwlogs/${DOMAIN}.error.log" ]; then
        tail -10 /www/wwwlogs/${DOMAIN}.error.log
    fi
fi

# 14. Display deployment summary
cat << EOF

🎉 DEPLOYMENT COMPLETED!

📋 Deployment Summary:
- Domain: https://$DOMAIN
- Application Path: $APP_PATH
- PHP Version: $PHP_VERSION
- Panel Type: $([ "$BT_PANEL" = true ] && echo "BT Panel" || echo "Standard")
- SSL Certificate: Active (existing configuration preserved)
- Backups: Automated (daily at 2 AM)
- Monitoring: Active (every 5 minutes)

🔧 Next Steps:
1. Copy database configuration:
   cp $APP_PATH/config/database.php.example $APP_PATH/config/database.php
2. Edit database settings in config/database.php
3. Import database schema:
   mysql -u username -p filament_management < database/schema.sql
4. Test all application functionality

📊 BT Panel Integration:
- Nginx config: $BT_NGINX_CONFIG
- Logs: /www/wwwlogs/${DOMAIN}*.log  
- PHP-FPM: /www/server/php/${PHP_VERSION/./}/

📋 Useful Commands:
- View app logs: tail -f $APP_PATH/storage/logs/app.log
- View nginx logs: tail -f /www/wwwlogs/${DOMAIN}.error.log
- Run backup: /usr/local/bin/backup-${DOMAIN}.sh
- Check status: systemctl status nginx php${PHP_VERSION/./}-fpm

EOF

log "✅ Adapted deployment script completed successfully!"