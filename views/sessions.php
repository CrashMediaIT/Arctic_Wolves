<?php
// Sessions Parent Page with Tabs
$tab = $_GET['page'] ?? 'sessions';
if ($tab === 'sessions') $tab = 'upcoming_sessions'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-calendar-check"></i> Sessions</h1>
    <p>Manage your training sessions, view upcoming schedules, and book new sessions</p>
</div>

<div class="tab-navigation" data-component="TabNavigation">
    <a href="?page=upcoming_sessions" class="tab-link <?= $tab === 'upcoming_sessions' ? 'active' : '' ?>" data-tab="upcoming_sessions">
        <i class="fa-solid fa-clock"></i> Upcoming Sessions
    </a>
    <a href="?page=booking" class="tab-link <?= $tab === 'booking' ? 'active' : '' ?>" data-tab="booking">
        <i class="fa-solid fa-calendar-plus"></i> Booking
    </a>
</div>

<div class="tab-content">
    <?php
    if ($tab === 'upcoming_sessions') {
        include __DIR__ . '/sessions_upcoming.php';
    } elseif ($tab === 'booking') {
        include __DIR__ . '/sessions_booking.php';
    }
    ?>
</div>
