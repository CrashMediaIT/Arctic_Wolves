<?php
/**
 * Parent Dashboard View
 * Shows all managed athletes and their upcoming sessions
 */

require_once __DIR__ . '/../security.php';

// Get all managed athletes for this parent
$athletes_stmt = $pdo->prepare("
    SELECT u.*, ma.relationship, ma.can_book, ma.can_view_stats, ma.id as managed_id,
           (SELECT COUNT(*) FROM bookings b 
            INNER JOIN sessions s ON b.session_id = s.id 
            WHERE b.user_id = u.id AND b.status = 'paid' AND s.session_date >= CURDATE()) as upcoming_sessions,
           (SELECT COUNT(*) FROM notifications WHERE user_id = u.id AND read_status = 0) as unread_notifications
    FROM managed_athletes ma
    INNER JOIN users u ON ma.athlete_id = u.id
    WHERE ma.parent_id = ?
    ORDER BY u.first_name, u.last_name
");
$athletes_stmt->execute([$user_id]);
$athletes = $athletes_stmt->fetchAll();
$athletes = decryptUserRows($athletes);

// Get total upcoming sessions for all athletes
$total_upcoming = 0;
$total_bookings = 0;
foreach ($athletes as $athlete) {
    $total_upcoming += $athlete['upcoming_sessions'];
    
    // Count total bookings for each athlete
    $bookings_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'paid'");
    $bookings_stmt->execute([$athlete['id']]);
    $total_bookings += $bookings_stmt->fetchColumn();
}

// Get unread notifications count for parent
$notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
$notif_stmt->execute([$user_id]);
$unread_count = $notif_stmt->fetchColumn();

// Get pending invitations sent by this parent
$pending_invitations = [];
try {
    $inv_stmt = $pdo->prepare("
        SELECT pi.*, GROUP_CONCAT(u.first_name, ' ', u.last_name SEPARATOR ', ') as athlete_names
        FROM parent_invitations pi
        LEFT JOIN parent_invitation_athletes pia ON pi.id = pia.invitation_id
        LEFT JOIN users u ON pia.athlete_id = u.id
        WHERE pi.inviter_id = ? AND pi.status = 'pending' AND pi.expires_at > NOW()
        GROUP BY pi.id
        ORDER BY pi.created_at DESC
    ");
    $inv_stmt->execute([$user_id]);
    $pending_invitations = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet
    $pending_invitations = [];
}

// Check for pending invitations for this user to accept
$incoming_invitations = [];
try {
    $user_email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $user_email_stmt->execute([$user_id]);
    $user_email = $user_email_stmt->fetchColumn();
    
    $incoming_stmt = $pdo->prepare("
        SELECT pi.*, u.first_name as inviter_first_name, u.last_name as inviter_last_name,
               GROUP_CONCAT(au.first_name, ' ', au.last_name SEPARATOR ', ') as athlete_names
        FROM parent_invitations pi
        INNER JOIN users u ON pi.inviter_id = u.id
        LEFT JOIN parent_invitation_athletes pia ON pi.id = pia.invitation_id
        LEFT JOIN users au ON pia.athlete_id = au.id
        WHERE pi.email = ? AND pi.status = 'pending' AND pi.expires_at > NOW()
        GROUP BY pi.id
        ORDER BY pi.created_at DESC
    ");
    $incoming_stmt->execute([$user_email]);
    $incoming_invitations = $incoming_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $incoming_invitations = [];
}

// Check for camp check-in eligible sessions (sessions with enable_child_checkin = 1 within 7 days)
$checkin_sessions = [];
try {
    $athlete_ids = array_column($athletes, 'id');
    if (!empty($athlete_ids)) {
        $placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
        $checkin_stmt = $pdo->prepare("
            SELECT s.*, b.id as booking_id, b.user_id as athlete_id,
                   u.first_name as athlete_first_name, u.last_name as athlete_last_name
            FROM sessions s
            INNER JOIN bookings b ON s.id = b.session_id AND b.status = 'paid'
            INNER JOIN users u ON b.user_id = u.id
            WHERE s.enable_child_checkin = 1
            AND s.session_date >= CURDATE()
            AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND b.user_id IN ($placeholders)
            ORDER BY s.session_date ASC, s.session_time ASC
        ");
        $checkin_stmt->execute($athlete_ids);
        $checkin_sessions = $checkin_stmt->fetchAll(PDO::FETCH_ASSOC);
        $checkin_sessions = decryptUserRows($checkin_sessions);
    }
} catch (PDOException $e) {
    // Column may not exist yet
    $checkin_sessions = [];
}
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
        margin: 0;
    }
    .add-athlete-btn {
        padding: 12px 24px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    .add-athlete-btn:hover {
        background: #e64500;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
    }
    .stat-value {
        font-size: 36px;
        font-weight: 900;
        color: var(--primary);
        display: block;
        margin-bottom: 5px;
    }
    .stat-label {
        font-size: 13px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 700;
    }
    .athletes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    .athlete-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 24px;
        transition: all 0.2s;
    }
    .athlete-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .athlete-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    .athlete-avatar {
        width: 60px;
        height: 60px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 24px;
        color: #fff;
    }
    .athlete-info {
        flex: 1;
    }
    .athlete-name {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    .athlete-meta {
        font-size: 13px;
        color: #64748b;
    }
    .athlete-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 12px;
        padding: 16px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
    }
    .athlete-stat-item {
        text-align: center;
    }
    .athlete-stat-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--primary);
        display: block;
    }
    .athlete-stat-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
    }
    .athlete-actions {
        display: flex;
        gap: 10px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 13px;
        flex: 1;
        transition: all 0.2s;
    }
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    .btn-primary:hover {
        background: #e64500;
    }
    .btn-secondary {
        background: #1e293b;
        color: #fff;
    }
    .btn-secondary:hover {
        background: #334155;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
    .empty-state h2 {
        font-size: 24px;
        color: #fff;
        margin-bottom: 10px;
    }
    .empty-state p {
        color: #64748b;
        margin-bottom: 20px;
    }
    .notification-badge {
        background: var(--primary);
        color: #fff;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
        margin-left: 5px;
    }
    @media (max-width: 768px) {
        .athletes-grid {
            grid-template-columns: 1fr;
        }
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
    }
</style>

<div class="dashboard-header">
    <h1 class="page-title">
        <i class="fas fa-users"></i> Manage Athletes
    </h1>
    <div style="display: flex; gap: 10px; align-items: center;">
        <button type="button" class="add-athlete-btn" onclick="document.getElementById('invite-parent-modal').style.display='flex'" style="background: #1e293b;">
            <i class="fas fa-user-plus"></i> Invite Parent/Guardian
        </button>
        <a href="?page=manage_athletes" class="add-athlete-btn">
            <i class="fas fa-plus-circle"></i> Add New Athlete
        </a>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <div style="padding: 16px; border-radius: 8px; margin-bottom: 20px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981;">
        <i class="fas fa-check-circle"></i>
        <?php
        switch ($_GET['status']) {
            case 'invitation_sent': echo 'Invitation sent successfully!'; break;
            case 'invitation_revoked': echo 'Invitation has been revoked.'; break;
            case 'invitation_accepted': echo 'Invitation accepted! Athletes have been added to your dashboard.'; break;
            default: echo 'Action completed successfully.';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="padding: 16px; border-radius: 8px; margin-bottom: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444;">
        <i class="fas fa-exclamation-circle"></i>
        <?php
        switch ($_GET['error']) {
            case 'invalid_email': echo 'Please enter a valid email address.'; break;
            case 'no_athletes_selected': echo 'Please select at least one athlete.'; break;
            case 'cannot_invite_self': echo 'You cannot invite yourself.'; break;
            case 'invitation_already_sent': echo 'An invitation has already been sent to this email.'; break;
            default: echo 'An error occurred. Please try again.';
        }
        ?>
    </div>
<?php endif; ?>

<!-- Incoming Invitations (from other parents) -->
<?php if (!empty($incoming_invitations)): ?>
    <div style="background: rgba(107, 70, 193, 0.1); border: 1px solid #6B46C1; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
        <h3 style="color: #fff; margin: 0 0 15px 0; font-size: 16px;"><i class="fas fa-envelope-open-text"></i> Pending Invitations for You</h3>
        <?php foreach ($incoming_invitations as $inv): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #0d1117; border-radius: 6px; margin-bottom: 8px;">
                <div>
                    <strong style="color: #fff;"><?= htmlspecialchars($inv['inviter_first_name'] . ' ' . $inv['inviter_last_name']) ?></strong>
                    <span style="color: #94a3b8;"> invited you as <strong><?= htmlspecialchars($inv['relationship']) ?></strong> for: <?= htmlspecialchars($inv['athlete_names'] ?? 'Athletes') ?></span>
                </div>
                <form method="POST" action="process_parent_invitations.php" style="margin: 0;">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="accept_invitation">
                    <input type="hidden" name="invitation_token" value="<?= htmlspecialchars($inv['token']) ?>">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;">
                        <i class="fas fa-check"></i> Accept
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Pending Invitations Sent -->
<?php if (!empty($pending_invitations)): ?>
    <div style="background: #0d1117; border: 1px solid #1e293b; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
        <h3 style="color: #fff; margin: 0 0 15px 0; font-size: 16px;"><i class="fas fa-paper-plane"></i> Pending Invitations Sent</h3>
        <?php foreach ($pending_invitations as $inv): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #06080b; border: 1px solid #1e293b; border-radius: 6px; margin-bottom: 8px;">
                <div>
                    <strong style="color: #fff;"><?= htmlspecialchars($inv['email']) ?></strong>
                    <span style="color: #94a3b8;"> — <?= htmlspecialchars($inv['relationship']) ?> for: <?= htmlspecialchars($inv['athlete_names'] ?? 'Athletes') ?></span>
                    <br><small style="color: #64748b;">Expires: <?= date('M j, Y', strtotime($inv['expires_at'])) ?></small>
                </div>
                <form method="POST" action="process_parent_invitations.php" style="margin: 0;">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="revoke_invitation">
                    <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>">
                    <button type="submit" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px; background: transparent; border: 1px solid #ef4444; color: #ef4444;" onclick="return confirm('Revoke this invitation?')">
                        <i class="fas fa-times"></i> Revoke
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-value"><?= count($athletes) ?></span>
        <span class="stat-label">Managed Athletes</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $total_upcoming ?></span>
        <span class="stat-label">Upcoming Sessions</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $total_bookings ?></span>
        <span class="stat-label">Total Bookings</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= array_sum(array_column($athletes, 'unread_notifications')) ?></span>
        <span class="stat-label">Total Notifications</span>
    </div>
</div>

<!-- Athletes List -->
<?php if (empty($athletes)): ?>
    <div class="empty-state">
        <i class="fas fa-user-plus"></i>
        <h2>No Athletes Added</h2>
        <p>Start by adding an athlete to manage their training sessions and bookings</p>
        <a href="?page=manage_athletes" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add Your First Athlete
        </a>
    </div>
<?php else: ?>
    <div class="athletes-grid">
        <?php foreach ($athletes as $athlete): ?>
            <?php
            // Calculate age
            $age = null;
            if ($athlete['birth_date']) {
                $birth = new DateTime($athlete['birth_date']);
                $today = new DateTime();
                $age = $birth->diff($today)->y;
            }
            
            // Get current team
            $team_stmt = $pdo->prepare("SELECT team_name FROM athlete_teams WHERE user_id = ? AND is_current = 1 ORDER BY created_at DESC LIMIT 1");
            $team_stmt->execute([$athlete['id']]);
            $team = $team_stmt->fetch();
            ?>
            
            <div class="athlete-card">
                <div class="athlete-header">
                    <div class="athlete-avatar">
                        <?= strtoupper(substr($athlete['first_name'], 0, 1)) ?>
                    </div>
                    <div class="athlete-info">
                        <div class="athlete-name">
                            <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            <?php if ($athlete['unread_notifications'] > 0): ?>
                                <span class="notification-badge"><?= $athlete['unread_notifications'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="athlete-meta">
                            <?php if ($age): ?>
                                <?= $age ?> years old
                            <?php endif; ?>
                            <?php if ($athlete['position']): ?>
                                • <?= htmlspecialchars($athlete['position']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($team): ?>
                            <div class="athlete-meta">
                                <i class="fas fa-hockey-puck"></i> <?= htmlspecialchars($team['team_name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="athlete-stats">
                    <div class="athlete-stat-item">
                        <span class="athlete-stat-value"><?= $athlete['upcoming_sessions'] ?></span>
                        <span class="athlete-stat-label">Upcoming</span>
                    </div>
                    <div class="athlete-stat-item">
                        <?php
                        $total_bookings_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'paid'");
                        $total_bookings_stmt->execute([$athlete['id']]);
                        $athlete_bookings = $total_bookings_stmt->fetchColumn();
                        ?>
                        <span class="athlete-stat-value"><?= $athlete_bookings ?></span>
                        <span class="athlete-stat-label">Total Bookings</span>
                    </div>
                </div>
                
                <div class="athlete-actions">
                    <a href="?page=schedule&athlete_id=<?= $athlete['id'] ?>" class="btn btn-primary" title="Book sessions for this athlete">
                        <i class="fas fa-calendar-plus"></i> Book Session
                    </a>
                    <a href="?page=session_history&athlete_id=<?= $athlete['id'] ?>" class="btn btn-secondary" title="View session history">
                        <i class="fas fa-history"></i> History
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Camp Check-In/Check-Out Section -->
<?php if (!empty($checkin_sessions)): ?>
    <div style="margin-top: 24px;">
        <h2 style="font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 16px;">
            <i class="fas fa-qrcode"></i> Camp Check-In / Check-Out
        </h2>
        <p style="color: #94a3b8; font-size: 14px; margin-bottom: 16px;">
            Generate QR codes for dropping off and picking up your athletes at upcoming camps.
        </p>
        <div class="athletes-grid">
            <?php foreach ($checkin_sessions as $cs): ?>
                <div class="athlete-card">
                    <div class="athlete-header">
                        <div class="athlete-avatar" style="background: #10b981;">
                            <i class="fas fa-qrcode" style="font-size: 24px;"></i>
                        </div>
                        <div class="athlete-info">
                            <div class="athlete-name"><?= htmlspecialchars($cs['title'] ?? 'Camp Session') ?></div>
                            <div class="athlete-meta">
                                <?= date('M j, Y', strtotime($cs['session_date'])) ?>
                                <?php if (!empty($cs['session_time'])): ?>
                                    at <?= date('g:i A', strtotime($cs['session_time'])) ?>
                                <?php endif; ?>
                            </div>
                            <div class="athlete-meta">
                                <i class="fas fa-child"></i> <?= htmlspecialchars(($cs['athlete_first_name'] ?? '') . ' ' . ($cs['athlete_last_name'] ?? '')) ?>
                            </div>
                        </div>
                    </div>
                    <div class="athlete-actions">
                        <a href="?page=camp_checkin&booking_id=<?= $cs['booking_id'] ?>&session_id=<?= $cs['id'] ?>&athlete_id=<?= $cs['athlete_id'] ?>" class="btn btn-primary">
                            <i class="fas fa-qrcode"></i> Manage Check-In
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Invite Parent/Guardian Modal -->
<div id="invite-parent-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: #0d1117; border: 1px solid #1e293b; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="font-size: 20px; font-weight: 700; color: #fff; margin: 0;">
                <i class="fas fa-user-plus"></i> Invite Parent/Guardian
            </h2>
            <button onclick="document.getElementById('invite-parent-modal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form method="POST" action="process_parent_invitations.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="send_invitation">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase;">Email Address *</label>
                <input type="email" name="invite_email" required placeholder="parent@example.com" style="width: 100%; padding: 12px; background: #06080b; border: 1px solid #1e293b; border-radius: 6px; color: #fff; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase;">Relationship *</label>
                <select name="invite_relationship" required style="width: 100%; padding: 12px; background: #06080b; border: 1px solid #1e293b; border-radius: 6px; color: #fff; font-size: 14px;">
                    <option value="parent">Parent</option>
                    <option value="grandparent">Grandparent</option>
                    <option value="guardian">Guardian</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase;">Select Athletes They Can Manage *</label>
                <?php if (!empty($athletes)): ?>
                    <?php foreach ($athletes as $athlete): ?>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #06080b; border: 1px solid #1e293b; border-radius: 6px; margin-bottom: 8px; cursor: pointer; color: #fff; font-size: 14px;">
                            <input type="checkbox" name="invite_athlete_ids[]" value="<?= $athlete['id'] ?>" style="accent-color: var(--primary);">
                            <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b; font-size: 13px;">No athletes to assign. Add athletes first.</p>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="document.getElementById('invite-parent-modal').style.display='none'" style="flex: 1; padding: 12px; background: #1e293b; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" style="flex: 1; padding: 12px; background: var(--primary); color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;"><i class="fas fa-paper-plane"></i> Send Invitation</button>
            </div>
        </form>
    </div>
</div>
