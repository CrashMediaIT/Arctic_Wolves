<?php
/**
 * PWA Drills - Mobile-native drill library for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;

// Fetch categories for filter dropdowns
$drillCategories = [];
try {
    $drillCategories = $pdo->query("SELECT id, name FROM drill_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $drillCategories = []; }

// Read filter GET params
$filterCategory = trim($_GET['filter_category'] ?? '');
$filterDifficulty = trim($_GET['filter_difficulty'] ?? '');

// Build My Drills query with optional filters
$myDrills = [];
try {
    $myWhere = "WHERE created_by = ?";
    $myParams = [$user_id];
    if ($filterCategory !== '') {
        $myWhere .= " AND category = ?";
        $myParams[] = $filterCategory;
    }
    if ($filterDifficulty !== '') {
        $myWhere .= " AND LOWER(difficulty) = ?";
        $myParams[] = strtolower($filterDifficulty);
    }
    $stmt = $pdo->prepare("
        SELECT id, title, description, difficulty, duration_minutes, category, coaching_points, video_url, created_by, created_at
        FROM drills
        $myWhere
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute($myParams);
    $myDrills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $myDrills = []; }

// Build Library query with optional filters
$libraryDrills = [];
try {
    $libWhere = "WHERE is_public = 1";
    $libParams = [];
    if ($filterCategory !== '') {
        $libWhere .= " AND category = ?";
        $libParams[] = $filterCategory;
    }
    if ($filterDifficulty !== '') {
        $libWhere .= " AND LOWER(difficulty) = ?";
        $libParams[] = strtolower($filterDifficulty);
    }
    $stmt = $pdo->prepare("
        SELECT id, title, description, difficulty, duration_minutes, category, coaching_points, video_url, created_by
        FROM drills
        $libWhere
        ORDER BY title ASC
        LIMIT 30
    ");
    $stmt->execute($libParams);
    $libraryDrills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $libraryDrills = []; }
?>
<style>
.m-drills { padding: 0; font-family: Inter, sans-serif; }
.m-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 13px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-drill-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; display: block; min-height: 44px; position: relative;
}
.m-drill-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-drill-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-drill-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-drill-badge-easy { background: rgba(16,185,129,0.15); color: #10B981; }
.m-drill-badge-medium { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-drill-badge-hard { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-drill-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-drill-desc {
    font-size: 12px; color: #A8A8B8; margin: 0 0 10px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.m-drill-footer { display: flex; gap: 12px; align-items: center; }
.m-drill-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-drill-tag {
    font-size: 10px; padding: 2px 8px; border-radius: 6px;
    background: rgba(107,70,193,0.12); color: #8B5CF6; font-weight: 500;
}
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* Search bar */
.m-drill-search {
    display: flex; align-items: center; gap: 8px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 0 12px; margin-bottom: 12px; min-height: 44px;
}
.m-drill-search i { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-drill-search input {
    flex: 1; background: none; border: none; outline: none;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    padding: 10px 0; min-height: 44px;
}
.m-drill-search input::placeholder { color: #6B6B7B; }

/* Filter row */
.m-drill-filters {
    display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;
}
.m-drill-filters select {
    flex: 1; min-width: 0; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 8px; color: #A8A8B8; font-size: 12px; font-family: Inter, sans-serif;
    padding: 8px 10px; min-height: 38px; appearance: auto; cursor: pointer;
}
.m-drill-filters select:focus { border-color: #8B5CF6; outline: none; }

/* Toolbar row */
.m-drill-toolbar {
    display: flex; gap: 12px; padding: 10px 16px;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
}
.m-drill-toolbar a {
    font-size: 12px; color: #8B5CF6; text-decoration: none;
    display: flex; align-items: center; gap: 4px; min-height: 32px;
}
.m-drill-toolbar a:active { opacity: 0.7; }

/* Card action buttons */
.m-drill-actions {
    display: flex; gap: 6px; flex-shrink: 0; margin-left: 8px;
}
.m-drill-actions button {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #A8A8B8; font-size: 13px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; padding: 0; flex-shrink: 0;
}
.m-drill-actions button:active { opacity: 0.7; }
.m-drill-actions .m-btn-edit:hover { color: #8B5CF6; border-color: #8B5CF6; }
.m-drill-actions .m-btn-delete:hover { color: #EF4444; border-color: #EF4444; }

/* Edit modal overlay */
.m-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,0.6); align-items: flex-end; justify-content: center;
}
.m-modal-overlay.m-modal-open { display: flex; }
.m-modal {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 480px;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
    animation: mSlideUp 0.25s ease-out;
}
@keyframes mSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 16px;
}
.m-modal-header h3 { font-size: 16px; font-weight: 700; color: #fff; margin: 0; }
.m-modal-close {
    width: 36px; height: 36px; border-radius: 50%; border: none;
    background: #0A0A0F; color: #A8A8B8; font-size: 16px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.m-modal label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 4px; margin-top: 12px;
}
.m-modal input, .m-modal select, .m-modal textarea {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    padding: 10px 12px; box-sizing: border-box; min-height: 44px;
}
.m-modal input:focus, .m-modal select:focus, .m-modal textarea:focus {
    border-color: #8B5CF6; outline: none;
}
.m-modal textarea { resize: vertical; min-height: 70px; }
.m-modal-submit {
    margin-top: 20px; width: 100%; padding: 14px; border: none; border-radius: 10px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff;
    font-size: 15px; font-weight: 600; font-family: Inter, sans-serif;
    cursor: pointer; min-height: 48px;
}
.m-modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.m-modal-msg { font-size: 13px; margin-top: 8px; text-align: center; }
.m-modal-msg.m-msg-ok { color: #10B981; }
.m-modal-msg.m-msg-err { color: #EF4444; }
</style>

<div class="m-drills">
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mDrillTab('mine', this)" type="button">My Drills</button>
        <button class="m-tab" onclick="mDrillTab('library', this)" type="button">Library</button>
    </div>

    <!-- Import/Export toolbar -->
    <div class="m-drill-toolbar">
        <a href="?page=import_drill"><i class="fas fa-file-import"></i> Import</a>
        <a href="?page=export_import_drills"><i class="fas fa-file-export"></i> Export</a>
    </div>

    <!-- My Drills Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-mine">
        <div class="m-drill-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search my drills…" oninput="mFilterDrills('mine', this.value)">
        </div>
        <div class="m-drill-filters">
            <select onchange="mApplyFilters()" id="m-filter-category">
                <option value="">All Categories</option>
                <?php foreach ($drillCategories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $filterCategory === $cat['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select onchange="mApplyFilters()" id="m-filter-difficulty">
                <option value="">All Levels</option>
                <option value="easy" <?= $filterDifficulty === 'easy' ? 'selected' : '' ?>>Easy</option>
                <option value="medium" <?= $filterDifficulty === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="hard" <?= $filterDifficulty === 'hard' ? 'selected' : '' ?>>Hard</option>
            </select>
        </div>
        <?php if (empty($myDrills)): ?>
            <div class="m-empty-state">
                <i class="fas fa-hockey-puck"></i>
                <p>No drills created yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($myDrills as $d):
                $diff = strtolower($d['difficulty'] ?? '');
                $badgeClass = match($diff) {
                    'easy', 'beginner' => 'easy',
                    'medium', 'intermediate' => 'medium',
                    'hard', 'advanced' => 'hard',
                    default => 'default',
                };
                $canEdit = ($isAdmin || (int)$d['created_by'] === (int)$user_id);
            ?>
            <div class="m-drill-card" data-drill-title="<?= htmlspecialchars(strtolower($d['title'])) ?>">
                <div class="m-drill-top">
                    <a href="?page=view_drill&id=<?= (int)$d['id'] ?>" style="flex:1;text-decoration:none;display:flex;align-items:flex-start;gap:8px;">
                        <span class="m-drill-title"><?= htmlspecialchars($d['title']) ?></span>
                        <?php if ($diff): ?>
                        <span class="m-drill-badge m-drill-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($diff)) ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($canEdit): ?>
                    <div class="m-drill-actions">
                        <button type="button" class="m-btn-edit" title="Edit" onclick="mOpenEditModal(<?= htmlspecialchars(json_encode($d, JSON_HEX_APOS|JSON_HEX_TAG)) ?>)"><i class="fas fa-pen"></i></button>
                        <button type="button" class="m-btn-delete" title="Delete" onclick="mDeleteDrill(<?= (int)$d['id'] ?>)"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($d['description'])): ?>
                <p class="m-drill-desc"><?= htmlspecialchars($d['description']) ?></p>
                <?php endif; ?>
                <div class="m-drill-footer">
                    <?php if ($d['duration_minutes']): ?>
                    <span class="m-drill-meta"><i class="fas fa-clock"></i> <?= (int)$d['duration_minutes'] ?>min</span>
                    <?php endif; ?>
                    <?php if (!empty($d['category'])): ?>
                    <span class="m-drill-tag"><?= htmlspecialchars($d['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Library Tab -->
    <div class="m-tab-panel" id="m-panel-library">
        <div class="m-drill-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search library…" oninput="mFilterDrills('library', this.value)">
        </div>
        <?php if (empty($libraryDrills)): ?>
            <div class="m-empty-state">
                <i class="fas fa-book-open"></i>
                <p>No public drills available</p>
            </div>
        <?php else: ?>
            <?php foreach ($libraryDrills as $d):
                $diff = strtolower($d['difficulty'] ?? '');
                $badgeClass = match($diff) {
                    'easy', 'beginner' => 'easy',
                    'medium', 'intermediate' => 'medium',
                    'hard', 'advanced' => 'hard',
                    default => 'default',
                };
                $canEdit = ($isAdmin || (int)$d['created_by'] === (int)$user_id);
            ?>
            <div class="m-drill-card" data-drill-title="<?= htmlspecialchars(strtolower($d['title'])) ?>">
                <div class="m-drill-top">
                    <a href="?page=view_drill&id=<?= (int)$d['id'] ?>" style="flex:1;text-decoration:none;display:flex;align-items:flex-start;gap:8px;">
                        <span class="m-drill-title"><?= htmlspecialchars($d['title']) ?></span>
                        <?php if ($diff): ?>
                        <span class="m-drill-badge m-drill-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($diff)) ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($canEdit): ?>
                    <div class="m-drill-actions">
                        <button type="button" class="m-btn-edit" title="Edit" onclick="mOpenEditModal(<?= htmlspecialchars(json_encode($d, JSON_HEX_APOS|JSON_HEX_TAG)) ?>)"><i class="fas fa-pen"></i></button>
                        <button type="button" class="m-btn-delete" title="Delete" onclick="mDeleteDrill(<?= (int)$d['id'] ?>)"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($d['description'])): ?>
                <p class="m-drill-desc"><?= htmlspecialchars($d['description']) ?></p>
                <?php endif; ?>
                <div class="m-drill-footer">
                    <?php if ($d['duration_minutes']): ?>
                    <span class="m-drill-meta"><i class="fas fa-clock"></i> <?= (int)$d['duration_minutes'] ?>min</span>
                    <?php endif; ?>
                    <?php if (!empty($d['category'])): ?>
                    <span class="m-drill-tag"><?= htmlspecialchars($d['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button type="button" class="m-fab" title="Create Drill" onclick="mOpenCreateModal()"><i class="fas fa-plus"></i></button>
</div>

<!-- Create / Edit Drill Modal -->
<div class="m-modal-overlay" id="m-edit-overlay" onclick="if(event.target===this)mCloseEditModal()">
    <div class="m-modal">
        <div class="m-modal-header">
            <h3 id="m-modal-title">Create Drill</h3>
            <button type="button" class="m-modal-close" onclick="mCloseEditModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="m-edit-form" onsubmit="return mSubmitEdit(event)">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="drill_id" id="m-edit-id">
            <input type="hidden" name="action" id="m-edit-action" value="create">
            <label for="m-edit-title">Drill Name <span style="color:#EF4444">*</span></label>
            <input type="text" id="m-edit-title" name="title" required placeholder="e.g. Cross-Ice Passing">
            <label for="m-edit-category">Category</label>
            <select id="m-edit-category" name="category">
                <option value="">— Select —</option>
                <?php foreach ($drillCategories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="m-edit-difficulty">Difficulty</label>
            <select id="m-edit-difficulty" name="difficulty">
                <option value="">— Select —</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
            <label for="m-edit-desc">Description</label>
            <textarea id="m-edit-desc" name="description" rows="3" placeholder="Brief description of the drill"></textarea>
            <label for="m-edit-duration">Duration (minutes)</label>
            <input type="number" id="m-edit-duration" name="duration_minutes" min="0" max="999" placeholder="e.g. 15">
            <label for="m-edit-equipment">Equipment Needed</label>
            <input type="text" id="m-edit-equipment" name="equipment_needed" placeholder="e.g. Pucks, Cones, Nets">
            <label for="m-edit-instructions">Instructions / Steps</label>
            <textarea id="m-edit-instructions" name="instructions" rows="3" placeholder="Step-by-step instructions"></textarea>
            <label for="m-edit-coaching">Coaching Points</label>
            <textarea id="m-edit-coaching" name="coaching_points" rows="2" placeholder="Key coaching points"></textarea>
            <label for="m-edit-video">Video URL</label>
            <input type="url" id="m-edit-video" name="video_url" placeholder="https://…">
            <div class="m-modal-msg" id="m-edit-msg"></div>
            <button type="submit" class="m-modal-submit" id="m-edit-submit">Create Drill</button>
        </form>
    </div>
</div>

<script>
function mDrillTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}

/* Client-side search filter */
function mFilterDrills(panelKey, query) {
    var q = query.toLowerCase().trim();
    var cards = document.querySelectorAll('#m-panel-' + panelKey + ' .m-drill-card');
    cards.forEach(function(card) {
        var title = card.getAttribute('data-drill-title') || '';
        card.style.display = (!q || title.indexOf(q) !== -1) ? '' : 'none';
    });
}

/* Category / difficulty filter (server-side via GET) */
function mApplyFilters() {
    var cat = document.getElementById('m-filter-category').value;
    var diff = document.getElementById('m-filter-difficulty').value;
    var params = new URLSearchParams(window.location.search);
    params.set('page', 'drills');
    if (cat) { params.set('filter_category', cat); } else { params.delete('filter_category'); }
    if (diff) { params.set('filter_difficulty', diff); } else { params.delete('filter_difficulty'); }
    window.location.search = params.toString();
}

function mGetCsrf() {
    var el = document.querySelector('[name="csrf_token"]');
    return el ? el.value : '';
}

/* Open modal for creating a new drill */
function mOpenCreateModal() {
    document.getElementById('m-modal-title').textContent = 'Create Drill';
    document.getElementById('m-edit-submit').textContent = 'Create Drill';
    document.getElementById('m-edit-action').value = 'create';
    document.getElementById('m-edit-id').value = '';
    document.getElementById('m-edit-title').value = '';
    document.getElementById('m-edit-category').value = '';
    document.getElementById('m-edit-difficulty').value = '';
    document.getElementById('m-edit-duration').value = '';
    document.getElementById('m-edit-desc').value = '';
    document.getElementById('m-edit-equipment').value = '';
    document.getElementById('m-edit-instructions').value = '';
    document.getElementById('m-edit-coaching').value = '';
    document.getElementById('m-edit-video').value = '';
    document.getElementById('m-edit-msg').textContent = '';
    document.getElementById('m-edit-overlay').classList.add('m-modal-open');
}

/* Open modal for editing an existing drill */
function mOpenEditModal(drill) {
    document.getElementById('m-modal-title').textContent = 'Edit Drill';
    document.getElementById('m-edit-submit').textContent = 'Save Changes';
    document.getElementById('m-edit-action').value = 'save_drill';
    document.getElementById('m-edit-id').value = drill.id || '';
    document.getElementById('m-edit-title').value = drill.title || '';
    document.getElementById('m-edit-category').value = drill.category || '';
    var diffMap = {easy:'beginner', medium:'intermediate', hard:'advanced'};
    var rawDiff = (drill.difficulty || '').toLowerCase();
    document.getElementById('m-edit-difficulty').value = diffMap[rawDiff] || rawDiff;
    document.getElementById('m-edit-duration').value = drill.duration_minutes || '';
    document.getElementById('m-edit-desc').value = drill.description || '';
    document.getElementById('m-edit-equipment').value = drill.equipment_needed || '';
    document.getElementById('m-edit-instructions').value = drill.instructions || '';
    document.getElementById('m-edit-coaching').value = drill.coaching_points || '';
    document.getElementById('m-edit-video').value = drill.video_url || '';
    document.getElementById('m-edit-msg').textContent = '';
    document.getElementById('m-edit-overlay').classList.add('m-modal-open');
}

function mCloseEditModal() {
    document.getElementById('m-edit-overlay').classList.remove('m-modal-open');
}

function mSubmitEdit(e) {
    e.preventDefault();
    var btn = document.getElementById('m-edit-submit');
    var msg = document.getElementById('m-edit-msg');
    btn.disabled = true;
    msg.textContent = '';
    msg.className = 'm-modal-msg';
    var form = document.getElementById('m-edit-form');
    var body = new URLSearchParams(new FormData(form));
    body.set('action', document.getElementById('m-edit-action').value);
    body.set('csrf_token', mGetCsrf());
    fetch('process_drills.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function(r) {
            if (r.ok || r.redirected) {
                msg.textContent = 'Saved!';
                msg.className = 'm-modal-msg m-msg-ok';
                window.location.reload();
            } else {
                throw new Error('Server returned ' + r.status);
            }
        })
        .catch(function(err) {
            msg.textContent = 'Error: ' + err.message;
            msg.className = 'm-modal-msg m-msg-err';
            btn.disabled = false;
        });
    return false;
}

/* Delete drill */
function mDeleteDrill(drillId) {
    if (!confirm('Delete this drill? This cannot be undone.')) return;
    var body = new URLSearchParams();
    body.set('action', 'delete_drill');
    body.set('drill_id', drillId);
    body.set('csrf_token', mGetCsrf());
    fetch('process_drills.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function(r) {
            if (r.ok || r.redirected) {
                window.location.reload();
            } else {
                throw new Error('Server returned ' + r.status);
            }
        })
        .catch(function(err) { alert('Delete failed: ' + err.message); });
}
</script>
