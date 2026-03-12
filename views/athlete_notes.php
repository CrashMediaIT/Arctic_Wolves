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
$error = $_GET['error'] ?? null;
?>

<style>
.notes-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.notes-page-title {
    font-size: 28px;
    font-weight: 900;
    color: #fff;
}
.notes-page-subtitle {
    font-size: 14px;
    color: #64748b;
    margin-top: 4px;
}
.note-card {
    background: #0d1117;
    border: 1px solid #1e293b;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
}
.note-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.note-author {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
}
.note-date {
    font-size: 12px;
    color: #64748b;
}
.note-content {
    font-size: 14px;
    color: #94a3b8;
    line-height: 1.6;
    white-space: pre-wrap;
}
.note-private-badge {
    display: inline-block;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 8px;
}
.add-note-form {
    background: #0d1117;
    border: 1px solid #1e293b;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}
.add-note-form textarea {
    width: 100%;
    min-height: 100px;
    background: #06080b;
    border: 1px solid #1e293b;
    border-radius: 6px;
    color: #fff;
    padding: 12px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    margin-bottom: 12px;
}
.add-note-form textarea:focus {
    outline: none;
    border-color: #6B46C1;
}
.note-form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.btn-add-note {
    background: #6B46C1;
    color: #fff;
    padding: 10px 20px;
    border-radius: 6px;
    border: none;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-add-note:hover {
    background: #7c3aed;
}
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #6B46C1;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}
.btn-back:hover {
    text-decoration: underline;
}
.empty-notes {
    text-align: center;
    padding: 60px 20px;
    background: #0d1117;
    border: 1px solid #1e293b;
    border-radius: 8px;
}
.empty-notes i {
    font-size: 48px;
    color: #64748b;
    opacity: 0.3;
    margin-bottom: 16px;
}
</style>

<?php if ($success === 'note_added'): ?>
<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Note added successfully.</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<div class="notes-page-header">
    <div>
        <h1 class="notes-page-title"><i class="fas fa-sticky-note"></i> Notes</h1>
        <p class="notes-page-subtitle">
            Notes for <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
        </p>
    </div>
    <a href="?page=athlete_detail&id=<?= $athlete_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Profile</a>
</div>

<!-- Add Note Form -->
<div class="add-note-form">
    <h3 style="color: #fff; font-size: 16px; margin-bottom: 12px;"><i class="fas fa-plus"></i> Add Note</h3>
    <form method="POST" action="process_coach_action.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="add_note">
        <input type="hidden" name="user_id" value="<?= $athlete_id ?>">
        <input type="hidden" name="redirect" value="athlete_notes&athlete_id=<?= $athlete_id ?>">
        <textarea name="note_content" placeholder="Write a note about this athlete..." required></textarea>
        <div class="note-form-actions">
            <label style="display: flex; align-items: center; gap: 8px; color: #94a3b8; font-size: 13px; cursor: pointer;">
                <input type="checkbox" name="is_private" value="1">
                <span>Private (only visible to you)</span>
            </label>
            <button type="submit" class="btn-add-note"><i class="fas fa-save"></i> Save Note</button>
        </div>
    </form>
</div>

<!-- Notes List -->
<?php if (empty($notes)): ?>
    <div class="empty-notes">
        <i class="fas fa-sticky-note"></i>
        <h2 style="font-size: 20px; color: #fff; margin-bottom: 8px;">No Notes Yet</h2>
        <p style="color: #64748b;">Add your first note about this athlete above.</p>
    </div>
<?php else: ?>
    <?php foreach ($notes as $note): ?>
        <div class="note-card">
            <div class="note-card-header">
                <div>
                    <span class="note-author">
                        <i class="fas fa-user-tie"></i>
                        <?= htmlspecialchars(trim(($note['coach_first'] ?? '') . ' ' . ($note['coach_last'] ?? ''))) ?>
                    </span>
                    <?php if ($note['is_private']): ?>
                        <span class="note-private-badge"><i class="fas fa-lock"></i> Private</span>
                    <?php endif; ?>
                </div>
                <span class="note-date"><?= date('M d, Y g:i A', strtotime($note['created_at'])) ?></span>
            </div>
            <div class="note-content"><?= nl2br(htmlspecialchars($note['note_content'])) ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
