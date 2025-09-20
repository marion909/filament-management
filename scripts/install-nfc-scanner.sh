#!/bin/bash

# NFC Scanner Installation Script for Filament Management System
# Run as root: sudo ./install-nfc-scanner.sh

set -e

echo "🚀 Installing NFC Scanner for Filament Management System"
echo "========================================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Please run as root (sudo ./install-nfc-scanner.sh)"
    exit 1
fi

# Update system
echo "📦 Updating system packages..."
apt update && apt upgrade -y

# Install Python and pip
echo "🐍 Installing Python dependencies..."
apt install -y python3 python3-pip python3-venv

# Install system dependencies for NFC
echo "🏷️  Installing NFC system dependencies..."
apt install -y libnfc-dev libusb-dev

# Create user for NFC scanner service
echo "👤 Creating NFC scanner user..."
if ! id "nfc-scanner" &>/dev/null; then
    useradd -r -s /bin/false nfc-scanner
    usermod -a -G plugdev nfc-scanner  # Add to plugdev group for USB access
fi

# Create directories
echo "📁 Creating directories..."
mkdir -p /opt/filament-management/scripts
mkdir -p /var/log/nfc-scanner

# Copy scanner script
echo "📋 Copying scanner files..."
cp nfc-scanner.py /opt/filament-management/scripts/
chmod +x /opt/filament-management/scripts/nfc-scanner.py

# Install Python dependencies
echo "🔧 Installing Python NFC library..."
pip3 install nfcpy requests

# Copy systemd service
echo "⚙️  Installing systemd service..."
cp nfc-scanner.service /etc/systemd/system/
systemctl daemon-reload

# Set permissions
echo "🔒 Setting permissions..."
chown -R nfc-scanner:nfc-scanner /opt/filament-management/scripts
chown -R nfc-scanner:nfc-scanner /var/log/nfc-scanner
chmod 644 /etc/systemd/system/nfc-scanner.service

# Configure NFC device access
echo "🔌 Configuring NFC device access..."
cat > /etc/udev/rules.d/99-nfc.rules << EOF
# NFC device permissions
SUBSYSTEM=="usb", ATTRS{idVendor}=="054c", ATTRS{idProduct}=="06c1", GROUP="plugdev", MODE="0664"
SUBSYSTEM=="usb", ATTRS{idVendor}=="04e6", GROUP="plugdev", MODE="0664"
SUBSYSTEM=="usb", ATTRS{idVendor}=="072f", GROUP="plugdev", MODE="0664"
EOF

udevadm control --reload-rules
udevadm trigger

# Create log rotation config
echo "📝 Configuring log rotation..."
cat > /etc/logrotate.d/nfc-scanner << EOF
/var/log/nfc-scanner/nfc-scanner.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 644 nfc-scanner nfc-scanner
}
EOF

echo "✅ Installation completed successfully!"
echo ""
echo "📝 Next steps:"
echo "1. Update API_URL in /opt/filament-management/scripts/nfc-scanner.py"
echo "2. Test the scanner: sudo -u nfc-scanner /opt/filament-management/scripts/nfc-scanner.py"
echo "3. Enable and start service: sudo systemctl enable nfc-scanner && sudo systemctl start nfc-scanner"
echo "4. Check status: sudo systemctl status nfc-scanner"
echo "5. View logs: sudo journalctl -u nfc-scanner -f"
echo ""
echo "🔧 Troubleshooting:"
echo "- Check NFC device connection: lsusb | grep -i nfc"
echo "- Test NFC library: python3 -c 'import nfc; print(nfc.ContactlessFrontend())'"
echo "- Check permissions: groups nfc-scanner"