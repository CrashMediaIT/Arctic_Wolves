<?php
// Sessions Parent Page with Tabs
$tab = $_GET['page'] ?? 'sessions';
if ($tab === 'sessions') $tab = 'upcoming_sessions'; // Default tab
?>

<style>
/* Sessions Tabs Navigation - Financial Reports Hub Style */
.sessions-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.sessions-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.sessions-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.sessions-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.sessions-tab i { font-size: 16px; }

/* Tab Content Container */
.sessions-tab-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-calendar-check"></i> Sessions</h1>
    <p class="page-description">Manage your training sessions, view upcoming schedules, and book new sessions</p>
</div>

<!-- Tabs Navigation -->
<div class="sessions-tabs">
    <a href="?page=upcoming_sessions" class="sessions-tab <?= $tab === 'upcoming_sessions' ? 'active' : '' ?>">
        <i class="fas fa-clock"></i> Upcoming Sessions
    </a>
    <a href="?page=booking" class="sessions-tab <?= $tab === 'booking' ? 'active' : '' ?>">
        <i class="fas fa-calendar-plus"></i> Booking
    </a>
</div>

<div class="sessions-tab-content">
    <?php
    if ($tab === 'upcoming_sessions') {
        include __DIR__ . '/sessions_upcoming.php';
    } elseif ($tab === 'booking') {
        include __DIR__ . '/sessions_booking.php';
    }
    ?>
</div>
