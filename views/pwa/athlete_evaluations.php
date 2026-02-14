<?php
/**
 * PWA Athlete Evaluations - Mobile-native evaluation view for athletes
 * Purpose-built for mobile phones.
 */

$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, es.notes,
               ek.name as skill_name, ek.category,
               u.first_name as evaluator_first, u.last_name as evaluator_last
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        LEFT JOIN users u ON u.id = es.evaluator_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($evaluations as &$ev) {
        if (!empty($ev['evaluator_first']) && class_exists('FieldEncryption')) {
            $ev['evaluator_first'] = FieldEncryption::decrypt($ev['evaluator_first']);
            $ev['evaluator_last'] = FieldEncryption::decrypt($ev['evaluator_last']);
        }
    }
    unset($ev);
} catch (PDOException $e) { $evaluations = []; }

// Also fetch published athlete_evaluations (matches desktop view)
$publishedEvals = [];
try {
    $aeStmt = $pdo->prepare("
        SELECT ae.id, ae.eval_date, ae.notes as coach_notes, ae.status,
               u.first_name as evaluator_first_name, u.last_name as evaluator_last_name
        FROM athlete_evaluations ae
        JOIN users u ON ae.evaluator_id = u.id
        WHERE ae.athlete_id = ? AND ae.status = 'published'
        ORDER BY ae.eval_date DESC
    ");
    $aeStmt->execute([$user_id]);
    $publishedEvals = $aeStmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $publishedEvals = decryptUserRows($publishedEvals);
    }
} catch (PDOException $e) { $publishedEvals = []; }

