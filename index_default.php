<?php
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Fetch landing page settings from database with fallback to defaults
$landing_settings = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'landing_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $landing_settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // Fallback to empty array if database error
        $landing_settings = [];
    }
}

// Helper function to get landing setting with fallback
function getLandingSetting($settings, $key, $default = '') {
    return (!empty($settings[$key])) ? $settings[$key] : $default;
}

// Default program data
$default_programs = [
    1 => [
        'title' => 'Player Dev',
        'image' => 'https://images.unsplash.com/photo-1580748141549-71748ddf0bdc?q=80&w=800',
        'tags' => 'Power Skating, Shooting',
        'description' => 'Forwards & Defense: Explosive edgework and shot mechanics.'
    ],
    2 => [
        'title' => 'Goalie Elite',
        'image' => 'https://images.unsplash.com/photo-1543326727-b5bf833b6c7a?q=80&w=800',
        'tags' => 'Positioning, Tracking',
        'description' => 'Crease management, angle control, and rebound psychology.'
    ],
    3 => [
        'title' => 'Conditioning',
        'image' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?q=80&w=800',
        'tags' => 'Strength, Power',
        'description' => 'Dryland training for endurance and explosive 60-minute power.'
    ],
    4 => [
        'title' => 'Nutrition',
        'image' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?q=80&w=800',
        'tags' => 'Protein, Recovery',
        'description' => 'Meal planning to fuel muscle growth and accelerate recovery.'
    ]
];

// Default standards data
$default_standards = [
    1 => ['label' => 'Ice Ratio', 'value' => '4:1 Player/Coach'],
    2 => ['label' => 'Technology', 'value' => 'Video Analysis'],
    3 => ['label' => 'Facility', 'value' => 'Pro-Grade Gym'],
    4 => ['label' => 'Methodology', 'value' => 'Periodization']
];

// Build program data with fallbacks
$programs = [];
for ($i = 1; $i <= 4; $i++) {
    $prefix = "landing_program_{$i}_";
    $programs[$i] = [
        'title' => getLandingSetting($landing_settings, $prefix . 'title', $default_programs[$i]['title']),
        'image' => getLandingSetting($landing_settings, $prefix . 'image', $default_programs[$i]['image']),
        'tags' => getLandingSetting($landing_settings, $prefix . 'tags', $default_programs[$i]['tags']),
        'description' => getLandingSetting($landing_settings, $prefix . 'description', $default_programs[$i]['description'])
    ];
}

// Build standards data with fallbacks
$standards = [];
for ($i = 1; $i <= 4; $i++) {
    $prefix = "landing_standard_{$i}_";
    $standards[$i] = [
        'label' => getLandingSetting($landing_settings, $prefix . 'label', $default_standards[$i]['label']),
        'value' => getLandingSetting($landing_settings, $prefix . 'value', $default_standards[$i]['value'])
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Arctic Wolves | Player Development</title>
    <meta name="description" content="Professional hockey development for players and goalies.">
    
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <nav class="container nav-flex">
            <div class="logo-area" style="display: flex; align-items: center; gap: 15px;">
                <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves Logo" style="height: 40px; width: auto;">
                
                <div>
                    <div class="logo-text">ARCTIC<span>WOLVES</span></div>
                    <div class="header-affiliation">Player Development</div>
                </div>
            </div>
            
            <div class="nav-menu">
                <a href="index.php">Home</a>
                <a href="sessions_public.php">Sessions</a>
                <a href="shop.php">Shop</a>
                <a href="shop_cart.php" style="position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                </a>
                <a href="login.php" class="nav-btn">Athlete Login</a>
            </div>
        </nav>
    </header>

    <section class="hero">
        <div class="scanline"></div>
        <div class="container hero-grid">
            <div class="hero-content">
                <a href="register.php" class="status-link">
                    <div class="status-indicator">
                        <span class="dot"></span> 
                        <span class="status-text">2026 Registration Open</span>
                    </div>
                </a>

                <h1>Arctic Wolves <br><span class="highlight">Player Development</span></h1>
                <p>Specialized on-ice and off-ice training protocols designed for competitive athletes seeking elite performance levels.</p>
                
                <div class="hero-actions">
                    <a href="#programs" class="btn-primary">View Programs</a>
                    <a href="register.php" class="btn-secondary">Register Now</a>
                </div>
            </div>
        </div>
    </section>

    <section id="programs" class="games-section">
        <div class="container">
            <div class="section-header">
                <h2>Training Programs</h2>
                <p>Four pillars of modern player development.</p>
            </div>

            <div class="programs-grid">
                
                <?php foreach ($programs as $program): ?>
                <div class="game-card">
                    <div class="card-img" style="background-image: url('<?= htmlspecialchars($program['image']) ?>');"></div>
                    <div class="card-body">
                        <h3><?= htmlspecialchars($program['title']) ?></h3>
                        <div class="tags"><?php 
                            $tags = array_filter(array_map('trim', explode(',', $program['tags'])), function($tag) { return $tag !== ''; });
                            foreach ($tags as $tag) {
                                echo '<span>' . htmlspecialchars($tag) . '</span>';
                            }
                        ?></div>
                        <p><?= htmlspecialchars($program['description']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>

            <div class="library-footer">
                <a href="register.php">
                    View Full Schedule & Availability <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section id="standards" class="specs-section">
        <div class="container specs-grid">
            <div class="specs-content">
                <span class="eyebrow">Elite Standards</span>
                <h2>The Science Behind The Sport</h2>
                <div class="spec-table">
                    <?php foreach ($standards as $standard): ?>
                    <div class="spec-row">
                        <span class="spec-label"><?= htmlspecialchars($standard['label']) ?></span>
                        <span class="spec-value"><?= htmlspecialchars($standard['value']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="panel-visual">
                <div class="panel-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Athlete Portal</h3>
                    <p>Track your workout progress, view ice schedules, and analyze video shifts through our custom dashboard.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="container footer-flex">
            <div class="footer-left">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" style="height: 30px; opacity: 0.8;">
                    <div class="logo-text" style="font-size: 1.2rem;">ARCTIC<span>WOLVES</span></div>
                </div>
                
                <p class="footer-desc">High-performance athletic development.</p>
                
                <div class="social-tray">
                    <a href="https://www.instagram.com/arcticwolveshockey/" target="_blank" rel="noopener noreferrer" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-right">
                <div class="footer-col">
                    <h4>Direct Contact</h4>
                    <a href="mailto:info@arcticwolves.ca" class="footer-email-link">info@arcticwolves.ca</a></a>
                </div>
                <div class="footer-col">
                    <h4>Account</h4>
                    <a href="login.php">Athlete Portal</a>
                    <a href="register.php">Registration</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container footer-bottom-flex">
                <p>&copy; 2026 Arctic Wolves Player Development. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
