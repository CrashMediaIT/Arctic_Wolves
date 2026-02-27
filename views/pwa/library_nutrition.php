<?php
/**
 * PWA Nutrition Library - Mobile-native meal plan library
 * Purpose-built for mobile phones.
 */

$mealPlans = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, description, calories
        FROM meal_plans
        WHERE is_active = 1
        ORDER BY name
        LIMIT 30
    ");
    $stmt->execute();
    $mealPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $mealPlans = []; }

$allAthletes = [];
try {
    $stmt2 = $pdo->query("SELECT id, first_name, last_name FROM users WHERE is_active = 1 AND role = 'athlete' ORDER BY first_name, last_name");
    $allAthletes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) $allAthletes = decryptUserRows($allAthletes);
} catch (PDOException $e) { $allAthletes = []; }

$totalPlans = count($mealPlans);
?>
<style>
.m-libnutrition { padding: 16px; padding-bottom: 80px; font-family: Inter, sans-serif; }
.m-libnutrition-header { margin-bottom: 16px; }
.m-libnutrition-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-libnutrition-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-meal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-meal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-meal-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-meal-cal {
    font-size: 12px; font-weight: 700; color: #10B981; flex-shrink: 0;
}
.m-meal-desc { font-size: 12px; color: #A8A8B8; margin: 4px 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-meal-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-meal-action-btn {
    font-size: 12px; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer;
    font-weight: 600; font-family: Inter, sans-serif; display: inline-flex; align-items: center; gap: 4px;
}
.m-meal-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-meal-btn-del { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-meal-btn-assign { background: rgba(16,185,129,0.15); color: #10B981; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-ln-fab {
    position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px;
    background: #6B46C1; color: #fff; border: none; border-radius: 50%;
    font-size: 24px; cursor: pointer; z-index: 999;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    display: flex; align-items: center; justify-content: center;
}
.m-ln-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none;
}
.m-ln-overlay.active { display: block; }
.m-ln-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh;
    overflow-y: auto; transform: translateY(100%); transition: transform 0.3s ease;
    padding: 20px 16px 32px;
}
.m-ln-sheet.active { transform: translateY(0); }
.m-ln-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-ln-field label {
    font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block;
}
.m-ln-field { margin-bottom: 14px; }
.m-ln-field input, .m-ln-field select, .m-ln-field textarea {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box;
    font-family: Inter, sans-serif; font-size: 14px;
}
.m-ln-field textarea { min-height: 80px; resize: vertical; }
.m-ln-submit {
    background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px;
    font-weight: 600; width: 100%; border: none; cursor: pointer;
    font-family: Inter, sans-serif; font-size: 15px; margin-top: 8px;
}
.m-ln-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
</style>

<div class="m-libnutrition">
    <div class="m-libnutrition-header">
        <h2 class="m-libnutrition-title">Nutrition Library</h2>
        <p class="m-libnutrition-sub"><?= $totalPlans ?> meal plan<?= $totalPlans !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($mealPlans)): ?>
        <div class="m-empty-state">
            <i class="fas fa-utensils"></i>
            <p>No meal plans available</p>
        </div>
    <?php else: ?>
        <?php foreach ($mealPlans as $m): ?>
        <div class="m-meal-card">
            <div class="m-meal-top">
                <span class="m-meal-name"><?= htmlspecialchars($m['name']) ?></span>
                <?php if (!empty($m['calories'])): ?>
                <span class="m-meal-cal"><?= (int)$m['calories'] ?> cal</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($m['description'])): ?>
            <div class="m-meal-desc"><?= htmlspecialchars($m['description']) ?></div>
            <?php endif; ?>
            <div class="m-meal-actions">
                <button type="button" class="m-meal-action-btn m-meal-btn-edit" onclick="mLnEdit(<?= (int)$m['id'] ?>, <?= htmlspecialchars(json_encode($m['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($m['description'] ?? ''), ENT_QUOTES) ?>, <?= (int)($m['calories'] ?? 0) ?>)"><i class="fas fa-pen"></i> Edit</button>
                <button type="button" class="m-meal-action-btn m-meal-btn-assign" onclick="mLnAssign(<?= (int)$m['id'] ?>, <?= htmlspecialchars(json_encode($m['name']), ENT_QUOTES) ?>)"><i class="fas fa-user-plus"></i> Assign</button>
                <button type="button" class="m-meal-action-btn m-meal-btn-del" onclick="mLnDelete(<?= (int)$m['id'] ?>)"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button type="button" class="m-ln-fab" onclick="mLnOpenCreate()"><i class="fas fa-plus"></i></button>

