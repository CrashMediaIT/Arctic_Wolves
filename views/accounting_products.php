<!-- Accounting Products View - Enhanced Sessions, Packages, and Discounts -->
<?php
// Fetch session templates from database
try {
    $templatesStmt = $pdo->query("
        SELECT tst.*, 
               st.name as session_type_name,
               CONCAT(u.first_name, ' ', u.last_name) as coach_name,
               l.name as location_name,
               pp.name as practice_plan_name
        FROM training_session_templates tst
        LEFT JOIN session_types st ON tst.session_type_id = st.id
        LEFT JOIN users u ON tst.coach_id = u.id
        LEFT JOIN locations l ON tst.location_id = l.id
        LEFT JOIN practice_plans pp ON tst.practice_plan_id = pp.id
        ORDER BY tst.created_at DESC
    ");
    $sessionTemplates = $templatesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Session templates fetch error: " . $e->getMessage());
    $sessionTemplates = [];
}

// Fetch session types from database (fallback for templates that don't exist yet)
try {
    $sessionTypesStmt = $pdo->query("SELECT * FROM session_types ORDER BY name");
    $sessionTypes = $sessionTypesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Session types fetch error: " . $e->getMessage());
    $sessionTypes = [];
}

// Fetch coaches from database
try {
    $coachesStmt = $pdo->query("
        SELECT id, first_name, last_name, role 
        FROM users 
        WHERE role IN ('coach', 'health_coach', 'admin') AND is_active = 1
        ORDER BY last_name, first_name
    ");
    $coaches = $coachesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Coaches fetch error: " . $e->getMessage());
    $coaches = [];
}

// Fetch teams from database
try {
    $teamsStmt = $pdo->query("SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name");
    $teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Teams fetch error: " . $e->getMessage());
    $teams = [];
}

// Fetch locations from database
try {
    $locationsStmt = $pdo->query("SELECT id, name, city FROM locations ORDER BY city, name");
    $locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Locations fetch error: " . $e->getMessage());
    $locations = [];
}

// Fetch practice plans from database
try {
    $plansStmt = $pdo->query("SELECT id, name FROM practice_plans ORDER BY name");
    $practicePlans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Practice plans fetch error: " . $e->getMessage());
    $practicePlans = [];
}

// Fetch skill categories (eval_skills table)
try {
    $skillsStmt = $pdo->query("
        SELECT es.id, es.name, es.description, ec.name as category_name 
        FROM eval_skills es 
        LEFT JOIN eval_categories ec ON es.category_id = ec.id 
        ORDER BY ec.name, es.name
    ");
    $skills = $skillsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Skills fetch error: " . $e->getMessage());
    $skills = [];
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
$sessionCount = count($sessionTemplates) > 0 ? count($sessionTemplates) : count($sessionTypes);
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
    <p class="page-description">Manage training sessions, packages, and discount codes</p>
</div>

<div class="products-content">
    <!-- Product Stats -->
    <div class="product-stats">
        <div class="product-stat-card sessions">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $sessionCount ?></span>
                <span class="stat-label">Sessions</span>
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
                <h3><i class="fas fa-calendar-day"></i> Training Sessions & Templates</h3>
                <button class="btn btn-primary" data-action="add" data-modal="add-session-modal"><i class="fas fa-plus"></i> Create Session</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <?php 
                    // Display session templates if they exist, otherwise fall back to session types
                    $displaySessions = count($sessionTemplates) > 0 ? $sessionTemplates : $sessionTypes;
                    
                    if (empty($displaySessions)): ?>
                        <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                            <i class="fas fa-calendar-day" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-dim);">No sessions yet. Click "Create Session" to add one.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($displaySessions as $session): 
                            $isActive = isset($session['is_active']) ? $session['is_active'] : 1;
                            $isTemplate = isset($session['is_template']) ? $session['is_template'] : (isset($session['template_id']) || !isset($session['default_price']));
                            $sessionType = $session['session_type'] ?? $session['session_type_category'] ?? 'on_ice';
                            $showOnLanding = isset($session['show_on_landing']) ? $session['show_on_landing'] : 0;
                            $price = $session['price'] ?? $session['default_price'] ?? 0;
                            $duration = $session['duration_minutes'] ?? 60;
                            $maxParticipants = $session['max_participants'] ?? null;
                        ?>
                        <div class="product-card session-card">
                            <div class="product-type-badge <?= $sessionType ?>">
                                <i class="fas fa-<?= $sessionType === 'on_ice' ? 'skating' : ($sessionType === 'nutrition' ? 'utensils' : ($sessionType === 'off_ice' ? 'dumbbell' : 'calendar-check')) ?>"></i>
                            </div>
                            <?php if ($showOnLanding): ?>
                            <div class="product-badge landing">Public</div>
                            <?php endif; ?>
                            <div class="product-header">
                                <h4><?= htmlspecialchars($session['name']) ?></h4>
                                <span class="product-status <?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                            </div>
                            <div class="product-price">$<?= number_format($price, 2) ?><small>/session</small></div>
                            <div class="product-details">
                                <p><i class="fas fa-clock"></i> <?= $duration ?> minutes</p>
                                <?php if ($maxParticipants): ?>
                                <p><i class="fas fa-users"></i> Max <?= $maxParticipants ?> participants</p>
                                <?php endif; ?>
                                <?php if (!empty($session['coach_name'])): ?>
                                <p><i class="fas fa-user-tie"></i> <?= htmlspecialchars($session['coach_name']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($session['description'])): ?>
                                <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($session['description'], 0, 50)) ?><?= strlen($session['description']) > 50 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <p class="session-type-label">
                                    <span class="type-badge <?= $sessionType ?>"><?= ucfirst(str_replace('_', ' ', $sessionType)) ?></span>
                                </p>
                            </div>
                            <div class="product-actions">
                                <button class="btn-action" data-action="edit" data-id="<?= $session['id'] ?>" data-type="session" data-modal="edit-session-modal" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-action" data-action="manage-dates" data-id="<?= $session['id'] ?>" data-type="session" data-modal="manage-dates-modal" title="Manage Dates"><i class="fas fa-calendar-plus"></i></button>
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
                            $showOnLanding = isset($package['show_on_landing']) ? $package['show_on_landing'] : 0;
                            $packageType = $package['package_type'] ?? 'sessions_only';
                            $storeCredit = $package['store_credit'] ?? 0;
                        ?>
                        <div class="product-card <?= $isActive ? 'featured' : '' ?>">
                            <?php if ($isActive): ?><div class="product-badge">Active</div><?php endif; ?>
                            <?php if ($showOnLanding): ?><div class="product-badge landing" style="right: 80px;">Public</div><?php endif; ?>
                            <div class="product-header">
                                <h4><?= htmlspecialchars($package['name']) ?></h4>
                                <span class="product-status <?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                            </div>
                            <div class="product-price">$<?= number_format($package['price'] ?? 0, 2) ?></div>
                            <div class="product-details">
                                <?php if ($package['credits'] ?? 0 > 0): ?>
                                <p><i class="fas fa-calendar-check"></i> <?= $package['credits'] ?> sessions</p>
                                <?php endif; ?>
                                <?php if ($storeCredit > 0): ?>
                                <p><i class="fas fa-wallet"></i> $<?= number_format($storeCredit, 2) ?> store credit</p>
                                <?php endif; ?>
                                <?php if (!empty($package['valid_days'])): ?>
                                <p><i class="fas fa-clock"></i> Valid <?= $package['valid_days'] ?> days</p>
                                <?php endif; ?>
                                <?php if (!empty($package['description'])): ?>
                                <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($package['description'], 0, 40)) ?><?= strlen($package['description']) > 40 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <p class="package-type-label">
                                    <span class="type-badge <?= $packageType ?>"><?= ucfirst(str_replace('_', ' ', $packageType)) ?></span>
                                </p>
                            </div>
                            <div class="product-actions">
                                <button class="btn-action" data-action="edit" data-id="<?= $package['id'] ?>" data-type="package" data-modal="edit-package-modal" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-action" data-action="manage-sessions" data-id="<?= $package['id'] ?>" data-type="package" data-modal="manage-package-sessions-modal" title="Manage Sessions"><i class="fas fa-list-check"></i></button>
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
                                <th>Description</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Valid Until</th>
                                <th>Uses</th>
                                <th>Auto-Generate</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($discounts as $discount): 
                                $isActive = !empty($discount['is_active']);
                                $discountType = $discount['discount_type'] ?? $discount['type'] ?? 'percentage';
                                $discountValue = $discount['discount_value'] ?? $discount['value'] ?? 0;
                                $storeCredit = $discount['store_credit_value'] ?? 0;
                                $validUntil = $discount['valid_until'] ?? $discount['expiry_date'] ?? null;
                                $maxUses = $discount['max_uses'] ?? $discount['usage_limit'] ?? null;
                                $currentUses = $discount['times_used'] ?? $discount['used_count'] ?? 0;
                                $autoGenerate = $discount['auto_generate_type'] ?? 'none';
                                $description = $discount['description'] ?? '';
                            ?>
                            <tr>
                                <td><strong style="font-family: monospace; background: var(--bg-main); padding: 4px 8px; border-radius: 4px;"><?= htmlspecialchars($discount['code']) ?></strong></td>
                                <td><?= htmlspecialchars($description) ?: '-' ?></td>
                                <td>
                                    <span class="discount-type-badge <?= $discountType ?>">
                                        <?= $discountType === 'percentage' ? '% Off' : ($discountType === 'store_credit' ? 'Credit' : '$ Off') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($discountType === 'percentage'): ?>
                                        <?= $discountValue ?>%
                                    <?php elseif ($discountType === 'store_credit'): ?>
                                        $<?= number_format($storeCredit > 0 ? $storeCredit : $discountValue, 2) ?> credit
                                    <?php else: ?>
                                        $<?= number_format($discountValue, 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $validUntil ? date('M d, Y', strtotime($validUntil)) : 'No expiry' ?></td>
                                <td><?= $currentUses ?> / <?= $maxUses ?? '∞' ?></td>
                                <td>
                                    <?php if ($autoGenerate !== 'none'): ?>
                                    <span class="auto-badge"><?= ucfirst(str_replace('_', ' ', $autoGenerate)) ?></span>
                                    <?php else: ?>
                                    <span style="color: var(--text-dim);">Manual</span>
                                    <?php endif; ?>
                                </td>
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

.product-type-badge.on_ice { background: linear-gradient(135deg, #3B82F6, #2563EB); }
.product-type-badge.off_ice { background: linear-gradient(135deg, #10b981, #059669); }
.product-type-badge.nutrition { background: linear-gradient(135deg, #f59e0b, #d97706); }
.product-type-badge.meeting { background: linear-gradient(135deg, #8B5CF6, #6B46C1); }
.product-type-badge.other { background: linear-gradient(135deg, #6b7280, #4b5563); }

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

.product-badge.landing {
    background: linear-gradient(135deg, #10b981, #059669);
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

.type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.type-badge.on_ice { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.type-badge.off_ice { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.type-badge.nutrition { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.type-badge.meeting { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }
.type-badge.sessions_only { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.type-badge.credit_only { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.type-badge.mixed { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }

.discount-type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
}

.discount-type-badge.percentage { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.discount-type-badge.fixed { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.discount-type-badge.store_credit { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

.auto-badge {
    background: rgba(139, 92, 246, 0.15);
    color: #8B5CF6;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
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

/* Multi-select checkboxes */
.skill-selector {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
    max-height: 200px;
    overflow-y: auto;
    padding: 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.skill-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--bg-card);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.skill-checkbox:hover {
    background: rgba(107, 70, 193, 0.1);
}

.skill-checkbox input {
    accent-color: var(--primary);
}

/* Session date list */
.session-dates-list {
    max-height: 300px;
    overflow-y: auto;
}

.session-date-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 10px;
}

.session-date-item .date-info {
    flex: 1;
}

.session-date-item .date-actions {
    display: flex;
    gap: 8px;
}

/* Session search for packages */
.session-search-container {
    margin-bottom: 16px;
}

.session-search-input {
    width: 100%;
    padding: 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    font-size: 14px;
}

.session-search-input:focus {
    outline: none;
    border-color: var(--primary);
}

.session-search-results {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
}

.session-search-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    border-bottom: 1px solid var(--border);
    transition: all 0.2s;
}

.session-search-item:hover {
    background: rgba(107, 70, 193, 0.1);
}

.session-search-item:last-child {
    border-bottom: none;
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

<!-- Add Session Modal -->
<div id="add-session-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-calendar-plus"></i> Create Training Session</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-session-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php" id="add-session-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_training_session">
            
            <div class="modal-body">
                <!-- Basic Info Section -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Session Name *</label>
                            <input type="text" name="name" class="form-input" required placeholder="e.g., Power Skating Clinic">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Session Type *</label>
                            <select name="session_type" class="form-input" required>
                                <option value="on_ice">On Ice</option>
                                <option value="off_ice">Off Ice / Workout</option>
                                <option value="nutrition">Nutrition Meeting</option>
                                <option value="meeting">General Meeting</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="What will athletes learn in this session?"></textarea>
                    </div>
                </div>
                
                <!-- Pricing & Duration Section -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-dollar-sign"></i> Pricing & Duration</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Price ($) *</label>
                            <input type="number" name="price" class="form-input" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration (minutes) *</label>
                            <input type="number" name="duration" class="form-input" min="15" step="15" value="60" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Participants</label>
                            <input type="number" name="max_participants" class="form-input" min="1" placeholder="Leave blank for unlimited">
                        </div>
                    </div>
                </div>
                
                <!-- Assignment Section -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-user-tie"></i> Assignments</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Coach</label>
                            <select name="coach_id" class="form-input">
                                <option value="">Select Coach (Optional)</option>
                                <?php foreach ($coaches as $coach): ?>
                                <option value="<?= $coach['id'] ?>"><?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?> (<?= ucfirst($coach['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <select name="location_id" class="form-input">
                                <option value="">Select Location (Optional)</option>
                                <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['name']) ?><?= $location['city'] ? ' - ' . htmlspecialchars($location['city']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Practice Plan</label>
                            <select name="practice_plan_id" class="form-input">
                                <option value="">Select Practice Plan (Optional)</option>
                                <?php foreach ($practicePlans as $plan): ?>
                                <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Session Type Category</label>
                            <select name="session_type_id" class="form-input">
                                <option value="">Select Category (Optional)</option>
                                <?php foreach ($sessionTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Skills Section -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-star"></i> Skill Types (Focus Areas)</h4>
                    <p class="form-help-text" style="margin-bottom: 12px; color: var(--text-dim); font-size: 13px;">Select the skills that will be worked on during this session</p>
                    
                    <div class="skill-selector">
                        <?php if (!empty($skills)): ?>
                            <?php foreach ($skills as $skill): ?>
                            <label class="skill-checkbox">
                                <input type="checkbox" name="skill_ids[]" value="<?= $skill['id'] ?>">
                                <span><?= htmlspecialchars($skill['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-dim); grid-column: 1/-1;">No skills defined yet. Create skills in Categories management.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Session Dates Section -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-calendar-alt"></i> Session Dates</h4>
                    <p class="form-help-text" style="margin-bottom: 12px; color: var(--text-dim); font-size: 13px;">Add one or more dates when this session will be held</p>
                    
                    <div id="session-dates-container">
                        <div class="session-date-input" data-index="0">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Date & Time</label>
                                    <input type="datetime-local" name="session_dates[0][datetime]" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Team (Optional)</label>
                                    <select name="session_dates[0][team_id]" class="form-input">
                                        <option value="">All Athletes</option>
                                        <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 0 0 auto; align-self: end;">
                                    <button type="button" class="btn-action remove-date" onclick="removeSessionDate(this)" style="display: none;"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addSessionDate()" style="margin-top: 12px;"><i class="fas fa-plus"></i> Add Another Date</button>
                </div>
                
                <!-- Display Options -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-eye"></i> Display Options</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-input">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 10px; margin-top: 30px;">
                                <input type="checkbox" name="show_on_landing" value="1">
                                <span>Show on Landing Page (Public Sessions Tab)</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 10px; margin-top: 30px;">
                                <input type="checkbox" name="is_template" value="1">
                                <span>Save as Template (Reusable)</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-session-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Session</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Package Modal -->
<div id="add-package-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-box"></i> Create Package</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-package-modal')">&times;</button>
        </div>
        <form method="POST" action="process_packages.php" id="add-package-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <!-- Basic Info Section -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-info-circle"></i> Package Details</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Package Name *</label>
                            <input type="text" name="name" class="form-input" required placeholder="e.g., Elite Training Bundle">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price ($) *</label>
                            <input type="number" name="price" class="form-input" step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="What's included in this package?"></textarea>
                    </div>
                </div>
                
                <!-- Package Type Section -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-list-check"></i> Package Contents</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Package Type *</label>
                            <select name="package_type" class="form-input" id="package-type-select" onchange="togglePackageTypeFields()">
                                <option value="sessions_only">Sessions Only</option>
                                <option value="credit_only">Store Credit Only</option>
                                <option value="mixed">Mixed (Sessions + Credit)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row" id="sessions-count-row">
                        <div class="form-group">
                            <label class="form-label">Number of Sessions</label>
                            <input type="number" name="session_count" class="form-input" min="0" value="0" placeholder="Number of sessions included">
                            <small class="form-help-text" style="color: var(--text-dim);">Generic session credits. For specific sessions, use the session selector below.</small>
                        </div>
                    </div>
                    
                    <div class="form-row" id="store-credit-row" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Store Credit Value ($)</label>
                            <input type="number" name="store_credit" class="form-input" step="0.01" min="0" value="0" placeholder="0.00">
                            <small class="form-help-text" style="color: var(--text-dim);">Dollar amount of store credit included in this package.</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Validity Period (days)</label>
                            <input type="number" name="validity_days" class="form-input" min="1" placeholder="e.g., 90">
                            <small class="form-help-text" style="color: var(--text-dim);">Leave blank for no expiration.</small>
                        </div>
                    </div>
                </div>
                
                <!-- Session Selector Section -->
                <div class="form-section" id="session-selector-section">
                    <h4 class="section-title"><i class="fas fa-calendar-check"></i> Assign Specific Sessions (Optional)</h4>
                    <p class="form-help-text" style="margin-bottom: 12px; color: var(--text-dim); font-size: 13px;">Search and add specific sessions to include in this package</p>
                    
                    <div class="session-search-container">
                        <input type="text" id="session-search-input" class="session-search-input" placeholder="Search sessions by name or skill type..." onkeyup="filterSessions()">
                    </div>
                    
                    <div class="session-search-results" id="session-search-results">
                        <?php 
                        $displaySessions = count($sessionTemplates) > 0 ? $sessionTemplates : $sessionTypes;
                        foreach ($displaySessions as $session): 
                            $sessionName = htmlspecialchars($session['name']);
                            $sessionPrice = $session['price'] ?? $session['default_price'] ?? 0;
                        ?>
                        <div class="session-search-item" data-name="<?= strtolower($sessionName) ?>">
                            <div>
                                <strong><?= $sessionName ?></strong>
                                <span style="color: var(--text-dim); margin-left: 10px;">$<?= number_format($sessionPrice, 2) ?></span>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addSessionToPackage(<?= $session['id'] ?>, '<?= addslashes($sessionName) ?>')">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div id="selected-sessions-container" style="margin-top: 16px;">
                        <h5 style="color: var(--text-white); margin-bottom: 10px;">Selected Sessions:</h5>
                        <div id="selected-sessions-list">
                            <p style="color: var(--text-dim); font-size: 13px;">No specific sessions selected yet.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Display Options -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-eye"></i> Display Options</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-input">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 10px; margin-top: 30px;">
                                <input type="checkbox" name="show_on_landing" value="1">
                                <span>Show on Landing Page (Above Individual Sessions)</span>
                            </label>
                        </div>
                    </div>
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
            <h2 class="modal-title"><i class="fas fa-tags"></i> Create Discount Code</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-discount-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php" id="add-discount-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_discount">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Code *</label>
                        <input type="text" name="code" class="form-input" required placeholder="e.g., HOCKEY10" style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-input" placeholder="e.g., Save 10% on all packages">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Type *</label>
                        <select name="type" class="form-input" id="discount-type-select" required onchange="toggleDiscountTypeFields()">
                            <option value="percentage">Percentage Off</option>
                            <option value="fixed">Fixed Amount Off</option>
                            <option value="store_credit">Store Credit</option>
                        </select>
                    </div>
                    <div class="form-group" id="discount-value-group">
                        <label class="form-label" id="discount-value-label">Percentage (%)</label>
                        <input type="number" name="value" class="form-input" step="0.01" min="0" required placeholder="10">
                    </div>
                </div>
                
                <div class="form-row" id="store-credit-discount-row" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Store Credit Amount ($)</label>
                        <input type="number" name="store_credit_value" class="form-input" step="0.01" min="0" placeholder="25.00">
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
                
                <!-- Auto-Generate Options -->
                <div class="form-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <h4 class="section-title"><i class="fas fa-magic"></i> Auto-Generation Rules (Optional)</h4>
                    <p class="form-help-text" style="margin-bottom: 12px; color: var(--text-dim); font-size: 13px;">Set up dynamic discount code generation rules</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Auto-Generate Type</label>
                            <select name="auto_generate_type" class="form-input" id="auto-generate-select" onchange="toggleAutoGenerateFields()">
                                <option value="none">Manual Code Only</option>
                                <option value="new_registration">New Registration Welcome</option>
                                <option value="time_based">Time-Based (Days Since Registration)</option>
                                <option value="referral">Referral Reward</option>
                            </select>
                        </div>
                        <div class="form-group" id="days-since-registration-group" style="display: none;">
                            <label class="form-label">Days Since Registration</label>
                            <input type="number" name="days_since_registration" class="form-input" min="1" placeholder="e.g., 30">
                            <small class="form-help-text" style="color: var(--text-dim);">Generate code X days after user registration</small>
                        </div>
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

<!-- Edit Session Modal (simplified - can be expanded) -->
<div id="edit-session-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Session</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-session-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dim); text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>
                Loading session details...
            </p>
        </div>
    </div>
</div>

<!-- Edit Package Modal (simplified - can be expanded) -->
<div id="edit-package-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Package</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-package-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dim); text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>
                Loading package details...
            </p>
        </div>
    </div>
</div>

<!-- Edit Discount Modal (simplified - can be expanded) -->
<div id="edit-discount-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Discount</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-discount-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dim); text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>
                Loading discount details...
            </p>
        </div>
    </div>
</div>

<!-- Manage Session Dates Modal -->
<div id="manage-dates-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-calendar-alt"></i> Manage Session Dates</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('manage-dates-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dim); text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>
                Loading session dates...
            </p>
        </div>
    </div>
</div>

<!-- Manage Package Sessions Modal -->
<div id="manage-package-sessions-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-list-check"></i> Manage Package Sessions</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('manage-package-sessions-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dim); text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>
                Loading package sessions...
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    var sessionDateIndex = 0;
    var selectedPackageSessions = [];
    
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
    
    // Handle toggle-status buttons
    document.querySelectorAll('[data-action="toggle-status"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var itemId = this.getAttribute('data-id');
            var itemType = this.getAttribute('data-type');
            var button = this;
            
            if (!confirm('Are you sure you want to toggle the status of this ' + itemType + '?')) return;
            
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
    
    // Handle edit buttons for modals
    document.querySelectorAll('[data-action="edit"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var itemId = this.getAttribute('data-id');
            var modal = document.getElementById(modalId);
            
            if (modal) {
                var idField = modal.querySelector('input[name$="_id"]');
                if (idField) idField.value = itemId;
                modal.classList.add('active');
            }
        });
    });
    
    // Convert forms to AJAX submissions
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
            .then(function(response) {
                var contentType = response.headers.get('content-type');
                var isJson = contentType && contentType.includes('application/json');
                
                if (!response.ok) {
                    if (isJson) {
                        return response.json().then(function(data) {
                            var errorMsg = data.message || data.error || 'Request failed with status: ' + response.status;
                            return { success: false, message: errorMsg };
                        }).catch(function() {
                            return { success: false, message: 'Request failed with status: ' + response.status };
                        });
                    }
                    return { success: false, message: 'Request failed with status: ' + response.status };
                }
                
                if (isJson) {
                    return response.json().catch(function() {
                        return { success: false, message: 'Invalid JSON response from server' };
                    });
                }
                
                return { success: false, message: 'Unexpected response from server. Please refresh and try again.' };
            })
            .then(function(data) {
                if (submitBtn) {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
                
                var message = (data && data.message) ? data.message : 'Operation failed';
                
                if (data && data.success) {
                    showNotification(message, 'success');
                    if (modal) closeModal(modal.id);
                    
                    var currentTab = 'sessions';
                    if (modal && modal.id.includes('package')) {
                        currentTab = 'packages';
                    } else if (modal && modal.id.includes('discount')) {
                        currentTab = 'discounts';
                    } else if (modal && modal.id.includes('session')) {
                        currentTab = 'sessions';
                    }
                    
                    setTimeout(function() { 
                        window.location.href = 'dashboard.php?page=products&tab=' + currentTab + '&status=success';
                    }, 1500);
                } else {
                    showNotification('Error: ' + message, 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                if (submitBtn) {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
                showNotification('An error occurred. Please try again.', 'error');
            });
        });
    });
});

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}

// Session date management
var sessionDateIndex = 0;
function addSessionDate() {
    sessionDateIndex++;
    var container = document.getElementById('session-dates-container');
    var teams = <?= json_encode($teams) ?>;
    
    var teamOptions = '<option value="">All Athletes</option>';
    teams.forEach(function(team) {
        teamOptions += '<option value="' + team.id + '">' + team.name + '</option>';
    });
    
    var newDate = document.createElement('div');
    newDate.className = 'session-date-input';
    newDate.setAttribute('data-index', sessionDateIndex);
    newDate.innerHTML = '<div class="form-row">' +
        '<div class="form-group">' +
            '<label class="form-label">Date & Time</label>' +
            '<input type="datetime-local" name="session_dates[' + sessionDateIndex + '][datetime]" class="form-input">' +
        '</div>' +
        '<div class="form-group">' +
            '<label class="form-label">Team (Optional)</label>' +
            '<select name="session_dates[' + sessionDateIndex + '][team_id]" class="form-input">' + teamOptions + '</select>' +
        '</div>' +
        '<div class="form-group" style="flex: 0 0 auto; align-self: end;">' +
            '<button type="button" class="btn-action remove-date" onclick="removeSessionDate(this)"><i class="fas fa-trash"></i></button>' +
        '</div>' +
    '</div>';
    
    container.appendChild(newDate);
    
    // Show remove button on first date if there's more than one
    var firstRemoveBtn = container.querySelector('.session-date-input[data-index="0"] .remove-date');
    if (firstRemoveBtn && container.querySelectorAll('.session-date-input').length > 1) {
        firstRemoveBtn.style.display = 'block';
    }
}

function removeSessionDate(button) {
    var dateInput = button.closest('.session-date-input');
    var container = document.getElementById('session-dates-container');
    
    if (container.querySelectorAll('.session-date-input').length > 1) {
        dateInput.remove();
    }
    
    // Hide remove button if only one date remains
    if (container.querySelectorAll('.session-date-input').length === 1) {
        var remainingRemoveBtn = container.querySelector('.remove-date');
        if (remainingRemoveBtn) remainingRemoveBtn.style.display = 'none';
    }
}

// Package type toggle
function togglePackageTypeFields() {
    var packageType = document.getElementById('package-type-select').value;
    var sessionsRow = document.getElementById('sessions-count-row');
    var creditRow = document.getElementById('store-credit-row');
    var sessionSelector = document.getElementById('session-selector-section');
    
    if (packageType === 'sessions_only') {
        sessionsRow.style.display = 'block';
        creditRow.style.display = 'none';
        sessionSelector.style.display = 'block';
    } else if (packageType === 'credit_only') {
        sessionsRow.style.display = 'none';
        creditRow.style.display = 'block';
        sessionSelector.style.display = 'none';
    } else { // mixed
        sessionsRow.style.display = 'block';
        creditRow.style.display = 'block';
        sessionSelector.style.display = 'block';
    }
}

// Discount type toggle
function toggleDiscountTypeFields() {
    var discountType = document.getElementById('discount-type-select').value;
    var valueLabel = document.getElementById('discount-value-label');
    var storeCreditRow = document.getElementById('store-credit-discount-row');
    
    if (discountType === 'percentage') {
        valueLabel.textContent = 'Percentage (%)';
        storeCreditRow.style.display = 'none';
    } else if (discountType === 'fixed') {
        valueLabel.textContent = 'Amount ($)';
        storeCreditRow.style.display = 'none';
    } else { // store_credit
        valueLabel.textContent = 'Value (ignored for credit)';
        storeCreditRow.style.display = 'block';
    }
}

// Auto-generate toggle
function toggleAutoGenerateFields() {
    var autoType = document.getElementById('auto-generate-select').value;
    var daysGroup = document.getElementById('days-since-registration-group');
    
    if (autoType === 'time_based') {
        daysGroup.style.display = 'block';
    } else {
        daysGroup.style.display = 'none';
    }
}

// Session search for packages
function filterSessions() {
    var searchInput = document.getElementById('session-search-input').value.toLowerCase();
    var items = document.querySelectorAll('.session-search-item');
    
    items.forEach(function(item) {
        var name = item.getAttribute('data-name');
        if (name.includes(searchInput)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

var selectedPackageSessions = [];
function addSessionToPackage(sessionId, sessionName) {
    if (selectedPackageSessions.find(s => s.id === sessionId)) {
        alert('This session is already added to the package.');
        return;
    }
    
    selectedPackageSessions.push({ id: sessionId, name: sessionName });
    updateSelectedSessionsList();
}

function removeSessionFromPackage(sessionId) {
    selectedPackageSessions = selectedPackageSessions.filter(s => s.id !== sessionId);
    updateSelectedSessionsList();
}

function updateSelectedSessionsList() {
    var container = document.getElementById('selected-sessions-list');
    
    if (selectedPackageSessions.length === 0) {
        container.innerHTML = '<p style="color: var(--text-dim); font-size: 13px;">No specific sessions selected yet.</p>';
        return;
    }
    
    var html = '';
    selectedPackageSessions.forEach(function(session) {
        html += '<div class="session-date-item">' +
            '<div class="date-info"><strong>' + session.name + '</strong></div>' +
            '<input type="hidden" name="package_session_ids[]" value="' + session.id + '">' +
            '<button type="button" class="btn-action danger" onclick="removeSessionFromPackage(' + session.id + ')"><i class="fas fa-times"></i></button>' +
        '</div>';
    });
    
    container.innerHTML = html;
}
</script>
