<?php
// Travel Parent Page with Tabs
$tab = $_GET['page'] ?? 'travel';
if ($tab === 'travel') $tab = 'mileage'; // Default tab
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-plane"></i> Travel</h1>
    <p class="page-description">Track travel expenses and mileage for reimbursement</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <a href="?page=mileage" class="page-tab <?= $tab === 'mileage' ? 'active' : '' ?>">
        <i class="fas fa-car"></i> Mileage
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'mileage') {
        include __DIR__ . '/travel_mileage.php';
    }
    ?>
</div>
