<!-- Accounting Products View -->
<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Operation completed successfully!</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
<div class="error-alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
    <span style="color: #ef4444; font-weight: 600;"><?= htmlspecialchars($_GET['message'] ?? 'An error occurred') ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-box-open"></i> Products & Pricing
    </h1>
    <p class="page-description">Manage sessions, packages, and discount codes</p>
</div>

<div class="products-content">
    <!-- Product Stats -->
    <div class="product-stats">
        <div class="product-stat-card sessions">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <span class="stat-value">3</span>
                <span class="stat-label">Session Types</span>
            </div>
        </div>
        <div class="product-stat-card packages">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-info">
                <span class="stat-value">2</span>
                <span class="stat-label">Active Packages</span>
            </div>
        </div>
        <div class="product-stat-card discounts">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-info">
                <span class="stat-value">2</span>
                <span class="stat-label">Discount Codes</span>
            </div>
        </div>
        <div class="product-stat-card revenue">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-info">
                <span class="stat-value">$848</span>
                <span class="stat-label">Avg Package Price</span>
            </div>
        </div>
    </div>

    <!-- Product Tabs -->
    <div class="product-tabs">
        <button class="tab-btn active" data-tab="sessions" data-action="switch-tab">
            <i class="fas fa-calendar-day"></i> 
            <span>Sessions</span>
            <small>3 types</small>
        </button>
        <button class="tab-btn" data-tab="packages" data-action="switch-tab">
            <i class="fas fa-box"></i> 
            <span>Packages</span>
            <small>2 active</small>
        </button>
        <button class="tab-btn" data-tab="discounts" data-action="switch-tab">
            <i class="fas fa-tags"></i> 
            <span>Discounts</span>
            <small>2 codes</small>
        </button>
    </div>

    <!-- Sessions Tab -->
    <div class="tab-content active" id="sessions-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-day"></i> Session Types</h3>
                <button class="btn btn-primary" data-action="add" data-modal="add-session-type-modal"><i class="fas fa-plus"></i> Add Session Type</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <div class="product-card session-card">
                        <div class="product-type-badge individual"><i class="fas fa-user"></i></div>
                        <div class="product-header">
                            <h4>Individual Training</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$75<small>/session</small></div>
                        <div class="product-details">
                            <p><i class="fas fa-clock"></i> 60 minutes</p>
                            <p><i class="fas fa-user"></i> 1-on-1 training</p>
                            <p><i class="fas fa-chart-line"></i> Personalized focus</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="1" data-type="session" data-modal="edit-session-type-modal"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="1" data-type="session"><i class="fas fa-toggle-on"></i></button>
                        </div>
                    </div>

                    <div class="product-card session-card">
                        <div class="product-type-badge group"><i class="fas fa-users"></i></div>
                        <div class="product-header">
                            <h4>Group Training</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$45<small>/session</small></div>
                        <div class="product-details">
                            <p><i class="fas fa-clock"></i> 90 minutes</p>
                            <p><i class="fas fa-users"></i> 4-8 players</p>
                            <p><i class="fas fa-trophy"></i> Team dynamics</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="2" data-type="session" data-modal="edit-session-type-modal"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="2" data-type="session"><i class="fas fa-toggle-on"></i></button>
                        </div>
                    </div>

                    <div class="product-card session-card">
                        <div class="product-type-badge skills"><i class="fas fa-hockey-puck"></i></div>
                        <div class="product-header">
                            <h4>Skills Development</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$60<small>/session</small></div>
                        <div class="product-details">
                            <p><i class="fas fa-clock"></i> 60 minutes</p>
                            <p><i class="fas fa-user"></i> 1-on-1</p>
                            <p><i class="fas fa-bullseye"></i> Skill-specific</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="3" data-type="session" data-modal="edit-session-type-modal"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="3" data-type="session"><i class="fas fa-toggle-on"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Packages Tab -->
    <div class="tab-content" id="packages-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-box"></i> Training Packages</h3>
                <button class="btn btn-primary" data-action="add" data-modal="add-package-modal"><i class="fas fa-plus"></i> Create Package</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <div class="product-card featured">
                        <div class="product-badge">Popular</div>
                        <div class="product-header">
                            <h4>Starter Package</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$299.00</div>
                        <div class="product-details">
                            <p><i class="fas fa-calendar-check"></i> 5 sessions</p>
                            <p><i class="fas fa-clock"></i> Valid 3 months</p>
                            <p><i class="fas fa-tag"></i> Save 20%</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="pkg-1" data-type="package" data-modal="edit-package-modal"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="pkg-1" data-type="package"><i class="fas fa-toggle-on"></i> Disable</button>
                        </div>
                    </div>

                    <div class="product-card featured">
                        <div class="product-badge">Best Value</div>
                        <div class="product-header">
                            <h4>Pro Package</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$549.00</div>
                        <div class="product-details">
                            <p><i class="fas fa-calendar-check"></i> 10 sessions</p>
                            <p><i class="fas fa-clock"></i> Valid 6 months</p>
                            <p><i class="fas fa-tag"></i> Save 27%</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="pkg-2" data-type="package" data-modal="edit-package-modal"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="pkg-2" data-type="package"><i class="fas fa-toggle-on"></i> Disable</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Discounts Tab -->
    <div class="tab-content" id="discounts-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Discount Codes</h3>
                <button class="btn btn-primary" data-action="add" data-modal="add-discount-modal"><i class="fas fa-plus"></i> Create Discount</button>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Valid Until</th>
                                <th>Uses</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>WINTER2024</strong></td>
                                <td>Percentage</td>
                                <td>15%</td>
                                <td>Mar 31, 2024</td>
                                <td>12 / 100</td>
                                <td><span class="status-badge active">Active</span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="Edit" data-action="edit" data-id="discount-1" data-type="discount" data-modal="edit-discount-modal"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon" title="Delete" data-action="delete" data-id="discount-1" data-type="discount" data-action-url="process_admin_action.php"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>NEWCLIENT50</strong></td>
                                <td>Fixed Amount</td>
                                <td>$50.00</td>
                                <td>Dec 31, 2024</td>
                                <td>5 / ∞</td>
                                <td><span class="status-badge active">Active</span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="Edit" data-action="edit" data-id="discount-2" data-type="discount" data-modal="edit-discount-modal"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon" title="Delete" data-action="delete" data-id="discount-2" data-type="discount" data-action-url="process_admin_action.php"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Product Stats */
