<?php
// Practice Plans Parent Page with Tabs
$tab = $_GET['page'] ?? 'practice';
if ($tab === 'practice') $tab = 'practice_library'; // Default tab
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-file-lines"></i> Practice Plans</h1>
    <p class="page-description">Organize practice plans, view library, and create new training schedules</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <a href="?page=practice_library" class="page-tab <?= $tab === 'practice_library' ? 'active' : '' ?>">
        <i class="fas fa-book"></i> Library
    </a>
    <a href="?page=practice_create" class="page-tab <?= $tab === 'practice_create' ? 'active' : '' ?>">
        <i class="fas fa-plus-circle"></i> Create a Practice
    </a>
    <a href="?page=practice_import" class="page-tab <?= $tab === 'practice_import' ? 'active' : '' ?>">
        <i class="fas fa-download"></i> Import Practice Plan
    </a>
    <a href="?page=export_import_plans" class="page-tab <?= $tab === 'export_import_plans' ? 'active' : '' ?>">
        <i class="fas fa-exchange-alt"></i> Export / Import All
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'practice_library') {
        include __DIR__ . '/practice_library.php';
    } elseif ($tab === 'practice_create' || $tab === 'create_practice') {
        include __DIR__ . '/practice_create.php';
    } elseif ($tab === 'practice_import') {
        include __DIR__ . '/practice_import.php';
    } elseif ($tab === 'export_import_plans') {
        include __DIR__ . '/practice_export_import.php';
    }
    ?>
</div>
