/**
 * Admin Dashboard JavaScript
 * Handles all admin panel functionality including user management, presets, and system operations
 */

// Global admin state
let adminState = {
    currentTab: 'overview',
    users: [],
    types: [],
    colors: [],
    presets: [],
    backups: [],
    systemStats: null,
    pagination: {
        users: { page: 1, total: 0, limit: 20 }
    },
    filters: {
        users: { search: '', role: '', status: '' }
    }
};

/**
 * Initialize admin dashboard
 */
function initAdminDashboard() {
    console.log('Initializing admin dashboard...');
    
    // Set up tab navigation
    setupTabNavigation();
    
    // Load initial data
    loadSystemStats();
    
    // Set up search and filters
    setupSearchAndFilters();
    
    // Set up auto-refresh for overview stats
    setInterval(loadSystemStats, 30000); // Refresh every 30 seconds
}

/**
 * Setup tab navigation
 */
function setupTabNavigation() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            switchTab(tabName);
        });
    });
}

/**
 * Switch to a specific tab
 */
function switchTab(tabName) {
    // Update button states
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    
    // Update content visibility
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`tab-${tabName}`).classList.add('active');
    
    // Load tab-specific data
    loadTabData(tabName);
    
    adminState.currentTab = tabName;
}

/**
 * Load data for specific tab
 */
async function loadTabData(tabName) {
    try {
        switch (tabName) {
            case 'overview':
                await loadSystemStats();
                break;
            case 'users':
                await loadUsers();
                break;
            case 'presets':
                await loadTypes();
                await loadColors();
                await loadPresets();
                break;
            case 'backups':
                await loadBackups();
                break;
            case 'system':
                await loadSystemStats();
                break;
        }
    } catch (error) {
        console.error('Failed to load tab data:', error);
        showNotification('Failed to load data', 'error');
    }
}

/**
 * Load system statistics
 */
async function loadSystemStats() {
    try {
        const response = await fetch('/api/admin/stats');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        adminState.systemStats = data.stats;
        
        updateSystemStatsDisplay();
        
    } catch (error) {
        console.error('Failed to load system stats:', error);
        showNotification('Failed to load system statistics', 'error');
    }
}

/**
 * Update system statistics display
 */
function updateSystemStatsDisplay() {
    const stats = adminState.systemStats;
    if (!stats) return;
    
    // Overview cards
    document.getElementById('stat-total-users').textContent = stats.users.total_users || '0';
    document.getElementById('stat-verified-users').textContent = stats.users.verified_users || '0';
    document.getElementById('stat-admin-users').textContent = stats.users.admin_users || '0';
    
    document.getElementById('stat-total-spools').textContent = stats.spools.total_spools || '0';
    document.getElementById('stat-active-spools').textContent = stats.spools.active_spools || '0';
    document.getElementById('stat-total-material').textContent = 
        stats.spools.total_material_grams ? (stats.spools.total_material_grams / 1000).toFixed(1) : '0';
    
    document.getElementById('stat-adjustments').textContent = stats.usage.total_adjustments || '0';
    document.getElementById('stat-usage').textContent = 
        stats.usage.total_usage_grams ? (stats.usage.total_usage_grams / 1000).toFixed(1) : '0';
    
    document.getElementById('stat-php-version').textContent = stats.system.php_version || 'Unknown';
    document.getElementById('stat-db-size').textContent = stats.system.database_size || '0';
    document.getElementById('stat-uptime').textContent = stats.system.uptime || 'Unknown';
    
    // System tab details
    document.getElementById('system-php-version').textContent = stats.system.php_version || 'Unknown';
    document.getElementById('system-db-size').textContent = stats.system.database_size + ' MB' || '0 MB';
    document.getElementById('system-uptime').textContent = stats.system.uptime || 'Unknown';
    
    // Disk usage
    if (stats.system.disk_usage) {
        const usage = stats.system.disk_usage;
        const percentage = usage.percentage || 0;
        
        document.getElementById('disk-usage-fill').style.width = percentage + '%';
        document.getElementById('disk-usage-text').textContent = 
            `${percentage}% used (${formatBytes(usage.used)} / ${formatBytes(usage.total)})`;
    }
}

/**
 * Load users with current filters and pagination
 */
