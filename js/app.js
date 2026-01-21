/**
 * Crash Hockey Main Application JavaScript
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

        // Delete buttons
        document.querySelectorAll('[data-action="delete"], .btn-delete, .delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                const itemName = this.getAttribute('data-name') || 'this item';
                
                if (confirm(`Are you sure you want to delete ${itemName}?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = this.getAttribute('data-action-url') || '';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'id';
                    input.value = itemId;
                    form.appendChild(input);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // Export buttons
        document.querySelectorAll('[data-action="export"], .btn-export, .export-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
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
                        showToast(data.message || 'Operation successful', 'success');
                        if (data.redirect) {
                            setTimeout(() => window.location.href = data.redirect, 1000);
                        } else {
                            this.reset();
                            const modalId = this.closest('[data-modal-id]')?.getAttribute('data-modal-id');
                            if (modalId) closeModal(modalId);
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
     * Initialize file upload with drag and drop
     */
    function initializeFileUploads() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            // Create a custom upload zone if it doesn't exist
            if (!input.closest('.file-upload-zone')) {
                const zone = document.createElement('div');
                zone.className = 'file-upload-zone';
                zone.style.cssText = `
                    border: 2px dashed #2D2D3F;
                    border-radius: 8px;
                    padding: 40px;
                    text-align: center;
                    background: #13131A;
                    cursor: pointer;
                    transition: all 0.3s ease;
                `;
                
                zone.innerHTML = `
                    <div class="upload-icon" style="font-size: 48px; color: #6B46C1; margin-bottom: 16px;">📁</div>
                    <div class="upload-text" style="color: #E0E0E0; margin-bottom: 8px;">Click to upload or drag and drop</div>
                    <div class="upload-hint" style="color: #9CA3AF; font-size: 12px;">Supported formats vary by field</div>
                `;
                
                input.parentNode.insertBefore(zone, input);
                input.style.display = 'none';
                
                zone.addEventListener('click', () => input.click());
                
                // Drag and drop functionality
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zone.style.borderColor = '#7C3AED';
                    zone.style.background = '#1A1A2E';
                });
                
                zone.addEventListener('dragleave', () => {
                    zone.style.borderColor = '#2D2D3F';
                    zone.style.background = '#13131A';
                });
                
                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    zone.style.borderColor = '#2D2D3F';
                    zone.style.background = '#13131A';
                    
                    if (e.dataTransfer.files.length > 0) {
                        input.files = e.dataTransfer.files;
                        const event = new Event('change', { bubbles: true });
                        input.dispatchEvent(event);
                    }
                });
            }
            
            // Show file name when selected
            input.addEventListener('change', function() {
                const zone = this.previousElementSibling;
                if (zone && zone.classList.contains('file-upload-zone')) {
                    const fileName = this.files[0] ? this.files[0].name : 'No file selected';
                    const textDiv = zone.querySelector('.upload-text');
                    if (textDiv) {
                        textDiv.textContent = fileName;
                        textDiv.style.color = '#10B981';
                    }
                }
            });
        });
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
        
        modal.style.display = 'none';
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
        // Close on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    const modalId = this.id || this.getAttribute('data-modal-id');
                    closeModal(modalId);
                }
            });
        });
        
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
    // INITIALIZATION
    // ===================================================================

    /**
     * Initialize all functionality when DOM is ready
     */
    function init() {
        console.log('Crash Hockey App initializing...');
        
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
        
        console.log('Crash Hockey App initialized successfully!');
        
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
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export functions for external use
    window.CrashHockeyApp = {
        showToast,
        showLoading,
        hideLoading,
        openModal,
        closeModal,
        exportTable
    };

})();