.product-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.product-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all 0.3s ease;
}

.product-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.product-stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.product-stat-card.sessions .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.product-stat-card.packages .stat-icon { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }
.product-stat-card.discounts .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.product-stat-card.revenue .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

.product-stat-card .stat-info { flex: 1; }

.product-stat-card .stat-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    display: block;
    margin-bottom: 4px;
}

.product-stat-card .stat-label {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Enhanced Tabs */
.product-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 28px;
    background: var(--bg-card);
    padding: 8px;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.tab-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 16px 32px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    flex: 1;
    color: var(--text-dim);
}

.tab-btn i {
    font-size: 20px;
    margin-bottom: 4px;
}

.tab-btn span {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
}

.tab-btn small {
    font-size: 11px;
    color: var(--text-dim);
}

.tab-btn:hover {
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary);
}

.tab-btn.active {
    background: var(--bg-main);
    border-color: var(--primary);
    color: var(--primary);
}

.tab-btn.active i {
    color: var(--primary);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
}

.product-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    position: relative;
    transition: all 0.3s;
}

.product-card:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
}

.product-card.featured {
    border: 2px solid var(--primary);
}

/* Product type badge */
.product-type-badge {
    position: absolute;
    top: -12px;
    left: 24px;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #fff;
}

.product-type-badge.individual { background: linear-gradient(135deg, #3B82F6, #2563EB); }
.product-type-badge.group { background: linear-gradient(135deg, #10b981, #059669); }
.product-type-badge.skills { background: linear-gradient(135deg, #8B5CF6, #6B46C1); }

.product-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: #fff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
    margin-top: 8px;
}

.product-header h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
}

.product-status {
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-status.active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.product-price {
    font-size: 36px;
    font-weight: 900;
    color: var(--primary);
    margin-bottom: 20px;
    line-height: 1;
}

.product-price small {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
    margin-left: 4px;
}

.product-details {
    margin-bottom: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.product-details p {
    font-size: 14px;
    color: var(--text-dim);
    padding: 8px 0;
}

.product-details i {
    color: var(--primary);
    margin-right: 10px;
    width: 20px;
}

.product-actions {
    display: flex;
    gap: 10px;
    padding-top: 18px;
    border-top: 1px solid var(--border);
}

@media (max-width: 768px) {
    .product-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .product-tabs {
        flex-direction: column;
    }
    
    .tab-btn {
        flex-direction: row;
        justify-content: center;
        gap: 12px;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .product-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Add Session Type Modal -->
<div id="add-session-type-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Session Type</h2>
            <button class="modal-close" onclick="closeModal('add-session-type-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_session_type">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Session Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input type="number" name="price" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Duration (minutes) *</label>
                        <input type="number" name="duration" class="form-input" min="15" step="15" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Max Participants</label>
                        <input type="number" name="max_participants" class="form-input" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-session-type-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Session Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Package Modal -->
<div id="add-package-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Create Package</h2>
            <button class="modal-close" onclick="closeModal('add-package-modal')">&times;</button>
        </div>
        <form method="POST" action="process_packages.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Package Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input type="number" name="price" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Number of Sessions *</label>
                        <input type="number" name="session_count" class="form-input" min="1" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Validity (days)</label>
                        <input type="number" name="validity_days" class="form-input" min="1" placeholder="e.g., 90">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="featured" value="1"> Featured Package
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-package-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Package</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Discount Modal -->
<div id="add-discount-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Create Discount</h2>
            <button class="modal-close" onclick="closeModal('add-discount-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_discount">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Discount Code *</label>
                    <input type="text" name="code" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Type *</label>
                        <select name="type" class="form-input" required>
                            <option value="">Select Type</option>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Value *</label>
                        <input type="number" name="value" class="form-input" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Usage Limit</label>
                        <input type="number" name="usage_limit" class="form-input" min="1" placeholder="Leave empty for unlimited">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-discount-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Discount</button>
            </div>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    document.querySelectorAll('.tab-btn[data-action="switch-tab"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var tabName = this.getAttribute('data-tab');
            
            // Remove active from all tabs and buttons
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(tabBtn) {
                tabBtn.classList.remove('active');
            });
            
            // Activate selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            this.classList.add('active');
        });
    });
    
    // Handle toggle-status buttons
    document.querySelectorAll('[data-action="toggle-status"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var itemId = this.getAttribute('data-id');
            var itemType = this.getAttribute('data-type');
            var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
            
            if (confirm('Are you sure you want to toggle the status of this ' + itemType + '?')) {
                fetch('process_admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=toggle_' + itemType + '_status&id=' + encodeURIComponent(itemId) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to toggle status'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        });
    });
    
    // Handle edit buttons for modals
    document.querySelectorAll('[data-action="edit"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var itemId = this.getAttribute('data-id');
            var modal = document.getElementById(modalId);
            
            if (modal) {
                // Populate modal with item data (would need AJAX call to get data)
                modal.classList.add('active');
            }
        });
    });
    
    // Handle add buttons for modals
    document.querySelectorAll('[data-action="add"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        });
    });
});

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}
</script>