async function loadUsers() {
    try {
        const filters = adminState.filters.users;
        const pagination = adminState.pagination.users;
        
        const params = new URLSearchParams({
            page: pagination.page.toString(),
            limit: pagination.limit.toString()
        });
        
        if (filters.search) params.set('search', filters.search);
        if (filters.role) params.set('role', filters.role);
        if (filters.status) params.set('status', filters.status);
        
        const response = await fetch(`/api/admin/users?${params}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        adminState.users = data.users || [];
        adminState.pagination.users = data.pagination || pagination;
        
        displayUsers();
        updateUsersPagination();
        
    } catch (error) {
        console.error('Failed to load users:', error);
        showNotification('Failed to load users', 'error');
    }
}

/**
 * Display users in table
 */
function displayUsers() {
    const tbody = document.getElementById('users-tbody');
    
    if (adminState.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="loading">No users found</td></tr>';
        return;
    }
    
    tbody.innerHTML = adminState.users.map(user => `
        <tr>
            <td>${escapeHtml(user.name)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td><span class="status-badge ${user.role}">${user.role}</span></td>
            <td><span class="status-badge ${user.verified_at ? 'verified' : 'unverified'}">
                ${user.verified_at ? 'Verified' : 'Unverified'}
            </span></td>
            <td>${formatDate(user.created_at)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="editUser(${user.id})">
                        <i data-lucide="edit-2"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})" 
                            ${user.id === getCurrentUserId() ? 'disabled title="Cannot delete yourself"' : ''}>
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    // Re-initialize Lucide icons
    lucide.createIcons();
}

/**
 * Update users pagination
 */
function updateUsersPagination() {
    const pagination = adminState.pagination.users;
    const container = document.getElementById('users-pagination');
    
    if (pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<button ${pagination.page <= 1 ? 'disabled' : ''} 
                    onclick="changeUsersPage(${pagination.page - 1})">
                <i data-lucide="chevron-left"></i>
             </button>`;
    
    // Page numbers
    const startPage = Math.max(1, pagination.page - 2);
    const endPage = Math.min(pagination.pages, pagination.page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="${i === pagination.page ? 'active' : ''}" 
                        onclick="changeUsersPage(${i})">
                    ${i}
                 </button>`;
    }
    
    // Next button
    html += `<button ${pagination.page >= pagination.pages ? 'disabled' : ''} 
                    onclick="changeUsersPage(${pagination.page + 1})">
                <i data-lucide="chevron-right"></i>
             </button>`;
    
    container.innerHTML = html;
    lucide.createIcons();
}

/**
 * Change users page
 */
function changeUsersPage(page) {
    adminState.pagination.users.page = page;
    loadUsers();
}

/**
 * Setup search and filter handlers
 */
function setupSearchAndFilters() {
    // User search
    const userSearch = document.getElementById('user-search');
    const userRoleFilter = document.getElementById('user-role-filter');
    const userStatusFilter = document.getElementById('user-status-filter');
    
    if (userSearch) {
        let searchTimeout;
        userSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                adminState.filters.users.search = this.value;
                adminState.pagination.users.page = 1;
                loadUsers();
            }, 300);
        });
    }
    
    if (userRoleFilter) {
        userRoleFilter.addEventListener('change', function() {
            adminState.filters.users.role = this.value;
            adminState.pagination.users.page = 1;
            loadUsers();
        });
    }
    
    if (userStatusFilter) {
        userStatusFilter.addEventListener('change', function() {
            adminState.filters.users.status = this.value;
            adminState.pagination.users.page = 1;
            loadUsers();
        });
    }
}

/**
 * Edit user
 */
