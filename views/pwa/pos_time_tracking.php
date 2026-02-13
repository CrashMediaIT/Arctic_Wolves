<?php
/**
 * PWA POS Time Tracking - Mobile-native clock in/out
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessPOS) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>POS access required</div>';
    return;
}

$activeClock = null;
try {
    $stmt = $pdo->prepare("SELECT id, clock_in_time, clock_out_time FROM time_entries WHERE user_id = ? AND clock_out_time IS NULL ORDER BY clock_in_time DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $activeClock = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $activeClock = null; }

$recentEntries = [];
try {
    $stmt = $pdo->prepare("SELECT id, clock_in_time, clock_out_time, total_hours FROM time_entries WHERE user_id = ? ORDER BY clock_in_time DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $recentEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentEntries = []; }

$isClockedIn = !empty($activeClock);
?>
<style>
.m-timetrack { padding: 16px; font-family: Inter, sans-serif; }
.m-timetrack-header { margin-bottom: 16px; }
.m-timetrack-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-timetrack-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-clock-status {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 24px; text-align: center; margin-bottom: 20px;
}
.m-clock-indicator {
    width: 16px; height: 16px; border-radius: 50%; display: inline-block;
    margin-bottom: 8px;
}
.m-clock-indicator-in { background: #10B981; box-shadow: 0 0 8px rgba(16,185,129,0.5); }
.m-clock-indicator-out { background: #EF4444; }
.m-clock-label { font-size: 14px; color: #A8A8B8; margin-bottom: 4px; }
.m-clock-time { font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 16px; }
.m-clock-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 32px; border-radius: 12px;
    font-size: 16px; font-weight: 600; color: #fff;
    border: none; cursor: pointer; text-decoration: none;
    min-height: 48px; min-width: 160px;
}
.m-clock-btn-in { background: linear-gradient(135deg, #059669, #10B981); }
.m-clock-btn-out { background: linear-gradient(135deg, #DC2626, #EF4444); }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-time-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-time-card-top {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 6px;
}
.m-time-card-date { font-size: 13px; font-weight: 600; color: #fff; }
.m-time-card-hours {
    font-size: 13px; font-weight: 700; color: #8B5CF6;
}
.m-time-card-detail { display: flex; gap: 16px; }
.m-time-card-in, .m-time-card-out { font-size: 12px; color: #A8A8B8; }
.m-time-card-in i, .m-time-card-out i { margin-right: 4px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-timetrack">
    <div class="m-timetrack-header">
        <h2 class="m-timetrack-title">Time Tracking</h2>
        <p class="m-timetrack-sub">Clock in &amp; out</p>
    </div>

    <div class="m-clock-status">
        <div class="m-clock-indicator m-clock-indicator-<?= $isClockedIn ? 'in' : 'out' ?>"></div>
        <div class="m-clock-label"><?= $isClockedIn ? 'Clocked In Since' : 'Currently Clocked Out' ?></div>
        <div class="m-clock-time">
            <?php if ($isClockedIn): ?>
                <?= date('g:i A', strtotime($activeClock['clock_in_time'])) ?>
            <?php else: ?>
                --:--
            <?php endif; ?>
        </div>
        <?php if ($isClockedIn): ?>
            <a href="?page=pos_time_tracking&action=clock_out" class="m-clock-btn m-clock-btn-out">
                <i class="fas fa-stop-circle"></i> Clock Out
            </a>
        <?php else: ?>
            <a href="?page=pos_time_tracking&action=clock_in" class="m-clock-btn m-clock-btn-in">
                <i class="fas fa-play-circle"></i> Clock In
            </a>
        <?php endif; ?>
    </div>

    <h3 class="m-section-title">Recent Entries</h3>
    <?php if (empty($recentEntries)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            No time entries yet
        </div>
    <?php else: ?>
        <?php foreach ($recentEntries as $entry):
            $inTime = $entry['clock_in_time'] ? date('g:i A', strtotime($entry['clock_in_time'])) : '--';
            $outTime = $entry['clock_out_time'] ? date('g:i A', strtotime($entry['clock_out_time'])) : 'Active';
            $entryDate = $entry['clock_in_time'] ? date('M j, Y', strtotime($entry['clock_in_time'])) : 'N/A';
            $hours = $entry['total_hours'] !== null ? number_format((float)$entry['total_hours'], 1) . 'h' : 'In Progress';
        ?>
        <div class="m-time-card">
            <div class="m-time-card-top">
                <span class="m-time-card-date"><?= $entryDate ?></span>
                <span class="m-time-card-hours"><?= $hours ?></span>
            </div>
            <div class="m-time-card-detail">
                <span class="m-time-card-in"><i class="fas fa-sign-in-alt" style="color:#10B981;"></i> <?= $inTime ?></span>
                <span class="m-time-card-out"><i class="fas fa-sign-out-alt" style="color:#EF4444;"></i> <?= $outTime ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
