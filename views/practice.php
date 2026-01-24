<?php
// Practice Plans Parent Page with Tabs
$tab = $_GET['page'] ?? 'practice';
if ($tab === 'practice') $tab = 'practice_library'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-file-lines"></i> Practice Plans</h1>
    <p>Organize practice plans, view library, and create new training schedules</p>
</div>

<div class="tab-navigation" data-component="TabNavigation">
    <a href="?page=practice_library" class="tab-link <?= $tab === 'practice_library' ? 'active' : '' ?>" data-tab="practice_library">
        <i class="fa-solid fa-book"></i> Library
    </a>
    <a href="?page=create_practice" class="tab-link <?= $tab === 'create_practice' ? 'active' : '' ?>" data-tab="create_practice">
        <i class="fa-solid fa-plus-circle"></i> Create a Practice
    </a>
</div>

<div class="tab-content">
    <?php
    if ($tab === 'practice_library') {
        include __DIR__ . '/practice_library.php';
    } elseif ($tab === 'create_practice') {
        include __DIR__ . '/practice_create.php';
    }
    ?>
</div>
