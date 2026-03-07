<?php
/**
 * Personal Drills - Create drills with video, title, and description
 * These drills are added directly to the drill library for use in development programs
 */

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Get personal drills created by this user (or all for admin)
$is_admin = ($user_role === 'admin');
if ($is_admin) {
    $personal_drills = $pdo->query("
        SELECT pd.*, u.first_name, u.last_name
        FROM personal_drills pd
        JOIN users u ON pd.created_by = u.id
        ORDER BY pd.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT pd.*, u.first_name, u.last_name
        FROM personal_drills pd
        JOIN users u ON pd.created_by = u.id
        WHERE pd.created_by = ?
        ORDER BY pd.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $personal_drills = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
.personal-drills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 16px;
}
.personal-drill-card {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 10px;
    padding: 20px;
    transition: transform 0.2s;
}
.personal-drill-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}
.personal-drill-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 8px;
}
.personal-drill-card p {
    font-size: 13px;
    color: var(--text-dim, #94a3b8);
    line-height: 1.5;
    margin-bottom: 12px;
}
.personal-drill-card .drill-meta {
    font-size: 11px;
    color: var(--text-dim, #94a3b8);
    border-top: 1px solid var(--border, #2d2d44);
    padding-top: 10px;
}
.create-personal-drill-form {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.create-personal-drill-form h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 16px;
}
.form-row {
    margin-bottom: 14px;
}
.form-row label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 6px;
}
.form-row input, .form-row textarea {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 8px;
    color: var(--text-white, #e2e8f0);
    font-size: 13px;
    font-family: inherit;
}
.form-row textarea {
    min-height: 80px;
    resize: vertical;
}
.btn-create-drill {
    padding: 10px 24px;
    background: var(--primary, #6B46C1);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: opacity 0.2s;
}
.btn-create-drill:hover { opacity: 0.9; }
</style>

<!-- Create Personal Drill Form -->
<div class="create-personal-drill-form">
    <h3><i class="fas fa-plus-circle"></i> Create Personal Drill</h3>
    <form id="personal-drill-form">
        <div class="form-row">
            <label for="pd-title">Title *</label>
            <input type="text" id="pd-title" name="title" required placeholder="Enter drill title">
        </div>
        <div class="form-row">
            <label for="pd-description">Description</label>
            <textarea id="pd-description" name="description" placeholder="Describe the drill, key points, and objectives"></textarea>
        </div>
        <div class="form-row">
            <label for="pd-video">Video URL</label>
            <input type="text" id="pd-video" name="video_url" placeholder="Paste a video URL (YouTube, Vimeo, etc.)">
        </div>
        <button type="submit" class="btn-create-drill"><i class="fas fa-save"></i> Create Drill & Add to Library</button>
    </form>
</div>

<!-- Existing Personal Drills -->
<h3 style="font-size:15px;font-weight:700;color:var(--text-white);margin-bottom:4px;">Personal Drills (<?= count($personal_drills) ?>)</h3>
<p style="font-size:13px;color:var(--text-dim);margin-bottom:16px;">These drills are added to the drill library and can be assigned to development programs.</p>

<?php if (empty($personal_drills)): ?>
<div style="text-align:center;padding:40px 20px;color:var(--text-dim);">
    <i class="fas fa-plus-circle" style="font-size:36px;display:block;margin-bottom:12px;opacity:0.5;"></i>
    <p>No personal drills created yet. Use the form above to create your first drill.</p>
</div>
<?php else: ?>
<div class="personal-drills-grid">
    <?php foreach ($personal_drills as $pd): ?>
    <div class="personal-drill-card">
        <h4><?= htmlspecialchars($pd['title']) ?></h4>
        <?php if ($pd['description']): ?>
            <p><?= htmlspecialchars(substr($pd['description'], 0, 200)) ?><?= strlen($pd['description']) > 200 ? '...' : '' ?></p>
        <?php endif; ?>
        <?php if ($pd['video_url']): ?>
            <a href="<?= htmlspecialchars($pd['video_url']) ?>" target="_blank" style="color:var(--primary);font-size:13px;display:block;margin-bottom:10px;"><i class="fas fa-play-circle"></i> Watch Video</a>
        <?php endif; ?>
        <div class="drill-meta">
            Created by <?= htmlspecialchars($pd['first_name'] . ' ' . $pd['last_name']) ?> &bull; <?= date('M j, Y', strtotime($pd['created_at'])) ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('personal-drill-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const title = document.getElementById('pd-title').value.trim();
    if (!title) { alert('Title is required.'); return; }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch('process_development_programs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: 'create_personal_drill',
            title: title,
            description: document.getElementById('pd-description').value.trim(),
            video_url: document.getElementById('pd-video').value.trim()
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to create drill.');
        }
    })
    .catch(() => alert('An error occurred.'));
});
</script>
