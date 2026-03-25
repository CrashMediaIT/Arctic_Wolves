<?php
/**
 * PWA Create Session - Mobile-native session creation modal
 * Opens as a bottom sheet overlay, dismissible with X or Cancel.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$sessionTypes = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM session_types ORDER BY name ASC");
    $sessionTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessionTypes = []; }
?>
<style>
.m-cs-overlay {
    display: none; position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.6);
}
.m-cs-overlay.m-cs-open { display: block; }
.m-cs-sheet {
    display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0;
    max-height: 90vh; overflow-y: auto; -webkit-overflow-scrolling: touch;
    padding: 0 0 env(safe-area-inset-bottom, 16px);
    animation: mCsSlideUp 0.25s ease-out;
}
.m-cs-sheet.m-cs-open { display: block; }
@keyframes mCsSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-cs-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px; border-bottom: 1px solid #2D2D3F;
    position: sticky; top: 0; background: #16161F; z-index: 1;
}
.m-cs-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; font-family: Inter, sans-serif; }
.m-cs-close {
    width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer;
    background: rgba(168,168,184,0.1); color: #A8A8B8; font-size: 16px;
    display: flex; align-items: center; justify-content: center; min-height: 44px;
}
.m-cs-body { padding: 16px; font-family: Inter, sans-serif; }
.m-form-group { margin-bottom: 16px; }
.m-form-label {
    font-size: 13px; font-weight: 600; color: #A8A8B8;
    display: block; margin-bottom: 6px;
}
.m-form-input, .m-form-select {
    width: 100%; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px; outline: none;
}
.m-form-input::placeholder { color: #6B6B7B; }
.m-form-input:focus, .m-form-select:focus { border-color: #6B46C1; }
.m-form-select { appearance: none; -webkit-appearance: none; }
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-cs-footer {
    padding: 12px 16px 16px; display: flex; flex-direction: column; gap: 8px;
    border-top: 1px solid #2D2D3F;
    position: sticky; bottom: 0; background: #16161F; z-index: 1;
}
.m-form-btn {
    display: block; width: 100%; padding: 14px; border: none; border-radius: 12px;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; min-height: 44px;
    text-align: center;
}
.m-form-btn:active { background: #8B5CF6; }
.m-cancel-btn {
    display: block; width: 100%; padding: 14px; border: none; border-radius: 12px;
    background: rgba(168,168,184,0.1); color: #A8A8B8; font-size: 15px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; min-height: 44px;
    text-align: center;
}
</style>

<div class="m-cs-overlay" id="mCsOverlay"></div>
<div class="m-cs-sheet" id="mCsSheet">
    <div class="m-cs-header">
        <h2 class="m-cs-title">Create Session</h2>
        <button type="button" class="m-cs-close" id="mCsClose" aria-label="Close">&times;</button>
    </div>
    <form method="post" action="process_create_session.php" id="mCsForm">
        <?= csrfTokenInput() ?>
        <div class="m-cs-body">
            <div class="m-form-group">
                <label class="m-form-label">Session Title</label>
                <input type="text" name="title" class="m-form-input" placeholder="e.g. Morning Skills" required>
            </div>

            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Date</label>
                    <input type="date" name="session_date" class="m-form-input" required>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Time</label>
                    <input type="time" name="session_time" class="m-form-input" required>
                </div>
            </div>

            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Duration (min)</label>
                    <input type="number" name="duration_minutes" class="m-form-input" placeholder="60" min="15" max="480">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Max Participants</label>
                    <input type="number" name="max_participants" class="m-form-input" placeholder="20" min="1" max="100">
                </div>
            </div>

            <div class="m-form-group">
                <label class="m-form-label">Arena / Location</label>
                <input type="text" name="arena" class="m-form-input" placeholder="e.g. Rink A">
            </div>

            <div class="m-form-group">
                <label class="m-form-label">Session Type</label>
                <select name="session_type_id" class="m-form-select">
                    <option value="">Select type...</option>
                    <?php foreach ($sessionTypes as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="m-cs-footer">
            <button type="submit" class="m-form-btn">Create Session</button>
            <button type="button" class="m-cancel-btn" id="mCsCancel">Cancel</button>
        </div>
    </form>
</div>

<script>
(function() {
    var overlay = document.getElementById('mCsOverlay');
    var sheet = document.getElementById('mCsSheet');
    var closeBtn = document.getElementById('mCsClose');
    var cancelBtn = document.getElementById('mCsCancel');

    function openSheet() {
        overlay.classList.add('m-cs-open');
        sheet.classList.add('m-cs-open');
    }

    function closeSheet() {
        overlay.classList.remove('m-cs-open');
        sheet.classList.remove('m-cs-open');
        // Navigate back to sessions page
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '?page=sessions';
        }
    }

    closeBtn.addEventListener('click', closeSheet);
    cancelBtn.addEventListener('click', closeSheet);
    overlay.addEventListener('click', closeSheet);

    // Users can open the sheet via button click
})();
</script>
