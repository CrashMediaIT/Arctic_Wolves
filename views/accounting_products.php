<!-- Accounting Products View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-box-open"></i> Products & Pricing
    </h1>
    <p class="page-description">Manage sessions, packages, and discounts</p>
</div>

<div class="products-content">
    <!-- Product Tabs -->
    <div class="product-tabs">
        <button class="tab-btn active" data-tab="sessions" data-action="switch-tab">
            <i class="fas fa-calendar-day"></i> Sessions
        </button>
        <button class="tab-btn" data-tab="packages" data-action="switch-tab">
            <i class="fas fa-box"></i> Packages
        </button>
        <button class="tab-btn" data-tab="discounts" data-action="switch-tab">
            <i class="fas fa-tags"></i> Discounts
        </button>
    </div>

    <!-- Sessions Tab -->
    <div class="tab-content active" id="sessions-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-day"></i> Session Types</h3>
                <button class="btn-primary" data-action="add" data-modal="add-session-type-modal"><i class="fas fa-plus"></i> Add Session Type</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <div class="product-card">
                        <div class="product-header">
                            <h4>Individual Training</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$75.00</div>
                        <div class="product-details">
                            <p><i class="fas fa-clock"></i> 60 minutes</p>
                            <p><i class="fas fa-user"></i> 1-on-1</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="1" data-type="session"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="1" data-type="session"><i class="fas fa-toggle-on"></i> Disable</button>
                        </div>
                    </div>

                    <div class="product-card">
                        <div class="product-header">
                            <h4>Group Training</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$45.00</div>
                        <div class="product-details">
                            <p><i class="fas fa-clock"></i> 90 minutes</p>
                            <p><i class="fas fa-users"></i> 4-8 players</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="2" data-type="session"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="2" data-type="session"><i class="fas fa-toggle-on"></i> Disable</button>
                        </div>
                    </div>

                    <div class="product-card">
                        <div class="product-header">
                            <h4>Skills Development</h4>
                            <span class="product-status active">Active</span>
                        </div>
                        <div class="product-price">$60.00</div>
                        <div class="product-details">
                            <p><i class="fas fa-clock"></i> 60 minutes</p>
                            <p><i class="fas fa-user"></i> 1-on-1</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn-secondary btn-small" data-action="edit" data-id="3" data-type="session"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small" data-action="toggle-status" data-id="3" data-type="session"><i class="fas fa-toggle-on"></i> Disable</button>
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
                <button class="btn-primary" data-action="add" data-modal="add-package-modal"><i class="fas fa-plus"></i> Create Package</button>
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
                            <button class="btn-secondary btn-small"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small"><i class="fas fa-toggle-on"></i> Disable</button>
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
                            <button class="btn-secondary btn-small"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary btn-small"><i class="fas fa-toggle-on"></i> Disable</button>
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
                <button class="btn-primary" data-action="add" data-modal="add-discount-modal"><i class="fas fa-plus"></i> Create Discount</button>
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
                                        <button class="btn-icon" title="Edit" data-action="edit" data-id="discount-1" data-type="discount"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon" title="Delete" data-action="delete" data-id="discount-1" data-type="discount"><i class="fas fa-trash"></i></button>
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
                                        <button class="btn-icon" title="Edit" data-action="edit" data-id="discount-2" data-type="discount"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon" title="Delete" data-action="delete" data-id="discount-2" data-type="discount"><i class="fas fa-trash"></i></button>
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
.product-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.product-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px;
    position: relative;
    transition: all 0.3s;
}

.product-card:hover {
    border-color: var(--neon);
    transform: translateY(-3px);
}

.product-card.featured {
    border: 2px solid var(--neon);
}

.product-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    color: #fff;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.product-header h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
}

.product-status {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.product-status.active {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.product-price {
    font-size: 32px;
    font-weight: 900;
    color: var(--neon);
    margin-bottom: 20px;
}

.product-details {
    margin-bottom: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

.product-details p {
    font-size: 14px;
    color: var(--text-dim);
    padding: 8px 0;
}

.product-details i {
    color: var(--neon);
    margin-right: 8px;
    width: 20px;
}

.product-actions {
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
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
                <button type="button" class="btn-secondary" onclick="closeModal('add-session-type-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Session Type</button>
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
                <button type="button" class="btn-secondary" onclick="closeModal('add-package-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Package</button>
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
                <button type="button" class="btn-secondary" onclick="closeModal('add-discount-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Discount</button>
            </div>
        </form>
    </div>
</div>