async function editUser(userId) {
    try {
        const response = await fetch(`/api/admin/users/${userId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        const user = data.user;
        
        // Populate edit form
        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-user-name').value = user.name;
        document.getElementById('edit-user-email').value = user.email;
        document.getElementById('edit-user-role').value = user.role;
        
        // Show modal
        showModal('user-edit-modal');
        
    } catch (error) {
        console.error('Failed to load user details:', error);
        showNotification('Failed to load user details', 'error');
    }
}

/**
 * Save user changes
 */
async function saveUser() {
    try {
        const userId = document.getElementById('edit-user-id').value;
        const formData = {
            name: document.getElementById('edit-user-name').value.trim(),
            email: document.getElementById('edit-user-email').value.trim(),
            role: document.getElementById('edit-user-role').value
        };
        
        // Validate form
        if (!formData.name || !formData.email) {
            showNotification('Name and email are required', 'error');
            return;
        }
        
        const response = await fetch(`/api/admin/users/${userId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        
        closeModal('user-edit-modal');
        loadUsers();
        showNotification('User updated successfully', 'success');
        
    } catch (error) {
        console.error('Failed to save user:', error);
        showNotification(error.message || 'Failed to save user', 'error');
    }
}

/**
 * Delete user
 */
async function deleteUser(userId) {
    const user = adminState.users.find(u => u.id === userId);
    
    if (!user) {
        showNotification('User not found', 'error');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete user "${user.name}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/users/${userId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        
        loadUsers();
        showNotification('User deleted successfully', 'success');
        
    } catch (error) {
        console.error('Failed to delete user:', error);
        showNotification(error.message || 'Failed to delete user', 'error');
    }
}

/**
 * Load filament types
 */
async function loadTypes() {
    try {
        const response = await fetch('/api/admin/types');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        adminState.types = data.types || [];
        
        displayTypes();
        
    } catch (error) {
        console.error('Failed to load types:', error);
        showNotification('Failed to load filament types', 'error');
    }
}

/**
 * Display filament types
 */
function displayTypes() {
    const container = document.getElementById('types-list');
    
    if (adminState.types.length === 0) {
        container.innerHTML = '<div class="loading">No types found</div>';
        return;
    }
    
    container.innerHTML = adminState.types.map(type => `
        <div class="preset-item">
            <div class="preset-info">
                <div class="preset-name">${escapeHtml(type.name)}</div>
                <div class="preset-details">${escapeHtml(type.description || 'No description')}</div>
            </div>
            <div class="preset-actions">
                <button class="btn btn-icon btn-sm btn-outline" onclick="editType(${type.id})" title="Edit">
                    <i data-lucide="edit-2"></i>
                </button>
                <button class="btn btn-icon btn-sm btn-danger" onclick="deleteType(${type.id})" title="Delete">
                    <i data-lucide="trash-2"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    lucide.createIcons();
}

/**
 * Load colors
 */
async function loadColors() {
    try {
        const response = await fetch('/api/admin/colors');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        adminState.colors = data.colors || [];
        
        displayColors();
        
    } catch (error) {
        console.error('Failed to load colors:', error);
        showNotification('Failed to load colors', 'error');
    }
}

/**
 * Display colors
 */
function displayColors() {
    const container = document.getElementById('colors-list');
    
    if (adminState.colors.length === 0) {
        container.innerHTML = '<div class="loading">No colors found</div>';
        return;
    }
    
    container.innerHTML = adminState.colors.map(color => `
        <div class="preset-item">
            <div class="preset-info">
                <div class="preset-name">
                    ${color.hex_code ? `<span class="color-swatch" style="background-color: ${color.hex_code}"></span>` : ''}
                    ${escapeHtml(color.name)}
                </div>
                <div class="preset-details">${color.hex_code || 'No color code'}</div>
            </div>
            <div class="preset-actions">
                <button class="btn btn-icon btn-sm btn-outline" onclick="editColor(${color.id})" title="Edit">
                    <i data-lucide="edit-2"></i>
                </button>
                <button class="btn btn-icon btn-sm btn-danger" onclick="deleteColor(${color.id})" title="Delete">
                    <i data-lucide="trash-2"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    lucide.createIcons();
}

/**
 * Load spool presets
 */
async function loadPresets() {
    try {
        const response = await fetch('/api/admin/presets');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        adminState.presets = data.presets || [];
        
        displayPresets();
        
    } catch (error) {
        console.error('Failed to load presets:', error);
        showNotification('Failed to load spool presets', 'error');
    }
}

/**
 * Display spool presets
 */
function displayPresets() {
    const container = document.getElementById('presets-list');
    
    if (adminState.presets.length === 0) {
        container.innerHTML = '<div class="loading">No presets found</div>';
        return;
    }
    
    container.innerHTML = adminState.presets.map(preset => `
        <div class="preset-item">
            <div class="preset-info">
                <div class="preset-name">${escapeHtml(preset.name)}</div>
                <div class="preset-details">${preset.grams}g</div>
            </div>
            <div class="preset-actions">
                <button class="btn btn-icon btn-sm btn-outline" onclick="editPreset(${preset.id})" title="Edit">
                    <i data-lucide="edit-2"></i>
                </button>
                <button class="btn btn-icon btn-sm btn-danger" onclick="deletePreset(${preset.id})" title="Delete">
                    <i data-lucide="trash-2"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    lucide.createIcons();
}

/**
 * Load backups
 */
async function loadBackups() {
    try {
        const response = await fetch('/api/admin/backups');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        adminState.backups = data.backups || [];
        
        displayBackups();
        
    } catch (error) {
        console.error('Failed to load backups:', error);
        showNotification('Failed to load backups', 'error');
    }
}

/**
 * Display backups
 */
function displayBackups() {
    const tbody = document.getElementById('backups-tbody');
    
    if (adminState.backups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="loading">No backups found</td></tr>';
        return;
    }
    
    tbody.innerHTML = adminState.backups.map(backup => `
        <tr>
            <td>${escapeHtml(backup.filename)}</td>
            <td>${backup.size_formatted}</td>
            <td>${formatDate(backup.created)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="downloadBackup('${backup.filename}')" title="Download">
                        <i data-lucide="download"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteBackup('${backup.filename}')" title="Delete">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    lucide.createIcons();
}

/**
 * Create backup
 */
async function createBackup() {
    if (!confirm('Create a new database backup?\n\nThis may take a few moments.')) {
        return;
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="loading-spinner"></span> Creating...';
    button.disabled = true;
    
    try {
        const response = await fetch('/api/admin/backup', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        
        showNotification(`Backup created: ${data.filename}`, 'success');
        loadBackups();
        
    } catch (error) {
        console.error('Failed to create backup:', error);
        showNotification(error.message || 'Failed to create backup', 'error');
    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

/**
 * Download backup
 */
function downloadBackup(filename) {
    window.location.href = `/api/admin/backups/${encodeURIComponent(filename)}`;
}

/**
 * Delete backup
 */
async function deleteBackup(filename) {
    if (!confirm(`Delete backup "${filename}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/backups/${encodeURIComponent(filename)}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        
        showNotification('Backup deleted successfully', 'success');
        loadBackups();
        
    } catch (error) {
        console.error('Failed to delete backup:', error);
        showNotification(error.message || 'Failed to delete backup', 'error');
    }
}

/**
 * Show modal for creating type
 */
function showCreateTypeModal() {
    document.getElementById('new-type-name').value = '';
    document.getElementById('new-type-description').value = '';
    showModal('create-type-modal');
}

/**
 * Create filament type
 */
async function createType() {
    try {
        const formData = {
            name: document.getElementById('new-type-name').value.trim(),
            description: document.getElementById('new-type-description').value.trim() || null
        };
        
        if (!formData.name) {
            showNotification('Name is required', 'error');
            return;
        }
        
        const response = await fetch('/api/admin/types', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        
        closeModal('create-type-modal');
        loadTypes();
        showNotification('Type created successfully', 'success');
        
    } catch (error) {
        console.error('Failed to create type:', error);
        showNotification(error.message || 'Failed to create type', 'error');
    }
}

/**
 * Show modal for creating color
 */
function showCreateColorModal() {
    document.getElementById('new-color-name').value = '';
    document.getElementById('new-color-hex').value = '';
    showModal('create-color-modal');
}

/**
 * Create color
 */
async function createColor() {
    try {
        const formData = {
            name: document.getElementById('new-color-name').value.trim(),
            hex_code: document.getElementById('new-color-hex').value.trim() || null
        };
        
        if (!formData.name) {
            showNotification('Name is required', 'error');
            return;
        }
        
        const response = await fetch('/api/admin/colors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        
        closeModal('create-color-modal');
        loadColors();
        showNotification('Color created successfully', 'success');
        
    } catch (error) {
        console.error('Failed to create color:', error);
        showNotification(error.message || 'Failed to create color', 'error');
    }
}

/**
 * Show modal for creating preset
 */
function showCreatePresetModal() {
    document.getElementById('new-preset-name').value = '';
    document.getElementById('new-preset-grams').value = '';
    showModal('create-preset-modal');
}

/**
 * Create spool preset
 */
async function createPreset() {
    try {
        const formData = {
            name: document.getElementById('new-preset-name').value.trim(),
            grams: parseInt(document.getElementById('new-preset-grams').value) || 0
        };
        
        if (!formData.name || formData.grams <= 0) {
            showNotification('Name and valid weight are required', 'error');
            return;
        }
        
        const response = await fetch('/api/admin/presets', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        
        closeModal('create-preset-modal');
        loadPresets();
        showNotification('Preset created successfully', 'success');
        
    } catch (error) {
        console.error('Failed to create preset:', error);
        showNotification(error.message || 'Failed to create preset', 'error');
    }
}

/**
 * System quick actions
 */
async function clearCache() {
    showNotification('Cache clearing not implemented', 'info');
}

async function exportLogs() {
    showNotification('Log export not implemented', 'info');
}

async function checkHealth() {
    try {
        const response = await fetch('/api/status');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        showNotification(`System health: ${data.status}`, 'success');
        
    } catch (error) {
        console.error('Health check failed:', error);
        showNotification('Health check failed', 'error');
    }
}

/**
 * Utility functions
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatDate(dateString) {
    if (!dateString) return 'Never';
    
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getCurrentUserId() {
    // This should be set from PHP session data
    return window.currentUserId || null;
}

function showNotification(message, type = 'info') {
    // Use existing notification system from main.js
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        // Fallback alert
        alert(message);
    }
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Additional placeholder functions for edit operations
function editType(typeId) {
    showNotification('Type editing not implemented yet', 'info');
}

function deleteType(typeId) {
    showNotification('Type deletion not implemented yet', 'info');
}

function editColor(colorId) {
    showNotification('Color editing not implemented yet', 'info');
}

function deleteColor(colorId) {
    showNotification('Color deletion not implemented yet', 'info');
}

function editPreset(presetId) {
    showNotification('Preset editing not implemented yet', 'info');
}

function deletePreset(presetId) {
    showNotification('Preset deletion not implemented yet', 'info');
}