<div class="m-ln-overlay" id="mLnOverlay" onclick="mLnClose()"></div>
<div class="m-ln-sheet" id="mLnSheet">
    <h3 class="m-ln-sheet-title" id="mLnSheetTitle">Create Nutrition Plan</h3>
    <form method="POST" action="process_nutrition.php" id="mLnForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mLnAction" value="create_plan">
        <input type="hidden" name="id" id="mLnId" value="">
        <div class="m-ln-field">
            <label>Plan Name *</label>
            <input type="text" name="name" id="mLnName" required placeholder="e.g., High Protein Athlete Diet">
        </div>
        <div class="m-ln-field">
            <label>Description</label>
            <textarea name="description" id="mLnDesc" placeholder="Describe the nutrition plan goals"></textarea>
        </div>
        <div class="m-ln-row">
            <div class="m-ln-field">
                <label>Target Calories</label>
                <input type="number" name="target_calories" id="mLnCalories" placeholder="e.g., 2500">
            </div>
            <div class="m-ln-field">
                <label>Target Protein (g)</label>
                <input type="number" name="target_protein_g" id="mLnProtein" placeholder="e.g., 150">
            </div>
        </div>
        <div class="m-ln-row">
            <div class="m-ln-field">
                <label>Target Carbs (g)</label>
                <input type="number" name="target_carbs_g" id="mLnCarbs" placeholder="e.g., 300">
            </div>
            <div class="m-ln-field">
                <label>Target Fat (g)</label>
                <input type="number" name="target_fat_g" id="mLnFat" placeholder="e.g., 80">
            </div>
        </div>
        <button type="submit" class="m-ln-submit" id="mLnSubmitBtn">Create Nutrition Plan</button>
    </form>
</div>

<!-- Assign Sheet -->
<div class="m-ln-overlay" id="mLnAssignOverlay" onclick="mLnCloseAssign()"></div>
<div class="m-ln-sheet" id="mLnAssignSheet">
    <h3 class="m-ln-sheet-title">Assign to Athlete</h3>
    <form method="POST" action="process_nutrition.php" id="mLnAssignForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="assign_athletes">
        <input type="hidden" name="nutrition_plan_id" id="mLnAssignPlanId">
        <div class="m-ln-field">
            <label>Nutrition Plan</label>
            <input type="text" id="mLnAssignPlanName" readonly>
        </div>
        <div class="m-ln-field">
            <label>Select Athlete *</label>
            <select name="athlete_ids[]" id="mLnAssignAthlete" required>
                <option value="">-- Select Athlete --</option>
                <?php foreach ($allAthletes as $ath): ?>
                <option value="<?= (int)$ath['id'] ?>"><?= htmlspecialchars($ath['first_name'] . ' ' . $ath['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-ln-field">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="m-ln-field">
            <label>Notes</label>
            <textarea name="notes" placeholder="Optional notes"></textarea>
        </div>
        <button type="submit" class="m-ln-submit">Assign Nutrition Plan</button>
    </form>
</div>

<form id="mLnDeleteForm" method="POST" action="process_nutrition.php" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_plan">
    <input type="hidden" name="id" id="mLnDeleteId">
</form>

<script>
function mLnOpenCreate() {
    document.getElementById('mLnSheetTitle').textContent = 'Create Nutrition Plan';
    document.getElementById('mLnAction').value = 'create_plan';
    document.getElementById('mLnId').value = '';
    document.getElementById('mLnName').value = '';
    document.getElementById('mLnDesc').value = '';
    document.getElementById('mLnCalories').value = '';
    document.getElementById('mLnProtein').value = '';
    document.getElementById('mLnCarbs').value = '';
    document.getElementById('mLnFat').value = '';
    document.getElementById('mLnSubmitBtn').textContent = 'Create Nutrition Plan';
    document.getElementById('mLnOverlay').classList.add('active');
    document.getElementById('mLnSheet').classList.add('active');
}
function mLnEdit(id, name, desc, cal) {
    document.getElementById('mLnSheetTitle').textContent = 'Edit Nutrition Plan';
    document.getElementById('mLnAction').value = 'update_plan';
    document.getElementById('mLnId').value = id;
    document.getElementById('mLnName').value = name;
    document.getElementById('mLnDesc').value = desc;
    document.getElementById('mLnCalories').value = cal || '';
    document.getElementById('mLnProtein').value = '';
    document.getElementById('mLnCarbs').value = '';
    document.getElementById('mLnFat').value = '';
    document.getElementById('mLnSubmitBtn').textContent = 'Update Nutrition Plan';
    document.getElementById('mLnOverlay').classList.add('active');
    document.getElementById('mLnSheet').classList.add('active');
}
async function mLnDelete(id) {
    if (await showConfirmModal('Delete this nutrition plan?')) {
        document.getElementById('mLnDeleteId').value = id;
        document.getElementById('mLnDeleteForm').submit();
    }
}
function mLnClose() {
    document.getElementById('mLnOverlay').classList.remove('active');
    document.getElementById('mLnSheet').classList.remove('active');
}
function mLnAssign(id, name) {
    document.getElementById('mLnAssignPlanId').value = id;
    document.getElementById('mLnAssignPlanName').value = name;
    document.getElementById('mLnAssignOverlay').classList.add('active');
    document.getElementById('mLnAssignSheet').classList.add('active');
}
function mLnCloseAssign() {
    document.getElementById('mLnAssignOverlay').classList.remove('active');
    document.getElementById('mLnAssignSheet').classList.remove('active');
}
</script>
