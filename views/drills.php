<?php
// Drills Parent Page with Tabs
$tab = $_GET['page'] ?? 'drills';
if ($tab === 'drills') $tab = 'drill_library'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-clipboard-list"></i> Drills</h1>
    <p>Manage drill library, create new drills, and import from external sources</p>
</div>

<div class="tab-navigation">
    <a href="?page=drill_library" class="tab-link <?= $tab === 'drill_library' ? 'active' : '' ?>">
        <i class="fa-solid fa-book"></i> Library
    </a>
    <a href="?page=create_drill" class="tab-link <?= $tab === 'create_drill' ? 'active' : '' ?>">
        <i class="fa-solid fa-plus-circle"></i> Create a Drill
    </a>
    <a href="?page=import_drill" class="tab-link <?= $tab === 'import_drill' ? 'active' : '' ?>">
        <i class="fa-solid fa-download"></i> Import a Drill
    </a>
</div>

<div class="tab-content">
    <?php
    if ($tab === 'drill_library') {
        include 'drills_library.php';
    } elseif ($tab === 'create_drill') {
        include 'drills_create.php';
    } elseif ($tab === 'import_drill') {
        include 'drills_import.php';
    }
    ?>
</div>
