<?php
// Travel Parent Page with Tabs
$tab = $_GET['page'] ?? 'travel';
if ($tab === 'travel') $tab = 'mileage'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-plane"></i> Travel</h1>
    <p>Track travel expenses and mileage for reimbursement</p>
</div>

<div class="tab-navigation">
    <a href="?page=mileage" class="tab-link <?= $tab === 'mileage' ? 'active' : '' ?>">
        <i class="fa-solid fa-car"></i> Mileage
    </a>
</div>

<div class="tab-content">
    <?php
    if ($tab === 'mileage') {
        include 'travel_mileage.php';
    }
    ?>
</div>
