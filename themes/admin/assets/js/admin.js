/**
 * Isolated Admin Theme JavaScript
 * All functions and variables are scoped to prevent conflicts
 */

(function() {
    'use strict';

    // Admin namespace
    window.AdminTheme = window.AdminTheme || {};

    // Initialize admin functionality
    AdminTheme.init = function() {
        initializeNavigation();
        initializeTables();
        initializeForms();
        initializeDropdowns();
        initializeSearch();
        initializeTooltips();
    };

    // Navigation functionality
    function initializeNavigation() {
        // Mobile menu toggle
        const menuToggle = document.querySelector('.admin-menu-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('admin-sidebar-open');
                menuToggle.classList.toggle('admin-menu-open');
            });
        }

        // Active navigation highlighting
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.admin-nav-link');
        
        navLinks.forEach(function(link) {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('admin-nav-active');
            }
        });
    }

    // Table functionality
    function initializeTables() {
        // Select all checkboxes
        const selectAllCheckbox = document.querySelector('.admin-checkbox-all');
        const itemCheckboxes = document.querySelectorAll('.admin-checkbox-item');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                itemCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateBulkActions();
            });
        }

        // Individual checkbox changes
        itemCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', updateBulkActions);
        });

        // Table row selection
        const tableRows = document.querySelectorAll('.admin-table-row');
        tableRows.forEach(function(row) {
            row.addEventListener('click', function(e) {
                // Don't select row if clicking on checkbox or action buttons
                if (e.target.type !== 'checkbox' && !e.target.closest('.admin-actions-dropdown')) {
                    const checkbox = row.querySelector('.admin-checkbox-item');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        updateBulkActions();
                    }
                }
            });
        });
    }

    // Form functionality
    function initializeForms() {
        // Form validation
        const forms = document.querySelectorAll('.admin-form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!validateForm(form)) {
                    e.preventDefault();
                }
            });
        });

        // Auto-save functionality for settings
        const autoSaveForms = document.querySelectorAll('[data-auto-save]');
        autoSaveForms.forEach(function(form) {
            let saveTimeout;
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(function() {
                        saveForm(form);
                    }, 1000);
                });
            });
        });
    }

    // Dropdown functionality
    function initializeDropdowns() {
        const dropdowns = document.querySelectorAll('.admin-actions-dropdown');
        
        dropdowns.forEach(function(dropdown) {
            const toggle = dropdown.querySelector('.admin-actions-toggle');
            const menu = dropdown.querySelector('.admin-actions-menu');
            
            if (toggle && menu) {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    document.querySelectorAll('.admin-actions-menu').forEach(function(otherMenu) {
                        if (otherMenu !== menu) {
                            otherMenu.classList.remove('admin-actions-menu-open');
                        }
                    });
                    
                    menu.classList.toggle('admin-actions-menu-open');
                });
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.admin-actions-menu').forEach(function(menu) {
                menu.classList.remove('admin-actions-menu-open');
            });
        });
    }

    // Search functionality
    function initializeSearch() {
        const searchInputs = document.querySelectorAll('.admin-search-input');
        
        searchInputs.forEach(function(input) {
            let searchTimeout;
            
            input.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    performSearch(input.value);
                }, 300);
            });
        });
    }

    // Tooltip functionality
    function initializeTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(function(element) {
            element.addEventListener('mouseenter', function() {
                showTooltip(element);
            });
            
            element.addEventListener('mouseleave', function() {
                hideTooltip();
            });
        });
    }

    // Form validation
    function validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(function(field) {
            if (!field.value.trim()) {
                showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                clearFieldError(field);
            }
        });

        return isValid;
    }

    // Show field error
    function showFieldError(field, message) {
        clearFieldError(field);
        
        field.classList.add('admin-form-input-error');
        
        const errorElement = document.createElement('div');
        errorElement.className = 'admin-form-error';
        errorElement.textContent = message;
        
        field.parentNode.appendChild(errorElement);
    }

    // Clear field error
    function clearFieldError(field) {
        field.classList.remove('admin-form-input-error');
        
        const errorElement = field.parentNode.querySelector('.admin-form-error');
        if (errorElement) {
            errorElement.remove();
        }
    }

    // Update bulk actions
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.admin-checkbox-item:checked');
        const bulkActions = document.querySelector('.admin-bulk-actions');
        
        if (bulkActions) {
            if (checkedBoxes.length > 0) {
                bulkActions.style.display = 'block';
                bulkActions.querySelector('.admin-selected-count').textContent = checkedBoxes.length;
            } else {
                bulkActions.style.display = 'none';
            }
        }
    }

    // Save form (AJAX)
    function saveForm(form) {
        const formData = new FormData(form);
        const url = form.getAttribute('data-save-url') || form.getAttribute('action');
        
        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (response.ok) {
                showNotification('Settings saved successfully', 'success');
            } else {
                throw new Error('Save failed');
            }
        })
        .catch(function(error) {
            showNotification('Error saving settings', 'error');
        });
    }

    // Perform search
    function performSearch(query) {
        const searchResults = document.querySelector('.admin-search-results');
        
        if (searchResults) {
            if (query.length > 2) {
                // Show loading state
                searchResults.innerHTML = '<div class="admin-search-loading">Searching...</div>';
                searchResults.style.display = 'block';
                
                // Perform AJAX search
                fetch('/admin/search?q=' + encodeURIComponent(query), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    displaySearchResults(data);
                })
                .catch(function(error) {
                    searchResults.innerHTML = '<div class="admin-search-error">Search failed</div>';
                });
            } else {
                searchResults.style.display = 'none';
            }
        }
    }

    // Display search results
    function displaySearchResults(results) {
        const searchResults = document.querySelector('.admin-search-results');
        
        if (results.length > 0) {
            let html = '';
            results.forEach(function(result) {
                html += '<div class="admin-search-result">';
                html += '<a href="' + result.url + '">';
                html += '<div class="admin-search-result-title">' + result.title + '</div>';
                html += '<div class="admin-search-result-type">' + result.type + '</div>';
                html += '</a>';
                html += '</div>';
            });
            searchResults.innerHTML = html;
        } else {
            searchResults.innerHTML = '<div class="admin-search-empty">No results found</div>';
        }
    }

    // Show tooltip
    function showTooltip(element) {
        const text = element.getAttribute('data-tooltip');
        if (!text) return;
        
        const tooltip = document.createElement('div');
        tooltip.className = 'admin-tooltip';
        tooltip.textContent = text;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        setTimeout(function() {
            tooltip.classList.add('admin-tooltip-visible');
        }, 10);
    }

    // Hide tooltip
    function hideTooltip() {
        const tooltip = document.querySelector('.admin-tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    // Show notification
    AdminTheme.showNotification = function(message, type) {
        type = type || 'info';
        
        const notification = document.createElement('div');
        notification.className = 'admin-notification admin-notification-' + type;
        notification.textContent = message;
        
        const container = document.querySelector('.admin-content') || document.body;
        container.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            notification.classList.add('admin-notification-hiding');
            setTimeout(function() {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
    };

    // Confirm action
    AdminTheme.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    // Plugin action functionality
    AdminTheme.pluginAction = function(action, pluginName) {
        console.log('Plugin action called:', action, pluginName);
        
        AdminTheme.showNotification('Processing ' + action + ' for ' + pluginName + '...', 'info');
        
        fetch('/admin/plugins/' + action + '/' + pluginName, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(function(data) {
            console.log('Response data:', data);
            if (data.success) {
                AdminTheme.showNotification(data.message, 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                AdminTheme.showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            AdminTheme.showNotification('Error performing action: ' + error.message, 'error');
        });
    };

    // Install plugin functionality
    AdminTheme.installPlugin = function(pluginId) {
        AdminTheme.confirmAction('Install ' + pluginId + '? This will install the plugin.', function() {
            fetch('/admin/plugins/install', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    plugin_name: pluginId
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    AdminTheme.showNotification(data.message, 'success');
                    setTimeout(function() {
                        window.location.href = '/admin/plugins';
                    }, 1500);
                } else {
                    AdminTheme.showNotification(data.message, 'error');
                }
            })
            .catch(function(error) {
                AdminTheme.showNotification('Error installing plugin', 'error');
            });
        });
    };

    // Scan for new plugins
    AdminTheme.scanForPlugins = function() {
        AdminTheme.showNotification('Scanning for plugins...', 'info');
        
        fetch('/admin/plugins/scan', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                AdminTheme.showNotification('Scan completed. Found ' + data.found + ' new plugins.', 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                AdminTheme.showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            AdminTheme.showNotification('Error scanning for plugins', 'error');
        });
    };

    // Refresh plugin list
    AdminTheme.refreshPluginList = function() {
        AdminTheme.showNotification('Refreshing plugin list...', 'info');
        setTimeout(function() {
            window.location.reload();
        }, 500);
    };

    // Reset plugin configuration
    AdminTheme.resetPluginConfig = function(pluginName) {
        AdminTheme.confirmAction('Reset ' + pluginName + ' configuration to default values?', function() {
            fetch('/admin/plugins/config/' + pluginName + '/reset', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    AdminTheme.showNotification(data.message, 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    AdminTheme.showNotification(data.message, 'error');
                }
            })
            .catch(function(error) {
                AdminTheme.showNotification('Error resetting configuration', 'error');
            });
        });
    };

    // Bulk actions
    AdminTheme.bulkAction = function(action) {
        const checkedBoxes = document.querySelectorAll('.admin-checkbox-item:checked');
        const ids = Array.from(checkedBoxes).map(function(box) {
            return box.value;
        });
        
        if (ids.length === 0) {
            AdminTheme.showNotification('No items selected', 'warning');
            return;
        }
        
        AdminTheme.confirmAction(
            'Are you sure you want to ' + action + ' ' + ids.length + ' item(s)?',
            function() {
                // Perform bulk action
                fetch('/admin/bulk-action', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: action,
                        ids: ids
                    })
                })
                .then(function(response) {
                    if (response.ok) {
                        AdminTheme.showNotification('Action completed successfully', 'success');
                        // Reload page or update UI
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        throw new Error('Action failed');
                    }
                })
                .catch(function(error) {
                    AdminTheme.showNotification('Error performing action', 'error');
                });
            }
        );
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', AdminTheme.init);
    } else {
        AdminTheme.init();
    }

})();
