<?php
/**
 * PWA Admin Business Partners - Mobile-native business partners management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$partners = [];
try {
    $stmt = $pdo->prepare("SELECT id, company_name, contact_name, email, phone, status, contact_email, contact_phone, company_phone, company_email, company_website, company_address, description, contact_title FROM business_partners ORDER BY company_name ASC LIMIT 50");
    $stmt->execute();
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("SELECT id, company_name, contact_name, email, phone, status FROM business_partners ORDER BY company_name ASC LIMIT 20");
        $stmt->execute();
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { $partners = []; }
}
?>
<style>
.m-partners { padding: 16px; font-family: Inter, sans-serif; }
.m-partners-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.m-partners-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-partners-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-partner-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-partner-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(59,130,246,0.15); color: #3B82F6; font-size: 16px; flex-shrink: 0;
}
.m-partner-body { flex: 1; min-width: 0; }
.m-partner-company { font-size: 14px; font-weight: 600; color: #fff; }
.m-partner-contact { font-size: 12px; color: #A8A8B8; margin-top: 1px; }
.m-partner-links { display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.m-partner-link {
    font-size: 11px; color: #8B5CF6; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
}
.m-partner-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; flex-shrink: 0; }
.m-partner-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-partner-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-partner-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-partner-actions { display: flex; gap: 6px; flex-shrink: 0; }
.m-partner-action-btn {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #A8A8B8; display: flex; align-items: center;
    justify-content: center; cursor: pointer; font-size: 12px;
}
.m-partner-action-btn.m-del { color: #EF4444; border-color: rgba(239,68,68,0.3); }
.m-partner-fab {
    position: fixed; bottom: 80px; right: 16px; width: 52px; height: 52px;
    border-radius: 14px; background: #6B46C1; color: #fff; border: none;
    font-size: 20px; cursor: pointer; z-index: 100;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
}
.m-partner-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6); z-index: 9998;
}
.m-partner-overlay.m-active { display: block; }
.m-partner-sheet {
    display: none; position: fixed; left: 0; right: 0; bottom: 0;
    background: #16161F; border-radius: 16px 16px 0 0; z-index: 9999;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
}
.m-partner-sheet.m-active { display: block; }
.m-partner-sheet-title {
    font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between;
}
.m-partner-sheet-close {
    background: none; border: none; color: #A8A8B8; font-size: 22px; cursor: pointer;
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
}
.m-partner-form-group { margin-bottom: 12px; }
.m-partner-form-label { font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 4px; display: block; }
.m-partner-form-input {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 14px;
    font-family: Inter, sans-serif; box-sizing: border-box;
}
.m-partner-form-input:focus { outline: none; border-color: #6B46C1; }
.m-partner-form-textarea { resize: vertical; min-height: 60px; }
.m-partner-submit {
    width: 100%; padding: 12px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; min-height: 44px;
    cursor: pointer; margin-top: 8px; font-family: Inter, sans-serif;
}
.m-partner-alert {
    padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: 12px;
    background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3);
}
.m-partner-alert-err {
    padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: 12px;
    background: rgba(239,68,68,0.15); color: #EF4444; border: 1px solid rgba(239,68,68,0.3);
}
</style>

<div class="m-partners">
    <div class="m-partners-header">
        <div>
            <h2 class="m-partners-title">Business Partners</h2>
            <p class="m-partners-sub"><?= count($partners) ?> partner<?= count($partners) !== 1 ? 's' : '' ?></p>
        </div>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="m-partner-alert"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['message'] ?? 'Operation completed!') ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
    <div class="m-partner-alert-err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['message'] ?? 'An error occurred.') ?></div>
    <?php endif; ?>

    <?php if (empty($partners)): ?>
        <div class="m-empty-state">
            <i class="fas fa-handshake"></i>
            <p>No business partners found</p>
        </div>
    <?php else: ?>
        <?php foreach ($partners as $p):
            $status = strtolower($p['status'] ?? 'unknown');
            $badgeClass = match($status) {
                'active' => 'active',
                'inactive', 'terminated' => 'inactive',
                default => 'default',
            };
        ?>
        <div class="m-partner-card">
            <div class="m-partner-icon"><i class="fas fa-building"></i></div>
            <div class="m-partner-body">
                <div class="m-partner-company"><?= htmlspecialchars($p['company_name'] ?? '') ?></div>
                <?php if (!empty($p['contact_name'])): ?>
                <div class="m-partner-contact"><?= htmlspecialchars($p['contact_name']) ?></div>
                <?php endif; ?>
                <div class="m-partner-links">
                    <?php if (!empty($p['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($p['email']) ?>" class="m-partner-link"><i class="fas fa-envelope"></i> <?= htmlspecialchars($p['email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($p['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($p['phone']) ?>" class="m-partner-link"><i class="fas fa-phone"></i> <?= htmlspecialchars($p['phone']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="m-partner-actions">
                <button class="m-partner-action-btn" onclick='mPartnerEdit(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                <form method="POST" action="process_business_partners.php" style="margin:0;" data-confirm="Delete this partner?">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="delete_partner">
                    <input type="hidden" name="partner_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="m-partner-action-btn m-del" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
            </div>
            <span class="m-partner-badge m-partner-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-partner-fab" onclick="mPartnerAdd()" title="Add Partner"><i class="fas fa-plus"></i></button>

<div class="m-partner-overlay" id="mPartnerOverlay" onclick="mPartnerClose()"></div>
<div class="m-partner-sheet" id="mPartnerSheet">
    <div class="m-partner-sheet-title">
        <span id="mPartnerSheetLabel"><i class="fas fa-plus-circle"></i> Add Partner</span>
        <button class="m-partner-sheet-close" onclick="mPartnerClose()">&times;</button>
    </div>
    <form method="POST" action="process_business_partners.php" id="mPartnerForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mPartnerAction" value="create_partner">
        <input type="hidden" name="partner_id" id="mPartnerEditId" value="">

        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Company Name *</label>
            <input type="text" name="company_name" id="mPF_company_name" class="m-partner-form-input" required placeholder="Partner company name">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Company Email</label>
            <input type="email" name="company_email" id="mPF_company_email" class="m-partner-form-input" placeholder="info@company.com">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Company Phone</label>
            <input type="tel" name="company_phone" id="mPF_company_phone" class="m-partner-form-input" placeholder="(555) 123-4567">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Company Website</label>
            <input type="url" name="company_website" id="mPF_company_website" class="m-partner-form-input" placeholder="https://www.company.com">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Company Address</label>
            <input type="text" name="company_address" id="mPF_company_address" class="m-partner-form-input" placeholder="Full company address">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Description</label>
            <textarea name="description" id="mPF_description" class="m-partner-form-input m-partner-form-textarea" placeholder="Brief description of the partnership"></textarea>
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Contact Name</label>
            <input type="text" name="contact_name" id="mPF_contact_name" class="m-partner-form-input" placeholder="Full name of point of contact">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Contact Title</label>
            <input type="text" name="contact_title" id="mPF_contact_title" class="m-partner-form-input" placeholder="e.g., Account Manager">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Contact Email</label>
            <input type="email" name="contact_email" id="mPF_contact_email" class="m-partner-form-input" placeholder="contact@company.com">
        </div>
        <div class="m-partner-form-group">
            <label class="m-partner-form-label">Contact Phone</label>
            <input type="tel" name="contact_phone" id="mPF_contact_phone" class="m-partner-form-input" placeholder="(555) 987-6543">
        </div>
        <button type="submit" class="m-partner-submit" id="mPartnerSubmitBtn"><i class="fas fa-save"></i> Create Partner</button>
    </form>
</div>

<script>
function mPartnerAdd() {
    document.getElementById('mPartnerAction').value = 'create_partner';
    document.getElementById('mPartnerEditId').value = '';
    document.getElementById('mPartnerSheetLabel').innerHTML = '<i class="fas fa-plus-circle"></i> Add Partner';
    document.getElementById('mPartnerSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Create Partner';
    document.getElementById('mPartnerForm').reset();
    mPartnerOpen();
}
function mPartnerEdit(p) {
    document.getElementById('mPartnerAction').value = 'update_partner';
    document.getElementById('mPartnerEditId').value = p.id || '';
    document.getElementById('mPartnerSheetLabel').innerHTML = '<i class="fas fa-edit"></i> Edit Partner';
    document.getElementById('mPartnerSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Partner';
    var fields = ['company_name','company_email','company_phone','company_website','company_address','description','contact_name','contact_title','contact_email','contact_phone'];
    fields.forEach(function(f) {
        var el = document.getElementById('mPF_' + f);
        if (el) el.value = p[f] || '';
    });
    mPartnerOpen();
}
function mPartnerOpen() {
    document.getElementById('mPartnerOverlay').classList.add('m-active');
    document.getElementById('mPartnerSheet').classList.add('m-active');
}
function mPartnerClose() {
    document.getElementById('mPartnerOverlay').classList.remove('m-active');
    document.getElementById('mPartnerSheet').classList.remove('m-active');
}
</script>
