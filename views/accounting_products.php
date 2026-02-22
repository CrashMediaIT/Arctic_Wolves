<!-- Accounting Products View - Enhanced Sessions, Packages, and Discounts -->
<?php
// Fetch session templates from database
try {
    $templatesStmt = $pdo->query("
        SELECT tst.*, 
               st.name as session_type_name,
               u.first_name as coach_first_name, u.last_name as coach_last_name,
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
    $sessionTemplates = decryptUserRows($sessionTemplates);
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
    $coaches = decryptUserRows($coaches);
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

// Fetch camp and multi-week program packages separately
try {
    $programsStmt = $pdo->query("
        SELECT p.*, ag.name as age_group_name, sl.name as skill_level_name
        FROM packages p
        LEFT JOIN age_groups ag ON p.age_group_id = ag.id
        LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
        WHERE p.package_type IN ('camp', 'multi_week')
        ORDER BY p.camp_start_date DESC, p.name
    ");
    $programPackages = $programsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Programs fetch error: " . $e->getMessage());
    $programPackages = [];
}

// Fetch age groups for package form
try {
    $ageGroupsStmt = $pdo->query("SELECT * FROM age_groups ORDER BY display_order, name");
    $age_groups = $ageGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Age groups fetch error: " . $e->getMessage());
    $age_groups = [];
}

// Fetch skill levels for package form
try {
    $skillLevelsStmt = $pdo->query("SELECT * FROM skill_levels ORDER BY display_order, name");
    $skill_levels = $skillLevelsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Skill levels fetch error: " . $e->getMessage());
    $skill_levels = [];
}

// Fetch discount codes from database
try {
    $discountsStmt = $pdo->query("SELECT * FROM discount_codes ORDER BY code");
    $discounts = $discountsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Discounts fetch error: " . $e->getMessage());
    $discounts = [];
}

// Fetch merchandise products from database with sizes
try {
    $merchProductsStmt = $pdo->query("
        SELECT mp.*, mc.name as category_name,
               COALESCE((SELECT SUM(mps.quantity) FROM merchandise_product_sizes mps WHERE mps.product_id = mp.id), 0) as total_quantity
        FROM merchandise_products mp 
        LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id 
        ORDER BY mp.name
    ");
    $merchProducts = $merchProductsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all sizes in a single query to avoid N+1 problem
    $productIds = array_column($merchProducts, 'id');
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sizesStmt = $pdo->prepare("SELECT * FROM merchandise_product_sizes WHERE product_id IN ($placeholders) ORDER BY product_id, id ASC");
        $sizesStmt->execute($productIds);
        $allSizes = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group sizes by product_id
        $sizesByProduct = [];
        foreach ($allSizes as $size) {
            $sizesByProduct[$size['product_id']][] = $size;
        }
        
        // Assign sizes to products
        foreach ($merchProducts as &$product) {
            $product['sizes'] = $sizesByProduct[$product['id']] ?? [];
        }
    } else {
        foreach ($merchProducts as &$product) {
            $product['sizes'] = [];
        }
    }
} catch (PDOException $e) {
    error_log("Merchandise products fetch error: " . $e->getMessage());
    $merchProducts = [];
}

// Fetch merchandise categories for the add/edit modals
try {
    $merchCategoriesStmt = $pdo->query("SELECT id, name FROM merchandise_categories WHERE is_active = 1 ORDER BY name");
    $merchCategories = $merchCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Merchandise categories fetch error: " . $e->getMessage());
    $merchCategories = [];
}

// Calculate stats
$sessionCount = count($sessionTemplates) > 0 ? count($sessionTemplates) : count($sessionTypes);
$packageCount = count(array_filter($packages, function($p) { return !empty($p['is_active']); }));
$discountCount = count(array_filter($discounts, function($d) { return !empty($d['is_active']); }));
$merchProductCount = count(array_filter($merchProducts, function($p) { return !empty($p['is_active']); }));
$avgPackagePrice = $packageCount > 0 ? array_sum(array_column($packages, 'price')) / count($packages) : 0;
$programCount = count(array_filter($programPackages, function($p) { return !empty($p['is_active']); }));

// Handle tab from URL
$activeTab = $_GET['tab'] ?? 'sessions';
?>

<?php if (isset($_GET['status']) && in_array($_GET['status'], ['success', 'added'])): ?>
<div class="alert alert-success" style="margin-bottom: 24px;">
    <i class="fas fa-check-circle"></i>
    <span>Operation completed successfully!</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
<div class="alert alert-error" style="margin-bottom: 24px;">
    <i class="fas fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($_GET['message'] ?? 'An error occurred') ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-box-open"></i> Products & Pricing</h1>
        <p class="page-description">Manage training sessions, packages, and discount codes</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= $sessionCount ?></span>
            <span class="stat-label">Sessions</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $packageCount ?></span>
            <span class="stat-label">Packages</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $discountCount ?></span>
            <span class="stat-label">Discounts</span>
        </div>
    </div>
</div>

<style>
/* Product Stats Cards */
.product-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.product-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.product-stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.product-stat-card.sessions .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.product-stat-card.packages .stat-icon { background: rgba(107, 70, 193, 0.15); color: var(--primary); }
.product-stat-card.discounts .stat-icon { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.product-stat-card.revenue .stat-icon { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.product-stat-card .stat-info { display: flex; flex-direction: column; }
.product-stat-card .stat-value { font-size: 24px; font-weight: 700; color: var(--text-white); }
.product-stat-card .stat-label { font-size: 13px; color: var(--text-dim); }
</style>

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
<div class="page-tabs">
    <button type="button" class="page-tab <?= $activeTab === 'sessions' ? 'active' : '' ?>" data-tab="sessions" data-action="switch-tab">
        <i class="fas fa-calendar-day"></i> Sessions
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'packages' ? 'active' : '' ?>" data-tab="packages" data-action="switch-tab">
        <i class="fas fa-box"></i> Packages
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'discounts' ? 'active' : '' ?>" data-tab="discounts" data-action="switch-tab">
        <i class="fas fa-tags"></i> Discounts
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'merchandise' ? 'active' : '' ?>" data-tab="merchandise" data-action="switch-tab">
        <i class="fas fa-tshirt"></i> Merchandise
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'programs_camps' ? 'active' : '' ?>" data-tab="programs_camps" data-action="switch-tab">
        <i class="fas fa-campground"></i> Programs & Camps
    </button>
</div>

<div class="page-tab-content">
    <!-- Sessions Tab -->
    <div class="tab-content <?= $activeTab === 'sessions' ? 'active' : '' ?>" id="sessions-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-day"></i> Training Sessions & Templates</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-session-modal"><i class="fas fa-plus"></i> Create Session</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <?php 
                    // Display session templates if they exist, otherwise fall back to session types
                    $displaySessions = count($sessionTemplates) > 0 ? $sessionTemplates : $sessionTypes;
                    
                    if (empty($displaySessions)): ?>
                        <div class="empty-state-card" style="grid-column: 1/-1;">
                            <i class="fas fa-calendar-day"></i>
                            <h4>No sessions yet</h4>
                            <p>Click "Create Session" to add one.</p>
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
                                <?php $acct_coach_name = trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')); ?>
                                <?php if (!empty($acct_coach_name)): ?>
                                <p><i class="fas fa-user-tie"></i> <?= htmlspecialchars($acct_coach_name) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($session['description'])): ?>
                                <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($session['description'], 0, 50)) ?><?= strlen($session['description']) > 50 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <p class="session-type-label">
                                    <span class="type-badge <?= $sessionType ?>"><?= ucfirst(str_replace('_', ' ', $sessionType)) ?></span>
                                </p>
                            </div>
                            <div class="product-actions">
                                <button type="button" class="btn-action" data-action="edit" data-id="<?= $session['id'] ?>" data-type="session" data-modal="edit-session-modal" title="Edit"><i class="fas fa-edit"></i></button>
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
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-box"></i> Training Packages</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-package-modal"><i class="fas fa-plus"></i> Create Package</button>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <?php if (empty($packages)): ?>
                        <div class="empty-state-card" style="grid-column: 1/-1;">
                            <i class="fas fa-box"></i>
                            <h4>No packages yet</h4>
                            <p>Click "Create Package" to add one.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($packages as $package): 
                            $isActive = !empty($package['is_active']);
                            $showOnLanding = isset($package['show_on_landing']) ? $package['show_on_landing'] : 0;
                            $packageType = $package['package_type'] ?? 'sessions_only';
                            $storeCredit = $package['store_credit'] ?? 0;
                        ?>
                        <div class="product-card <?= $isActive ? 'featured' : '' ?>">
                            <?php if ($showOnLanding): ?><div class="product-badge landing">Public</div><?php endif; ?>
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
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Discount Codes</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-discount-modal"><i class="fas fa-plus"></i> Create Discount</button>
            </div>
            <div class="card-body">
                <?php if (empty($discounts)): ?>
                    <div class="empty-state-card">
                        <i class="fas fa-tags"></i>
                        <h4>No discount codes yet</h4>
                        <p>Click "Create Discount" to add one.</p>
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

    <!-- Merchandise Tab -->
    <div class="tab-content <?= $activeTab === 'merchandise' ? 'active' : '' ?>" id="merchandise-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tshirt"></i> Merchandise Products</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-merchandise-product-modal"><i class="fas fa-plus"></i> Add Product</button>
            </div>
            <div class="card-body">
                <?php if (empty($merchProducts)): ?>
                <div class="empty-state-card">
                    <i class="fas fa-tshirt"></i>
                    <h4>No Merchandise Products</h4>
                    <p>Create your first merchandise product to start selling in the shop and POS.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock / Sizes</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($merchProducts as $product): ?>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <?php if (!empty($product['sku'])): ?>
                                        <small style="color: var(--text-dim);">SKU: <?= htmlspecialchars($product['sku']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></td>
                                <td>$<?= number_format($product['price'] ?? 0, 2) ?></td>
                                <td>
                                    <?php if (!empty($product['sizes'])): ?>
                                        <div class="size-stock-display">
                                            <?php foreach ($product['sizes'] as $size): ?>
                                                <span class="size-badge" title="<?= htmlspecialchars($size['size']) ?>: <?= $size['quantity'] ?> in stock">
                                                    <?= htmlspecialchars($size['size']) ?>: <?= $size['quantity'] ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-dim);"><?= $product['total_quantity'] ?? $product['stock_quantity'] ?? 0 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge <?= !empty($product['is_active']) ? 'active' : 'inactive' ?>"><?= !empty($product['is_active']) ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action" data-action="edit" data-id="<?= $product['id'] ?>" data-type="merch-product" data-modal="edit-merchandise-product-modal" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button class="btn-action danger" data-action="delete" data-id="<?= $product['id'] ?>" data-type="merch-product" title="Delete"><i class="fas fa-trash"></i></button>
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

    <!-- Programs & Camps Tab -->
    <div class="tab-content <?= $activeTab === 'programs_camps' ? 'active' : '' ?>" id="programs_camps-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-campground"></i> Programs & Camps</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-program-modal"><i class="fas fa-plus"></i> Create Program / Camp</button>
            </div>
            <div class="card-body">
                <?php if (empty($programPackages)): ?>
                <div class="empty-state-card">
                    <i class="fas fa-campground"></i>
                    <h4>No Programs or Camps</h4>
                    <p>Click "Create Program / Camp" to get started.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Dates</th>
                                <th>Price</th>
                                <th>Age Group</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programPackages as $prog): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($prog['name']) ?></strong></td>
                                <td>
                                    <span class="type-badge <?= $prog['package_type'] ?>">
                                        <i class="fas fa-<?= $prog['package_type'] === 'camp' ? 'campground' : 'calendar-week' ?>"></i>
                                        <?= $prog['package_type'] === 'camp' ? 'Camp' : 'Multi-Week' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($prog['camp_start_date']) && !empty($prog['camp_end_date'])): ?>
                                        <?= date('M j', strtotime($prog['camp_start_date'])) ?> – <?= date('M j, Y', strtotime($prog['camp_end_date'])) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-dim);">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>$<?= number_format($prog['price'] ?? 0, 2) ?></td>
                                <td><?= htmlspecialchars($prog['age_group_name'] ?? $prog['age_group'] ?? 'All') ?></td>
                                <td><span class="status-badge <?= !empty($prog['is_active']) ? 'active' : 'inactive' ?>"><?= !empty($prog['is_active']) ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action" data-action="edit" data-id="<?= $prog['id'] ?>" data-type="package" data-modal="edit-package-modal" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button class="btn-action <?= !empty($prog['is_active']) ? '' : 'active' ?>" data-action="toggle-status" data-id="<?= $prog['id'] ?>" data-type="package" title="<?= !empty($prog['is_active']) ? 'Disable' : 'Enable' ?>"><i class="fas fa-toggle-<?= !empty($prog['is_active']) ? 'on' : 'off' ?>"></i></button>
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

