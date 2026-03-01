<?php
/**
 * PWA Coach Evaluations - Mobile-native evaluations performed by coach
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$isAdminUser = ($_SESSION['user_role'] ?? '') === 'admin';

$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.id, es.score, es.max_score, es.evaluation_date,
               es.comments, es.athlete_id,
               ek.name as skill_name, u.first_name, u.last_name,
               ev.first_name as evaluator_first_name, ev.last_name as evaluator_last_name
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        LEFT JOIN users u ON u.id = es.athlete_id
        LEFT JOIN users ev ON ev.id = es.evaluator_id
        WHERE es.evaluator_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (class_exists('FieldEncryption')) {
        foreach ($evaluations as &$evalRow) {
            foreach (['first_name', 'last_name', 'evaluator_first_name', 'evaluator_last_name'] as $field) {
                if (!empty($evalRow[$field])) {
                    $evalRow[$field] = FieldEncryption::decrypt($evalRow[$field]);
                }
            }
        }
        unset($evalRow);
    }
} catch (PDOException $e) { $evaluations = []; }

$athletes = [];
try {
    $stmt2 = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name, u.last_name
        FROM users u
        WHERE u.role = 'athlete' AND u.is_active = 1
        AND (
            EXISTS (SELECT 1 FROM managed_athletes ma WHERE ma.athlete_id = u.id AND ma.coach_id = ?)
            OR EXISTS (SELECT 1 FROM bookings b INNER JOIN sessions s ON b.session_id = s.id WHERE b.user_id = u.id AND s.coach_id = ?)
        )
        ORDER BY u.last_name, u.first_name
    ");
    $stmt2->execute([$user_id, $user_id]);
    $athletes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) { $athletes = decryptUserRows($athletes); }
} catch (PDOException $e) { $athletes = []; }

$evalSkills = [];
try {
    $stmt3 = $pdo->query("SELECT id, name FROM eval_skills WHERE is_active = 1 ORDER BY name");
    $evalSkills = $stmt3->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evalSkills = []; }

$eval_stats = ['total' => count($evaluations), 'published' => count($evaluations), 'draft' => 0];
$totalEvals = $eval_stats['total'];
?>
<style>
.m-coach-evals { padding: 16px; font-family: Inter, sans-serif; }
.m-coach-evals-header { margin-bottom: 12px; }
.m-coach-evals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coach-evals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-admin-link {
    display: inline-flex; align-items: center; gap: 4px; margin-top: 8px;
    font-size: 12px; font-weight: 600; color: #8B5CF6; text-decoration: none;
    background: rgba(107,70,193,0.12); border-radius: 8px; padding: 6px 10px;
}
.m-stats-row { display: flex; gap: 8px; margin-bottom: 12px; }
.m-stat-card {
    flex: 1; background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 10px; display: flex; align-items: center; gap: 8px; min-width: 0;
}
.m-stat-icon {
    width: 32px; height: 32px; border-radius: 8px; display: flex;
    align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;
}
.m-stat-total { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-stat-published { background: rgba(16,185,129,0.15); color: #10B981; }
.m-stat-draft { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-stat-info { display: flex; flex-direction: column; min-width: 0; }
.m-stat-value { font-size: 18px; font-weight: 800; color: #fff; line-height: 1.1; }
.m-stat-label { font-size: 10px; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.3px; }
.m-ceval-filter { margin-bottom: 12px; }
.m-ceval-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-ceval-card.m-status-published { border-left: 3px solid #10B981; }
.m-ceval-card.m-status-draft { border-left: 3px solid #F59E0B; }
.m-ceval-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.m-ceval-skill { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-ceval-score-badge { font-size: 12px; font-weight: 700; color: #8B5CF6; flex-shrink: 0; }
.m-ceval-status-badge {
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
    padding: 2px 8px; border-radius: 4px; flex-shrink: 0;
}
.m-badge-published { background: rgba(16,185,129,0.15); color: #10B981; }
.m-badge-draft { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-ceval-athlete { font-size: 12px; color: #A8A8B8; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
.m-ceval-evaluator { font-size: 11px; color: #6B6B7B; margin-bottom: 8px; display: flex; align-items: center; gap: 4px; }
.m-ceval-evaluator i { color: #8B5CF6; }
.m-ceval-bar-wrap { margin-bottom: 6px; }
.m-ceval-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-ceval-bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-ceval-notes {
    font-size: 12px; color: #A8A8B8; margin-bottom: 6px; word-break: break-word;
    overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    cursor: pointer; line-height: 1.5;
}
.m-ceval-notes.m-expanded { -webkit-line-clamp: unset; display: block; }
.m-ceval-notes-empty { font-size: 12px; color: #6B6B7B; font-style: italic; margin-bottom: 6px; }
.m-ceval-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-ceval-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-ceval-btn { background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center; gap: 4px; }
.m-ceval-detail { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-ceval-detail.m-visible { display: block; }
.m-ceval-detail-label { font-size: 11px; color: #6B6B7B; margin-bottom: 2px; }
.m-ceval-detail-text { font-size: 13px; color: #A8A8B8; margin-bottom: 8px; word-break: break-word; }
.m-fab { position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: #6B46C1; color: #fff; border: none; font-size: 22px; cursor: pointer; z-index: 999; box-shadow: 0 4px 12px rgba(107,70,193,0.4); display: flex; align-items: center; justify-content: center; }
.m-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; }
.m-overlay.m-visible { display: block; }
.m-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh; overflow-y: auto; z-index: 1001; transform: translateY(100%); transition: transform 0.3s ease; padding: 20px 16px 32px; }
.m-sheet.m-visible { transform: translateY(0); }
.m-sheet-handle { width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-form-input, .m-form-select, .m-form-textarea { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box; font-family: Inter, sans-serif; font-size: 14px; }
.m-form-textarea { min-height: 80px; resize: vertical; }
.m-form-submit { background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px; font-weight: 600; width: 100%; border: none; cursor: pointer; font-family: Inter, sans-serif; font-size: 14px; margin-top: 8px; }
</style>

<div class="m-coach-evals">
    <div class="m-coach-evals-header">
        <h2 class="m-coach-evals-title">My Evaluations</h2>
        <p class="m-coach-evals-sub"><?= $totalEvals ?> evaluation<?= $totalEvals !== 1 ? 's' : '' ?> performed</p>
        <?php if ($isAdminUser): ?>
        <a href="dashboard.php?page=admin_eval_framework" class="m-admin-link">
            <i class="fas fa-cog"></i> Eval Framework
        </a>
        <?php endif; ?>
    </div>

    <div class="m-stats-row">
        <div class="m-stat-card">
            <div class="m-stat-icon m-stat-total"><i class="fas fa-clipboard-list"></i></div>
            <div class="m-stat-info">
                <span class="m-stat-value" id="mStatTotal"><?= $eval_stats['total'] ?></span>
                <span class="m-stat-label">Total</span>
            </div>
        </div>
        <div class="m-stat-card">
            <div class="m-stat-icon m-stat-published"><i class="fas fa-check-circle"></i></div>
            <div class="m-stat-info">
                <span class="m-stat-value" id="mStatPublished"><?= $eval_stats['published'] ?></span>
                <span class="m-stat-label">Published</span>
            </div>
        </div>
        <div class="m-stat-card">
            <div class="m-stat-icon m-stat-draft"><i class="fas fa-edit"></i></div>
            <div class="m-stat-info">
                <span class="m-stat-value" id="mStatDraft"><?= $eval_stats['draft'] ?></span>
                <span class="m-stat-label">Drafts</span>
            </div>
        </div>
    </div>

    <div class="m-ceval-filter">
        <select id="mCevalAthleteFilter" class="m-form-select" aria-label="Filter by athlete">
            <option value="">All Athletes</option>
            <?php foreach ($athletes as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="mCevalList">
    <?php if (empty($evaluations)): ?>
        <div class="m-empty-state" id="mCevalEmpty">
            <i class="fas fa-clipboard-check"></i>
            <p>No evaluations performed yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($evaluations as $ev):
            $score = (float)($ev['score'] ?? 0);
            $maxScore = (float)($ev['max_score'] ?? 10);
            $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
            $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
            $athName = htmlspecialchars(($ev['first_name'] ?? '') . ' ' . ($ev['last_name'] ?? ''));
            $evaluatorName = htmlspecialchars(trim(($ev['evaluator_first_name'] ?? '') . ' ' . ($ev['evaluator_last_name'] ?? '')));
            $status = 'published';
            $notes = $ev['comments'] ?? '';
        ?>
        <div class="m-ceval-card m-status-<?= $status ?>" data-athlete-id="<?= (int)($ev['athlete_id'] ?? 0) ?>">
            <div class="m-ceval-top">
                <span class="m-ceval-skill"><?= htmlspecialchars($ev['skill_name'] ?? 'Unnamed Skill') ?></span>
                <span class="m-ceval-status-badge m-badge-<?= $status ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-ceval-top">
                <span class="m-ceval-athlete"><i class="fas fa-user"></i> <?= $athName ?></span>
                <span class="m-ceval-score-badge"><?= $score ?>/<?= $maxScore ?></span>
            </div>
            <?php if ($evaluatorName !== ''): ?>
            <div class="m-ceval-evaluator">
                <i class="fas fa-user-tie"></i> <?= $evaluatorName ?>
            </div>
            <?php endif; ?>
            <div class="m-ceval-bar-wrap">
                <div class="m-ceval-bar">
                    <div class="m-ceval-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php if ($notes !== ''): ?>
            <div class="m-ceval-notes"><?= nl2br(htmlspecialchars($notes)) ?></div>
            <?php else: ?>
            <div class="m-ceval-notes-empty">No comments provided</div>
            <?php endif; ?>
            <?php if (!empty($ev['evaluation_date'])): ?>
            <div class="m-ceval-date">
                <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?>
            </div>
            <?php endif; ?>
            <div class="m-ceval-actions">
                <button class="m-ceval-btn m-ceval-detail-toggle" data-eval-id="<?= (int)$ev['id'] ?>">
                    <i class="fas fa-eye"></i> Details
                </button>
            </div>
            <div class="m-ceval-detail" id="mCevalDetail-<?= (int)$ev['id'] ?>">
                <div class="m-ceval-detail-label">Athlete</div>
                <div class="m-ceval-detail-text"><?= $athName ?></div>
                <?php if ($evaluatorName !== ''): ?>
                <div class="m-ceval-detail-label">Evaluator</div>
                <div class="m-ceval-detail-text"><?= $evaluatorName ?></div>
                <?php endif; ?>
                <div class="m-ceval-detail-label">Score</div>
                <div class="m-ceval-detail-text"><?= $score ?> / <?= $maxScore ?> (<?= $pct ?>%)</div>
                <?php if ($notes !== ''): ?>
                <div class="m-ceval-detail-label">Comments</div>
                <div class="m-ceval-detail-text"><?= nl2br(htmlspecialchars($notes)) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div id="mCevalFilterEmpty" style="display:none;">
            <div class="m-empty-state">
                <i class="fas fa-search"></i>
                <p>No evaluations for this athlete</p>
            </div>
        </div>
    <?php endif; ?>
    </div>
</div>

<button class="m-fab" id="mCevalFab" aria-label="New evaluation">
    <i class="fas fa-plus"></i>
</button>

<div class="m-overlay" id="mCevalOverlay"></div>
<div class="m-sheet" id="mCevalSheet">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">New Evaluation</h3>
    <form id="mCevalForm">
        <?= csrfTokenInput() ?>
        <div class="m-form-group">
            <label class="m-form-label">Athlete *</label>
            <select name="athlete_id" class="m-form-select" required>
                <option value="">Select athlete…</option>
                <?php foreach ($athletes as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Evaluation Date *</label>
            <input type="date" name="evaluation_date" class="m-form-input" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Title</label>
            <input type="text" name="title" class="m-form-input" placeholder="e.g., Mid-Season Assessment">
        </div>
        <button type="submit" class="m-form-submit">Create Evaluation</button>
    </form>
</div>

<script>
(function() {
    var overlay = document.getElementById('mCevalOverlay');
    var sheet = document.getElementById('mCevalSheet');
    var fab = document.getElementById('mCevalFab');
    var form = document.getElementById('mCevalForm');
    var filterSelect = document.getElementById('mCevalAthleteFilter');

    function openSheet() {
        overlay.classList.add('m-visible');
        sheet.classList.add('m-visible');
    }
    function closeSheet() {
        overlay.classList.remove('m-visible');
        sheet.classList.remove('m-visible');
    }

    if (fab) { fab.addEventListener('click', openSheet); }
    if (overlay) { overlay.addEventListener('click', closeSheet); }

    document.querySelectorAll('.m-ceval-detail-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-eval-id');
            var el = document.getElementById('mCevalDetail-' + id);
            if (el) { el.classList.toggle('m-visible'); }
        });
    });

    document.querySelectorAll('.m-ceval-notes').forEach(function(el) {
        el.addEventListener('click', function() {
            this.classList.toggle('m-expanded');
        });
    });

    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            var athleteId = this.value;
            var cards = document.querySelectorAll('.m-ceval-card');
            var visibleCount = 0;
            var publishedCount = 0;
            var draftCount = 0;
            cards.forEach(function(card) {
                if (!athleteId || card.getAttribute('data-athlete-id') === athleteId) {
                    card.style.display = '';
                    visibleCount++;
                    if (card.classList.contains('m-status-published')) { publishedCount++; }
                    if (card.classList.contains('m-status-draft')) { draftCount++; }
                } else {
                    card.style.display = 'none';
                }
            });
            var totalEl = document.getElementById('mStatTotal');
            var pubEl = document.getElementById('mStatPublished');
            var draftEl = document.getElementById('mStatDraft');
            if (totalEl) { totalEl.textContent = visibleCount; }
            if (pubEl) { pubEl.textContent = publishedCount; }
            if (draftEl) { draftEl.textContent = draftCount; }
            var emptyEl = document.getElementById('mCevalFilterEmpty');
            if (emptyEl) {
                emptyEl.style.display = (visibleCount === 0 && cards.length > 0) ? '' : 'none';
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData(form);
            fd.append('action', 'create_evaluation');
            fetch('process_eval_skills.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        persistToast(data.message || 'Operation completed successfully', 'success');
                        location.reload();
                    } else {
                        showToast(data.message || 'Error creating evaluation', 'error');
                    }
                })
                .catch(function() { showToast('Error creating evaluation', 'error'); });
        });
    }
})();
</script>
