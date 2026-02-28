/**
 * Arctic Wolves Main Application JavaScript
 * Complete functionality for all interactive elements
 * Version: 1.0.0
 * 
 * Features:
 * - Search functionality for all tables
 * - Filter functionality (multi-column, date ranges)
 * - Button click handlers
 * - Form submissions
 * - Modern date picker with calendar
 * - File upload with drag-drop
 * - AJAX operations
 * - Export functionality
 * - Real-time validation
 * - Loading indicators
 * - Toast notifications
 */

(function() {
    'use strict';

    // ===================================================================
    // UTILITY FUNCTIONS
    // ===================================================================

    /**
     * Persist a toast message in sessionStorage so it survives a page reload
     */
    function persistToast(message, type = 'info') {
        try {
            sessionStorage.setItem('arctic_toast', JSON.stringify({ message, type }));
        } catch (e) {
            // sessionStorage unavailable, show immediately as fallback
            showToast(message, type);
        }
    }

    /**
     * Show any toast message that was persisted before a page reload
     */
    function showPersistedToast() {
        try {
            const raw = sessionStorage.getItem('arctic_toast');
            if (raw) {
                sessionStorage.removeItem('arctic_toast');
                const { message, type } = JSON.parse(raw);
                showToast(message, type);
            }
        } catch (e) {
            // ignore parse errors
        }
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#6B46C1'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            font-family: Inter, sans-serif;
            font-size: 14px;
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Show an in-app confirmation modal instead of browser confirm().
     * Returns a Promise that resolves to true (confirmed) or false (cancelled).
     */
    function showConfirmModal(message, confirmText, cancelText) {
        confirmText = confirmText || 'Confirm';
        cancelText = cancelText || 'Cancel';
        return new Promise(function(resolve) {
            // Create overlay
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:10001;';

            var card = document.createElement('div');
            card.style.cssText = 'background:var(--bg-card,#16161F);border:1px solid var(--border,#2D2D3F);border-radius:12px;padding:32px;max-width:420px;width:90%;text-align:center;';

            var icon = document.createElement('div');
            icon.innerHTML = '<i class="fas fa-exclamation-triangle" style="font-size:32px;color:#F59E0B;margin-bottom:16px;"></i>';

            var msg = document.createElement('p');
            msg.textContent = message;
            msg.style.cssText = 'color:var(--text-white,#fff);font-size:15px;margin-bottom:24px;line-height:1.5;';

            var actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:12px;justify-content:center;';

            var cancelBtn = document.createElement('button');
            cancelBtn.textContent = cancelText;
            cancelBtn.style.cssText = 'padding:10px 24px;border-radius:8px;border:1px solid var(--border,#2D2D3F);background:transparent;color:var(--text-white,#fff);cursor:pointer;font-size:14px;font-weight:600;';

            var confirmBtn = document.createElement('button');
            confirmBtn.textContent = confirmText;
            confirmBtn.style.cssText = 'padding:10px 24px;border-radius:8px;border:none;background:linear-gradient(135deg,var(--primary,#6B46C1),var(--accent,#8B5CF6));color:white;cursor:pointer;font-size:14px;font-weight:600;';

            function cleanup(result) {
                overlay.remove();
                resolve(result);
            }

            cancelBtn.addEventListener('click', function() { cleanup(false); });
            confirmBtn.addEventListener('click', function() { cleanup(true); });
            overlay.addEventListener('click', function(e) { if (e.target === overlay) cleanup(false); });

            actions.appendChild(cancelBtn);
            actions.appendChild(confirmBtn);
            card.appendChild(icon);
            card.appendChild(msg);
            card.appendChild(actions);
            overlay.appendChild(card);
            document.body.appendChild(overlay);
            confirmBtn.focus();
        });
    }

    // Expose showConfirmModal globally so views and other scripts can use it
    window.showConfirmModal = showConfirmModal;

    /**
     * Show an in-app alert modal instead of browser alert().
     * Returns a Promise that resolves when the user clicks OK.
     */
    function showAlertModal(message, okText) {
        okText = okText || 'OK';
        return new Promise(function(resolve) {
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:10001;';

            var card = document.createElement('div');
            card.style.cssText = 'background:var(--bg-card,#16161F);border:1px solid var(--border,#2D2D3F);border-radius:12px;padding:32px;max-width:420px;width:90%;text-align:center;';

            var icon = document.createElement('div');
            icon.innerHTML = '<i class="fas fa-info-circle" style="font-size:32px;color:#6B46C1;margin-bottom:16px;"></i>';

            var msg = document.createElement('p');
            msg.textContent = message;
            msg.style.cssText = 'color:var(--text-white,#fff);font-size:15px;margin-bottom:24px;line-height:1.5;';

            var actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:12px;justify-content:center;';

            var okBtn = document.createElement('button');
            okBtn.textContent = okText;
            okBtn.style.cssText = 'padding:10px 24px;border-radius:8px;border:none;background:linear-gradient(135deg,var(--primary,#6B46C1),var(--accent,#8B5CF6));color:white;cursor:pointer;font-size:14px;font-weight:600;';

            function cleanup() {
                overlay.remove();
                resolve();
            }

            okBtn.addEventListener('click', cleanup);
            overlay.addEventListener('click', function(e) { if (e.target === overlay) cleanup(); });

            actions.appendChild(okBtn);
            card.appendChild(icon);
            card.appendChild(msg);
            card.appendChild(actions);
            overlay.appendChild(card);
            document.body.appendChild(overlay);
            okBtn.focus();
        });
    }

    window.showAlertModal = showAlertModal;

    /**
     * Show an in-app prompt modal instead of browser prompt().
     * Returns a Promise that resolves to the entered string or null if cancelled.
     */
    function showPromptModal(message, defaultValue) {
        defaultValue = defaultValue || '';
        return new Promise(function(resolve) {
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:10001;';

            var card = document.createElement('div');
            card.style.cssText = 'background:var(--bg-card,#16161F);border:1px solid var(--border,#2D2D3F);border-radius:12px;padding:32px;max-width:420px;width:90%;text-align:center;';

            var icon = document.createElement('div');
            icon.innerHTML = '<i class="fas fa-edit" style="font-size:32px;color:#6B46C1;margin-bottom:16px;"></i>';

            var msg = document.createElement('p');
            msg.textContent = message;
            msg.style.cssText = 'color:var(--text-white,#fff);font-size:15px;margin-bottom:16px;line-height:1.5;';

            var input = document.createElement('input');
            input.type = 'text';
            input.value = defaultValue;
            input.style.cssText = 'width:100%;padding:10px 14px;border-radius:8px;border:1px solid var(--border,#2D2D3F);background:var(--bg-dark,#0D0D14);color:var(--text-white,#fff);font-size:14px;margin-bottom:20px;box-sizing:border-box;';

            var actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:12px;justify-content:center;';

            var cancelBtn = document.createElement('button');
            cancelBtn.textContent = 'Cancel';
            cancelBtn.style.cssText = 'padding:10px 24px;border-radius:8px;border:1px solid var(--border,#2D2D3F);background:transparent;color:var(--text-white,#fff);cursor:pointer;font-size:14px;font-weight:600;';

            var okBtn = document.createElement('button');
            okBtn.textContent = 'OK';
            okBtn.style.cssText = 'padding:10px 24px;border-radius:8px;border:none;background:linear-gradient(135deg,var(--primary,#6B46C1),var(--accent,#8B5CF6));color:white;cursor:pointer;font-size:14px;font-weight:600;';

            function cleanup(result) {
                overlay.remove();
                resolve(result);
            }

            cancelBtn.addEventListener('click', function() { cleanup(null); });
            okBtn.addEventListener('click', function() { cleanup(input.value); });
            overlay.addEventListener('click', function(e) { if (e.target === overlay) cleanup(null); });
            input.addEventListener('keydown', function(e) { if (e.key === 'Enter') cleanup(input.value); });

            actions.appendChild(cancelBtn);
            actions.appendChild(okBtn);
            card.appendChild(icon);
            card.appendChild(msg);
            card.appendChild(input);
            card.appendChild(actions);
            overlay.appendChild(card);
            document.body.appendChild(overlay);
            input.focus();
        });
    }

    window.showPromptModal = showPromptModal;

    /**
     * Show loading indicator
     */
    function showLoading(element) {
        const loader = document.createElement('div');
        loader.className = 'loader';
        loader.innerHTML = '<div class="spinner"></div>';
        loader.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        `;
        element.style.position = 'relative';
        element.appendChild(loader);
        return loader;
    }

    /**
     * Hide loading indicator
     */
    function hideLoading(loader) {
        if (loader && loader.parentNode) {
            loader.parentNode.removeChild(loader);
        }
    }

    /**
     * Debounce function for search
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // ===================================================================
    // SEARCH FUNCTIONALITY
    // ===================================================================

    /**
     * Initialize search for all tables
     */
    function initializeSearch() {
        const searchInputs = document.querySelectorAll('[data-search-table]');
        
        searchInputs.forEach(input => {
            const tableName = input.getAttribute('data-search-table');
            const table = document.querySelector(`table[data-table="${tableName}"], table.${tableName}-table, #${tableName}-table`);
            
            if (!table) return;
            
            const debouncedSearch = debounce(() => {
                const searchTerm = input.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                let visibleCount = 0;
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Show "no results" message if needed
                const noResults = table.querySelector('.no-results');
                if (visibleCount === 0) {
                    if (!noResults) {
                        const tr = document.createElement('tr');
                        tr.className = 'no-results';
                        tr.innerHTML = `<td colspan="100" style="text-align: center; padding: 20px; color: #9CA3AF;">No results found for "${searchTerm}"</td>`;
                        table.querySelector('tbody').appendChild(tr);
                    }
                } else if (noResults) {
                    noResults.remove();
                }
            }, 300);
            
            input.addEventListener('input', debouncedSearch);
            input.addEventListener('keyup', debouncedSearch);
        });
    }

    // ===================================================================
    // FILTER FUNCTIONALITY
    // ===================================================================

    /**
     * Initialize filters for tables
     */
    function initializeFilters() {
        const filterSelects = document.querySelectorAll('[data-filter-table]');
        
        filterSelects.forEach(select => {
            const tableName = select.getAttribute('data-filter-table');
            const column = select.getAttribute('data-filter-column');
            const table = document.querySelector(`table[data-table="${tableName}"], table.${tableName}-table, #${tableName}-table`);
            
            if (!table) return;
            
            select.addEventListener('change', () => {
                const filterValue = select.value.toLowerCase();
                const columnIndex = parseInt(select.getAttribute('data-column-index')) || 0;
                const rows = table.querySelectorAll('tbody tr:not(.no-results)');
                
                rows.forEach(row => {
                    if (filterValue === '' || filterValue === 'all') {
                        row.style.display = '';
                    } else {
                        const cell = row.cells[columnIndex];
                        if (cell) {
                            const cellText = cell.textContent.toLowerCase();
                            row.style.display = cellText.includes(filterValue) ? '' : 'none';
                        }
                    }
                });
            });
        });
    }

    /**
     * Initialize date range filters
     */
    function initializeDateRangeFilters() {
        const dateRangeFilters = document.querySelectorAll('[data-date-range-filter]');
        
        dateRangeFilters.forEach(container => {
            const startDate = container.querySelector('[data-start-date]');
            const endDate = container.querySelector('[data-end-date]');
            const applyBtn = container.querySelector('[data-apply-filter]');
            const clearBtn = container.querySelector('[data-clear-filter]');
            
            if (!startDate || !endDate || !applyBtn) return;
            
            const tableName = container.getAttribute('data-date-range-filter');
            const table = document.querySelector(`table[data-table="${tableName}"]`);
            if (!table) return;
            
            applyBtn.addEventListener('click', () => {
                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                const dateColumnIndex = parseInt(container.getAttribute('data-date-column')) || 0;
                
                const rows = table.querySelectorAll('tbody tr:not(.no-results)');
                rows.forEach(row => {
                    const cell = row.cells[dateColumnIndex];
                    if (cell) {
                        const rowDate = new Date(cell.textContent);
                        row.style.display = (rowDate >= start && rowDate <= end) ? '' : 'none';
                    }
                });
                
                showToast('Date filter applied', 'success');
            });
            
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    startDate.value = '';
                    endDate.value = '';
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => row.style.display = '');
                    showToast('Filter cleared', 'info');
                });
            }
        });
    }

    // ===================================================================
    // BUTTON FUNCTIONALITY
    // ===================================================================

    /**
     * Initialize all button click handlers
     */
    function initializeButtons() {
        // Add buttons
        document.querySelectorAll('[data-action="add"], .btn-add, .add-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const modalId = this.getAttribute('data-modal') || this.getAttribute('data-target');
                if (modalId) {
                    openModal(modalId);
                } else {
                    const form = this.closest('form');
                    if (form) form.submit();
                }
            });
        });

        // Edit buttons
        document.querySelectorAll('[data-action="edit"], .btn-edit, .edit-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                const modalId = this.getAttribute('data-modal');
                if (modalId) {
                    openModal(modalId, itemId);
                }
            });
        });

        // Delete buttons — only handle elements with an explicit data-action-url.
        // Pages with their own delete handlers (accounting_products, admin_categories,
        // drills_library, etc.) manage confirmation and AJAX deletion themselves.
        // Binding here without data-action-url would show a duplicate browser confirm
        // and submit to the wrong endpoint, preventing the real delete from working.
        document.querySelectorAll('[data-action="delete"][data-action-url], .btn-delete[data-action-url], .delete-btn[data-action-url]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                const itemType = this.getAttribute('data-type') || 'item';
                const itemName = this.getAttribute('data-name') || 'this item';
                const actionUrl = this.getAttribute('data-action-url');
                
                showConfirmModal('Are you sure you want to delete ' + itemName + '?', 'Delete', 'Cancel').then(function(confirmed) {
                    if (confirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = actionUrl;
                        
                        // Add CSRF token
                        const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
                        if (csrfToken) {
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = 'csrf_token';
                            csrfInput.value = csrfToken;
                            form.appendChild(csrfInput);
                        }
                        
                        // Add action parameter based on type
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        if (itemType === 'schedule') {
                            actionInput.value = 'schedule_delete';
                        } else {
                            actionInput.value = 'delete';
                        }
                        form.appendChild(actionInput);
                        
                        // Add the item ID with correct parameter name
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        if (itemType === 'schedule') {
                            input.name = 'schedule_id';
                        } else {
                            input.name = 'id';
                        }
                        input.value = itemId;
                        form.appendChild(input);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });

        // Export buttons (skip links that navigate to PHP endpoints)
        document.querySelectorAll('[data-action="export"], .btn-export, .export-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Allow normal navigation for links to server-side exports
                if (this.tagName === 'A' && this.getAttribute('href') && this.getAttribute('data-action') !== 'export') {
                    return;
                }
                e.preventDefault();
                const format = this.getAttribute('data-format') || 'csv';
                const tableName = this.getAttribute('data-table');
                exportTable(tableName, format);
            });
        });

        // Upload buttons
        document.querySelectorAll('[data-action="upload"], .btn-upload, .upload-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const fileInput = this.getAttribute('data-file-input');
                if (fileInput) {
                    document.getElementById(fileInput).click();
                }
            });
        });

        // Save buttons
        document.querySelectorAll('[data-action="save"], .btn-save, .save-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const form = this.closest('form');
                if (form && !form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                }
            });
        });

        // Cancel buttons
        document.querySelectorAll('[data-action="cancel"], .btn-cancel, .cancel-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const modalId = this.getAttribute('data-modal');
                if (modalId) {
                    closeModal(modalId);
                } else {
                    window.history.back();
                }
            });
        });

        // Generic action buttons with navigation
        document.querySelectorAll('[data-action]').forEach(btn => {
            const action = btn.getAttribute('data-action');
            
            // Skip if already handled by specific handlers above or by page-level handlers
            if (['add', 'edit', 'delete', 'export', 'upload', 'save', 'cancel', 'switch-tab', 'register-session', 'join-waitlist', 'purchase-package', 'play-video', 'view-video', 'delete-video'].includes(action)) {
                return;
            }
            
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const page = this.getAttribute('data-page');
                const modal = this.getAttribute('data-modal');
                const url = this.getAttribute('data-url');
                const type = this.getAttribute('data-type');
                const sessionId = this.getAttribute('data-session-id');
                const itemId = this.getAttribute('data-id');
                
                // Handle contact action (Contact Coach buttons)
                if (action === 'contact') {
                    if (modal) {
                        openModal(modal);
                        return;
                    }
                    if (page) {
                        // Navigate to contact/messages page
                        window.location.href = `?page=${page}`;
                        return;
                    }
                }
                
                // Handle add-expense action
                if (action === 'add-expense') {
                    if (page) {
                        window.location.href = `?page=${page}`;
                        return;
                    }
                }
                
                // Handle create-invoice action
                if (action === 'create-invoice') {
                    // First check if there's a modal on the current page
                    const modal = document.getElementById('create-invoice-modal');
                    if (modal) {
                        openModal('create-invoice-modal');
                        return;
                    }
                    // Otherwise navigate to page
                    if (page) {
                        window.location.href = `?page=${page}&action=create`;
                        return;
                    }
                }
                
                // Handle record-payment action
                if (action === 'record-payment') {
                    if (page) {
                        window.location.href = `?page=${page}&action=payment`;
                        return;
                    }
                }
                
                // Handle generate-report action
                if (action === 'generate-report') {
                    if (page) {
                        window.location.href = `?page=${page}`;
                        return;
                    }
                }
                
                // Handle issue-credit action
                if (action === 'issue-credit') {
                    if (page) {
                        window.location.href = `?page=${page}`;
                        return;
                    }
                }
                
                // Handle view-products action
                if (action === 'view-products') {
                    if (page) {
                        window.location.href = `?page=${page}`;
                        return;
                    }
                }
                
                // Handle view-all action
                if (action === 'view-all') {
                    if (page) {
                        window.location.href = `?page=${page}`;
                        return;
                    }
                }
                
                // Handle view action (generic navigation)
                if (action === 'view') {
                    if (page) {
                        window.location.href = `?page=${page}`;
                        return;
                    }
                }
                
                // Handle run action (cron jobs, etc.)
                if (action === 'run' && itemId) {
                    showConfirmModal('Run this job now?', 'Run', 'Cancel').then(function(confirmed) {
                        if (confirmed) {
                            const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
                            showToast('Running job...', 'info');
                            
                            fetch('process_cron_jobs.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=run_now&id=${itemId}&csrf_token=${encodeURIComponent(csrfToken)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    persistToast('Job completed successfully', 'success');
                                    window.location.reload();
                                } else {
                                    showToast(data.message || 'Job failed', 'error');
                                }
                            })
                            .catch(error => {
                                showToast('Error running job', 'error');
                                console.error('Run job error:', error);
                            });
                        }
                    });
                    return;
                }
                
                // Handle toggle action (pause/resume cron jobs)
                if (action === 'toggle' && itemId) {
                    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
                    
                    fetch('process_cron_jobs.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=toggle&id=${itemId}&csrf_token=${encodeURIComponent(csrfToken)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            persistToast(data.message || 'Status updated', 'success');
                            window.location.reload();
                        } else {
                            showToast(data.message || 'Update failed', 'error');
                        }
                    })
                    .catch(error => {
                        showToast('Error updating status', 'error');
                        console.error('Toggle error:', error);
                    });
                    return;
                }
                
                // Handle toggle-status action (enable/disable users, sessions, packages, etc.)
                if (action === 'toggle-status' && itemId) {
                    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
                    const entityType = type || 'item';
                    
                    showConfirmModal('Are you sure you want to toggle the status of this ' + entityType + '?', 'Confirm', 'Cancel').then(function(confirmed) {
                        if (confirmed) {
                            let endpoint = 'process_admin_action.php';
                            let actionName = 'toggle_status';
                            
                            // Determine endpoint based on type
                            if (entityType === 'session') {
                                endpoint = 'process_admin_action.php';
                                actionName = 'toggle_session_status';
                            } else if (entityType === 'package') {
                                endpoint = 'process_packages.php';
                                actionName = 'toggle_status';
                            } else if (entityType === 'user') {
                                endpoint = 'process_admin_action.php';
                                actionName = 'toggle_user_status';
                            }
                            
                            fetch(endpoint, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=${actionName}&id=${itemId}&csrf_token=${encodeURIComponent(csrfToken)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    persistToast(data.message || 'Status updated successfully', 'success');
                                    window.location.reload();
                                } else {
                                    showToast(data.message || 'Failed to update status', 'error');
                                }
                            })
                            .catch(error => {
                                showToast('Error updating status', 'error');
                                console.error('Toggle status error:', error);
                            });
                        }
                    });
                    return;
                }
                
                // Handle permissions action (user permissions)
                if (action === 'permissions' && itemId) {
                    window.location.href = `?page=user_permissions&user_id=${itemId}`;
                    return;
                }
                
                // Handle view-session action specifically
                if (action === 'view-session' && sessionId) {
                    // Only navigate if sessionId is numeric (real session, not demo)
                    // Demo sessions (e.g., "demo-1") are handled by local page handlers
                    if (/^\d+$/.test(sessionId)) {
                        window.location.href = `?page=session_detail&id=${sessionId}`;
                    }
                    // For non-numeric IDs (demo sessions), do nothing - let local handlers manage
                    return;
                }
                
                // Handle cancel-session action
                if (action === 'cancel-session' && sessionId) {
                    // Validate sessionId is numeric
                    if (!/^\d+$/.test(sessionId)) {
                        console.error('Invalid session ID:', sessionId);
                        return;
                    }
                    
                    showConfirmModal('Are you sure you want to cancel this session?', 'Cancel Session', 'Go Back').then(function(confirmed) {
                        if (confirmed) {
                            const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
                            if (!csrfToken) {
                                showToast('Security token missing. Please refresh the page.', 'error');
                                return;
                            }
                            
                            // Send cancel request
                            fetch('process_booking.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=cancel&session_id=${encodeURIComponent(sessionId)}&csrf_token=${encodeURIComponent(csrfToken)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    persistToast('Session cancelled successfully', 'success');
                                    window.location.reload();
                                } else {
                                    showToast(data.message || 'Failed to cancel session', 'error');
                                }
                            })
                            .catch(error => {
                                showToast('An error occurred', 'error');
                                console.error('Cancel session error:', error);
                            });
                        }
                    });
                    return;
                }
                
                // Handle download-report action
                if (action === 'download-report') {
                    const file = this.getAttribute('data-file');
                    if (file) {
                        window.location.href = file;
                    } else if (itemId) {
                        window.location.href = `process_reports.php?action=download&report_id=${itemId}`;
                    }
                    return;
                }
                
                // Handle view-report action
                if (action === 'view-report') {
                    const file = this.getAttribute('data-file');
                    if (file) {
                        window.open(file, '_blank');
                    } else if (itemId) {
                        window.open(`process_reports.php?action=view&report_id=${itemId}`, '_blank');
                    }
                    return;
                }
                
                // If there's a modal, open it
                if (modal) {
                    openModal(modal);
                    return;
                }
                
                // If there's a URL, navigate to it
                if (url) {
                    window.location.href = url;
                    return;
                }
                
                // If there's a page parameter, navigate to that page
                if (page) {
                    window.location.href = `?page=${page}`;
                    return;
                }
                
                // If there's a type for creating items, navigate to appropriate page
                if (type && action === 'add') {
                    // Handle specific types
                    const typePages = {
                        'goal': 'goals',
                        'session': 'create_session',
                        'invoice': 'billing_dashboard',
                        'payment': 'billing_dashboard',
                        'expense': 'expenses',
                        'refund': 'credits_refunds',
                        'drill': 'create_drill',
                        'practice_plan': 'create_practice'
                    };
                    
                    if (typePages[type]) {
                        window.location.href = `?page=${typePages[type]}`;
                        return;
                    }
                }
                
                // If button has a form parent, submit it
                const form = this.closest('form');
                if (form) {
                    form.submit();
                    return;
                }
                
                // Log warning if no action could be taken (development only)
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('Button clicked but no action handler found:', {
                        action: action,
                        buttonId: this.id || 'no-id',
                        buttonClass: this.className
                    });
                }
            });
        });
    }

    // ===================================================================
    // FORM FUNCTIONALITY
    // ===================================================================

    /**
     * Initialize form submissions with AJAX
     */
    function initializeForms() {
        document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const url = this.action || window.location.href;
                const method = this.method || 'POST';
                
                const loader = showLoading(this);
                
                fetch(url, {
                    method: method,
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading(loader);
                    if (data.success) {
                        if (data.redirect) {
                            persistToast(data.message || 'Operation successful', 'success');
                            window.location.href = data.redirect;
                        } else {
                            showToast(data.message || 'Operation successful', 'success');
                            this.reset();
                            const modalId = this.closest('[data-modal-id]')?.getAttribute('data-modal-id');
                            if (modalId) closeModal(modalId);
                            // Dispatch event for live UI updates without full page reload
                            document.dispatchEvent(new CustomEvent('arctic:content-updated', {
                                detail: { action: 'create', data: data }
                            }));
                        }
                    } else {
                        showToast(data.message || 'Operation failed', 'error');
                    }
                })
                .catch(error => {
                    hideLoading(loader);
                    showToast('An error occurred', 'error');
                    console.error('Form submission error:', error);
                });
            });
        });
    }

    /**
     * Initialize real-time form validation
     */
    function initializeValidation() {
        const inputs = document.querySelectorAll('input[required], textarea[required], select[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (!this.checkValidity()) {
                    this.classList.add('is-invalid');
                    const errorMsg = this.validationMessage;
                    let errorDiv = this.nextElementSibling;
                    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        this.parentNode.insertBefore(errorDiv, this.nextSibling);
                    }
                    errorDiv.textContent = errorMsg;
                } else {
                    this.classList.remove('is-invalid');
                    const errorDiv = this.nextElementSibling;
                    if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                        errorDiv.remove();
                    }
                }
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.checkValidity()) {
                    this.classList.remove('is-invalid');
                    const errorDiv = this.nextElementSibling;
                    if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                        errorDiv.remove();
                    }
                }
            });
        });
    }

    // ===================================================================
    // DATE PICKER FUNCTIONALITY
    // ===================================================================

    /**
     * Initialize modern date pickers with calendar
     */
    function initializeDatePickers() {
        const dateInputs = document.querySelectorAll('input[type="date"], [data-date-picker]');
        
        dateInputs.forEach(input => {
            // Ensure the input has the correct styling
            input.style.fontFamily = 'Inter, sans-serif';
            input.style.height = '45px';
            input.style.padding = '0 16px';
            input.style.border = '1px solid #2D2D3F';
            input.style.borderRadius = '8px';
            input.style.background = '#0A0A0F';
            input.style.color = '#E0E0E0';
            input.style.transition = 'all 0.3s ease';
            
            // Add focus styles
            input.addEventListener('focus', function() {
                this.style.borderColor = '#7C3AED';
                this.style.boxShadow = '0 0 0 3px rgba(124, 58, 237, 0.2)';
            });
            
            input.addEventListener('blur', function() {
                this.style.borderColor = '#2D2D3F';
                this.style.boxShadow = 'none';
            });
            
            // Allow both typing and calendar selection
            input.setAttribute('placeholder', 'YYYY-MM-DD or click to select');
        });
    }

    // ===================================================================
    // FILE UPLOAD FUNCTIONALITY
    // ===================================================================

    /**
     * Initialize file upload with drag and drop zones.
     * Drop zones are always visible and highlight when a file
     * is dragged into the browser window.
     */
    function initializeFileUploads() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        var dragZones = [];
        var dragCounter = 0;

        fileInputs.forEach(input => {
            // Skip inputs that already have a custom upload UI
            if (input.closest('.file-upload-zone') ||
                input.closest('.file-upload-area') ||
                input.closest('[data-component="FileUpload"]') ||
                input.closest('.msg-input-toolbar') ||
                input.closest('.messenger-input-toolbar')) {
                return;
            }

            // Create drop zone for drag-and-drop file uploads
            var zone = document.createElement('div');
            zone.className = 'file-upload-zone';
            zone.style.cssText = 'display:block;border:2px dashed #2D2D3F;border-radius:8px;padding:40px;text-align:center;background:#13131A;cursor:pointer;transition:all 0.3s ease;';
            zone.innerHTML = '<div class="upload-icon" style="font-size:48px;color:#6B46C1;margin-bottom:16px;"><i class="fas fa-cloud-arrow-up"></i></div>' +
                '<div class="upload-text" style="color:#E0E0E0;margin-bottom:8px;">Drop file here or click to browse</div>' +
                '<div class="upload-hint" style="color:#9CA3AF;font-size:12px;">Supported formats vary by field</div>';

            zone.addEventListener('click', function() { input.click(); });

            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                zone.style.borderColor = '#7C3AED';
                zone.style.background = '#1A1A2E';
            });

            zone.addEventListener('dragleave', function() {
                zone.style.borderColor = '#2D2D3F';
                zone.style.background = '#13131A';
            });

            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                zone.style.borderColor = '#2D2D3F';
                zone.style.background = '#13131A';
                if (e.dataTransfer.files.length > 0) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            input.parentNode.insertBefore(zone, input);
            input.style.display = 'none';
            dragZones.push({ zone: zone });

            // Show file name when selected
            input.addEventListener('change', function() {
                var fileName = this.files[0] ? this.files[0].name : '';
                var textDiv = zone.querySelector('.upload-text');
                if (textDiv) {
                    if (fileName) {
                        textDiv.textContent = fileName;
                        textDiv.style.color = '#10B981';
                    } else {
                        textDiv.textContent = 'Drop file here or click to browse';
                        textDiv.style.color = '#E0E0E0';
                    }
                }
            });
        });

        // Contextual drag: show drop zones only when dragging into the window
        if (dragZones.length > 0) {
            document.addEventListener('dragenter', function(e) {
                e.preventDefault();
                dragCounter++;
                if (dragCounter === 1) {
                    dragZones.forEach(function(item) {
                        item.zone.style.borderColor = '#7C3AED';
                        item.zone.style.background = '#1A1A2E';
                    });
                }
            });

            document.addEventListener('dragleave', function(e) {
                e.preventDefault();
                dragCounter--;
                if (dragCounter <= 0) {
                    dragCounter = 0;
                    dragZones.forEach(function(item) {
                        item.zone.style.borderColor = '#2D2D3F';
                        item.zone.style.background = '#13131A';
                    });
                }
            });

            document.addEventListener('drop', function(e) {
                dragCounter = 0;
                dragZones.forEach(function(item) {
                    item.zone.style.borderColor = '#2D2D3F';
                    item.zone.style.background = '#13131A';
                });
            });
        }
    }

    // ===================================================================
    // MODAL FUNCTIONALITY
    // ===================================================================

    /**
     * Open modal
     */
    function openModal(modalId, itemId = null) {
        const modal = document.getElementById(modalId) || document.querySelector(`[data-modal-id="${modalId}"]`);
        if (!modal) {
            console.warn(`Modal with ID ${modalId} not found`);
            return;
        }
        
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // If editing, populate form with data
        if (itemId) {
            // This would typically fetch data via AJAX
            console.log('Loading data for item:', itemId);
        }
    }

    /**
     * Close modal
     */
    function closeModal(modalId) {
        const modal = document.getElementById(modalId) || document.querySelector(`[data-modal-id="${modalId}"]`);
        if (!modal) return;
        
        modal.style.display = '';
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form if present
        const form = modal.querySelector('form');
        if (form) form.reset();
    }

    /**
     * Initialize modals
     */
    function initializeModals() {
        // Close buttons
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function() {
                const modalId = this.getAttribute('data-close-modal');
                closeModal(modalId);
            });
        });
        
        // Escape key closes modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    const modalId = activeModal.id || activeModal.getAttribute('data-modal-id');
                    closeModal(modalId);
                }
            }
        });
    }

    // ===================================================================
    // EXPORT FUNCTIONALITY
    // ===================================================================

    /**
     * Export table to CSV or Excel
     */
    function exportTable(tableName, format = 'csv') {
        const table = document.querySelector(`table[data-table="${tableName}"], table.${tableName}-table, #${tableName}-table`);
        if (!table) {
            showToast('Table not found', 'error');
            return;
        }
        
        const rows = [];
        const headerRow = [];
        
        // Get headers
        table.querySelectorAll('thead th').forEach(th => {
            headerRow.push(th.textContent.trim());
        });
        rows.push(headerRow);
        
        // Get visible rows only
        table.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.style.display !== 'none' && !tr.classList.contains('no-results')) {
                const row = [];
                tr.querySelectorAll('td').forEach(td => {
                    row.push(td.textContent.trim());
                });
                rows.push(row);
            }
        });
        
        if (format === 'csv') {
            const csv = rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${tableName}_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            showToast('Export successful', 'success');
        }
    }

    // ===================================================================
    // CHECKBOXES & RADIO BUTTONS
    // ===================================================================

    /**
     * Initialize custom checkboxes and radio buttons
     */
    function initializeCustomInputs() {
        // Make checkboxes and radios functional
        document.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
            input.addEventListener('change', function() {
                // Handle any custom logic here
                const form = this.closest('form');
                if (form && form.hasAttribute('data-auto-submit')) {
                    form.submit();
                }
            });
        });
    }

    // ===================================================================
    // TABLE SORTING
    // ===================================================================

    /**
     * Initialize sortable table columns
     */
    function initializeTableSorting() {
        document.querySelectorAll('th[data-sortable]').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const table = this.closest('table');
                const columnIndex = Array.from(this.parentNode.children).indexOf(this);
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results)'));
                
                const currentOrder = this.getAttribute('data-sort-order') || 'asc';
                const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                
                rows.sort((a, b) => {
                    const aValue = a.cells[columnIndex].textContent.trim();
                    const bValue = b.cells[columnIndex].textContent.trim();
                    
                    const aNum = parseFloat(aValue);
                    const bNum = parseFloat(bValue);
                    
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return newOrder === 'asc' ? aNum - bNum : bNum - aNum;
                    } else {
                        return newOrder === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                    }
                });
                
                rows.forEach(row => tbody.appendChild(row));
                
                // Update sort indicators
                table.querySelectorAll('th[data-sortable]').forEach(header => {
                    header.removeAttribute('data-sort-order');
                });
                this.setAttribute('data-sort-order', newOrder);
            });
        });
    }

    // ===================================================================
    // TAB NAVIGATION
    // ===================================================================

    /**
     * Initialize tab switching functionality
     */
    function initializeTabNavigation() {
        // Handle tab buttons with data-action="switch-tab"
        document.querySelectorAll('[data-action="switch-tab"]').forEach(btn => {
            // Skip if this button already has a page-specific handler
            if (btn.hasAttribute('data-tab-handled')) {
                return;
            }
            
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetTab = this.getAttribute('data-tab');
                if (!targetTab) return;
                
                // Sanitize tab name to prevent selector injection
                const sanitizedTab = targetTab.replace(/[^a-zA-Z0-9_-]/g, '');
                if (sanitizedTab !== targetTab) {
                    console.warn('Invalid tab name:', targetTab);
                    return;
                }
                
                // Try to find container, including .page-tabs parent for sibling .page-tab-content
                let tabContainer = this.closest('.products-content, .content-wrapper, .page-content, .page-tab-content');
                if (!tabContainer) {
                    // Check if button is in .page-tabs which is a sibling of .page-tab-content
                    const pageTabs = this.closest('.page-tabs');
                    if (pageTabs && pageTabs.nextElementSibling && pageTabs.nextElementSibling.classList.contains('page-tab-content')) {
                        tabContainer = pageTabs.parentElement;
                    }
                }
                tabContainer = tabContainer || document;
                
                // Remove active class from all tabs (support both .tab-btn and .page-tab)
                tabContainer.querySelectorAll('.tab-btn, .page-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Hide all tab content
                tabContainer.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                    content.style.display = 'none';
                });
                
                // Show target tab content
                const targetContent = tabContainer.querySelector(`#${sanitizedTab}-tab, [data-tab-content="${sanitizedTab}"]`);
                if (targetContent) {
                    targetContent.classList.add('active');
                    targetContent.style.display = 'block';
                }
            });
        });
        
        // Handle regular tab links (using href)
        document.querySelectorAll('.tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                // For tab links with hrefs, let them navigate naturally
                // They already have the active class set by PHP
            });
        });
    }

    // ===================================================================
    // VIDEO PLAYER MODAL (Video.js)
    // ===================================================================

    /**
     * Open a global Video.js-powered player modal.
     * Lazily injects the Video.js CDN and creates the modal DOM on first use.
     * Handles multiple video formats that browsers may not natively support.
     *
     * @param {string} videoUrl  - URL of the video to play (may be a proxy URL)
     * @param {string} title     - Display title for the modal header
     * @param {string} videoId   - Optional video DB id (for future use)
     */
    function openVideoPlayerModal(videoUrl, title, videoId) {
        // Lazy-load Video.js CSS + JS
        if (!document.getElementById('videojs-css')) {
            var link = document.createElement('link');
            link.id = 'videojs-css';
            link.rel = 'stylesheet';
            link.href = 'https://vjs.zencdn.net/8.10.0/video-js.css';
            document.head.appendChild(link);
        }

        function setupModal() {
            // Create modal DOM if it doesn't exist
            var modal = document.getElementById('aw-video-player-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'aw-video-player-modal';
                modal.className = 'aw-vp-modal';
                modal.innerHTML =
                    '<div class="aw-vp-overlay"></div>' +
                    '<div class="aw-vp-content">' +
                        '<div class="aw-vp-header">' +
                            '<h3 id="aw-vp-title"><i class="fas fa-play-circle"></i> Video Player</h3>' +
                            '<button class="aw-vp-close" aria-label="Close"><i class="fas fa-times"></i></button>' +
                        '</div>' +
                        '<div class="aw-vp-body">' +
                            '<video id="aw-vp-video" class="video-js vjs-big-play-centered vjs-theme-forest" controls preload="auto" width="100%">' +
                                '<p class="vjs-no-js">Please enable JavaScript to view this video.</p>' +
                            '</video>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(modal);

                // Inject scoped styles
                if (!document.getElementById('aw-vp-styles')) {
                    var s = document.createElement('style');
                    s.id = 'aw-vp-styles';
                    s.textContent =
                        '.aw-vp-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:10000;align-items:center;justify-content:center;}' +
                        '.aw-vp-modal.active{display:flex;}' +
                        '.aw-vp-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);}' +
                        '.aw-vp-content{position:relative;width:95%;max-width:960px;background:#0d1117;border:1px solid #1e293b;border-radius:14px;overflow:hidden;}' +
                        '.aw-vp-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #1e293b;}' +
                        '.aw-vp-header h3{font-size:16px;font-weight:700;color:#fff;display:flex;align-items:center;gap:8px;margin:0;}' +
                        '.aw-vp-header h3 i{color:#7c3aed;}' +
                        '.aw-vp-close{width:36px;height:36px;background:transparent;border:1px solid #1e293b;color:#fff;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;}' +
                        '.aw-vp-close:hover{background:#7c3aed;border-color:#7c3aed;}' +
                        '.aw-vp-body{padding:0;background:#000;}' +
                        '.aw-vp-body .video-js{width:100%;max-height:75vh;}' +
                        '.aw-vp-body .video-js .vjs-big-play-button{background:rgba(124,58,237,.85);border:none;border-radius:50%;width:80px;height:80px;line-height:80px;font-size:36px;}';
                    document.head.appendChild(s);
                }

                // Close handlers
                modal.querySelector('.aw-vp-overlay').addEventListener('click', closeVideoPlayerModal);
                modal.querySelector('.aw-vp-close').addEventListener('click', closeVideoPlayerModal);
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && modal.classList.contains('active')) {
                        closeVideoPlayerModal();
                    }
                });
            }

            // Set title
            document.getElementById('aw-vp-title').innerHTML = '<i class="fas fa-play-circle"></i> ' + (title || 'Video Player');

            // Determine MIME type from URL
            var mimeType = 'video/mp4';
            if (videoUrl) {
                var urlLower = videoUrl.toLowerCase();
                if (urlLower.indexOf('.webm') !== -1) mimeType = 'video/webm';
                else if (urlLower.indexOf('.mov') !== -1) mimeType = 'video/mp4';
                else if (urlLower.indexOf('.mkv') !== -1) mimeType = 'video/x-matroska';
                else if (urlLower.indexOf('.avi') !== -1) mimeType = 'video/x-msvideo';
            }

            // Dispose previous Video.js instance if any
            var vjsEl = document.getElementById('aw-vp-video');
            if (vjsEl && typeof videojs !== 'undefined' && videojs.getPlayer && videojs.getPlayer('aw-vp-video')) {
                videojs.getPlayer('aw-vp-video').dispose();
                // Re-create the <video> element after dispose
                var newVid = document.createElement('video');
                newVid.id = 'aw-vp-video';
                newVid.className = 'video-js vjs-big-play-centered vjs-theme-forest';
                newVid.setAttribute('controls', '');
                newVid.setAttribute('preload', 'auto');
                newVid.setAttribute('width', '100%');
                newVid.innerHTML = '<p class="vjs-no-js">Please enable JavaScript to view this video.</p>';
                modal.querySelector('.aw-vp-body').innerHTML = '';
                modal.querySelector('.aw-vp-body').appendChild(newVid);
            }

            // Show modal
            modal.classList.add('active');

            if (!videoUrl) {
                showToast('No video URL available', 'error');
                return;
            }

            // Initialize Video.js player
            if (typeof videojs !== 'undefined') {
                var player = videojs('aw-vp-video', {
                    controls: true,
                    autoplay: false,
                    preload: 'auto',
                    fluid: true,
                    responsive: true,
                    playbackRates: [0.25, 0.5, 1, 1.5, 2],
                    html5: {
                        vhs: { overrideNative: true },
                        nativeVideoTracks: false,
                        nativeAudioTracks: false
                    },
                    sources: [{ src: videoUrl, type: mimeType }]
                });

                // If the browser can't play the assigned type, fall back to
                // application/octet-stream so Video.js attempts native playback
                player.on('error', function() {
                    if (player.error() && player.error().code === 4) {
                        player.src({ src: videoUrl, type: 'application/octet-stream' });
                        player.load();
                    }
                });
            } else {
                // Video.js not loaded yet — fallback to plain HTML5 <video>
                vjsEl = document.getElementById('aw-vp-video');
                if (vjsEl) {
                    vjsEl.innerHTML = '<source src="' + videoUrl + '" type="' + mimeType + '">Your browser does not support this video format.';
                    vjsEl.load();
                }
            }
        }

        // Load Video.js script if not present
        if (typeof videojs === 'undefined' && !document.getElementById('videojs-script')) {
            var script = document.createElement('script');
            script.id = 'videojs-script';
            script.src = 'https://vjs.zencdn.net/8.10.0/video.min.js';
            script.onload = setupModal;
            document.head.appendChild(script);
        } else {
            setupModal();
        }
    }

    /**
     * Close the global video player modal and clean up resources
     */
    function closeVideoPlayerModal() {
        var modal = document.getElementById('aw-video-player-modal');
        if (!modal) return;
        modal.classList.remove('active');

        // Dispose Video.js player to stop playback and free memory
        if (typeof videojs !== 'undefined' && videojs.getPlayer && videojs.getPlayer('aw-vp-video')) {
            try { videojs.getPlayer('aw-vp-video').pause(); } catch (e) { /* ignore */ }
        }
    }

    // ===================================================================
    // VIDEO FUNCTIONALITY
    // ===================================================================

    /**
     * Initialize video-specific functionality
     */
    function initializeVideoFeatures() {
        // Rating selector
        document.querySelectorAll('[data-component="RatingSelector"]').forEach(selector => {
            const stars = selector.querySelectorAll('i[data-rating]');
            const ratingInput = document.querySelector('[data-field="rating-value"]');
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    
                    // Update hidden input
                    if (ratingInput) {
                        ratingInput.value = rating;
                    }
                    
                    // Update visual state
                    stars.forEach(s => {
                        const sRating = parseInt(s.getAttribute('data-rating'));
                        s.classList.toggle('active', sRating <= rating);
                    });
                });
                
                // Hover effect
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    stars.forEach(s => {
                        const sRating = parseInt(s.getAttribute('data-rating'));
                        s.style.color = sRating <= rating ? '#FF9D00' : '';
                    });
                });
            });
            
            selector.addEventListener('mouseleave', function() {
                const currentRating = ratingInput ? parseInt(ratingInput.value) : 0;
                stars.forEach(s => {
                    const sRating = parseInt(s.getAttribute('data-rating'));
                    s.style.color = sRating <= currentRating ? '#FF9D00' : '';
                });
            });
        });
        
        // File upload trigger
        document.querySelectorAll('[data-action="trigger-file-input"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const fileInput = this.closest('[data-component="FileUpload"]')?.querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.click();
                }
            });
        });
        
        // File input change handler
        document.querySelectorAll('[data-field="video-file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileName = this.files[0]?.name || '';
                const fileNameDisplay = this.closest('[data-component="FileUpload"]')?.querySelector('.file-name');
                if (fileNameDisplay && fileName) {
                    fileNameDisplay.textContent = fileName;
                    fileNameDisplay.style.display = 'block';
                }
            });
        });
        
        // View video / Play video action — open Video.js modal
        document.querySelectorAll('[data-action="view-video"], [data-action="play-video"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const videoUrl = this.getAttribute('data-video-url');
                const videoId = this.getAttribute('data-video-id');
                const title = this.getAttribute('data-title')
                    || this.closest('.video-card, .video-list-item')?.querySelector('.video-title, h4')?.textContent
                    || 'Video';
                openVideoPlayerModal(videoUrl, title, videoId);
            });
        });
        
        // Edit video action
        document.querySelectorAll('[data-action="edit-video"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const videoId = this.getAttribute('data-video-id');
                // Validate videoId is numeric
                if (videoId && /^\d+$/.test(videoId)) {
                    // Navigate to edit page or open edit modal
                    window.location.href = `?page=edit_video&id=${encodeURIComponent(videoId)}`;
                }
            });
        });
        
        // Delete video action — page-level handlers only.
        // Pages like video_coach_reviews.php and video_drill_review.php manage
        // their own delete-video confirmation and AJAX; a global handler here
        // would show a duplicate confirmation modal.
    }

    // ===================================================================
    // DATA-CONFIRM HANDLER
    // ===================================================================

    /**
     * Intercept form submit and button click events when a data-confirm
     * attribute is present and show an in-app confirmation modal instead
     * of using the browser's native confirm() dialog.
     */
    function initializeDataConfirm() {
        document.addEventListener('submit', function(e) {
            var form = e.target;
            var msg = form.getAttribute('data-confirm');
            if (!msg) return;
            if (form.getAttribute('data-confirm-ok') === '1') {
                form.removeAttribute('data-confirm-ok');
                return;
            }
            e.preventDefault();
            showConfirmModal(msg).then(function(ok) {
                if (ok) {
                    form.setAttribute('data-confirm-ok', '1');
                    // Use requestSubmit when available so submit-event listeners fire
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            });
        }, true);

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-confirm]');
            if (!btn) return;
            // Skip forms – they are handled via the submit listener
            if (btn.tagName === 'FORM') return;
            var msg = btn.getAttribute('data-confirm');
            if (!msg) return;
            if (btn.getAttribute('data-confirm-ok') === '1') {
                btn.removeAttribute('data-confirm-ok');
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            showConfirmModal(msg).then(function(ok) {
                if (ok) {
                    btn.setAttribute('data-confirm-ok', '1');
                    btn.click();
                }
            });
        }, true);
    }

    // ===================================================================
    // INITIALIZATION
    // ===================================================================

    /**
     * Initialize all functionality when DOM is ready
     */
    function init() {
        console.log('Arctic Wolves App initializing...');
        
        // Show any toast messages that were persisted before a page reload
        showPersistedToast();
        
        // Initialize all components
        initializeSearch();
        initializeFilters();
        initializeDateRangeFilters();
        initializeButtons();
        initializeForms();
        initializeValidation();
        initializeDatePickers();
        initializeFileUploads();
        initializeModals();
        initializeCustomInputs();
        initializeTableSorting();
        initializeTabNavigation();
        initializeVideoFeatures();
        initializeDataConfirm();
        
        console.log('Arctic Wolves App initialized successfully!');
        
        // Add CSS for animations
        if (!document.getElementById('app-animations')) {
            const style = document.createElement('style');
            style.id = 'app-animations';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                .spinner {
                    border: 3px solid #2D2D3F;
                    border-top: 3px solid #6B46C1;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                }
                .modal.active {
                    display: flex;
                }
            `;
            document.head.appendChild(style);
        }
    }

    // Run initialization when DOM is ready
    // ===================================================================
    // FILE UPLOAD HELPER
    // ===================================================================
    
    /**
     * Update file label with selected filename
     * @param {string} labelId - ID of the label element to update
     * @param {HTMLInputElement} fileInput - The file input element
     */
    function updateFileLabel(labelId, fileInput) {
        const label = document.getElementById(labelId);
        if (!label) return;
        
        if (fileInput.files && fileInput.files[0]) {
            // Truncate long filenames
            let filename = fileInput.files[0].name;
            if (filename.length > 50) {
                filename = filename.substring(0, 47) + '...';
            }
            label.textContent = filename;
            label.style.color = '#10B981';
        } else {
            label.textContent = 'Drag & drop file or click to browse';
            label.style.color = '';
        }
    }

    // ===================================================================
    // INITIALIZATION
    // ===================================================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export functions for external use
    window.ArcticWolvesApp = {
        showToast,
        persistToast,
        showLoading,
        hideLoading,
        openModal,
        closeModal,
        exportTable
    };

    // Also expose critical functions globally for inline onclick handlers
    window.closeModal = closeModal;
    window.openModal = openModal;
    window.showToast = showToast;
    window.persistToast = persistToast;
    window.updateFileLabel = updateFileLabel;

})();