<!-- Add Program / Camp Modal -->
<div id="add-program-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-campground"></i> Create Program / Camp</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-program-modal')">&times;</button>
        </div>
        <form method="POST" action="process_packages.php" id="add-program-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-info-circle"></i> Program Details</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Program Name *</label>
                            <input type="text" name="name" class="form-input" required placeholder="e.g., Summer Hockey Camp 2026">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Program Type *</label>
                            <select name="package_type" class="form-input" required id="programTypeSelect" onchange="toggleProgramFields()">
                                <option value="camp">Camp (date range with daily schedule)</option>
                                <option value="multi_week">Multi-Week Program (select specific dates)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Describe what participants will learn..."></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-dollar-sign"></i> Pricing</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Price ($) *</label>
                            <input type="number" name="price" class="form-input" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Participants</label>
                            <input type="number" name="max_participants" class="form-input" min="1" placeholder="Leave blank for unlimited">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-users"></i> Target Audience</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Age Group</label>
                            <select name="age_group_id" class="form-input">
                                <option value="">All Ages</option>
                                <?php foreach ($age_groups as $ag): ?>
                                <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Skill Level</label>
                            <select name="skill_level_id" class="form-input">
                                <option value="">All Levels</option>
                                <?php foreach ($skill_levels as $sl): ?>
                                <option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Camp Date Range Section -->
                <div class="form-section" id="programCampDates">
                    <h4 class="section-title"><i class="fas fa-calendar-alt"></i> Camp Schedule</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="camp_start_date" class="form-input" id="programCampStartDate">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="camp_end_date" class="form-input" id="programCampEndDate">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Default Daily Start Time</label>
                            <input type="time" name="daily_start_time" class="form-input" value="09:00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Default Daily End Time</label>
                            <input type="time" name="daily_end_time" class="form-input" value="17:00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <select name="location_id" class="form-input">
                            <option value="">Select Location (Optional)</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?><?= $loc['city'] ? ' - ' . htmlspecialchars($loc['city']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Multi-Week Dates Section -->
                <div class="form-section" id="programMultiWeekDates" style="display: none;">
                    <h4 class="section-title"><i class="fas fa-calendar-week"></i> Program Dates</h4>
                    <p class="form-help-text" style="margin-bottom: 12px; color: var(--text-dim); font-size: 13px;">Click dates on the calendar to select or deselect them. Each date can have its own time and location.</p>
                    
                    <!-- Inline Calendar Picker for Programs -->
                    <div class="arctic-calendar" id="program-calendar">
                        <div class="arctic-cal-header">
                            <button type="button" class="arctic-cal-nav" onclick="programCalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                            <span class="arctic-cal-title" id="program-cal-title"></span>
                            <button type="button" class="arctic-cal-nav" onclick="programCalNav(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="arctic-cal-weekdays">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="arctic-cal-days" id="program-cal-days"></div>
                    </div>
                    
                    <!-- Selected Dates List -->
                    <div id="program-dates-container"></div>
                    <p id="program-dates-empty" style="color: var(--text-dim); font-size: 13px; text-align: center; padding: 12px; display: block;"><i class="fas fa-mouse-pointer"></i> Click on dates above to add them to this program</p>
                </div>
                
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
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-program-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Program</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Arctic Calendar Picker */
.arctic-calendar {
    background: var(--bg-secondary, #1e293b);
    border: 1px solid var(--border, #334155);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    max-width: 420px;
    overflow: hidden;
    box-sizing: border-box;
}
.arctic-cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.arctic-cal-title {
    font-weight: 600;
    font-size: 15px;
    color: var(--text-white, #e2e8f0);
}
.arctic-cal-nav {
    background: none;
    border: 1px solid var(--border, #334155);
    color: var(--text-primary, #e2e8f0);
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.arctic-cal-nav:hover {
    background: var(--primary, #6b46c1);
    border-color: var(--primary, #6b46c1);
    color: #fff;
}
.arctic-cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    text-align: center;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-dim, #94a3b8);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.arctic-cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    overflow: hidden;
    max-width: 100%;
    box-sizing: border-box;
}
.arctic-cal-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    border: none;
    background: none;
    color: var(--text-primary, #e2e8f0);
    transition: all 0.15s;
    font-weight: 500;
    position: relative;
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
}
.arctic-cal-day:hover:not(.disabled):not(.empty) {
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light, #a78bfa);
}
.arctic-cal-day.selected {
    background: var(--primary, #6b46c1);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.35);
}
.arctic-cal-day.today:not(.selected) {
    border: 2px solid var(--primary, #6b46c1);
}
.arctic-cal-day.disabled {
    color: var(--text-dim, #475569);
    opacity: 0.3;
    cursor: default;
}
.arctic-cal-day.empty {
    cursor: default;
}

/* Selected date entries */
.session-date-entry {
    background: var(--bg-secondary, #1e293b);
    border: 1px solid var(--border, #334155);
    border-radius: 10px;
    padding: 12px 16px;
    margin-top: 8px;
    position: relative;
    animation: slideIn 0.2s ease;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
.session-date-entry .date-label {
    font-weight: 600;
    color: var(--primary-light, #a78bfa);
    font-size: 13px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.session-date-entry .date-fields {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: end;
}
.session-date-entry .date-fields .form-group {
    margin-bottom: 0;
}
.session-date-entry .remove-date-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: none;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.15s;
}
.session-date-entry .remove-date-btn:hover {
    background: rgba(239, 68, 68, 0.25);
}

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

/* Note: Page tabs (.page-tabs, .page-tab) are styled in css/style-guide.css */

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
    display: block; /* Override .session-card flex layout */
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
.type-badge.credits { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.type-badge.dollar_value { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.type-badge.bundled { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }

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

/* Size/Stock rows for merchandise */
.size-stock-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.size-stock-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.size-stock-row .form-input {
    margin-bottom: 0;
}

.size-stock-row .size-input {
    flex: 1;
    min-width: 150px;
}

.size-stock-row .qty-input {
    width: 100px;
}

.size-stock-row .remove-size-btn {
    flex-shrink: 0;
}

.add-size-btn {
    margin-top: 8px;
}

.form-help {
    display: block;
    margin-top: 8px;
    color: var(--text-dim);
    font-size: 12px;
}

/* Size badges display in table */
.size-stock-display {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.size-badge {
    display: inline-block;
    padding: 3px 8px;
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light);
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .product-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .size-stock-row {
        flex-wrap: wrap;
    }
    
    .size-stock-row .form-input:first-child {
        flex: 1 1 100%;
    }
}

@media (max-width: 480px) {
    .product-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Add Session Modal - Recreated -->
<div id="add-session-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-calendar-plus"></i> Create Training Session</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-session-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php" id="add-session-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_training_session">
            
            <div class="modal-body">
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
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-calendar-alt"></i> Session Dates</h4>
                    <p class="form-help-text" style="margin-bottom: 12px; color: var(--text-dim); font-size: 13px;">Click dates on the calendar to select or deselect them. Each selected date can have its own time and location.</p>
                    
                    <!-- Inline Calendar Picker -->
                    <div class="arctic-calendar" id="session-calendar">
                        <div class="arctic-cal-header">
                            <button type="button" class="arctic-cal-nav" onclick="sessionCalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                            <span class="arctic-cal-title" id="session-cal-title"></span>
                            <button type="button" class="arctic-cal-nav" onclick="sessionCalNav(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="arctic-cal-weekdays">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="arctic-cal-days" id="session-cal-days"></div>
                    </div>
                    
                    <!-- Selected Dates List -->
                    <div id="session-dates-container"></div>
                    <p id="session-dates-empty" style="color: var(--text-dim); font-size: 13px; text-align: center; padding: 12px; display: block;"><i class="fas fa-mouse-pointer"></i> Click on dates above to add them to this session</p>
                </div>
                
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-session-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Session</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Package Modal - Recreated -->
<div id="add-package-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-box"></i> Create Package</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-package-modal')">&times;</button>
        </div>
        <form method="POST" action="process_packages.php" id="add-package-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
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
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-list-check"></i> Package Contents</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Package Type *</label>
                            <select name="package_type" class="form-input" id="package-type-select" onchange="togglePackageTypeFields()">
                                <option value="credits">Session Credits (set number of sessions)</option>
                                <option value="dollar_value">Dollar Value (store credit amount)</option>
                                <option value="bundled">Bundled Sessions (pick from sessions library)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row" id="credits-count-row">
                        <div class="form-group">
                            <label class="form-label">Number of Sessions *</label>
                            <input type="number" name="credits" class="form-input" min="1" value="1" placeholder="Number of sessions">
                            <small class="form-help-text" style="color: var(--text-dim);">How many sessions can be booked with this package.</small>
                        </div>
                    </div>
                    
                    <div class="form-row" id="dollar-value-row" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Store Credit Value ($) *</label>
                            <input type="number" name="store_credit" class="form-input" min="0" step="0.01" value="0" placeholder="Dollar value">
                            <small class="form-help-text" style="color: var(--text-dim);">Dollar amount of store credit included in this package.</small>
                        </div>
                    </div>
                    
                    <div class="form-row" id="bundled-sessions-row" style="display: none;">
                        <div class="form-group">
                            <div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 8px; padding: 12px;">
                                <i class="fas fa-info-circle" style="color: #8B5CF6;"></i>
                                <span style="color: var(--text-dim);">After creating this package, use the <strong style="color: var(--text-white);">Manage Sessions</strong> button to select specific sessions from your sessions library.</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Camp/Program Date Fields -->
                    <div id="camp-fields-row" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="camp_start_date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date *</label>
                                <input type="date" name="camp_end_date" class="form-input">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Daily Start Time</label>
                                <input type="time" name="daily_start_time" class="form-input" value="09:00">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Daily End Time</label>
                                <input type="time" name="daily_end_time" class="form-input" value="16:00">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" name="enable_child_checkin" value="1"> Enable Child Check-in/Pickup
                                </label>
                                <small class="form-help-text" style="color: var(--text-dim);">Generate daily check-in codes for parent pickup.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div id="multi-week-fields-row" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" name="allow_individual_sessions" value="1"> Allow Individual Session Purchase
                                </label>
                                <small class="form-help-text" style="color: var(--text-dim);">Allow athletes to buy individual sessions from this program.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Validity Period (days)</label>
                            <input type="number" name="valid_days" class="form-input" min="1" value="365" placeholder="e.g., 365">
                            <small class="form-help-text" style="color: var(--text-dim);">How many days the credits are valid for.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-users"></i> Target Audience (Optional)</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Age Group</label>
                            <select name="age_group" class="form-input">
                                <option value="">All Ages</option>
                                <?php if (isset($age_groups) && is_array($age_groups)): ?>
                                    <?php foreach ($age_groups as $ag): ?>
                                        <option value="<?= htmlspecialchars($ag['name']) ?>"><?= htmlspecialchars($ag['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Skill Level</label>
                            <select name="skill_level" class="form-input">
                                <option value="">All Levels</option>
                                <?php if (isset($skill_levels) && is_array($skill_levels)): ?>
                                    <?php foreach ($skill_levels as $sl): ?>
                                        <option value="<?= htmlspecialchars($sl['name']) ?>"><?= htmlspecialchars($sl['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
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
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-package-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Package</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Discount Modal - Recreated -->
<div id="add-discount-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-tags"></i> Create Discount Code</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-discount-modal')">&times;</button>
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
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Type *</label>
                        <select name="type" class="form-input" id="discount-type-select" required onchange="toggleDiscountTypeFields()">
                            <option value="percentage">Percentage Off</option>
                            <option value="fixed">Fixed Amount Off</option>
                        </select>
                    </div>
                    <div class="form-group" id="discount-value-group">
                        <label class="form-label" id="discount-value-label">Percentage (%) *</label>
                        <input type="number" name="value" class="form-input" step="0.01" min="0" required placeholder="10">
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-discount-modal')"><i class="fas fa-times"></i> Cancel</button>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeModal(&quot;edit-session-modal&quot;)">&times;</button>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeModal(&quot;edit-package-modal&quot;)">&times;</button>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeModal(&quot;edit-discount-modal&quot;)">&times;</button>
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

<!-- Add Merchandise Product Modal -->
<div id="add-merchandise-product-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-tshirt"></i> Add Merchandise Product</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-merchandise-product-modal')">&times;</button>
        </div>
        <form action="process_merchandise_products.php" method="POST" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-input" required placeholder="e.g., Team Jersey">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-input">
                            <option value="">-- No Category --</option>
                            <?php foreach ($merchCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input type="number" name="price" class="form-input" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SKU (Optional)</label>
                        <input type="text" name="sku" class="form-input" placeholder="PROD-001">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Product description..."></textarea>
                </div>
                
                <!-- Size/Stock Options -->
                <div class="form-group">
                    <label class="form-label" id="size-stock-label">Size & Stock Options</label>
                    <div class="size-stock-container" id="add-merch-sizes-container" aria-labelledby="size-stock-label">
                        <div class="size-stock-row">
                            <input type="text" name="sizes[]" class="form-input size-input" placeholder="Size (e.g., S, M, L, XL)" aria-label="Size name">
                            <input type="number" name="quantities[]" class="form-input qty-input" min="0" value="0" placeholder="Qty" aria-label="Quantity">
                            <button type="button" class="btn-action danger remove-size-btn" onclick="removeSizeRow(this)" title="Remove size" aria-label="Remove this size"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm add-size-btn" onclick="addMerchSizeRow('add-merch-sizes-container')">
                        <i class="fas fa-plus"></i> Add Size
                    </button>
                    <small class="form-help">Add different sizes with their stock quantities. Leave empty for products without sizes.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Track Inventory</label>
                        <select name="track_inventory" class="form-input">
                            <option value="1">Yes - Track stock levels</option>
                            <option value="0">No - Unlimited stock</option>
                        </select>
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
                    <label class="form-label">Product Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-merchandise-product-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Merchandise Product Modal -->
<div id="edit-merchandise-product-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Merchandise Product</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-merchandise-product-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dim); text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>
                Loading product details...
            </p>
        </div>
    </div>
</div>

<script>
// Session edit data (from PHP)
var editCoaches = <?= json_encode(array_map(function($c) { return ['id' => $c['id'], 'name' => $c['first_name'] . ' ' . $c['last_name'], 'role' => $c['role']]; }, $coaches)) ?>;
var editLocations = <?= json_encode(array_map(function($l) { return ['id' => $l['id'], 'name' => $l['name'], 'city' => $l['city'] ?? '']; }, $locations)) ?>;
var editPracticePlans = <?= json_encode(array_map(function($p) { return ['id' => $p['id'], 'name' => $p['name']]; }, $practicePlans)) ?>;
var editSessionTypes = <?= json_encode(array_map(function($t) { return ['id' => $t['id'], 'name' => $t['name']]; }, $sessionTypes)) ?>;
var editMerchCategories = <?= json_encode(array_map(function($c) { return ['id' => $c['id'], 'name' => $c['name']]; }, $merchCategories)) ?>;

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
    document.querySelectorAll('.page-tab[data-action="switch-tab"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Prevent app.js handler from also running
            
            var tabName = this.getAttribute('data-tab');
            if (!tabName) return;
            
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.classList.remove('active');
                tab.style.display = 'none';
            });
            document.querySelectorAll('.page-tab').forEach(function(tabBtn) {
                tabBtn.classList.remove('active');
            });
            
            var targetContent = document.getElementById(tabName + '-tab');
            if (targetContent) {
                targetContent.classList.add('active');
                targetContent.style.display = 'block';
            }
            this.classList.add('active');
            
            // Update URL without page reload
            var url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
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
                    persistToast(data.message || 'Status updated successfully!', 'success');
                    var icon = button.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-toggle-on');
                        icon.classList.toggle('fa-toggle-off');
                    }
                    location.reload();
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
                    persistToast(itemType.charAt(0).toUpperCase() + itemType.slice(1) + ' deleted successfully!', 'success');
                    location.reload();
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
            var itemType = this.getAttribute('data-type');
            var modal = document.getElementById(modalId);
            
            if (!modal) return;
            
            var modalBody = modal.querySelector('.modal-body');
            
            // Show loading state
            modalBody.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 40px;">' +
                '<i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                'Loading details...</p>';
            modal.classList.add('active');
            
            // Determine the action based on type
            var action = '';
            if (itemType === 'session') action = 'get_session';
            else if (itemType === 'package') action = 'get_package';
            else if (itemType === 'discount') action = 'get_discount';
            else if (itemType === 'merch-product') action = 'get_merchandise_product';
            else {
                console.error('Unknown item type: ' + itemType);
                modalBody.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 40px;">' +
                    '<i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                    'Unknown item type. Please refresh and try again.</p>';
                return;
            }
            
            // Fetch the data
            fetch('process_admin_action.php?action=' + action + '&id=' + itemId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        populateEditModal(modalBody, itemType, data.data, itemId);
                    } else {
                        modalBody.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 40px;">' +
                            '<i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                            'Error: ' + (data.message || 'Could not load data') + '</p>';
                    }
                })
                .catch(function(err) {
                    console.error('Fetch error:', err);
                    modalBody.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 40px;">' +
                        '<i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                        'Error loading data. Please try again.</p>';
                });
        });
    });
    
    // Handle manage-dates buttons for session date management
    document.querySelectorAll('[data-action="manage-dates"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var sessionId = this.getAttribute('data-id');
            var modal = document.getElementById(modalId);
            
            if (!modal) return;
            
            var modalBody = modal.querySelector('.modal-body');
            
            // Show loading state
            modalBody.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 40px;">' +
                '<i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                'Loading session dates...</p>';
            modal.classList.add('active');
            
            // Fetch the session data including dates
            fetch('process_admin_action.php?action=get_session&id=' + sessionId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        populateManageDatesModal(modalBody, data.data, sessionId);
                    } else {
                        modalBody.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 40px;">' +
                            '<i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                            'Error: ' + (data.message || 'Could not load session dates') + '</p>';
                    }
                })
                .catch(function(err) {
                    console.error('Fetch error:', err);
                    modalBody.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 40px;">' +
                        '<i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                        'Error loading session dates. Please try again.</p>';
                });
        });
    });
    
    // Function to populate the manage dates modal
    function populateManageDatesModal(container, sessionData, sessionId) {
        var csrfToken = document.querySelector('input[name="csrf_token"]').value;
        var teams = <?= json_encode($teams) ?>;
        var dates = sessionData.dates || [];
        
        var teamOptions = '<option value="">All Athletes</option>';
        teams.forEach(function(team) {
            teamOptions += '<option value="' + team.id + '">' + escapeHtml(team.name) + '</option>';
        });
        
        var html = '<div class="manage-dates-container">' +
            '<h4 style="margin-bottom: 16px; color: var(--text-color);"><i class="fas fa-calendar-alt"></i> ' + escapeHtml(sessionData.name) + '</h4>';
        
        // Existing dates list
        html += '<div class="existing-dates-section" style="margin-bottom: 24px;">' +
            '<h5 style="margin-bottom: 12px; color: var(--text-dim);">Current Session Dates</h5>' +
            '<div id="session-dates-list" style="max-height: 250px; overflow-y: auto;">';
        
        if (dates.length === 0) {
            html += '<p style="color: var(--text-dim); font-style: italic; padding: 12px;">No dates scheduled for this session yet.</p>';
        } else {
            dates.forEach(function(date) {
                var dateObj = new Date(date.session_date);
                var formattedDate = dateObj.toLocaleString('en-US', { 
                    weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                    hour: 'numeric', minute: '2-digit'
                });
                var teamName = date.team_name ? date.team_name : 'All Athletes';
                var statusClass = Number(date.is_active) === 1 ? 'active' : 'inactive';
                
                html += '<div class="session-date-item" data-date-id="' + date.id + '" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px;">' +
                    '<div class="date-info">' +
                        '<strong style="color: var(--text-color);">' + formattedDate + '</strong>' +
                        '<span style="color: var(--text-dim); font-size: 13px; margin-left: 12px;"><i class="fas fa-users"></i> ' + escapeHtml(teamName) + '</span>' +
                        '<span class="status-badge ' + statusClass + '" style="margin-left: 8px; font-size: 11px; padding: 2px 8px; border-radius: 12px;">' + (Number(date.is_active) === 1 ? 'Active' : 'Inactive') + '</span>' +
                    '</div>' +
                    '<button type="button" class="btn-action danger" onclick="removeSessionDate(' + date.id + ', this)" title="Remove Date"><i class="fas fa-trash"></i></button>' +
                '</div>';
            });
        }
        
        html += '</div></div>';
        
        // Add new date form
        html += '<div class="add-date-section" style="border-top: 1px solid var(--border); padding-top: 20px;">' +
            '<h5 style="margin-bottom: 12px; color: var(--text-dim);">Add New Date</h5>' +
            '<form id="add-session-date-form" onsubmit="submitAddSessionDate(event, ' + sessionId + ')">' +
                '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                '<input type="hidden" name="action" value="add_session_date">' +
                '<input type="hidden" name="template_id" value="' + sessionId + '">' +
                '<div class="form-row" style="display: flex; gap: 12px; flex-wrap: wrap;">' +
                    '<div class="form-group" style="flex: 1; min-width: 200px;">' +
                        '<label class="form-label">Date & Time *</label>' +
                        '<input type="datetime-local" name="session_date" class="form-input" required>' +
                    '</div>' +
                    '<div class="form-group" style="flex: 1; min-width: 150px;">' +
                        '<label class="form-label">Team (Optional)</label>' +
                        '<select name="team_id" class="form-input">' + teamOptions + '</select>' +
                    '</div>' +
                    '<div class="form-group" style="flex: 0 0 auto; align-self: end;">' +
                        '<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Date</button>' +
                    '</div>' +
                '</div>' +
            '</form>' +
        '</div>';
        
        // Footer
        html += '<div class="modal-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">' +
            '<button type="button" class="btn btn-secondary" onclick="closeModal(\'manage-dates-modal\')"><i class="fas fa-times"></i> Close</button>' +
        '</div>';
        
        html += '</div>';
        
        container.innerHTML = html;
    }
    
    // Function to submit add session date form
    window.submitAddSessionDate = function(event, sessionId) {
        event.preventDefault();
        var form = document.getElementById('add-session-date-form');
        var formData = new FormData(form);
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        submitBtn.disabled = true;
        
        fetch('process_admin_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            
            if (data.success) {
                showNotification(data.message || 'Date added successfully!', 'success');
                // Reload the modal content to show the new date
                var modal = document.getElementById('manage-dates-modal');
                var modalBody = modal.querySelector('.modal-body');
                modalBody.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 40px;">' +
                    '<i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                    'Refreshing dates...</p>';
                
                fetch('process_admin_action.php?action=get_session&id=' + sessionId)
                    .then(function(response) { return response.json(); })
                    .then(function(sessionData) {
                        if (sessionData.success) {
                            populateManageDatesModal(modalBody, sessionData.data, sessionId);
                        }
                    });
            } else {
                showNotification(data.message || 'Error adding date', 'error');
            }
        })
        .catch(function(err) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            console.error(err);
            showNotification('Error adding date. Please try again.', 'error');
        });
    };
    
    // Function to remove a session date
    window.removeSessionDate = function(dateId, btn) {
        if (!confirm('Are you sure you want to remove this session date? This action cannot be undone.')) {
            return;
        }
        
        var csrfToken = document.querySelector('input[name="csrf_token"]').value;
        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'remove_session_date');
        formData.append('date_id', dateId);
        
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        fetch('process_admin_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message || 'Date removed successfully!', 'success');
                // Remove the date item from DOM
                var dateItem = btn.closest('.session-date-item');
                if (dateItem) {
                    dateItem.remove();
                }
                // Check if list is now empty
                var datesList = document.getElementById('session-dates-list');
                if (datesList && datesList.querySelectorAll('.session-date-item').length === 0) {
                    datesList.innerHTML = '<p style="color: var(--text-dim); font-style: italic; padding: 12px;">No dates scheduled for this session yet.</p>';
                }
            } else {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                showNotification(data.message || 'Error removing date', 'error');
            }
        })
        .catch(function(err) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            console.error(err);
            showNotification('Error removing date. Please try again.', 'error');
        });
    };
    
    // Handle manage-sessions buttons for package sessions
    document.querySelectorAll('[data-action="manage-sessions"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var itemId = this.getAttribute('data-id');
            var modal = document.getElementById(modalId);
            
            if (!modal) return;
            
            var modalBody = modal.querySelector('.modal-body');
            
            // Show loading state
            modalBody.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 40px;">' +
                '<i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                'Loading package sessions...</p>';
            modal.classList.add('active');
            
            // Fetch package data including sessions
            fetch('process_admin_action.php?action=get_package&id=' + itemId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        populateManageSessionsModal(modalBody, data.data, itemId);
                    } else {
                        modalBody.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 40px;">' +
                            '<i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                            'Error: ' + (data.message || 'Could not load package sessions') + '</p>';
                    }
                })
                .catch(function(err) {
                    console.error('Fetch error:', err);
                    modalBody.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 40px;">' +
                        '<i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px; display: block;"></i>' +
                        'Error loading package sessions. Please try again.</p>';
                });
        });
    });
    
    // Function to populate manage sessions modal for packages
    function populateManageSessionsModal(container, data, packageId) {
        var sessions = data.sessions || [];
        
        var html = '<div class="manage-sessions-content">' +
            '<h4 style="margin-bottom: 16px; color: var(--text-white);">' + escapeHtml(data.name || 'Package') + '</h4>' +
            '<p style="color: var(--text-dim); margin-bottom: 16px;">Credits: ' + (data.credits || 0) + ' sessions</p>';
        
        if (sessions.length === 0) {
            html += '<p style="color: var(--text-dim); text-align: center; padding: 20px;">' +
                '<i class="fas fa-list" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>' +
                'No specific sessions linked to this package.</p>';
        } else {
            html += '<div class="sessions-list" style="max-height: 400px; overflow-y: auto;">';
            sessions.forEach(function(session) {
                html += '<div class="session-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px;">' +
                    '<div>' +
                        '<strong style="color: var(--text-white);">' + escapeHtml(session.session_name || session.session_description || 'Session') + '</strong>' +
                        '<span style="margin-left: 8px; color: var(--text-dim); font-size: 12px;">x' + (session.num_sessions || 1) + '</span>' +
                    '</div>' +
                '</div>';
            });
            html += '</div>';
        }
        
        html += '<div class="modal-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px;">' +
            '<button type="button" class="btn btn-secondary" onclick="closeModal(\'manage-package-sessions-modal\')"><i class="fas fa-times"></i> Close</button>' +
        '</div></div>';
        
        container.innerHTML = html;
    }
    
    // Function to populate edit modal with fetched data
    function populateEditModal(container, type, data, itemId) {
        var csrfToken = document.querySelector('input[name="csrf_token"]').value;
        
        if (type === 'session') {
            // Build coach checkboxes for multi-select
            var coachCheckboxes = '';
            var assignedCoaches = data.coach_ids && data.coach_ids.trim() ? data.coach_ids.split(',').map(function(id) { return id.trim(); }) : [];
            if (data.coach_id && assignedCoaches.indexOf(String(data.coach_id)) === -1) {
                assignedCoaches.push(String(data.coach_id));
            }
            editCoaches.forEach(function(coach) {
                var checked = assignedCoaches.indexOf(String(coach.id)) !== -1 ? ' checked' : '';
                coachCheckboxes += '<label class="skill-checkbox" style="margin-bottom: 4px;"><input type="checkbox" name="coach_ids[]" value="' + coach.id + '"' + checked + '><span>' + escapeHtml(coach.name) + ' (' + escapeHtml(coach.role) + ')</span></label>';
            });
            
            // Build location options
            var locationOptions = '<option value="">Select Location (Optional)</option>';
            editLocations.forEach(function(loc) {
                var selected = data.location_id == loc.id ? ' selected' : '';
                locationOptions += '<option value="' + loc.id + '"' + selected + '>' + escapeHtml(loc.name) + (loc.city ? ' - ' + escapeHtml(loc.city) : '') + '</option>';
            });
            
            // Build practice plan options
            var planOptions = '<option value="">Select Practice Plan (Optional)</option>';
            editPracticePlans.forEach(function(plan) {
                var selected = data.practice_plan_id == plan.id ? ' selected' : '';
                planOptions += '<option value="' + plan.id + '"' + selected + '>' + escapeHtml(plan.name) + '</option>';
            });
            
            // Build session type options
            var typeOptions = '<option value="">Select Category (Optional)</option>';
            editSessionTypes.forEach(function(st) {
                var selected = data.session_type_id == st.id ? ' selected' : '';
                typeOptions += '<option value="' + st.id + '"' + selected + '>' + escapeHtml(st.name) + '</option>';
            });
            
            container.innerHTML = 
                '<form method="POST" action="process_admin_action.php" id="edit-session-form">' +
                '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                '<input type="hidden" name="action" value="update_training_session">' +
                '<input type="hidden" name="id" value="' + itemId + '">' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Session Name *</label>' +
                        '<input type="text" name="name" class="form-input" required value="' + escapeHtml(data.name || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Session Type *</label>' +
                        '<select name="session_type" class="form-input" required>' +
                            '<option value="on_ice"' + (data.session_type === 'on_ice' ? ' selected' : '') + '>On Ice</option>' +
                            '<option value="off_ice"' + (data.session_type === 'off_ice' ? ' selected' : '') + '>Off Ice / Workout</option>' +
                            '<option value="nutrition"' + (data.session_type === 'nutrition' ? ' selected' : '') + '>Nutrition Meeting</option>' +
                            '<option value="meeting"' + (data.session_type === 'meeting' ? ' selected' : '') + '>General Meeting</option>' +
                            '<option value="other"' + (data.session_type === 'other' ? ' selected' : '') + '>Other</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Description</label>' +
                    '<textarea name="description" class="form-textarea" rows="3">' + escapeHtml(data.description || '') + '</textarea>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Price ($)</label>' +
                        '<input type="number" name="price" class="form-input" step="0.01" min="0" value="' + (data.price || 0) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Duration (minutes)</label>' +
                        '<input type="number" name="duration" class="form-input" min="15" max="480" value="' + (data.duration_minutes || 60) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Max Participants</label>' +
                        '<input type="number" name="max_participants" class="form-input" min="1" value="' + (data.max_participants || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Session Type Category</label>' +
                        '<select name="session_type_id" class="form-input">' + typeOptions + '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Location</label>' +
                        '<select name="location_id" class="form-input">' + locationOptions + '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Practice Plan</label>' +
                    '<select name="practice_plan_id" class="form-input">' + planOptions + '</select>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Coaches</label>' +
                    '<p style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">Select one or more coaches for this session</p>' +
                    '<div class="skill-selector" style="max-height: 150px; overflow-y: auto;">' + coachCheckboxes + '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Status</label>' +
                        '<select name="is_active" class="form-input">' +
                            '<option value="1"' + (data.is_active == 1 ? ' selected' : '') + '>Active</option>' +
                            '<option value="0"' + (data.is_active == 0 ? ' selected' : '') + '>Inactive</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="checkbox-label" style="display: flex; align-items: center; gap: 10px; margin-top: 30px;">' +
                            '<input type="checkbox" name="show_on_landing" value="1"' + (data.show_on_landing == 1 ? ' checked' : '') + '>' +
                            '<span>Show on Landing Page</span>' +
                        '</label>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="checkbox-label" style="display: flex; align-items: center; gap: 10px; margin-top: 30px;">' +
                            '<input type="checkbox" name="is_template" value="1"' + (data.is_template == 1 ? ' checked' : '') + '>' +
                            '<span>Save as Template</span>' +
                        '</label>' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">' +
                    '<button type="button" class="btn btn-secondary" onclick="closeModal(&quot;edit-session-modal&quot;)"><i class="fas fa-times"></i> Cancel</button>' +
                    '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>' +
                '</div>' +
                '</form>';
            attachFormSubmitHandler(container.querySelector('form'));
        } else if (type === 'package') {
            container.innerHTML = 
                '<form method="POST" action="process_admin_action.php" id="edit-package-form">' +
                '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                '<input type="hidden" name="action" value="update_package">' +
                '<input type="hidden" name="id" value="' + itemId + '">' +
                '<div class="form-group">' +
                    '<label class="form-label">Package Name *</label>' +
                    '<input type="text" name="name" class="form-input" required value="' + escapeHtml(data.name || '') + '">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Description</label>' +
                    '<textarea name="description" class="form-textarea" rows="3">' + escapeHtml(data.description || '') + '</textarea>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Price ($)</label>' +
                        '<input type="number" name="price" class="form-input" step="0.01" min="0" value="' + (data.price || 0) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Credits/Sessions</label>' +
                        '<input type="number" name="credits" class="form-input" min="0" value="' + (data.credits || 0) + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Valid Days</label>' +
                        '<input type="number" name="valid_days" class="form-input" min="1" value="' + (data.valid_days || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Package Type</label>' +
                        '<select name="package_type" class="form-input">' +
                            '<option value="credits"' + (data.package_type == 'credits' ? ' selected' : '') + '>Credits</option>' +
                            '<option value="bundle"' + (data.package_type == 'bundle' ? ' selected' : '') + '>Bundle</option>' +
                            '<option value="subscription"' + (data.package_type == 'subscription' ? ' selected' : '') + '>Subscription</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Age Group</label>' +
                        '<input type="text" name="age_group" class="form-input" value="' + escapeHtml(data.age_group || '') + '" placeholder="e.g. U10, U12, Adult">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Skill Level</label>' +
                        '<input type="text" name="skill_level" class="form-input" value="' + escapeHtml(data.skill_level || '') + '" placeholder="e.g. Beginner, Advanced">' +
                    '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Store Credit ($)</label>' +
                        '<input type="number" name="store_credit" class="form-input" step="0.01" min="0" value="' + (data.store_credit || 0) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Status</label>' +
                        '<select name="is_active" class="form-input">' +
                            '<option value="1"' + (data.is_active == 1 ? ' selected' : '') + '>Active</option>' +
                            '<option value="0"' + (data.is_active == 0 ? ' selected' : '') + '>Inactive</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<div class="checklist-grid">' +
                        '<label class="checkbox-option">' +
                            '<input type="checkbox" name="show_on_landing" value="1"' + (data.show_on_landing == 1 ? ' checked' : '') + '>' +
                            '<span>Show on Landing Page</span>' +
                        '</label>' +
                        '<label class="checkbox-option">' +
                            '<input type="checkbox" name="enable_child_checkin" value="1"' + (data.enable_child_checkin == 1 ? ' checked' : '') + '>' +
                            '<span>Enable Child Check-in</span>' +
                        '</label>' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">' +
                    '<button type="button" class="btn btn-secondary" onclick="closeModal(&quot;edit-package-modal&quot;)"><i class="fas fa-times"></i> Cancel</button>' +
                    '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>' +
                '</div>' +
                '</form>';
            attachFormSubmitHandler(container.querySelector('form'));
        } else if (type === 'discount') {
            container.innerHTML = 
                '<form method="POST" action="process_admin_action.php" id="edit-discount-form">' +
                '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                '<input type="hidden" name="action" value="update_discount">' +
                '<input type="hidden" name="id" value="' + itemId + '">' +
                '<div class="form-group">' +
                    '<label class="form-label">Discount Code *</label>' +
                    '<input type="text" name="code" class="form-input" required value="' + escapeHtml(data.code || '') + '">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Description</label>' +
                    '<textarea name="description" class="form-textarea" rows="2">' + escapeHtml(data.description || '') + '</textarea>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Discount Type</label>' +
                        '<select name="discount_type" class="form-input">' +
                            '<option value="percentage"' + (data.discount_type === 'percentage' ? ' selected' : '') + '>Percentage</option>' +
                            '<option value="fixed"' + (data.discount_type === 'fixed' ? ' selected' : '') + '>Fixed Amount</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Discount Value</label>' +
                        '<input type="number" name="discount_value" class="form-input" step="0.01" min="0" value="' + (data.discount_value || 0) + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Start Date</label>' +
                        '<input type="date" name="start_date" class="form-input" value="' + (data.start_date || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">End Date</label>' +
                        '<input type="date" name="end_date" class="form-input" value="' + (data.end_date || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Max Uses (0 = unlimited)</label>' +
                        '<input type="number" name="max_uses" class="form-input" min="0" value="' + (data.max_uses || 0) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Status</label>' +
                        '<select name="is_active" class="form-input">' +
                            '<option value="1"' + (data.is_active == 1 ? ' selected' : '') + '>Active</option>' +
                            '<option value="0"' + (data.is_active == 0 ? ' selected' : '') + '>Inactive</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">' +
                    '<button type="button" class="btn btn-secondary" onclick="closeModal(&quot;edit-discount-modal&quot;)"><i class="fas fa-times"></i> Cancel</button>' +
                    '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>' +
                '</div>' +
                '</form>';
            attachFormSubmitHandler(container.querySelector('form'));
        } else if (type === 'merch-product') {
            // Build category options
            var categoryOptions = '<option value="">No Category</option>';
            editMerchCategories.forEach(function(cat) {
                var selected = data.category_id == cat.id ? ' selected' : '';
                categoryOptions += '<option value="' + cat.id + '"' + selected + '>' + escapeHtml(cat.name) + '</option>';
            });
            
            // Build existing size rows
            var sizeRowsHtml = '';
            if (data.sizes && data.sizes.length > 0) {
                data.sizes.forEach(function(size) {
                    sizeRowsHtml += '<div class="size-stock-row">' +
                        '<input type="hidden" name="size_ids[]" value="' + size.id + '">' +
                        '<input type="text" name="sizes[]" class="form-input size-input" value="' + escapeHtml(size.size) + '" placeholder="Size" aria-label="Size name">' +
                        '<input type="number" name="quantities[]" class="form-input qty-input" min="0" value="' + (size.quantity || 0) + '" placeholder="Qty" aria-label="Quantity">' +
                        '<button type="button" class="btn-action danger remove-size-btn" onclick="removeSizeRow(this)" title="Remove size" aria-label="Remove this size"><i class="fas fa-trash"></i></button>' +
                    '</div>';
                });
            } else {
                sizeRowsHtml = '<div class="size-stock-row">' +
                    '<input type="hidden" name="size_ids[]" value="">' +
                    '<input type="text" name="sizes[]" class="form-input size-input" placeholder="Size (e.g., S, M, L, XL)" aria-label="Size name">' +
                    '<input type="number" name="quantities[]" class="form-input qty-input" min="0" value="0" placeholder="Qty" aria-label="Quantity">' +
                    '<button type="button" class="btn-action danger remove-size-btn" onclick="removeSizeRow(this)" title="Remove size" aria-label="Remove this size"><i class="fas fa-trash"></i></button>' +
                '</div>';
            }
            
            container.innerHTML = 
                '<form method="POST" action="process_merchandise_products.php" id="edit-merchandise-product-form" enctype="multipart/form-data">' +
                '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                '<input type="hidden" name="action" value="update">' +
                '<input type="hidden" name="id" value="' + itemId + '">' +
                '<div class="form-group">' +
                    '<label class="form-label">Product Name *</label>' +
                    '<input type="text" name="name" class="form-input" required value="' + escapeHtml(data.name || '') + '">' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">SKU</label>' +
                        '<input type="text" name="sku" class="form-input" value="' + escapeHtml(data.sku || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Category</label>' +
                        '<select name="category_id" class="form-input">' +
                            categoryOptions +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Description</label>' +
                    '<textarea name="description" class="form-textarea" rows="3">' + escapeHtml(data.description || '') + '</textarea>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Price ($) *</label>' +
                        '<input type="number" name="price" class="form-input" step="0.01" min="0" required value="' + (data.price || 0) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Cost Price ($)</label>' +
                        '<input type="number" name="cost_price" class="form-input" step="0.01" min="0" value="' + (data.cost_price || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Product Image</label>' +
                    '<input type="file" name="image" class="form-input" accept="image/*">' +
                    (data.image_url ? '<div style="margin-top: 8px;"><img src="' + escapeHtml(data.image_url) + '" style="max-width: 150px; max-height: 100px; border-radius: 8px; object-fit: cover;"></div>' : '') +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Size & Stock Options</label>' +
                    '<div class="size-stock-container" id="edit-merch-sizes-container">' +
                        sizeRowsHtml +
                    '</div>' +
                    '<button type="button" class="btn btn-secondary btn-sm add-size-btn" onclick="addEditMerchSizeRow()">' +
                        '<i class="fas fa-plus"></i> Add Size' +
                    '</button>' +
                    '<small class="form-help">Add different sizes with their stock quantities. Leave empty for products without sizes.</small>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label class="form-label">Track Inventory</label>' +
                        '<select name="track_inventory" class="form-input">' +
                            '<option value="1"' + (data.track_inventory == 1 ? ' selected' : '') + '>Yes - Track stock levels</option>' +
                            '<option value="0"' + (data.track_inventory == 0 ? ' selected' : '') + '>No - Unlimited stock</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label class="form-label">Status</label>' +
                        '<select name="is_active" class="form-input">' +
                            '<option value="1"' + (data.is_active == 1 ? ' selected' : '') + '>Active</option>' +
                            '<option value="0"' + (data.is_active == 0 ? ' selected' : '') + '>Inactive</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="modal-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">' +
                    '<button type="button" class="btn btn-secondary" onclick="closeModal(&quot;edit-merchandise-product-modal&quot;)"><i class="fas fa-times"></i> Cancel</button>' +
                    '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>' +
                '</div>' +
                '</form>';
            attachFormSubmitHandler(container.querySelector('form'));
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Helper function to attach form submit handler
    function attachFormSubmitHandler(form) {
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(form);
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn ? submitBtn.innerHTML : '';
            
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
            }
            
            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
                if (data.success) {
                    persistToast(data.message || 'Operation completed successfully', 'success');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(function(err) {
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
                console.error(err);
                alert('Error saving changes. Please try again.');
            });
        });
    }
    
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
            
            fetch(form.getAttribute('action'), {
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
                
                // Try to parse as JSON anyway - some servers don't set content-type properly
                return response.text().then(function(text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        // If we got here and response was ok, it might be a redirect or non-JSON success
                        // Check if it looks like a successful HTML redirect
                        if (text.includes('status=success') || text.includes('Operation completed')) {
                            return { success: true, message: 'Operation completed successfully!' };
                        }
                        return { success: false, message: 'Unexpected response from server. Please refresh and try again.' };
                    }
                });
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
                    if (modal && modal.id.includes('program')) {
                        currentTab = 'programs_camps';
                    } else if (modal && modal.id.includes('package')) {
                        currentTab = 'packages';
                    } else if (modal && modal.id.includes('discount')) {
                        currentTab = 'discounts';
                    } else if (modal && modal.id.includes('merchandise')) {
                        currentTab = 'merchandise';
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
        // Reset size rows to single empty row
        var sizesContainer = modal.querySelector('.size-stock-container');
        if (sizesContainer) {
            sizesContainer.innerHTML = '<div class="size-stock-row">' +
                '<input type="text" name="sizes[]" class="form-input size-input" placeholder="Size (e.g., S, M, L, XL)" aria-label="Size name">' +
                '<input type="number" name="quantities[]" class="form-input qty-input" min="0" value="0" placeholder="Qty" aria-label="Quantity">' +
                '<button type="button" class="btn-action danger remove-size-btn" onclick="removeSizeRow(this)" title="Remove size" aria-label="Remove this size"><i class="fas fa-trash"></i></button>' +
            '</div>';
        }
    }
}

// Merchandise size/stock row management
function addMerchSizeRow(containerId) {
    var container = document.getElementById(containerId);
    var newRow = document.createElement('div');
    newRow.className = 'size-stock-row';
    
    var sizeInput = document.createElement('input');
    sizeInput.type = 'text';
    sizeInput.name = 'sizes[]';
    sizeInput.className = 'form-input size-input';
    sizeInput.placeholder = 'Size (e.g., S, M, L, XL)';
    sizeInput.setAttribute('aria-label', 'Size name');
    
    var qtyInput = document.createElement('input');
    qtyInput.type = 'number';
    qtyInput.name = 'quantities[]';
    qtyInput.className = 'form-input qty-input';
    qtyInput.min = '0';
    qtyInput.value = '0';
    qtyInput.placeholder = 'Qty';
    qtyInput.setAttribute('aria-label', 'Quantity');
    
    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-action danger remove-size-btn';
    removeBtn.title = 'Remove size';
    removeBtn.setAttribute('aria-label', 'Remove this size');
    removeBtn.onclick = function() { removeSizeRow(this); };
    removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
    
    newRow.appendChild(sizeInput);
    newRow.appendChild(qtyInput);
    newRow.appendChild(removeBtn);
    container.appendChild(newRow);
}

function removeSizeRow(btn) {
    var row = btn.closest('.size-stock-row');
    if (row) {
        row.remove();
    }
}

function addEditMerchSizeRow() {
    var container = document.getElementById('edit-merch-sizes-container');
    var newRow = document.createElement('div');
    newRow.className = 'size-stock-row';
    
    var sizeIdInput = document.createElement('input');
    sizeIdInput.type = 'hidden';
    sizeIdInput.name = 'size_ids[]';
    sizeIdInput.value = '';
    
    var sizeInput = document.createElement('input');
    sizeInput.type = 'text';
    sizeInput.name = 'sizes[]';
    sizeInput.className = 'form-input size-input';
    sizeInput.placeholder = 'Size (e.g., S, M, L, XL)';
    sizeInput.setAttribute('aria-label', 'Size name');
    
    var qtyInput = document.createElement('input');
    qtyInput.type = 'number';
    qtyInput.name = 'quantities[]';
    qtyInput.className = 'form-input qty-input';
    qtyInput.min = '0';
    qtyInput.value = '0';
    qtyInput.placeholder = 'Qty';
    qtyInput.setAttribute('aria-label', 'Quantity');
    
    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-action danger remove-size-btn';
    removeBtn.title = 'Remove size';
    removeBtn.setAttribute('aria-label', 'Remove this size');
    removeBtn.onclick = function() { removeSizeRow(this); };
    removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
    
    newRow.appendChild(sizeIdInput);
    newRow.appendChild(sizeInput);
    newRow.appendChild(qtyInput);
    newRow.appendChild(removeBtn);
    container.appendChild(newRow);
}

// Session date management
// ===== Arctic Calendar Date Picker =====
var sessionCalLocations = <?= json_encode($locations) ?>;
var sessionCalTeams = <?= json_encode($teams) ?>;

// Reusable calendar factory
function ArcticCalendar(config) {
    var self = this;
    this.containerId = config.containerId;
    this.daysId = config.daysId;
    this.titleId = config.titleId;
    this.datesContainerId = config.datesContainerId;
    this.emptyId = config.emptyId;
    this.fieldPrefix = config.fieldPrefix || 'session_dates';
    this.defaultStartTime = config.defaultStartTime || '09:00';
    this.defaultEndTime = config.defaultEndTime || '10:00';
    this.showTeam = config.showTeam !== false;
    this.selectedDates = {}; // { 'YYYY-MM-DD': index }
    this.dateIndex = 0;
    
    var now = new Date();
    this.currentMonth = now.getMonth();
    this.currentYear = now.getFullYear();
    
    this.render = function() {
        var titleEl = document.getElementById(self.titleId);
        var daysEl = document.getElementById(self.daysId);
        if (!titleEl || !daysEl) return;
        
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        titleEl.textContent = months[self.currentMonth] + ' ' + self.currentYear;
        
        var firstDay = new Date(self.currentYear, self.currentMonth, 1).getDay();
        var daysInMonth = new Date(self.currentYear, self.currentMonth + 1, 0).getDate();
        var today = new Date();
        today.setHours(0,0,0,0);
        
        var html = '';
        // Empty cells before first day
        for (var e = 0; e < firstDay; e++) {
            html += '<button type="button" class="arctic-cal-day empty" disabled></button>';
        }
        
        for (var d = 1; d <= daysInMonth; d++) {
            var dateObj = new Date(self.currentYear, self.currentMonth, d);
            var dateStr = self.formatDate(dateObj);
            var isPast = dateObj < today;
            var isToday = dateObj.getTime() === today.getTime();
            var isSelected = self.selectedDates.hasOwnProperty(dateStr);
            
            var cls = 'arctic-cal-day';
            if (isPast) cls += ' disabled';
            if (isToday) cls += ' today';
            if (isSelected) cls += ' selected';
            
            if (isPast) {
                html += '<button type="button" class="' + cls + '" disabled>' + d + '</button>';
            } else {
                html += '<button type="button" class="' + cls + '" data-date="' + dateStr + '" onclick="' + self.containerId + 'Cal.toggleDate(\'' + dateStr + '\')">' + d + '</button>';
            }
        }
        
        daysEl.innerHTML = html;
    };
    
    this.formatDate = function(d) {
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    };
    
    this.formatDisplayDate = function(dateStr) {
        var parts = dateStr.split('-');
        var d = new Date(parts[0], parts[1] - 1, parts[2]);
        var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    };
    
    this.toggleDate = function(dateStr) {
        if (self.selectedDates.hasOwnProperty(dateStr)) {
            // Deselect
            self.removeDateEntry(dateStr);
            delete self.selectedDates[dateStr];
        } else {
            // Select
            self.dateIndex++;
            self.selectedDates[dateStr] = self.dateIndex;
            self.addDateEntry(dateStr, self.dateIndex);
        }
        self.render();
        self.updateEmpty();
    };
    
    this.addDateEntry = function(dateStr, idx) {
        var container = document.getElementById(self.datesContainerId);
        var locOptions = '<option value="">Default Location</option>';
        sessionCalLocations.forEach(function(loc) {
            locOptions += '<option value="' + loc.id + '">' + (loc.name || '') + (loc.city ? ' - ' + loc.city : '') + '</option>';
        });
        
        var teamHtml = '';
        if (self.showTeam) {
            var teamOptions = '<option value="">All Athletes</option>';
            sessionCalTeams.forEach(function(team) {
                teamOptions += '<option value="' + team.id + '">' + (team.name || '') + '</option>';
            });
            teamHtml = '<div class="form-group" style="flex: 1; min-width: 130px;">' +
                '<label class="form-label" style="font-size:12px;">Team</label>' +
                '<select name="' + self.fieldPrefix + '[' + idx + '][team_id]" class="form-input" style="font-size:13px;">' + teamOptions + '</select>' +
            '</div>';
        }
        
        var entry = document.createElement('div');
        entry.className = 'session-date-entry';
        entry.setAttribute('data-date', dateStr);
        entry.innerHTML = '<input type="hidden" name="' + self.fieldPrefix + '[' + idx + '][date]" value="' + dateStr + '">' +
            '<button type="button" class="remove-date-btn" onclick="' + self.containerId + 'Cal.toggleDate(\'' + dateStr + '\')"><i class="fas fa-times"></i></button>' +
            '<div class="date-label"><i class="fas fa-calendar-day"></i> ' + self.formatDisplayDate(dateStr) + '</div>' +
            '<div class="date-fields">' +
                '<div class="form-group" style="flex: 0 0 110px;">' +
                    '<label class="form-label" style="font-size:12px;">Start Time</label>' +
                    '<input type="time" name="' + self.fieldPrefix + '[' + idx + '][start_time]" class="form-input" value="' + self.defaultStartTime + '" style="font-size:13px;">' +
                '</div>' +
                '<div class="form-group" style="flex: 0 0 110px;">' +
                    '<label class="form-label" style="font-size:12px;">End Time</label>' +
                    '<input type="time" name="' + self.fieldPrefix + '[' + idx + '][end_time]" class="form-input" value="' + self.defaultEndTime + '" style="font-size:13px;">' +
                '</div>' +
                '<div class="form-group" style="flex: 1; min-width: 150px;">' +
                    '<label class="form-label" style="font-size:12px;">Location</label>' +
                    '<select name="' + self.fieldPrefix + '[' + idx + '][location_id]" class="form-input" style="font-size:13px;">' + locOptions + '</select>' +
                '</div>' +
                teamHtml +
            '</div>';
        
        // Insert sorted by date
        var entries = container.querySelectorAll('.session-date-entry');
        var inserted = false;
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].getAttribute('data-date') > dateStr) {
                container.insertBefore(entry, entries[i]);
                inserted = true;
                break;
            }
        }
        if (!inserted) container.appendChild(entry);
    };
    
    this.removeDateEntry = function(dateStr) {
        var container = document.getElementById(self.datesContainerId);
        var entry = container.querySelector('.session-date-entry[data-date="' + dateStr + '"]');
        if (entry) entry.remove();
    };
    
    this.updateEmpty = function() {
        var emptyEl = document.getElementById(self.emptyId);
        if (emptyEl) {
            emptyEl.style.display = Object.keys(self.selectedDates).length === 0 ? 'block' : 'none';
        }
    };
    
    this.nav = function(dir) {
        self.currentMonth += dir;
        if (self.currentMonth > 11) { self.currentMonth = 0; self.currentYear++; }
        if (self.currentMonth < 0) { self.currentMonth = 11; self.currentYear--; }
        self.render();
    };
    
    // Initial render
    this.render();
}

// Session calendar instance
var sessionCal = new ArcticCalendar({
    containerId: 'session',
    daysId: 'session-cal-days',
    titleId: 'session-cal-title',
    datesContainerId: 'session-dates-container',
    emptyId: 'session-dates-empty',
    fieldPrefix: 'session_dates',
    defaultStartTime: '09:00',
    defaultEndTime: '10:00',
    showTeam: true
});
// Expose for onclick
window.sessionCal = sessionCal;
function sessionCalNav(dir) { sessionCal.nav(dir); }

// Program calendar instance (initialized when tab opens)
var programCal = new ArcticCalendar({
    containerId: 'program',
    daysId: 'program-cal-days',
    titleId: 'program-cal-title',
    datesContainerId: 'program-dates-container',
    emptyId: 'program-dates-empty',
    fieldPrefix: 'program_dates',
    defaultStartTime: '09:00',
    defaultEndTime: '17:00',
    showTeam: false
});
window.programCal = programCal;
function programCalNav(dir) { programCal.nav(dir); }

// Keep old function names for backwards compatibility (no-ops since calendar handles it)
function addSessionDate() {}
function removeSessionDate() {}

// Program / Camp modal helpers
function toggleProgramFields() {
    var typeSelect = document.getElementById('programTypeSelect');
    if (!typeSelect) return;
    var isCamp = typeSelect.value === 'camp';
    var campSection = document.getElementById('programCampDates');
    var multiWeekSection = document.getElementById('programMultiWeekDates');
    // Hide old camp date range - calendar picker is used for both types
    if (campSection) campSection.style.display = 'none';
    // Show calendar for both camp and multi_week types
    if (multiWeekSection) {
        multiWeekSection.style.display = 'block';
        programCal.render();
    }
}

function addProgramDate() {}
function removeProgramDate() {}

// Package type toggle
function togglePackageTypeFields() {
    var packageType = document.getElementById('package-type-select');
    if (!packageType) return;
    
    var val = packageType.value;
    
    var creditsRow = document.getElementById('credits-count-row');
    
    // Simple toggle: for bundled packages, credits are optional
    // For credit packages, credits are required
    if (creditsRow) {
        var creditsInput = creditsRow.querySelector('input[name="credits"]');
        if (creditsInput) {
            creditsInput.required = (val === 'credits');
        }
        // Show/hide based on package type
        creditsRow.style.display = (val === 'credits') ? 'block' : 'none';
    }
    
    // Dollar value row
    var dollarValueRow = document.getElementById('dollar-value-row');
    if (dollarValueRow) {
        dollarValueRow.style.display = (val === 'dollar_value') ? 'block' : 'none';
        var storeCreditsInput = dollarValueRow.querySelector('input[name="store_credit"]');
        if (storeCreditsInput) {
            storeCreditsInput.required = (val === 'dollar_value');
        }
    }
    
    // Bundled sessions row
    var bundledSessionsRow = document.getElementById('bundled-sessions-row');
    if (bundledSessionsRow) {
        bundledSessionsRow.style.display = (val === 'bundled') ? 'block' : 'none';
    }
    
    // Camp fields row
    var campFieldsRow = document.getElementById('camp-fields-row');
    if (campFieldsRow) {
        campFieldsRow.style.display = (val === 'camp' || val === 'multi_week') ? 'block' : 'none';
    }
    
    // Multi-week fields row
    var multiWeekFieldsRow = document.getElementById('multi-week-fields-row');
    if (multiWeekFieldsRow) {
        multiWeekFieldsRow.style.display = (val === 'multi_week') ? 'block' : 'none';
    }
}

// Discount type toggle
function toggleDiscountTypeFields() {
    var discountType = document.getElementById('discount-type-select');
    if (!discountType) return;
    
    var valueLabel = document.getElementById('discount-value-label');
    if (!valueLabel) return;
    
    if (discountType.value === 'percentage') {
        valueLabel.textContent = 'Percentage (%) *';
    } else {
        valueLabel.textContent = 'Amount ($) *';
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
