<?php
$currentUser = $_SESSION['user'] ?? null;
$isAdmin = $currentUser && $currentUser['role'] === 'admin';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Get security tokens if router is available
global $router;
$csrfToken = '';
$cspNonce = '';
if ($router && method_exists($router, 'getCsrfToken')) {
    $csrfToken = $router->getCsrfToken();
    $cspNonce = $router->getCspNonce();
}
?>

<header class="header">
    <div class="container">
        <div class="header-content">
            <!-- Logo and Brand -->
            <div class="header-brand">
                <a href="/" class="brand-link">
                    <i data-lucide="package"></i>
                    <span class="brand-text">Filament Manager</span>
                </a>
            </div>

            <!-- Navigation -->
            <nav class="header-nav">
                <?php if ($currentUser): ?>
                    <a href="/" class="nav-link <?= $currentPath === '/' ? 'active' : '' ?>">
                        <i data-lucide="home"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="/spools" class="nav-link <?= $currentPath === '/spools' ? 'active' : '' ?>">
                        <i data-lucide="package"></i>
                        <span>Spools</span>
                    </a>
                    
                    <?php if ($isAdmin): ?>
                        <a href="/admin" class="nav-link <?= $currentPath === '/admin' ? 'active' : '' ?>">
                            <i data-lucide="shield"></i>
                            <span>Admin</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <!-- User Menu -->
            <div class="header-user">
                <?php if ($currentUser): ?>
                    <div class="user-menu">
                        <button class="user-menu-trigger" onclick="toggleUserMenu()">
                            <div class="user-avatar">
                                <i data-lucide="user"></i>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($currentUser['name']) ?></div>
                                <div class="user-role"><?= ucfirst($currentUser['role']) ?></div>
                            </div>
                            <i data-lucide="chevron-down" class="dropdown-icon"></i>
                        </button>
                        
                        <div class="user-menu-dropdown" id="user-menu-dropdown">
                            <div class="user-menu-header">
                                <div class="user-name"><?= htmlspecialchars($currentUser['name']) ?></div>
                                <div class="user-email"><?= htmlspecialchars($currentUser['email']) ?></div>
                            </div>
                            
                            <div class="user-menu-divider"></div>
                            
                            <a href="/profile" class="user-menu-item">
                                <i data-lucide="user"></i>
                                <span>Profile</span>
                            </a>
                            
                            <a href="/settings" class="user-menu-item">
                                <i data-lucide="settings"></i>
                                <span>Settings</span>
                            </a>
                            
                            <?php if ($isAdmin): ?>
                                <div class="user-menu-divider"></div>
                                
                                <a href="/admin" class="user-menu-item">
                                    <i data-lucide="shield"></i>
                                    <span>Admin Panel</span>
                                </a>
                            <?php endif; ?>
                            
                            <div class="user-menu-divider"></div>
                            
                            <button onclick="logout()" class="user-menu-item user-menu-logout">
                                <i data-lucide="log-out"></i>
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-links">
                        <a href="/login" class="btn btn-outline">Login</a>
                        <a href="/register" class="btn btn-primary">Register</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav" id="mobile-nav">
        <?php if ($currentUser): ?>
            <a href="/" class="mobile-nav-link <?= $currentPath === '/' ? 'active' : '' ?>">
                <i data-lucide="home"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="/spools" class="mobile-nav-link <?= $currentPath === '/spools' ? 'active' : '' ?>">
                <i data-lucide="package"></i>
                <span>Spools</span>
            </a>
            
            <?php if ($isAdmin): ?>
                <a href="/admin" class="mobile-nav-link <?= $currentPath === '/admin' ? 'active' : '' ?>">
                    <i data-lucide="shield"></i>
                    <span>Admin</span>
                </a>
            <?php endif; ?>
            
            <div class="mobile-nav-divider"></div>
            
            <a href="/profile" class="mobile-nav-link">
                <i data-lucide="user"></i>
                <span>Profile</span>
            </a>
            
            <a href="/settings" class="mobile-nav-link">
                <i data-lucide="settings"></i>
                <span>Settings</span>
            </a>
            
            <button onclick="logout()" class="mobile-nav-link mobile-nav-logout">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </button>
        <?php else: ?>
            <a href="/login" class="mobile-nav-link">
                <i data-lucide="log-in"></i>
                <span>Login</span>
            </a>
            
            <a href="/register" class="mobile-nav-link">
                <i data-lucide="user-plus"></i>
                <span>Register</span>
            </a>
        <?php endif; ?>
    </div>
</header>

<script nonce="<?= $cspNonce ?? '' ?>">
// User menu toggle
function toggleUserMenu() {
    const dropdown = document.getElementById('user-menu-dropdown');
    const isOpen = dropdown.style.display === 'block';
    
    // Close all dropdowns first
    closeAllDropdowns();
    
    if (!isOpen) {
        dropdown.style.display = 'block';
        
        // Close when clicking outside
        setTimeout(() => {
            document.addEventListener('click', closeUserMenuOnOutsideClick);
        }, 0);
    }
}

function closeUserMenuOnOutsideClick(event) {
    const userMenu = event.target.closest('.user-menu');
    if (!userMenu) {
        closeAllDropdowns();
        document.removeEventListener('click', closeUserMenuOnOutsideClick);
    }
}

// Mobile menu toggle
function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobile-nav');
    const isOpen = mobileNav.classList.contains('open');
    
    if (isOpen) {
        mobileNav.classList.remove('open');
        document.body.classList.remove('mobile-menu-open');
    } else {
        mobileNav.classList.add('open');
        document.body.classList.add('mobile-menu-open');
    }
}

// Close all dropdowns
function closeAllDropdowns() {
    const dropdowns = document.querySelectorAll('.user-menu-dropdown');
    dropdowns.forEach(dropdown => {
        dropdown.style.display = 'none';
    });
}

// Logout function
async function logout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/auth/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            window.location.href = '/login';
        } else {
            console.error('Logout failed');
            // Force redirect anyway
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('Logout error:', error);
        // Force redirect anyway
        window.location.href = '/login';
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const mobileNav = document.getElementById('mobile-nav');
    const mobileToggle = event.target.closest('.mobile-menu-toggle');
    
    if (!mobileToggle && !event.target.closest('.mobile-nav') && mobileNav.classList.contains('open')) {
        toggleMobileMenu();
    }
});

// Close mobile menu on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const mobileNav = document.getElementById('mobile-nav');
        if (mobileNav.classList.contains('open')) {
            toggleMobileMenu();
        }
    }
});

// Initialize Lucide icons when header is loaded
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

// Set current user ID for admin.js and CSRF token
<?php if ($currentUser): ?>
window.currentUserId = <?= $currentUser['id'] ?>;
<?php endif; ?>

// Set CSRF token for AJAX requests
<?php if ($csrfToken): ?>
window.csrfToken = '<?= $csrfToken ?>';
// Set CSRF token in request headers for fetch requests
if (typeof fetch !== 'undefined') {
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = window.csrfToken;
        return originalFetch(url, options);
    };
}
<?php endif; ?>
</script>

<!-- Security Meta Tags -->
<?php if ($csrfToken): ?>
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
<?php endif; ?>

<script <?= $cspNonce ? 'nonce="' . $cspNonce . '"' : '' ?>>
// Additional security initialization can go here
console.log('Security tokens initialized');
</script>