/**
 * Evaluation Framework Drag and Drop
 * Uses SortableJS for criteria and category reordering
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDragAndDrop);
    } else {
        initDragAndDrop();
    }

    function initDragAndDrop() {
        // Check if we're on the evaluation framework page
        const evalFramework = document.querySelector('.eval-framework-content');
        if (!evalFramework) return;

        // Check if Sortable library is loaded
        if (typeof Sortable === 'undefined') {
            console.error('SortableJS library not loaded');
            return;
        }

        initializeCriteriaLists();
        initializeCategoryList();
    }

    /**
     * Initialize drag-and-drop for criteria items within each category
     */
    function initializeCriteriaLists() {
        const criteriaLists = document.querySelectorAll('.criteria-list');
        
        criteriaLists.forEach(list => {
            const categoryElement = list.closest('.framework-category');
            const categoryId = categoryElement ? categoryElement.dataset.categoryId : null;
            
            if (!categoryId) {
                console.warn('Category ID not found for criteria list');
                return;
            }

            new Sortable(list, {
                animation: 150,
                handle: '.criteria-handle',
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    saveCriteriaOrder(list, categoryId);
                }
            });
        });
    }

    /**
     * Initialize drag-and-drop for categories
     */
    function initializeCategoryList() {
        const frameworkTree = document.querySelector('.framework-tree');
        
        if (!frameworkTree) return;

        new Sortable(frameworkTree, {
            animation: 150,
            handle: '.category-header',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                saveCategoryOrder(frameworkTree);
            }
        });
    }

    /**
     * Save new criteria order to backend
     */
    function saveCriteriaOrder(list, categoryId) {
        const items = list.querySelectorAll('.criteria-item');
        const order = [];
        
        items.forEach((item, index) => {
            const skillId = item.dataset.skillId;
            if (skillId) {
                order.push({
                    skill_id: parseInt(skillId, 10),
                    display_order: index
                });
            }
        });

        // Send to backend
        fetch('process_eval_framework.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'reorder_skills',
                category_id: categoryId,
                order: JSON.stringify(order),
                csrf_token: getCsrfToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Criteria order saved', 'success');
            } else {
                showToast(data.message || 'Failed to save order', 'error');
            }
        })
        .catch(error => {
            console.error('Error saving criteria order:', error);
            showToast('Failed to save criteria order', 'error');
        });
    }

    /**
     * Save new category order to backend
     */
    function saveCategoryOrder(tree) {
        const categories = tree.querySelectorAll('.framework-category');
        const order = [];
        
        categories.forEach((category, index) => {
            const categoryId = category.dataset.categoryId;
            if (categoryId) {
                order.push({
                    category_id: parseInt(categoryId, 10),
                    display_order: index
                });
            }
        });

        // Send to backend
        fetch('process_eval_framework.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'reorder_categories',
                order: JSON.stringify(order),
                csrf_token: getCsrfToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Category order saved', 'success');
            } else {
                showToast(data.message || 'Failed to save order', 'error');
            }
        })
        .catch(error => {
            console.error('Error saving category order:', error);
            showToast('Failed to save category order', 'error');
        });
    }

    /**
     * Get CSRF token from hidden input or meta tag
     */
    function getCsrfToken() {
        const tokenInput = document.querySelector('input[name="csrf_token"]');
        if (tokenInput) return tokenInput.value;
        
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) return tokenMeta.getAttribute('content');
        
        return '';
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
            font-family: Inter, sans-serif;
            font-size: 14px;
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

})();
