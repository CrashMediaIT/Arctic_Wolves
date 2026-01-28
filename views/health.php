<?php
// Health Parent Page with Tabs
$tab = $_GET['page'] ?? 'health';
if ($tab === 'health') $tab = 'strength_conditioning'; // Default tab
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-heart-pulse"></i> Health</h1>
    <p class="page-description">Track your fitness progress, nutrition plans, and workout routines</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <a href="?page=strength_conditioning" class="page-tab <?= $tab === 'strength_conditioning' ? 'active' : '' ?>">
        <i class="fas fa-dumbbell"></i> Strength & Conditioning
    </a>
    <a href="?page=nutrition" class="page-tab <?= $tab === 'nutrition' ? 'active' : '' ?>">
        <i class="fas fa-utensils"></i> Nutrition
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'strength_conditioning') {
        include __DIR__ . '/health_workouts.php';
    } elseif ($tab === 'nutrition') {
        include __DIR__ . '/health_nutrition.php';
    }
    ?>
</div>
