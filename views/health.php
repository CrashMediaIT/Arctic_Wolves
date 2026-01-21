<?php
// Health Parent Page with Tabs
$tab = $_GET['page'] ?? 'health';
if ($tab === 'health') $tab = 'strength_conditioning'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-heart-pulse"></i> Health</h1>
    <p>Track your fitness progress, nutrition plans, and workout routines</p>
</div>

<div class="tab-navigation">
    <a href="?page=strength_conditioning" class="tab-link <?= $tab === 'strength_conditioning' ? 'active' : '' ?>">
        <i class="fa-solid fa-dumbbell"></i> Strength & Conditioning
    </a>
    <a href="?page=nutrition" class="tab-link <?= $tab === 'nutrition' ? 'active' : '' ?>">
        <i class="fa-solid fa-utensils"></i> Nutrition
    </a>
</div>

<div class="tab-content">
    <?php
    if ($tab === 'strength_conditioning') {
        include 'health_workouts.php';
    } elseif ($tab === 'nutrition') {
        include 'health_nutrition.php';
    }
    ?>
</div>
