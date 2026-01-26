<!-- Accounting Products View -->
<?php
// Fetch session types from database
try {
    $sessionTypesStmt = $pdo->query("SELECT * FROM session_types ORDER BY name");
    $sessionTypes = $sessionTypesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Session types fetch error: " . $e->getMessage());
    $sessionTypes = [];
}

// Fetch packages from database
try {
    $packagesStmt = $pdo->query("SELECT * FROM packages ORDER BY name");
    $packages = $packagesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Packages fetch error: " . $e->getMessage());
    $packages = [];
}

// Fetch discount codes from database
try {
    $discountsStmt = $pdo->query("SELECT * FROM discount_codes ORDER BY code");
    $discounts = $discountsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Discounts fetch error: " . $e->getMessage());
    $discounts = [];
}

// Calculate stats
$sessionCount = count($sessionTypes);
$packageCount = count(array_filter($packages, function($p) { return !empty($p['is_active']); }));
$discountCount = count(array_filter($discounts, function($d) { return !empty($d['is_active']); }));
$avgPackagePrice = $packageCount > 0 ? array_sum(array_column($packages, 'price')) / count($packages) : 0;

// Handle tab from URL
$activeTab = $_GET['tab'] ?? 'sessions';
?>

<?php if (isset($_GET['status']) && in_array($_GET['status'], ['success', 'added'])): ?>
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
                <span class="stat-value"><?= $sessionCount ?></span>
                <span class="stat-label">Session Types</span>
            </div>
        </div>
        <div class="product-stat-card packages">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $packageCount ?></span>
                <span class="stat-label">Active Packages</span>
            </div>
        </div>
        <div class="product-stat-card discounts">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $discountCount ?></span>
                <span class="stat-label">Discount Codes</span>
            </div>
        </div>
        <div class="product-stat-card revenue">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($avgPackagePrice, 0) ?></span>
                <span class="stat-label">Avg Package Price</span>
            </div>
        </div>
    </div>

    <!-- Product Tabs -->
    <div class="product-tabs">
        <button class="tab-btn <?= $activeTab === 'sessions' ? 'active' : '' ?>" data-tab="sessions" data-action="switch-tab">
            <i class="fas fa-calendar-day"></i> 
            <span>Sessions</span>
            <small><?= $sessionCount ?> types</small>
        </button>
        <button class="tab-btn <?= $activeTab === 'packages' ? 'active' : '' ?>" data-tab="packages" data-action="switch-tab">
            <i class="fas fa-box"></i> 
            <span>Packages</span>
            <small><?= $packageCount ?> active</small>
        </button>
        <button class="tab-btn <?= $activeTab === 'discounts' ? 'active' : '' ?>" data-tab="discounts" data-action="switch-tab">
            <i class="fas fa-tags"></i> 
            <span>Discounts</span>
            <small><?= $discountCount ?> codes</small>
        </button>
    </div>

    <!-- Sessions Tab -->
    <div class="tab-content <?= $activeTab === 'sessions' ? 'active' : '' ?>" id="sessions-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-day"></i> Session Types</h3>
                <button class="btn btn-primary" data-action="add" data-modal="add-session-type-modal"><i class="fas fa-plus"></i> Add Session Type</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <?php if (empty($sessionTypes)): ?>
                        <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                            <i class="fas fa-calendar-day" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-dim);">No session types yet. Click "Add Session Type" to create one.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sessionTypes as $session): 
                            $isActive = isset($session['is_active']) ? $session['is_active'] : 1;
                        ?>
                        <div class="product-card session-card">
                            <div class="product-type-badge individual"><i class="fas fa-calendar-check"></i></div>
                            <div class="product-header">
                                <h4><?= htmlspecialchars($session['name']) ?></h4>
                                <span class="product-status <?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                            </div>
                            <div class="product-price">$<?= number_format($session['default_price'] ?? 0, 2) ?><small>/session</small></div>
                            <div class="product-details">
                                <p><i class="fas fa-clock"></i> <?= $session['duration_minutes'] ?? 60 ?> minutes</p>
                                <?php if (!empty($session['description'])): ?>
                                <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($session['description'], 0, 50)) ?><?= strlen($session['description']) > 50 ? '...' : '' ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="product-actions">
                                <button class="btn-action" data-action="edit" data-id="<?= $session['id'] ?>" data-type="session" data-modal="edit-session-type-modal" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-action <?= $isActive ? '' : 'active' ?>" data-action="toggle-status" data-id="<?= $session['id'] ?>" data-type="session" title="<?= $isActive ? 'Disable' : 'Enable' ?>"><i class="fas fa-toggle-<?= $isActive ? 'on' : 'off' ?>"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Packages Tab -->
    <div class="tab-content <?= $activeTab === 'packages' ? 'active' : '' ?>" id="packages-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-box"></i> Training Packages</h3>
                <button class="btn btn-primary" data-action="add" data-modal="add-package-modal"><i class="fas fa-plus"></i> Create Package</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <?php if (empty($packages)): ?>
                        <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                            <i class="fas fa-box" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-dim);">No packages yet. Click "Create Package" to add one.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($packages as $package): 
                            $isActive = !empty($package['is_active']);
                        ?>
                        <div class="product-card <?= $isActive ? 'featured' : '' ?>">
                            <?php if ($isActive): ?><div class="product-badge">Active</div><?php endif; ?>
                            <div class="product-header">
                                <h4><?= htmlspecialchars($package['name']) ?></h4>
                                <span class="product-status <?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                            </div>
                            <div class="product-price">$<?= number_format($package['price'] ?? 0, 2) ?></div>
                            <div class="product-details">
                                <p><i class="fas fa-calendar-check"></i> <?= $package['credits'] ?? 0 ?> sessions</p>
                                <?php if (!empty($package['valid_days'])): ?>
                                <p><i class="fas fa-clock"></i> Valid <?= $package['valid_days'] ?> days</p>
                                <?php endif; ?>
                                <?php if (!empty($package['description'])): ?>
                                <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($package['description'], 0, 40)) ?><?= strlen($package['description']) > 40 ? '...' : '' ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="product-actions">
                                <button class="btn-action" data-action="edit" data-id="<?= $package['id'] ?>" data-type="package" data-modal="edit-package-modal" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-action <?= $isActive ? '' : 'active' ?>" data-action="toggle-status" data-id="<?= $package['id'] ?>" data-type="package" title="<?= $isActive ? 'Disable' : 'Enable' ?>"><i class="fas fa-toggle-<?= $isActive ? 'on' : 'off' ?>"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Discounts Tab -->
    <div class="tab-content <?= $activeTab === 'discounts' ? 'active' : '' ?>" id="discounts-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Discount Codes</h3>
                <button class="btn btn-primary" data-action="add" data-modal="add-discount-modal"><i class="fas fa-plus"></i> Create Discount</button>
            </div>
            <div class="card-body">
                <?php if (empty($discounts)): ?>
                    <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-tags" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                        <p style="color: var(--text-dim);">No discount codes yet. Click "Create Discount" to add one.</p>
                    </div>
                <?php else: ?>
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
                            <?php foreach ($discounts as $discount): 
                                $isActive = !empty($discount['is_active']);
                                $discountType = $discount['discount_type'] ?? $discount['type'] ?? 'percentage';
                                $discountValue = $discount['discount_value'] ?? $discount['value'] ?? 0;
                                $validUntil = $discount['valid_until'] ?? $discount['expiry_date'] ?? null;
                                $maxUses = $discount['max_uses'] ?? $discount['usage_limit'] ?? null;
                                $currentUses = $discount['times_used'] ?? $discount['used_count'] ?? 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($discount['code']) ?></strong></td>
                                <td><?= ucfirst($discountType) ?></td>
                                <td><?= $discountType === 'percentage' ? $discountValue . '%' : '$' . number_format($discountValue, 2) ?></td>
                                <td><?= $validUntil ? date('M d, Y', strtotime($validUntil)) : 'No expiry' ?></td>
                                <td><?= $currentUses ?> / <?= $maxUses ?? '∞' ?></td>
                                <td><span class="status-badge <?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action" data-action="edit" data-id="<?= $discount['id'] ?>" data-type="discount" data-modal="edit-discount-modal" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button class="btn-action danger" data-action="delete" data-id="<?= $discount['id'] ?>" data-type="discount" title="Delete"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
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

