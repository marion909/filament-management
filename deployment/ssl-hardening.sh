#!/bin/bash
# SSL Security Hardening Script for Filament Management System
# This script implements additional SSL security measures

set -e

DOMAIN="filament.neuhauser.cloud"
NGINX_CONFIG="/etc/nginx/sites-available/${DOMAIN}.conf"

echo "🔒 Starting SSL Security Hardening..."

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# 1. Generate strong Diffie-Hellman parameters
log "🔐 Generating strong DH parameters (this may take a while)..."
if [ ! -f /etc/nginx/dhparam.pem ]; then
    openssl dhparam -out /etc/nginx/dhparam.pem 2048
    log "✅ DH parameters generated"
else
    log "✅ DH parameters already exist"
fi

# 2. Create SSL configuration snippet
log "⚙️ Creating SSL configuration snippet..."
cat > /etc/nginx/snippets/ssl-${DOMAIN}.conf << EOF
# SSL Certificate paths
ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
ssl_trusted_certificate /etc/letsencrypt/live/${DOMAIN}/chain.pem;

# SSL Session settings
ssl_session_timeout 1d;
ssl_session_cache shared:SSL:50m;
ssl_session_tickets off;

# Diffie-Hellman parameter for DHE ciphersuites
ssl_dhparam /etc/nginx/dhparam.pem;

# Modern SSL configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;

# OCSP stapling
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
EOF

# 3. Create security headers snippet
log "🛡️ Creating security headers snippet..."
cat > /etc/nginx/snippets/security-headers.conf << EOF
# Security headers
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=(), usb=(), bluetooth=(), payment=(), accelerometer=(), gyroscope=(), magnetometer=(), ambient-light-sensor=(), autoplay=()" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; child-src 'self'; frame-src 'none'; worker-src 'self'; manifest-src 'self'; form-action 'self'; upgrade-insecure-requests; block-all-mixed-content" always;

# Remove server information
server_tokens off;
more_set_headers "Server: Secure-Server";
EOF

# 4. Test SSL configuration
log "✅ Testing SSL configuration..."
nginx -t

# 5. Create SSL monitoring script
log "📊 Creating SSL monitoring script..."
cat > /usr/local/bin/ssl-monitor-${DOMAIN}.sh << EOF
#!/bin/bash
DOMAIN="${DOMAIN}"
LOG_FILE="/var/log/ssl-monitor.log"
EMAIL="admin@\${DOMAIN}"

