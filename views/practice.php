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
    <a href="?page=practice_create" class="tab-link <?= $tab === 'practice_create' ? 'active' : '' ?>" data-tab="practice_create">
        <i class="fa-solid fa-plus-circle"></i> Create a Practice
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'practice_library') {
        include __DIR__ . '/practice_library.php';
    } elseif ($tab === 'practice_create' || $tab === 'create_practice') {
        include __DIR__ . '/practice_create.php';
    }
    ?>
</div>

<style>
.tab-navigation {
    display: flex;
    gap: 4px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 6px;
    margin-bottom: 24px;
}

.tab-link {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
    text-decoration: none;
    transition: all 0.3s ease;
}

.tab-link:hover {
    color: var(--text-white);
    background: var(--bg-main);
}

.tab-link.active {
    background: linear-gradient(135deg, var(--primary), var(--neon));
    color: #fff;
}

.tab-link i {
    font-size: 16px;
}

.page-tab-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
