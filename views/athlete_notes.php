<?php
/**
 * Athlete Notes View
 * View and manage notes for a specific athlete
 */

require_once __DIR__ . '/../security.php';

$athlete_id = isset($_GET['athlete_id']) ? intval($_GET['athlete_id']) : null;

if (!$athlete_id) {
    echo "<div class='alert alert-error'>No athlete specified.</div>";
    return;
}

// Only coaches and admins can view/add notes
if (!$isCoach && !$isAdmin) {
    echo "<div class='alert alert-error'>You do not have permission to view notes.</div>";
    return;
}

// Get athlete info
$athlete_stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
$athlete_stmt->execute([$athlete_id]);
$athlete = $athlete_stmt->fetch();
$athlete = $athlete ? decryptUserRow($athlete) : null;

if (!$athlete) {
    echo "<div class='alert alert-error'>Athlete not found.</div>";
    return;
}

// Get notes for this athlete
$notes_stmt = $pdo->prepare("
    SELECT an.*, u.first_name as coach_first, u.last_name as coach_last
    FROM athlete_notes an
    LEFT JOIN users u ON an.coach_id = u.id
    WHERE an.user_id = ?
    ORDER BY an.created_at DESC
");
$notes_stmt->execute([$athlete_id]);
$notes = $notes_stmt->fetchAll();
$notes = decryptUserRows($notes);

// Handle success/error messages
$success = $_GET['success'] ?? null;
$error   = $_GET['error']   ?? null;
?>

<style>
.notes-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.notes-header-content { flex: 1; }
.notes-title {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white, #fff);
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 0 4px 0;
}
.notes-title i { color: var(--primary, #6B46C1); }
.notes-subtitle {
    font-size: 14px;
    color: var(--text-dim, #64748b);
    margin: 0;
}
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    color: var(--text-dim, #94a3b8);
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-back:hover {
    border-color: var(--primary, #6B46C1);
    color: var(--text-white, #fff);
}
.notes-add-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.notes-add-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.notes-add-title i { color: var(--primary, #6B46C1); }
.notes-textarea {
    width: 100%;
    min-height: 110px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: var(--text-white, #fff);
    padding: 12px 16px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    margin-bottom: 12px;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.notes-textarea:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
}
.notes-form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.notes-private-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-dim, #94a3b8);
    font-size: 13px;
    cursor: pointer;
}
.note-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    transition: border-color 0.2s;
}
.note-card:hover { border-color: rgba(107, 70, 193, 0.4); }
.note-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    gap: 12px;
}
.note-card-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.note-author {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white, #fff);
}
.note-author-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--primary, #6B46C1), #4a0070);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.note-private-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.note-date {
    font-size: 12px;
    color: var(--text-dim, #64748b);
    white-space: nowrap;
}
.note-body {
    font-size: 14px;
    color: var(--text-secondary, #94a3b8);
    line-height: 1.7;
    white-space: pre-wrap;
}
.notes-empty {
    text-align: center;
    padding: 60px 24px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
}
.notes-empty i {
    font-size: 48px;
    color: var(--primary, #6B46C1);
    opacity: 0.35;
    margin-bottom: 16px;
    display: block;
}
.notes-empty h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin: 0 0 8px 0;
}
.notes-empty p {
    color: var(--text-dim, #64748b);
    font-size: 14px;
    margin: 0;
}
.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #10b981;
    font-weight: 600;
    font-size: 14px;
}
.alert-success i { font-size: 18px; }
.notes-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--primary, #6B46C1);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    min-width: 22px;
    height: 22px;
    border-radius: 11px;
    padding: 0 6px;
}
</style>

<?php if ($success === 'note_added'): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i>
    <span>Note added successfully.</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left:auto; background:none; border:none; color:#10b981; cursor:pointer; font-size:18px; padding:0 4px;">&times;</button>
</div>
<?php endif; ?>

<div class="notes-header">
    <div class="notes-header-content">
        <h1 class="notes-title">
            <i class="fas fa-sticky-note"></i>
            Notes
            <?php if (count($notes) > 0): ?>
                <span class="notes-count-badge"><?= count($notes) ?></span>
            <?php endif; ?>
        </h1>
        <p class="notes-subtitle">Coaching notes for <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></p>
    </div>
    <a href="?page=athlete_detail&id=<?= $athlete_id ?>" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Profile
    </a>
</div>

<!-- Add Note Form -->
<div class="notes-add-card">
    <h3 class="notes-add-title"><i class="fas fa-plus-circle"></i> Add Note</h3>
    <form method="POST" action="process_coach_action.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="add_note">
        <input type="hidden" name="user_id" value="<?= $athlete_id ?>">
        <input type="hidden" name="redirect" value="athlete_notes&athlete_id=<?= $athlete_id ?>">
        <textarea name="note_content" class="notes-textarea" placeholder="Write a coaching note about this athlete..." required></textarea>
        <div class="notes-form-footer">
            <label class="notes-private-label">
                <input type="checkbox" name="is_private" value="1" style="width:15px;height:15px;">
                <i class="fas fa-lock" style="font-size:12px;"></i>
                <span>Private — only visible to you</span>
            </label>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Note
            </button>
        </div>
    </form>
</div>

<!-- Notes List -->
<?php if (empty($notes)): ?>
    <div class="notes-empty">
        <i class="fas fa-sticky-note"></i>
        <h3>No Notes Yet</h3>
        <p>Add your first coaching note about this athlete above.</p>
    </div>
<?php else: ?>
    <?php foreach ($notes as $note):
        $first = $note['coach_first'] ?? '';
        $last  = $note['coach_last']  ?? '';
        $initials = strtoupper(
            ($first !== '' ? substr($first, 0, 1) : '') .
            ($last  !== '' ? substr($last,  0, 1) : '')
        );
        if ($initials === '') {
            $initials = '?';
        }
    ?>
        <div class="note-card">
            <div class="note-card-header">
                <div class="note-card-meta">
                    <div class="note-author">
                        <div class="note-author-avatar"><?= htmlspecialchars($initials) ?></div>
                        <span><?= htmlspecialchars(trim(($note['coach_first'] ?? '') . ' ' . ($note['coach_last'] ?? ''))) ?></span>
                    </div>
                    <?php if (!empty($note['is_private'])): ?>
                        <span class="note-private-badge"><i class="fas fa-lock"></i> Private</span>
                    <?php endif; ?>
                </div>
                <span class="note-date"><?= date('M d, Y g:i A', strtotime($note['created_at'])) ?></span>
            </div>
            <div class="note-body"><?= nl2br(htmlspecialchars($note['note_content'])) ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