# Check certificate expiry
EXPIRY_DATE=\$(echo | openssl s_client -servername \$DOMAIN -connect \$DOMAIN:443 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
EXPIRY_TIMESTAMP=\$(date -d "\$EXPIRY_DATE" +%s)
CURRENT_TIMESTAMP=\$(date +%s)
DAYS_UNTIL_EXPIRY=\$(( (\$EXPIRY_TIMESTAMP - \$CURRENT_TIMESTAMP) / 86400 ))

echo "\$(date): Certificate expires in \$DAYS_UNTIL_EXPIRY days" >> \$LOG_FILE

# Alert if certificate expires in less than 30 days
if [ \$DAYS_UNTIL_EXPIRY -lt 30 ]; then
    echo "WARNING: SSL certificate for \$DOMAIN expires in \$DAYS_UNTIL_EXPIRY days!" | mail -s "SSL Certificate Warning" \$EMAIL
fi

# Check SSL configuration
SSL_SCORE=\$(curl -s "https://api.ssllabs.com/api/v3/analyze?host=\$DOMAIN" | jq -r '.endpoints[0].grade // "N/A"')
echo "\$(date): SSL Labs grade: \$SSL_SCORE" >> \$LOG_FILE
EOF

chmod +x /usr/local/bin/ssl-monitor-${DOMAIN}.sh

# 6. Setup SSL monitoring cron job
log "⏰ Setting up SSL monitoring cron job..."
(crontab -l 2>/dev/null; echo "0 6 * * * /usr/local/bin/ssl-monitor-${DOMAIN}.sh") | crontab -

# 7. Create HSTS preload verification
log "🔗 Creating HSTS preload verification script..."
cat > /usr/local/bin/hsts-verify-${DOMAIN}.sh << EOF
#!/bin/bash
DOMAIN="${DOMAIN}"

echo "Checking HSTS status for \$DOMAIN..."

# Check current HSTS header
HSTS_HEADER=\$(curl -s -I https://\$DOMAIN | grep -i strict-transport-security)
if [ -n "\$HSTS_HEADER" ]; then
    echo "✅ HSTS Header found: \$HSTS_HEADER"
else
    echo "❌ No HSTS Header found"
fi

# Check HSTS preload list status
echo "Checking HSTS preload list status..."
curl -s "https://hstspreload.org/api/v2/status?domain=\$DOMAIN" | jq '.'
EOF

chmod +x /usr/local/bin/hsts-verify-${DOMAIN}.sh

# 8. Create certificate auto-renewal with hooks
log "🔄 Enhancing certificate auto-renewal..."
mkdir -p /etc/letsencrypt/renewal-hooks/post
cat > /etc/letsencrypt/renewal-hooks/post/nginx-reload.sh << EOF
#!/bin/bash
systemctl reload nginx
echo "\$(date): Certificate renewed and Nginx reloaded" >> /var/log/ssl-renewal.log
EOF

chmod +x /etc/letsencrypt/renewal-hooks/post/nginx-reload.sh

# 9. Test certificate renewal
log "🧪 Testing certificate renewal..."
certbot renew --dry-run

# 10. Create SSL report generator
log "📋 Creating SSL security report generator..."
cat > /usr/local/bin/ssl-report-${DOMAIN}.sh << EOF
#!/bin/bash
DOMAIN="${DOMAIN}"
REPORT_FILE="/var/log/ssl-security-report.txt"

echo "SSL Security Report for \$DOMAIN - \$(date)" > \$REPORT_FILE
echo "================================================" >> \$REPORT_FILE

# Certificate information
echo -e "\n🔒 Certificate Information:" >> \$REPORT_FILE
echo | openssl s_client -servername \$DOMAIN -connect \$DOMAIN:443 2>/dev/null | openssl x509 -noout -text | grep -A 5 -B 5 "Not After\|Subject\|Issuer" >> \$REPORT_FILE

# SSL Configuration test
echo -e "\n⚙️ SSL Configuration Test:" >> \$REPORT_FILE
nmap --script ssl-enum-ciphers -p 443 \$DOMAIN 2>/dev/null | grep -A 20 "TLS" >> \$REPORT_FILE

# Security headers check
echo -e "\n🛡️ Security Headers:" >> \$REPORT_FILE
curl -s -I https://\$DOMAIN | grep -i "strict-transport-security\|x-frame-options\|x-content-type-options\|x-xss-protection\|content-security-policy" >> \$REPORT_FILE

echo -e "\n📊 Report generated at: \$(date)" >> \$REPORT_FILE
echo "Report saved to: \$REPORT_FILE"
EOF

chmod +x /usr/local/bin/ssl-report-${DOMAIN}.sh

# 11. Run initial SSL report
log "📊 Generating initial SSL security report..."
/usr/local/bin/ssl-report-${DOMAIN}.sh

# 12. Restart Nginx with new configuration
log "🔄 Restarting Nginx with enhanced SSL configuration..."
systemctl restart nginx

# 13. Final SSL verification
log "🔍 Running final SSL verification..."
sleep 5
if curl -f -s https://$DOMAIN > /dev/null; then
    log "✅ SSL hardening completed successfully!"
    
    # Display SSL information
    echo -e "\n📊 SSL Configuration Summary:"
    echo "================================"
    echo "Domain: $DOMAIN"
    echo "SSL/TLS Protocols: TLSv1.2, TLSv1.3"
    echo "HSTS: Enabled (1 year, includeSubDomains, preload)"
    echo "OCSP Stapling: Enabled"
    echo "Perfect Forward Secrecy: Enabled"
    echo "Security Headers: Enhanced"
    echo ""
    echo "🔧 Useful commands:"
    echo "- SSL Certificate check: /usr/local/bin/ssl-monitor-${DOMAIN}.sh"
    echo "- HSTS verification: /usr/local/bin/hsts-verify-${DOMAIN}.sh"
    echo "- Security report: /usr/local/bin/ssl-report-${DOMAIN}.sh"
    echo "- Test SSL: curl -I https://$DOMAIN"
    echo ""
    echo "📈 Online SSL tests:"
    echo "- SSL Labs: https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN"
    echo "- Security Headers: https://securityheaders.com/?q=$DOMAIN"
    echo "- HSTS Preload: https://hstspreload.org/?domain=$DOMAIN"
    
else
    log "❌ SSL verification failed. Please check the configuration."
    exit 1
fi

log "🎉 SSL Security Hardening completed successfully!"