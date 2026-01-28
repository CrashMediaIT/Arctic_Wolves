<?php
// Travel Parent Page with Tabs
$tab = $_GET['page'] ?? 'travel';
if ($tab === 'travel') $tab = 'mileage'; // Default tab
?>

<style>
/* Travel Tabs Navigation - Financial Reports Hub Style */
.travel-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.travel-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.travel-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.travel-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.travel-tab i { font-size: 16px; }

/* Tab Content Container */
.travel-tab-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-plane"></i> Travel</h1>
    <p class="page-description">Track travel expenses and mileage for reimbursement</p>
</div>

<!-- Tabs Navigation -->
<div class="travel-tabs">
    <a href="?page=mileage" class="travel-tab <?= $tab === 'mileage' ? 'active' : '' ?>">
        <i class="fas fa-car"></i> Mileage
    </a>
</div>

<div class="travel-tab-content">
    <?php
    if ($tab === 'mileage') {
        include __DIR__ . '/travel_mileage.php';
    }
    ?>
</div>
