<?php
// Sessions Parent Page with Tabs
$tab = $_GET['page'] ?? 'sessions';
if ($tab === 'sessions') $tab = 'upcoming_sessions'; // Default tab
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-calendar-check"></i> Training</h1>
    <p class="page-description">Manage your training sessions, view upcoming schedules, and book new sessions</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <a href="?page=upcoming_sessions" class="page-tab <?= $tab === 'upcoming_sessions' ? 'active' : '' ?>">
        <i class="fas fa-clock"></i> Upcoming Sessions
    </a>
    <a href="?page=booking" class="page-tab <?= $tab === 'booking' ? 'active' : '' ?>">
        <i class="fas fa-calendar-plus"></i> Booking
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'upcoming_sessions') {
        include __DIR__ . '/sessions_upcoming.php';
    } elseif ($tab === 'booking') {
        include __DIR__ . '/sessions_booking.php';
    }
    ?>
</div>