$totalEvals = count($evaluations) + count($publishedEvals);
?>
<style>
.m-ath-evals { padding: 16px; font-family: Inter, sans-serif; }
.m-ath-evals-header { margin-bottom: 16px; }
.m-ath-evals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ath-evals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-eval-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; cursor: pointer;
    transition: border-color 0.2s ease;
}
.m-eval-card.m-eval-expanded { border-color: #8B5CF6; }
.m-eval-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.m-eval-skill { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-eval-cat {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.15); color: #8B5CF6; white-space: nowrap;
}
.m-eval-score-wrap { margin-bottom: 8px; }
.m-eval-score-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-eval-score-label { font-size: 11px; color: #6B6B7B; }
.m-eval-score-value { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-eval-score-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-eval-score-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-eval-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-eval-actions { margin-top: 10px; }
.m-eval-toggle {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px;
    padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif; min-height: 44px; display: flex;
    align-items: center; gap: 6px; width: 100%; justify-content: center;
}
.m-eval-toggle:active { opacity: 0.85; }
.m-eval-detail { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-eval-detail.m-visible { display: block; }
.m-eval-detail-row { margin-bottom: 8px; }
.m-eval-detail-label { font-size: 11px; color: #6B6B7B; margin-bottom: 2px; }
.m-eval-detail-text { font-size: 13px; color: #A8A8B8; line-height: 1.5; }
.m-eval-section-label {
    font-size: 13px; font-weight: 600; color: #6B6B7B; text-transform: uppercase;
    letter-spacing: 0.5px; margin: 16px 0 10px; padding: 0 4px;
}
/* Bottom sheet for full evaluation detail */
.m-eval-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 1000; align-items: flex-end; justify-content: center;
}
.m-eval-overlay.m-visible { display: flex; }
.m-eval-sheet {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 500px;
    max-height: 80vh; overflow-y: auto; padding: 20px 16px 32px;
    animation: mEvalSlideUp 0.3s ease;
}
@keyframes mEvalSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-eval-sheet-handle { width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-eval-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.m-eval-sheet-sub { font-size: 12px; color: #A8A8B8; margin: 0 0 16px; }
.m-eval-sheet-section { margin-bottom: 14px; }
.m-eval-sheet-section-title { font-size: 11px; font-weight: 600; color: #6B6B7B; text-transform: uppercase; margin-bottom: 6px; }
.m-eval-sheet-text { font-size: 14px; color: #fff; line-height: 1.6; }
.m-eval-sheet-notes { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; padding: 12px; font-size: 13px; color: #A8A8B8; line-height: 1.6; }
.m-eval-sheet-close {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px;
    font-weight: 600; font-size: 14px; cursor: pointer; width: 100%; margin-top: 10px;
    font-family: Inter, sans-serif;
}
</style>

<div class="m-ath-evals">
    <div class="m-ath-evals-header">
        <h2 class="m-ath-evals-title">My Evaluations</h2>
        <p class="m-ath-evals-sub"><?= $totalEvals ?> evaluation<?= $totalEvals !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($evaluations) && empty($publishedEvals)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-check"></i>
            <p>No evaluations yet</p>
        </div>
    <?php else: ?>
        <!-- Published Coach Evaluations (matches desktop athlete_evaluations.php) -->
        <?php if (!empty($publishedEvals)): ?>
        <div class="m-eval-section-label">Coach Evaluations</div>
        <?php foreach ($publishedEvals as $idx => $ae): ?>
        <div class="m-eval-card" onclick="mOpenEvalSheet(<?= $idx ?>)">
            <div class="m-eval-top">
                <span class="m-eval-skill"><i class="fas fa-clipboard-check" style="color:#8B5CF6;margin-right:4px;font-size:12px;"></i> Evaluation - <?= date('M j, Y', strtotime($ae['eval_date'])) ?></span>
                <span class="m-eval-cat">Published</span>
            </div>
            <div class="m-eval-date">
                <i class="fas fa-user-tie"></i> by <?= htmlspecialchars(($ae['evaluator_first_name'] ?? '') . ' ' . ($ae['evaluator_last_name'] ?? '')) ?>
            </div>
            <div class="m-eval-actions">
                <button class="m-eval-toggle" type="button">
                    <i class="fas fa-eye"></i> View Details
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Skill Scores -->
        <?php if (!empty($evaluations)): ?>
        <div class="m-eval-section-label">Skill Scores</div>
        <?php foreach ($evaluations as $ev):
            $score = (float)($ev['score'] ?? 0);
            $maxScore = (float)($ev['max_score'] ?? 10);
            $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
            $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
        ?>
        <div class="m-eval-card" onclick="mToggleEvalCard(this)">
            <div class="m-eval-top">
                <span class="m-eval-skill"><?= htmlspecialchars($ev['skill_name'] ?? 'Unnamed Skill') ?></span>
                <?php if (!empty($ev['category'])): ?>
                <span class="m-eval-cat"><?= htmlspecialchars($ev['category']) ?></span>
                <?php endif; ?>
            </div>
            <div class="m-eval-score-wrap">
                <div class="m-eval-score-header">
                    <span class="m-eval-score-label">Score</span>
                    <span class="m-eval-score-value"><?= $score ?> / <?= $maxScore ?></span>
                </div>
                <div class="m-eval-score-bar">
                    <div class="m-eval-score-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php if (!empty($ev['evaluation_date'])): ?>
            <div class="m-eval-date">
                <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?>
            </div>
            <?php endif; ?>
            <div class="m-eval-detail">
                <?php if (!empty($ev['evaluator_first']) || !empty($ev['evaluator_last'])): ?>
                <div class="m-eval-detail-row">
                    <div class="m-eval-detail-label">Evaluator</div>
                    <div class="m-eval-detail-text"><i class="fas fa-user-tie" style="color:#8B5CF6;margin-right:4px;font-size:11px;"></i> <?= htmlspecialchars(($ev['evaluator_first'] ?? '') . ' ' . ($ev['evaluator_last'] ?? '')) ?></div>
                </div>
                <?php endif; ?>
                <div class="m-eval-detail-row">
                    <div class="m-eval-detail-label">Score Breakdown</div>
                    <div class="m-eval-detail-text"><?= $score ?> out of <?= $maxScore ?> (<?= $pct ?>%)</div>
                </div>
                <?php if (!empty($ev['notes'])): ?>
                <div class="m-eval-detail-row">
                    <div class="m-eval-detail-label">Notes</div>
                    <div class="m-eval-detail-text"><?= nl2br(htmlspecialchars($ev['notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Evaluation Detail Bottom Sheet -->
<div class="m-eval-overlay" id="mEvalOverlay" onclick="if(event.target===this)mCloseEvalSheet()">
    <div class="m-eval-sheet">
        <div class="m-eval-sheet-handle"></div>
        <div class="m-eval-sheet-title" id="mEvalSheetTitle"></div>
        <div class="m-eval-sheet-sub" id="mEvalSheetSub"></div>
        <div class="m-eval-sheet-section">
            <div class="m-eval-sheet-section-title">Evaluator</div>
            <div class="m-eval-sheet-text" id="mEvalSheetEvaluator"></div>
        </div>
        <div class="m-eval-sheet-section" id="mEvalSheetNotesWrap" style="display:none;">
            <div class="m-eval-sheet-section-title">Coach Notes</div>
            <div class="m-eval-sheet-notes" id="mEvalSheetNotes"></div>
        </div>
        <button class="m-eval-sheet-close" onclick="mCloseEvalSheet()" type="button">Close</button>
    </div>
</div>

<script>
var mPublishedEvals = <?= json_encode(array_map(function($ae) {
    return [
        'date' => date('M j, Y', strtotime($ae['eval_date'])),
        'evaluator' => ($ae['evaluator_first_name'] ?? '') . ' ' . ($ae['evaluator_last_name'] ?? ''),
        'notes' => $ae['coach_notes'] ?? ''
    ];
}, $publishedEvals), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function mToggleEvalCard(card) {
    var detail = card.querySelector('.m-eval-detail');
    if (detail) {
        detail.classList.toggle('m-visible');
        card.classList.toggle('m-eval-expanded');
    }
}

function mOpenEvalSheet(idx) {
    var ev = mPublishedEvals[idx];
    if (!ev) return;
    document.getElementById('mEvalSheetTitle').textContent = 'Evaluation - ' + ev.date;
    document.getElementById('mEvalSheetSub').textContent = ev.date;
    document.getElementById('mEvalSheetEvaluator').textContent = ev.evaluator;
    var notesWrap = document.getElementById('mEvalSheetNotesWrap');
    if (ev.notes) {
        notesWrap.style.display = 'block';
        document.getElementById('mEvalSheetNotes').innerHTML = ev.notes.replace(/\n/g, '<br>');
    } else {
        notesWrap.style.display = 'none';
    }
    document.getElementById('mEvalOverlay').classList.add('m-visible');
}

function mCloseEvalSheet() {
    document.getElementById('mEvalOverlay').classList.remove('m-visible');
}
</script>
