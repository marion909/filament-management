<?php
$pageTitle = 'Admin Panel';
$currentUser = $_SESSION['user'] ?? null;

if (!$currentUser || $currentUser['role'] !== 'admin') {
    header('Location: /login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Filament Management</title>
    <link href="/css/main.css" rel="stylesheet">
    <link href="/css/admin.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <?php include __DIR__ . '/partials/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i data-lucide="shield"></i>
                    Admin Panel
                </h1>
                <p class="page-description">
                    System administration and configuration
                </p>
            </div>

            <!-- Admin Navigation Tabs -->
            <nav class="admin-tabs">
                <button class="tab-button active" data-tab="overview">
                    <i data-lucide="bar-chart-3"></i>
                    Overview
                </button>
                <button class="tab-button" data-tab="users">
                    <i data-lucide="users"></i>
                    Users
                </button>
                <button class="tab-button" data-tab="presets">
                    <i data-lucide="settings"></i>
                    Presets
                </button>
                <button class="tab-button" data-tab="backups">
                    <i data-lucide="database"></i>
                    Backups
                </button>
                <button class="tab-button" data-tab="system">
                    <i data-lucide="monitor"></i>
                    System
                </button>
            </nav>

            <!-- Tab Content -->
            <div id="admin-content">
                <!-- Overview Tab -->
                <section id="tab-overview" class="tab-content active">
                    <div class="overview-grid">
                        <div class="overview-card">
                            <div class="card-header">
                                <h3>
                                    <i data-lucide="users"></i>
                                    Users
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="stats-row">
                                    <span class="stat-label">Total Users:</span>
                                    <span class="stat-value" id="stat-total-users">-</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Verified:</span>
                                    <span class="stat-value" id="stat-verified-users">-</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Admins:</span>
                                    <span class="stat-value" id="stat-admin-users">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <div class="card-header">
                                <h3>
                                    <i data-lucide="package"></i>
                                    Spools
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="stats-row">
                                    <span class="stat-label">Total Spools:</span>
                                    <span class="stat-value" id="stat-total-spools">-</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Active:</span>
                                    <span class="stat-value" id="stat-active-spools">-</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Material (kg):</span>
                                    <span class="stat-value" id="stat-total-material">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <div class="card-header">
                                <h3>
                                    <i data-lucide="activity"></i>
                                    Usage
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="stats-row">
                                    <span class="stat-label">Adjustments:</span>
                                    <span class="stat-value" id="stat-adjustments">-</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Used (kg):</span>
                                    <span class="stat-value" id="stat-usage">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <div class="card-header">
                                <h3>
                                    <i data-lucide="server"></i>
                                    System
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="stats-row">
                                    <span class="stat-label">PHP Version:</span>
                                    <span class="stat-value" id="stat-php-version">-</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">DB Size (MB):</span>
                                    <span class="stat-value" id="stat-db-size">-</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Uptime:</span>
                                    <span class="stat-value" id="stat-uptime">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary" onclick="loadSystemStats()">
                        <i data-lucide="refresh-ccw"></i>
                        Refresh Statistics
                    </button>
                </section>

                <!-- Users Tab -->
                <section id="tab-users" class="tab-content">
                    <div class="section-header">
                        <h2>User Management</h2>
                        <div class="section-actions">
                            <div class="search-box">
                                <input type="text" id="user-search" placeholder="Search users..." />
                                <i data-lucide="search"></i>
                            </div>
                            <select id="user-role-filter">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                            <select id="user-status-filter">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="unverified">Unverified</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="users-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-tbody">
                                <tr>
                                    <td colspan="6" class="loading">Loading users...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="users-pagination" class="pagination"></div>
                </section>

                <!-- Presets Tab -->
                <section id="tab-presets" class="tab-content">
                    <div class="presets-grid">
                        <!-- Filament Types -->
                        <div class="preset-section">
                            <div class="section-header">
                                <h3>Filament Types</h3>
                                <button class="btn btn-sm btn-primary" onclick="showCreateTypeModal()">
                                    <i data-lucide="plus"></i>
                                    Add Type
                                </button>
                            </div>
                            <div class="preset-list" id="types-list">
                                <div class="loading">Loading types...</div>
                            </div>
                        </div>

                        <!-- Colors -->
                        <div class="preset-section">
                            <div class="section-header">
                                <h3>Colors</h3>
                                <button class="btn btn-sm btn-primary" onclick="showCreateColorModal()">
                                    <i data-lucide="plus"></i>
                                    Add Color
                                </button>
                            </div>
                            <div class="preset-list" id="colors-list">
                                <div class="loading">Loading colors...</div>
                            </div>
                        </div>

                        <!-- Spool Presets -->
                        <div class="preset-section">
                            <div class="section-header">
                                <h3>Spool Presets</h3>
                                <button class="btn btn-sm btn-primary" onclick="showCreatePresetModal()">
                                    <i data-lucide="plus"></i>
                                    Add Preset
                                </button>
                            </div>
                            <div class="preset-list" id="presets-list">
                                <div class="loading">Loading presets...</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Backups Tab -->
                <section id="tab-backups" class="tab-content">
                    <div class="section-header">
                        <h2>Database Backups</h2>
                        <button class="btn btn-primary" onclick="createBackup()">
                            <i data-lucide="download"></i>
                            Create Backup
                        </button>
                    </div>

                    <div class="backup-info">
                        <div class="info-card">
                            <h4>Backup Information</h4>
                            <p>Backups include all database tables with data. They are compressed using gzip and stored securely.</p>
                            <ul>
                                <li>Automatic daily backups (if configured)</li>
                                <li>Manual backup creation available</li>
                                <li>Backup files are named with timestamp</li>
                                <li>Old backups can be deleted to save space</li>
                            </ul>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="backups-table">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backups-tbody">
                                <tr>
                                    <td colspan="4" class="loading">Loading backups...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- System Tab -->
                <section id="tab-system" class="tab-content">
                    <div class="system-grid">
                        <div class="system-card">
                            <div class="card-header">
                                <h3>
                                    <i data-lucide="hard-drive"></i>
                                    Disk Usage
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="disk-usage">
                                    <div class="usage-bar">
                                        <div class="usage-fill" id="disk-usage-fill"></div>
                                    </div>
                                    <div class="usage-stats">
                                        <span id="disk-usage-text">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="system-card">
                            <div class="card-header">
                                <h3>
                                    <i data-lucide="info"></i>
                                    System Information
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="info-list">
                                    <div class="info-row">
                                        <span class="info-label">PHP Version:</span>
                                        <span class="info-value" id="system-php-version">-</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Database Size:</span>
                                        <span class="info-value" id="system-db-size">-</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">System Uptime:</span>
                                        <span class="info-value" id="system-uptime">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="system-card">
                            <div class="card-header">
                                <h3>
                                    <i data-lucide="log-in"></i>
                                    Quick Actions
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="action-buttons">
                                    <button class="btn btn-outline" onclick="clearCache()">
                                        <i data-lucide="trash-2"></i>
                                        Clear Cache
                                    </button>
                                    <button class="btn btn-outline" onclick="exportLogs()">
                                        <i data-lucide="file-text"></i>
                                        Export Logs
                                    </button>
                                    <button class="btn btn-outline" onclick="checkHealth()">
                                        <i data-lucide="heart"></i>
                                        Health Check
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- User Edit Modal -->
    <div id="user-edit-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="modal-close" onclick="closeModal('user-edit-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="user-edit-form">
                    <input type="hidden" id="edit-user-id" />
                    
                    <div class="form-group">
                        <label for="edit-user-name">Name</label>
                        <input type="text" id="edit-user-name" required />
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-user-email">Email</label>
                        <input type="email" id="edit-user-email" required />
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-user-role">Role</label>
                        <select id="edit-user-role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('user-edit-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveUser()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Type/Color/Preset Modals -->
    <div id="create-type-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Filament Type</h3>
                <button class="modal-close" onclick="closeModal('create-type-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-type-form">
                    <div class="form-group">
                        <label for="new-type-name">Name</label>
                        <input type="text" id="new-type-name" required placeholder="e.g., PLA, ABS, PETG" />
                    </div>
                    <div class="form-group">
                        <label for="new-type-description">Description (Optional)</label>
                        <textarea id="new-type-description" placeholder="Additional information about this material"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('create-type-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="createType()">Create Type</button>
            </div>
        </div>
    </div>

    <div id="create-color-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Color</h3>
                <button class="modal-close" onclick="closeModal('create-color-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-color-form">
                    <div class="form-group">
                        <label for="new-color-name">Name</label>
                        <input type="text" id="new-color-name" required placeholder="e.g., Red, Blue, Natural" />
                    </div>
                    <div class="form-group">
                        <label for="new-color-hex">Hex Code (Optional)</label>
                        <input type="text" id="new-color-hex" placeholder="#FF0000" pattern="^#[0-9A-Fa-f]{6}$" />
                        <small>Format: #RRGGBB</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('create-color-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="createColor()">Create Color</button>
            </div>
        </div>
    </div>

    <div id="create-preset-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Spool Preset</h3>
                <button class="modal-close" onclick="closeModal('create-preset-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-preset-form">
                    <div class="form-group">
                        <label for="new-preset-name">Name</label>
                        <input type="text" id="new-preset-name" required placeholder="e.g., 1kg Standard" />
                    </div>
                    <div class="form-group">
                        <label for="new-preset-grams">Weight (grams)</label>
                        <input type="number" id="new-preset-grams" required min="1" placeholder="1000" />
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('create-preset-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="createPreset()">Create Preset</button>
            </div>
        </div>
    </div>

    <script src="/js/admin.js"></script>
    <script nonce="<?= $cspNonce ?? '' ?>">
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Initialize admin dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initAdminDashboard();
        });
    </script>
</body>
</html>