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
$settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
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
        </div>
        
        <div class="packages-grid">
            <?php foreach ($packages as $package): 
                $price_with_tax = $package['price'] * (1 + $tax_rate / 100);
            ?>
                <div class="package-card" data-type="<?php echo $package['package_type']; ?>">
                    <div class="package-header <?php echo $package['package_type']; ?>">
                        <h3><?php echo htmlspecialchars($package['name']); ?></h3>
                        <div class="package-type-badge">
                            <?php echo ucfirst($package['package_type']); ?>
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
                                    
                                    foreach ($athletes as $athlete): ?>
                                        <label class="athlete-option">
                                            <input type="checkbox" name="athlete_ids[]" value="<?php echo $athlete['id']; ?>">
                                            <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn-purchase">
                                <i class="fas fa-shopping-cart"></i> Purchase Package
                            </button>
                        </form>
                        
                        <?php if ($package['package_type'] === 'bundled'): ?>
                            <a href="#" class="view-sessions-link" data-package-id="<?php echo $package['id']; ?>">
                                View Included Sessions
                            </a>
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
    margin-bottom: 30px;
}

.credits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.credit-card {
    background: white;
    padding: 15px;
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
    margin: 15px 0;
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
    padding: 15px;
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
    margin-bottom: 15px;
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
</script>
