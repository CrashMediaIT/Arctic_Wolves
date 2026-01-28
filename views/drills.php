<?php
// Drills Parent Page with Tabs
$tab = $_GET['page'] ?? 'drills';
if ($tab === 'drills') $tab = 'drill_library'; // Default tab
?>

<style>
/* Drills Tabs Navigation - Financial Reports Hub Style */
.drills-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.drills-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.drills-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.drills-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.drills-tab i { font-size: 16px; }

/* Tab Content Container */
.drills-tab-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Drills</h1>
    <p class="page-description">Manage drill library, create new drills, and import from external sources</p>
</div>

<!-- Tabs Navigation -->
<div class="drills-tabs">
    <a href="?page=drill_library" class="drills-tab <?= $tab === 'drill_library' ? 'active' : '' ?>">
        <i class="fas fa-book"></i> Library
    </a>
    <a href="?page=create_drill" class="drills-tab <?= $tab === 'create_drill' ? 'active' : '' ?>">
        <i class="fas fa-plus-circle"></i> Create a Drill
    </a>
    <a href="?page=import_drill" class="drills-tab <?= $tab === 'import_drill' ? 'active' : '' ?>">
        <i class="fas fa-download"></i> Import a Drill
    </a>
</div>

<div class="drills-tab-content">
    <?php
    if ($tab === 'drill_library') {
        include __DIR__ . '/drills_library.php';
    } elseif ($tab === 'create_drill') {
        include __DIR__ . '/drills_create.php';
    } elseif ($tab === 'import_drill') {
        include __DIR__ . '/drills_import.php';
    }
    ?>
</div>
