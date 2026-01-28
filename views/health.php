<?php
// Health Parent Page with Tabs
$tab = $_GET['page'] ?? 'health';
if ($tab === 'health') $tab = 'strength_conditioning'; // Default tab
?>

<style>
/* Health Tabs Navigation - Financial Reports Hub Style */
.health-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.health-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.health-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.health-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.health-tab i { font-size: 16px; }

/* Tab Content Container */
.health-tab-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-heart-pulse"></i> Health</h1>
    <p class="page-description">Track your fitness progress, nutrition plans, and workout routines</p>
</div>

<!-- Tabs Navigation -->
<div class="health-tabs">
    <a href="?page=strength_conditioning" class="health-tab <?= $tab === 'strength_conditioning' ? 'active' : '' ?>">
        <i class="fas fa-dumbbell"></i> Strength & Conditioning
    </a>
    <a href="?page=nutrition" class="health-tab <?= $tab === 'nutrition' ? 'active' : '' ?>">
        <i class="fas fa-utensils"></i> Nutrition
    </a>
</div>

<div class="health-tab-content">
    <?php
    if ($tab === 'strength_conditioning') {
        include __DIR__ . '/health_workouts.php';
    } elseif ($tab === 'nutrition') {
        include __DIR__ . '/health_nutrition.php';
    }
    ?>
</div>
