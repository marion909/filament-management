/**
 * Main JavaScript for Filament Management System
 * Contains shared functionality and utilities
 */

// Main application object
const FilamentApp = {
    // Configuration
    config: {
        apiBaseUrl: '/api',
        notificationDuration: 5000,
        modalTransitionDuration: 300
    },

    // State management
    state: {
        currentUser: null,
        isLoggedIn: false,
        currentPage: window.location.pathname
    },

    // Initialize the application
    init() {
        console.log('FilamentApp initializing...');
        
        // Initialize components
        this.initializeHeader();
        this.initializeModals();
        this.initializeForms();
        this.initializeNotifications();
        
        // Check authentication status
        this.checkAuthStatus();
        
        console.log('FilamentApp initialized successfully');
    },

    // Header functionality
    initializeHeader() {
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const mobileNav = document.querySelector('.mobile-nav');
        
        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = mobileNav.classList.contains('open');
                
                if (isOpen) {
                    this.closeMobileMenu();
                } else {
                    this.openMobileMenu();
                }
            });
        }

        // User menu dropdown
        const userMenuTrigger = document.querySelector('.user-menu-trigger');
        const userMenuDropdown = document.querySelector('.user-menu-dropdown');
        
        if (userMenuTrigger && userMenuDropdown) {
            userMenuTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = userMenuDropdown.style.display === 'block';
                
                if (isOpen) {
                    this.closeUserMenu();
                } else {
                    this.openUserMenu();
                }
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            this.closeMobileMenu();
            this.closeUserMenu();
        });

        // Close mobile menu on window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                this.closeMobileMenu();
            }
        });
    },

    // Open mobile menu
    openMobileMenu() {
        const mobileNav = document.querySelector('.mobile-nav');
        if (mobileNav) {
            mobileNav.classList.add('open');
            document.body.classList.add('mobile-menu-open');
        }
    },

    // Close mobile menu
    closeMobileMenu() {
        const mobileNav = document.querySelector('.mobile-nav');
        if (mobileNav) {
            mobileNav.classList.remove('open');
            document.body.classList.remove('mobile-menu-open');
        }
    },

    // Open user menu
    openUserMenu() {
        const userMenuTrigger = document.querySelector('.user-menu-trigger');
        const userMenuDropdown = document.querySelector('.user-menu-dropdown');
        
        if (userMenuTrigger && userMenuDropdown) {
            userMenuTrigger.setAttribute('aria-expanded', 'true');
            userMenuDropdown.style.display = 'block';
        }
    },

    // Close user menu
    closeUserMenu() {
        const userMenuTrigger = document.querySelector('.user-menu-trigger');
        const userMenuDropdown = document.querySelector('.user-menu-dropdown');
        
        if (userMenuTrigger && userMenuDropdown) {
            userMenuTrigger.setAttribute('aria-expanded', 'false');
            userMenuDropdown.style.display = 'none';
        }
    },

    // Modal functionality
    initializeModals() {
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal(e.target);
            }
        });

        // Close modal with close button
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-close') || e.target.closest('.modal-close')) {
                const modal = e.target.closest('.modal');
                if (modal) {
                    this.closeModal(modal);
                }
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="display: flex"]');
                if (openModal) {
                    this.closeModal(openModal);
                }
            }
        });
    },

    // Open modal
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Focus first input if available
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    },

    // Close modal
    closeModal(modal) {
        if (typeof modal === 'string') {
            modal = document.getElementById(modal);
        }
        
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            
            // Reset form if exists
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
        }
    },

    // Form handling
    initializeForms() {
        // Handle form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ajax-form')) {
                e.preventDefault();
                this.handleFormSubmission(e.target);
            }
        });
    },

    // Handle AJAX form submission
    async handleFormSubmission(form) {
        const formData = new FormData(form);
        const url = form.action || window.location.pathname;
        const method = form.method || 'POST';
        
        // Add CSRF token if not already present
        if (!formData.has('csrf_token') && window.csrfToken) {
            formData.append('csrf_token', window.csrfToken);
        }
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton ? submitButton.textContent : '';
        
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Loading...';
        }

        try {
            const headers = {
                'X-Requested-With': 'XMLHttpRequest'
            };
            
            // Add CSRF token to headers
            if (window.csrfToken) {
                headers['X-CSRF-Token'] = window.csrfToken;
            }
            
            const response = await fetch(url, {
                method: method,
                body: formData,
                headers: headers
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message || 'Operation successful', 'success');
                
                // Handle redirect
                if (result.redirect) {
                    window.location.href = result.redirect;
                    return;
                }
                
                // Close modal if form is in modal
                const modal = form.closest('.modal');
                if (modal) {
                    this.closeModal(modal);
                }
                
                // Refresh page or update UI
                if (result.reload) {
                    window.location.reload();
                }
            } else {
                this.showNotification(result.message || 'An error occurred', 'error');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showNotification('An unexpected error occurred', 'error');
        } finally {
            // Restore button state
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }
    },

    // Notification system
    initializeNotifications() {
        // Auto-dismiss notifications
        document.addEventListener('click', (e) => {
            if (e.target.closest('.notification')) {
                const notification = e.target.closest('.notification');
                this.dismissNotification(notification);
            }
        });
    },

    // Show notification
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-message">${message}</div>
                <button class="notification-close" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        // Initialize Feather icons if available
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Auto-dismiss after duration
        setTimeout(() => {
            this.dismissNotification(notification);
        }, this.config.notificationDuration);
    },

    // Dismiss notification
    dismissNotification(notification) {
        if (notification && notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    },

    // API helper methods
    async apiRequest(endpoint, options = {}) {
        const url = this.config.apiBaseUrl + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        // Add CSRF token to headers
        if (window.csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = window.csrfToken;
        }

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        // Handle rate limiting
        if (response.status === 429) {
            const retryAfter = response.headers.get('Retry-After');
            const message = retryAfter ? `Rate limited. Try again in ${retryAfter} seconds.` : 'Rate limited. Please try again later.';
            this.showNotification(message, 'warning');
            throw new Error('Rate limited');
        }
        
        // Handle CSRF errors
        if (response.status === 403) {
            const result = await response.json().catch(() => ({}));
            if (result.type === 'csrf') {
                this.showNotification('Security token expired. Please refresh the page.', 'error');
                setTimeout(() => window.location.reload(), 2000);
                throw new Error('CSRF token invalid');
            }
        }
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    },

    // GET request
    async get(endpoint) {
        return this.apiRequest(endpoint, { method: 'GET' });
    },

    // POST request
    async post(endpoint, data) {
        return this.apiRequest(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    // PUT request
    async put(endpoint, data) {
        return this.apiRequest(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    // DELETE request
    async delete(endpoint) {
        return this.apiRequest(endpoint, { method: 'DELETE' });
    },

    // Check authentication status
    async checkAuthStatus() {
        try {
            const response = await this.get('/auth/status');
            this.state.isLoggedIn = response.authenticated;
            this.state.currentUser = response.user;
        } catch (error) {
            console.error('Auth status check failed:', error);
            this.state.isLoggedIn = false;
            this.state.currentUser = null;
        }
    },

    // Format date
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    // Format file size
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Confirm action
    confirm(message, callback) {
        if (window.confirm(message)) {
            callback();
        }
    },

    // Logout function
    async logout() {
        try {
            await this.post('/auth/logout', {});
            this.showNotification('Successfully logged out', 'success');
            setTimeout(() => {
                window.location.href = '/login';
            }, 1000);
        } catch (error) {
            console.error('Logout failed:', error);
            this.showNotification('Logout failed', 'error');
        }
    }
};

// Utility functions
const Utils = {
    // Generate random ID
    generateId() {
        return Math.random().toString(36).substr(2, 9);
    },

    // Sanitize HTML
    sanitizeHtml(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    },

    // Copy to clipboard
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            FilamentApp.showNotification('Copied to clipboard', 'success');
        } catch (error) {
            console.error('Copy failed:', error);
            FilamentApp.showNotification('Copy failed', 'error');
        }
    },

    // Validate email
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    // Validate password strength
    validatePassword(password) {
        const minLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

        return {
            valid: minLength && hasUpper && hasLower && hasNumber,
            checks: {
                minLength,
                hasUpper,
                hasLower,
                hasNumber,
                hasSpecial
            }
        };
    }
};

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    FilamentApp.init();
});

// Handle page visibility changes
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        // Refresh auth status when page becomes visible
        FilamentApp.checkAuthStatus();
    }
});

// Add slideOut animation for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .notification-content {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .notification-message {
        flex: 1;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .notification-close {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        background: transparent;
        border: none;
        border-radius: 0.25rem;
        color: currentColor;
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.2s;
    }

    .notification-close:hover {
        opacity: 1;
    }

    .notification-close i {
        width: 14px;
        height: 14px;
    }
`;
document.head.appendChild(style);

// Export for global access
window.FilamentApp = FilamentApp;
window.Utils = Utils;