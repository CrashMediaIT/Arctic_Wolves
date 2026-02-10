<?php
// Drills Parent Page with Tabs
$tab = $_GET['page'] ?? 'drills';
if ($tab === 'drills') $tab = 'drill_library'; // Default tab
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Drills</h1>
    <p class="page-description">Manage drill library, create new drills, and import from external sources</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <a href="?page=drill_library" class="page-tab <?= $tab === 'drill_library' ? 'active' : '' ?>">
        <i class="fas fa-book"></i> Library
    </a>
    <a href="?page=create_drill" class="page-tab <?= $tab === 'create_drill' ? 'active' : '' ?>">
        <i class="fas fa-plus-circle"></i> Create a Drill
    </a>
    <a href="?page=import_drill" class="page-tab <?= $tab === 'import_drill' ? 'active' : '' ?>">
        <i class="fas fa-download"></i> Import a Drill
    </a>
    <a href="?page=export_import_drills" class="page-tab <?= $tab === 'export_import_drills' ? 'active' : '' ?>">
        <i class="fas fa-exchange-alt"></i> Export / Import All
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'drill_library') {
        include __DIR__ . '/drills_library.php';
    } elseif ($tab === 'create_drill') {
        include __DIR__ . '/drills_create.php';
    } elseif ($tab === 'import_drill') {
        include __DIR__ . '/drills_import.php';
    } elseif ($tab === 'export_import_drills') {
        include __DIR__ . '/drills_export_import.php';
    }
    ?>
</div>
