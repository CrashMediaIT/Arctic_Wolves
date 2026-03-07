<?php
// Personal Development Parent Page with Tabs
// Available to all users - shows hockey development programs
$tab = $_GET['page'] ?? 'personal_development';
if ($tab === 'personal_development') $tab = 'personal_development_programs'; // Default tab
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-hockey-puck"></i> Personal Development</h1>
    <p class="page-description">Browse and enroll in hockey development programs tailored to your growth</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <a href="?page=personal_development_programs" class="page-tab <?= $tab === 'personal_development_programs' ? 'active' : '' ?>">
        <i class="fas fa-skating"></i> Development Programs
    </a>
    <a href="?page=personal_development_my_program" class="page-tab <?= $tab === 'personal_development_my_program' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i> My Program
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'personal_development_programs') {
        include __DIR__ . '/personal_development_programs.php';
    } elseif ($tab === 'personal_development_my_program') {
        include __DIR__ . '/personal_development_my_program.php';
    }
    ?>
</div>