.product-status.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
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

/* Consistent action buttons */
.btn-action {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-dim);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-action:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.btn-action.danger:hover {
    background: #ef4444;
    border-color: #ef4444;
}

.btn-action.active {
    background: rgba(16, 185, 129, 0.15);
    border-color: #10b981;
    color: #10b981;
}

.btn-action i {
    font-size: 14px;
}

/* Table action buttons */
.table-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.table-actions .btn-action {
    width: 32px;
    height: 32px;
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
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    
    // Show notification helper
    function showNotification(message, type) {
        var existing = document.querySelector('.notification-widget');
        if (existing) existing.remove();
        
        var div = document.createElement('div');
        div.className = 'notification-widget';
        div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease;';
        if (type === 'success') {
            div.style.background = 'rgba(16, 185, 129, 0.95)';
            div.style.color = '#fff';
        } else {
            div.style.background = 'rgba(239, 68, 68, 0.95)';
            div.style.color = '#fff';
        }
        div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message + '<button onclick="this.parentElement.remove()" style="margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>';
        document.body.appendChild(div);
        setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
    }
    
    // Tab switching functionality
    document.querySelectorAll('.tab-btn[data-action="switch-tab"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var tabName = this.getAttribute('data-tab');
            
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(tabBtn) {
                tabBtn.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
            this.classList.add('active');
        });
    });
    
    // Handle toggle-status buttons - different endpoints for different types
    document.querySelectorAll('[data-action="toggle-status"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var itemId = this.getAttribute('data-id');
            var itemType = this.getAttribute('data-type');
            var button = this;
            
            if (!confirm('Are you sure you want to toggle the status of this ' + itemType + '?')) return;
            
            // Determine the correct endpoint
            var endpoint = 'process_admin_action.php';
            var action = 'toggle_' + itemType + '_status';
            
            if (itemType === 'package') {
                endpoint = 'process_packages.php';
                action = 'toggle_status';
            }
            
            fetch(endpoint, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=' + action + '&id=' + encodeURIComponent(itemId) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Status updated successfully!', 'success');
                    // Toggle button icon/text
                    var icon = button.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-toggle-on');
                        icon.classList.toggle('fa-toggle-off');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to toggle status'), 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        });
    });
    
    // Handle delete buttons with confirmation
    document.querySelectorAll('[data-action="delete"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var itemId = this.getAttribute('data-id');
            var itemType = this.getAttribute('data-type');
            
            if (!confirm('Are you sure you want to delete this ' + itemType + '? This cannot be undone.')) return;
            
            var endpoint = 'process_admin_action.php';
            var bodyData = 'action=delete_' + itemType + '&csrf_token=' + encodeURIComponent(csrfToken);
            
            if (itemType === 'discount') {
                bodyData = 'action=delete_discount&discount_id=' + encodeURIComponent(itemId) + '&csrf_token=' + encodeURIComponent(csrfToken);
            } else {
                bodyData += '&id=' + encodeURIComponent(itemId);
            }
            
            fetch(endpoint, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: bodyData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(itemType.charAt(0).toUpperCase() + itemType.slice(1) + ' deleted successfully!', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to delete'), 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
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
                // Set item ID in hidden field if exists
                var idField = modal.querySelector('input[name$="_id"]');
                if (idField) idField.value = itemId;
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
    
    // Convert forms to AJAX submissions with success widget
    document.querySelectorAll('.modal form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(form);
            var modal = form.closest('.modal');
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                // Get content type to determine how to parse
                var contentType = response.headers.get('content-type');
                
                // Check for successful response
                if (!response.ok) {
                    // Try to parse error message from JSON response
                    if (contentType && contentType.includes('application/json')) {
                        return response.json().then(function(data) {
                            throw new Error(data.message || data.error || 'Request failed');
                        });
                    }
                    throw new Error('Request failed with status: ' + response.status);
                }
                
                // If JSON response, parse it
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                
                // If we got a redirect or HTML response, the server didn't return JSON
                // This means the AJAX handler wasn't triggered properly
                throw new Error('Server did not return JSON response. Form may not have been processed correctly.');
            })
            .then(data => {
                if (submitBtn) {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
                
                if (data && data.success) {
                    showNotification(data.message || 'Operation completed successfully!', 'success');
                    if (modal) closeModal(modal.id);
                    
                    // Determine which tab to stay on based on the modal ID
                    var currentTab = 'sessions';
                    if (modal && modal.id.includes('package')) {
                        currentTab = 'packages';
                    } else if (modal && modal.id.includes('discount')) {
                        currentTab = 'discounts';
                    } else if (modal && modal.id.includes('session')) {
                        currentTab = 'sessions';
                    }
                    
                    // Reload with tab parameter to stay on the same tab
                    setTimeout(function() { 
                        window.location.href = 'dashboard.php?page=products&tab=' + currentTab + '&status=success';
                    }, 1500);
                } else {
                    showNotification('Error: ' + (data && data.message ? data.message : 'Operation failed'), 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                if (submitBtn) {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
                showNotification(error.message || 'An error occurred. Please try again.', 'error');
            });
        });
    });
});

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        // Reset form if exists
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}
</script>
