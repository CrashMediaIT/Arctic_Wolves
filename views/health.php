<?php
// Health Parent Page with Tabs
$tab = $_GET['page'] ?? 'health';
if ($tab === 'health') $tab = 'strength_conditioning'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-heart-pulse"></i> Health</h1>
    <p>Track your fitness progress, nutrition plans, and workout routines</p>
</div>

<div class="tab-navigation" data-component="TabNavigation">
    <a href="?page=strength_conditioning" class="tab-link <?= $tab === 'strength_conditioning' ? 'active' : '' ?>" data-tab="strength_conditioning">
        <i class="fa-solid fa-dumbbell"></i> Strength & Conditioning
    </a>
    <a href="?page=nutrition" class="tab-link <?= $tab === 'nutrition' ? 'active' : '' ?>" data-tab="nutrition">
        <i class="fa-solid fa-utensils"></i> Nutrition
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
