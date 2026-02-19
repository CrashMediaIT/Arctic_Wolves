<?php
// views/packages.php - Browse and purchase session packages
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'security.php';

// Get user info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Get active packages
$stmt = $pdo->prepare("
    SELECT p.*, 
           ag.name as age_group_name,
           sl.name as skill_level_name,
           (SELECT COUNT(*) FROM package_sessions WHERE package_id = p.id) as session_count
    FROM packages p
    LEFT JOIN age_groups ag ON p.age_group_id = ag.id
    LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
    WHERE p.is_active = 1
    ORDER BY p.package_type, p.price
");
$stmt->execute();
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's current package credits
$credits_stmt = $pdo->prepare("
    SELECT upc.*, p.name as package_name
    FROM user_package_credits upc
    JOIN packages p ON upc.package_id = p.id
    WHERE upc.user_id = ? AND upc.credits_remaining > 0 AND upc.expiry_date >= CURDATE()
    ORDER BY upc.expiry_date ASC
");
$credits_stmt->execute([$user_id]);
$user_credits = $credits_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tax settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$tax_rate = floatval($settings['tax_rate'] ?? 13.00);
$tax_name = $settings['tax_name'] ?? 'HST';
?>

<div class="packages-container">
    <h2><i class="fas fa-box"></i> Session Packages</h2>
    
    <?php if (!empty($user_credits)): ?>
    <div class="credit-summary">
        <h3>Your Active Credits</h3>
        <div class="credits-grid">
            <?php foreach ($user_credits as $credit): ?>
                <div class="credit-card">
                    <h4><?php echo htmlspecialchars($credit['package_name']); ?></h4>
                    <div class="credit-balance">
                        <span class="credits"><?php echo $credit['credits_remaining']; ?></span> sessions remaining
                    </div>
                    <div class="credit-expiry">
                        Expires: <?php echo date('M j, Y', strtotime($credit['expiry_date'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="package-types">
        <h3>Available Packages</h3>
        
        <div class="package-filter">
            <button class="filter-btn active" data-type="all">All Packages</button>
            <button class="filter-btn" data-type="credits">Credit Packages</button>
            <button class="filter-btn" data-type="bundled">Bundled Packages</button>
            <button class="filter-btn" data-type="camp">Camps</button>
            <button class="filter-btn" data-type="multi_week">Multi-Week Programs</button>
        </div>
        
        <div class="packages-grid">
            <?php foreach ($packages as $package): 
                $price_with_tax = $package['price'] * (1 + $tax_rate / 100);
            ?>
                <div class="package-card" data-type="<?php echo $package['package_type']; ?>">
                    <div class="package-header <?php echo $package['package_type']; ?>">
                        <h3><?php echo htmlspecialchars($package['name']); ?></h3>
                        <div class="package-type-badge">
                            <?php echo ucfirst(str_replace('_', ' ', $package['package_type'])); ?>
                        </div>
                    </div>
                    
                    <div class="package-body">
                        <div class="package-description">
                            <?php echo nl2br(htmlspecialchars($package['description'])); ?>
                        </div>
                        
                        <div class="package-details">
                            <?php if ($package['package_type'] === 'credits'): ?>
                                <div class="detail-item">
                                    <i class="fas fa-ticket-alt"></i>
                                    <span><?php echo $package['credits']; ?> Session Credits</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Valid for <?php echo $package['valid_days']; ?> days</span>
                                </div>
                            <?php elseif ($package['package_type'] === 'camp'): ?>
                                <?php if ($package['camp_start_date'] && $package['camp_end_date']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-campground"></i>
                                    <span><?php echo date('M j', strtotime($package['camp_start_date'])); ?> - <?php echo date('M j, Y', strtotime($package['camp_end_date'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($package['daily_start_time'] && $package['daily_end_time']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($package['daily_start_time'])); ?> - <?php echo date('g:i A', strtotime($package['daily_end_time'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php
                                // Get camp daily schedule
                                $camp_sched = $pdo->prepare("SELECT * FROM camp_daily_schedules WHERE package_id = ? ORDER BY schedule_date");
                                $camp_sched->execute([$package['id']]);
                                $camp_days = $camp_sched->fetchAll(PDO::FETCH_ASSOC);
                                if (!empty($camp_days)):
                                ?>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-day"></i>
                                    <span><?php echo count($camp_days); ?> day program</span>
                                </div>
                                <?php endif; ?>
                            <?php elseif ($package['package_type'] === 'multi_week'): ?>
                                <?php
                                $mw_dates = $pdo->prepare("SELECT * FROM multiweek_program_dates WHERE package_id = ? ORDER BY session_date");
                                $mw_dates->execute([$package['id']]);
                                $program_dates = $mw_dates->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo count($program_dates); ?> sessions over multiple weeks</span>
                                </div>
                                <?php if (!empty($program_dates)): ?>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($program_dates[0]['start_time'])); ?> - <?php echo date('g:i A', strtotime($program_dates[0]['end_time'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($package['allow_individual_sessions']): ?>
                                <div class="detail-item" style="color: #10b981;">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Individual sessions available</span>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="detail-item">
                                    <i class="fas fa-list"></i>
                                    <span><?php echo $package['session_count']; ?> Specific Sessions</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($package['age_group_name']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo htmlspecialchars($package['age_group_name']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($package['skill_level_name']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo htmlspecialchars($package['skill_level_name']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="package-pricing">
                            <div class="price-main">$<?php echo number_format($package['price'], 2); ?></div>
                            <div class="price-tax">+ $<?php echo number_format($package['price'] * $tax_rate / 100, 2); ?> <?php echo $tax_name; ?></div>
                            <div class="price-total">Total: $<?php echo number_format($price_with_tax, 2); ?></div>
                            <?php if ($package['package_type'] === 'credits' && $package['credits'] > 0): ?>
                                <div class="price-per-session">
                                    $<?php echo number_format($price_with_tax / $package['credits'], 2); ?> per session
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="package-footer">
                        <form action="process_purchase_package.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                            
                            <?php 
                            // Show add-on options for camp and multi-week packages
                            if (in_array($package['package_type'], ['camp', 'multi_week'])):
                                $addons_stmt = $pdo->prepare("SELECT * FROM camp_add_ons WHERE package_id = ? ORDER BY display_order");
                                $addons_stmt->execute([$package['id']]);
                                $addons = $addons_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($addons)):
                            ?>
                            <div class="addon-options">
                                <h4 style="margin-bottom: 10px; color: #333;"><i class="fas fa-puzzle-piece"></i> Add-On Options</h4>
                                <?php foreach ($addons as $addon): ?>
                                <label class="addon-option">
                                    <input type="checkbox" name="selected_addons[]" value="<?php echo $addon['id']; ?>" 
                                           <?php echo $addon['is_default'] ? 'checked' : ''; ?>>
                                    <div class="addon-info">
                                        <span class="addon-name"><?php echo htmlspecialchars($addon['name']); ?></span>
                                        <?php if ($addon['description']): ?>
                                        <span class="addon-desc"><?php echo htmlspecialchars($addon['description']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($addon['price'] > 0): ?>
                                        <span class="addon-price">+ $<?php echo number_format($addon['price'], 2); ?></span>
                                        <?php else: ?>
                                        <span class="addon-price" style="color: #10b981;">Included</span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php 
                                endif;
                            endif; 
                            ?>
                            
                            <?php if ($user_role === 'parent'): ?>
                                <div class="athlete-selector">
                                    <label>Purchase for:</label>
                                    <?php
                                    $athletes_stmt = $pdo->prepare("
                                        SELECT u.id, u.first_name, u.last_name 
                                        FROM users u
                                        JOIN managed_athletes ma ON u.id = ma.athlete_id
                                        WHERE ma.parent_id = ? AND ma.can_book = 1
                                    ");
                                    $athletes_stmt->execute([$user_id]);
                                    $athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    $athletes = decryptUserRows($athletes);
                                    
                                    foreach ($athletes as $athlete): ?>
                                        <label class="athlete-option">
                                            <input type="checkbox" name="athlete_ids[]" value="<?php echo $athlete['id']; ?>">
                                            <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn-purchase">
                                <i class="fas fa-shopping-cart"></i> 
                                <?php 
                                if ($package['package_type'] === 'camp') echo 'Register for Camp';
                                elseif ($package['package_type'] === 'multi_week') echo 'Enroll in Program';
                                else echo 'Purchase Package';
                                ?>
                            </button>
                        </form>
                        
                        <?php if ($package['package_type'] === 'bundled'): ?>
                            <a href="#" class="view-sessions-link" data-package-id="<?php echo $package['id']; ?>">
                                View Included Sessions
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($package['package_type'] === 'camp' && !empty($camp_days)): ?>
                            <a href="#" class="view-sessions-link" onclick="toggleScheduleDetail(<?php echo $package['id']; ?>); return false;">
                                <i class="fas fa-calendar-day"></i> View Daily Schedule
                            </a>
                            <div id="schedule-detail-<?php echo $package['id']; ?>" class="schedule-detail" style="display: none;">
                                <?php foreach ($camp_days as $day): ?>
                                <div class="schedule-day-item">
                                    <strong><?php echo date('l, M j', strtotime($day['schedule_date'])); ?></strong>
                                    <span><?php echo date('g:i A', strtotime($day['start_time'])); ?> - <?php echo date('g:i A', strtotime($day['end_time'])); ?></span>
                                    <?php if ($day['title']): ?>
                                    <em><?php echo htmlspecialchars($day['title']); ?></em>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($package['package_type'] === 'multi_week' && !empty($program_dates)): ?>
                            <a href="#" class="view-sessions-link" onclick="toggleScheduleDetail(<?php echo $package['id']; ?>); return false;">
                                <i class="fas fa-calendar-alt"></i> View All Dates
                            </a>
                            <div id="schedule-detail-<?php echo $package['id']; ?>" class="schedule-detail" style="display: none;">
                                <?php foreach ($program_dates as $pd): ?>
                                <div class="schedule-day-item">
                                    <strong><?php echo date('l, M j', strtotime($pd['session_date'])); ?></strong>
                                    <span><?php echo date('g:i A', strtotime($pd['start_time'])); ?> - <?php echo date('g:i A', strtotime($pd['end_time'])); ?></span>
                                    <?php if ($pd['title']): ?>
                                    <em><?php echo htmlspecialchars($pd['title']); ?></em>
                                    <?php endif; ?>
                                    <?php if ($pd['individual_price'] && $package['allow_individual_sessions']): ?>
                                    <span class="individual-price">Individual: $<?php echo number_format($pd['individual_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.packages-container {
    padding: 20px;
}

.credit-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.credits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 12px;
}

.credit-card {
    background: white;
    padding: 16px;
    border-radius: 8px;
    border-left: 4px solid var(--primary, #7000a4);
}

.credit-balance {
    font-size: 24px;
    color: var(--primary, #7000a4);
    margin: 10px 0;
}

.credit-balance .credits {
    font-weight: bold;
    font-size: 32px;
}

.package-filter {
    display: flex;
    gap: 10px;
    margin: 20px 0;
}

.filter-btn {
    padding: 10px 20px;
    border: 2px solid #ddd;
    background: white;
    cursor: pointer;
    border-radius: 5px;
    transition: all 0.3s;
}

.filter-btn.active, .filter-btn:hover {
    background: var(--primary, #7000a4);
    color: white;
    border-color: var(--primary, #7000a4);
}

.packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.package-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s;
}

.package-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.package-header {
    padding: 20px;
    color: white;
    position: relative;
}

.package-header.credits {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.package-header.bundled {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.package-type-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255,255,255,0.3);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    text-transform: uppercase;
}

.package-body {
    padding: 20px;
}

.package-details {
    margin: 12px 0;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    color: #555;
}

.detail-item i {
    color: var(--primary, #7000a4);
    width: 20px;
}

.package-pricing {
    margin: 20px 0;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
}

.price-main {
    font-size: 36px;
    font-weight: bold;
    color: #333;
}

.price-tax {
    color: #666;
    font-size: 14px;
}

.price-total {
    font-size: 20px;
    font-weight: bold;
    color: var(--primary, #7000a4);
    margin-top: 5px;
}

.price-per-session {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    font-style: italic;
}

.package-footer {
    padding: 20px;
    border-top: 1px solid #eee;
}

.athlete-selector {
    margin-bottom: 12px;
}

.athlete-option {
    display: block;
    padding: 8px;
    cursor: pointer;
}

.btn-purchase {
    width: 100%;
    padding: 12px;
    background: var(--primary, #7000a4);
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-purchase:hover {
    background: #e64400;
}

.view-sessions-link {
    display: block;
    text-align: center;
    margin-top: 10px;
    color: var(--primary, #7000a4);
    text-decoration: none;
}

.package-header.camp {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.package-header.multi_week {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.addon-options {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.addon-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.addon-option:hover {
    border-color: var(--primary, #7000a4);
    background: #faf5ff;
}

.addon-option input[type="checkbox"] {
    margin-top: 3px;
}

.addon-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.addon-name {
    font-weight: 600;
    color: #333;
}

.addon-desc {
    font-size: 12px;
    color: #666;
}

.addon-price {
    font-size: 13px;
    font-weight: 600;
    color: var(--primary, #7000a4);
}

.schedule-detail {
    margin-top: 10px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    max-height: 300px;
    overflow-y: auto;
}

.schedule-day-item {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    color: #555;
    align-items: center;
}

.schedule-day-item:last-child {
    border-bottom: none;
}

.schedule-day-item strong {
    color: #333;
    min-width: 140px;
}

.individual-price {
    background: #10b981;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .packages-grid {
        grid-template-columns: 1fr;
    }
    
    .credits-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Package filtering
    const filterButtons = document.querySelectorAll('.filter-btn');
    const packageCards = document.querySelectorAll('.package-card');
    
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filterType = this.dataset.type;
            
            packageCards.forEach(card => {
                if (filterType === 'all' || card.dataset.type === filterType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});

function toggleScheduleDetail(packageId) {
    var el = document.getElementById('schedule-detail-' + packageId);
    if (el) {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
}
</script>
