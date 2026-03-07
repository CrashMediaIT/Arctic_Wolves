<?php
/**
 * My Program - View assigned drills and communicate with coach
 * Shows athlete's enrolled programs with assigned drills and messaging
 */

$user_id = $_SESSION['user_id'] ?? 0;

// Get user's active enrollments with assigned drills
$enrollments_stmt = $pdo->prepare("
    SELECT dpe.*
    FROM development_program_enrollments dpe
    WHERE dpe.athlete_id = ? AND dpe.status = 'active'
    ORDER BY dpe.enrolled_at DESC
");
$enrollments_stmt->execute([$user_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get drills for each enrollment
foreach ($enrollments as &$enrollment) {
    $drills_stmt = $pdo->prepare("
        SELECT dpd.*, d.title as drill_title, d.description as drill_description,
               d.video_url as drill_video_url, d.custom_image as drill_image,
               u.first_name as coach_first, u.last_name as coach_last
        FROM development_program_drills dpd
        JOIN drills d ON dpd.drill_id = d.id
        JOIN users u ON dpd.assigned_by = u.id
        WHERE dpd.enrollment_id = ?
        ORDER BY dpd.sort_order, dpd.created_at
    ");
    $drills_stmt->execute([$enrollment['id']]);
    $enrollment['drills'] = $drills_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent messages
    $msgs_stmt = $pdo->prepare("
        SELECT dpm.*, u.first_name as sender_first, u.last_name as sender_last
        FROM development_program_messages dpm
        JOIN users u ON dpm.sender_id = u.id
        WHERE dpm.enrollment_id = ?
        ORDER BY dpm.created_at DESC
        LIMIT 20
    ");
    $msgs_stmt->execute([$enrollment['id']]);
    $enrollment['messages'] = array_reverse($msgs_stmt->fetchAll(PDO::FETCH_ASSOC));
}
unset($enrollment);
?>

<style>
.my-program-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-dim, #94a3b8);
}
.my-program-empty i {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
    opacity: 0.5;
}
.enrollment-section {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.enrollment-section h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 16px;
}
.enrollment-section h3 i { margin-right: 8px; }
.drill-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.drill-item {
    background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 8px;
    padding: 16px;
}
.drill-item h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 6px;
}
.drill-item p {
    font-size: 13px;
    color: var(--text-dim, #94a3b8);
    line-height: 1.5;
}
.drill-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.drill-status.assigned { background: rgba(59,130,246,0.15); color: #3b82f6; }
.drill-status.in_progress { background: rgba(245,158,11,0.15); color: #f59e0b; }
.drill-status.completed { background: rgba(16,185,129,0.15); color: #10b981; }
.program-chat {
    margin-top: 20px;
    border-top: 1px solid var(--border, #2d2d44);
    padding-top: 16px;
}
.chat-messages {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 12px;
}
.chat-msg {
    padding: 8px 12px;
    margin-bottom: 8px;
    border-radius: 8px;
    font-size: 13px;
}
.chat-msg.from-coach {
    background: rgba(107, 70, 193, 0.1);
    border-left: 3px solid var(--primary, #6B46C1);
}
.chat-msg.from-me {
    background: rgba(59, 130, 246, 0.1);
    border-left: 3px solid #3b82f6;
}
.chat-msg .msg-meta {
    font-size: 11px;
    color: var(--text-dim, #94a3b8);
    margin-bottom: 4px;
}
.chat-input-row {
    display: flex;
    gap: 8px;
}
.chat-input-row input {
    flex: 1;
    padding: 10px 14px;
    background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 8px;
    color: var(--text-white, #e2e8f0);
    font-size: 13px;
}
.chat-input-row button {
    padding: 10px 16px;
    background: var(--primary, #6B46C1);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
}
</style>

<?php if (empty($enrollments)): ?>
<div class="my-program-empty">
    <i class="fas fa-hockey-puck"></i>
    <h3>No Active Programs</h3>
    <p>You haven't enrolled in any development programs yet. Visit the <a href="?page=personal_development_programs" style="color:var(--primary);">Development Programs</a> tab to register.</p>
</div>
<?php else: ?>
    <?php foreach ($enrollments as $enrollment): ?>
    <div class="enrollment-section">
        <h3>
            <?php if ($enrollment['program_type'] === 'goalie_dev'): ?>
                <i class="fas fa-shield-alt" style="color:#3b82f6;"></i> Long Term Goalie Development
            <?php else: ?>
                <i class="fas fa-hockey-puck" style="color:#10b981;"></i> Long Term Player Development
            <?php endif; ?>
        </h3>

        <?php if (empty($enrollment['drills'])): ?>
            <p style="color:var(--text-dim);font-size:14px;">No drills assigned yet. Your coach will add drills to your program soon.</p>
        <?php else: ?>
            <div class="drill-list">
            <?php foreach ($enrollment['drills'] as $drill): ?>
                <div class="drill-item">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <h4><?= htmlspecialchars($drill['drill_title']) ?></h4>
                        <span class="drill-status <?= htmlspecialchars($drill['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($drill['status'])) ?></span>
                    </div>
                    <?php if ($drill['drill_description']): ?>
                        <p><?= htmlspecialchars(substr($drill['drill_description'], 0, 200)) ?></p>
                    <?php endif; ?>
                    <?php if ($drill['coach_notes']): ?>
                        <p style="color:#f59e0b;"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($drill['coach_notes']) ?></p>
                    <?php endif; ?>
                    <?php if ($drill['drill_video_url']): ?>
                        <a href="<?= htmlspecialchars($drill['drill_video_url']) ?>" target="_blank" style="color:var(--primary);font-size:13px;"><i class="fas fa-play-circle"></i> Watch Video</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Chat Section -->
        <div class="program-chat">
            <h4 style="font-size:14px;font-weight:600;color:var(--text-white);margin-bottom:12px;"><i class="fas fa-comments"></i> Program Chat</h4>
            <div class="chat-messages" id="chat-<?= (int)$enrollment['id'] ?>">
                <?php if (empty($enrollment['messages'])): ?>
                    <p style="color:var(--text-dim);font-size:13px;text-align:center;padding:20px;">No messages yet. Start a conversation with your coach.</p>
                <?php else: ?>
                    <?php foreach ($enrollment['messages'] as $msg): ?>
                    <div class="chat-msg <?= $msg['sender_id'] == $user_id ? 'from-me' : 'from-coach' ?>">
                        <div class="msg-meta"><?= htmlspecialchars($msg['sender_first'] . ' ' . $msg['sender_last']) ?> &bull; <?= date('M j, g:ia', strtotime($msg['created_at'])) ?></div>
                        <?= htmlspecialchars($msg['message']) ?>
                        <?php if ($msg['video_url']): ?>
                            <div style="margin-top:6px;"><a href="<?= htmlspecialchars($msg['video_url']) ?>" target="_blank" style="color:var(--primary);font-size:12px;"><i class="fas fa-video"></i> Video</a></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="chat-input-row">
                <input type="text" id="msg-input-<?= (int)$enrollment['id'] ?>" placeholder="Type a message..." onkeydown="if(event.key==='Enter')sendDevMessage(<?= (int)$enrollment['id'] ?>)">
                <button onclick="sendDevMessage(<?= (int)$enrollment['id'] ?>)"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function sendDevMessage(enrollmentId) {
    const input = document.getElementById('msg-input-' + enrollmentId);
    const message = input.value.trim();
    if (!message) return;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch('process_development_programs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ action: 'send_message', enrollment_id: enrollmentId, message: message })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            location.reload();
        } else {
            alert(data.error || 'Failed to send message.');
        }
    })
    .catch(() => alert('An error occurred.'));
}
</script>
