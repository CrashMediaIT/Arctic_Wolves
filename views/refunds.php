<?php
// views/refunds.php - Refund management interface
require_once __DIR__ . '/../security.php';

if ($_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

// Get recent sessions for search
$sessions_stmt = $pdo->query("
    SELECT id, session_name, session_date 
    FROM sessions 
    WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ORDER BY session_date DESC
    LIMIT 100
");
$sessions = $sessions_stmt->fetchAll();
?>

<style>
    .refunds-container {
        max-width: 1600px;
        margin: 0 auto;
    }
    
    .search-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
    }
    
    .search-card h3 {
        color: white;
        font-size: 1.2rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .search-card h3 i {
        color: var(--primary);
    }
    
    .search-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-group label {
        display: block;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .form-group input, .form-group select {
        width: 100%;
        padding: 12px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        color: white;
        font-size: 1rem;
    }
    
    .form-group input:focus, .form-group select:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.95rem;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: #e64500;
    }
    
    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    .results-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .results-table th {
        background: rgba(255, 77, 0, 0.1);
        color: var(--primary);
        padding: 15px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid var(--primary);
    }
    
    .results-table td {
        padding: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
    }
    
    .results-table tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    
    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .badge-success {
        background: #10b981;
        color: white;
    }
    
    .badge-info {
        background: #3b82f6;
        color: white;
    }
    
    .badge-warning {
        background: #f59e0b;
        color: white;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: #0a0e14;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .modal-header h3 {
        color: white;
        font-size: 1.4rem;
        margin: 0;
    }
    
    .close-modal {
        background: transparent;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
    }
    
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }
    
    .tab {
        padding: 12px 25px;
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.6);
        cursor: pointer;
        font-weight: 600;
        border-bottom: 3px solid transparent;
        transition: 0.2s;
    }
    
    .tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
</style>

<div class="dash-content refunds-container">
    <div class="dash-header">
        <h2><i class="fas fa-undo"></i> Refund Management</h2>
        <p style="color: rgba(255, 255, 255, 0.6);">Process refunds and view refund history</p>
    </div>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('bookings')">Search Bookings</button>
        <button class="tab" onclick="switchTab('history')">Refund History</button>
    </div>
    
    <!-- Search Bookings Tab -->
    <div id="bookingsTab" class="tab-content active">
        <div class="search-card">
            <h3><i class="fas fa-search"></i> Search Bookings</h3>
            
            <div class="search-grid">
                <div class="form-group">
                    <label>Customer Email</label>
                    <input type="email" id="searchEmail" placeholder="Enter email">
                </div>
                
                <div class="form-group">
                    <label>Session</label>
                    <select id="searchSession">
                        <option value="">-- All Sessions --</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?= $session['id'] ?>">
                                <?= htmlspecialchars($session['session_name']) ?> - 
                                <?= date('M d, Y', strtotime($session['session_date'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" id="searchStartDate" value="<?= date('Y-m-01') ?>">
                </div>
                
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" id="searchEndDate" value="<?= date('Y-m-t') ?>">
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="searchBookings()">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
        
        <div id="bookingsResults" style="overflow-x: auto;"></div>
    </div>
    
    <!-- Refund History Tab -->
    <div id="historyTab" class="tab-content">
        <div class="search-card">
            <h3>
                <i class="fas fa-history"></i> Refund History
                <button class="btn btn-secondary" onclick="exportRefunds()" style="margin-left: auto; padding: 8px 15px; font-size: 0.85rem;">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </h3>
            
            <div class="search-grid" style="grid-template-columns: 1fr 1fr auto;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" id="historyStartDate" value="<?= date('Y-m-01') ?>">
                </div>
                
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" id="historyEndDate" value="<?= date('Y-m-t') ?>">
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary" onclick="loadRefundHistory()">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </div>
        
        <div id="historyResults" style="overflow-x: auto;"></div>
    </div>
</div>

<!-- Refund Modal -->
<div id="refundModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-undo"></i> Process Refund</h3>
            <button class="close-modal" onclick="closeRefundModal()">×</button>
        </div>
        
        <form id="refundForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="process_refund">
            <input type="hidden" name="booking_id" id="refundBookingId">
            <input type="hidden" id="refundUserId" value="">
            
            <div class="form-group">
                <label>Customer</label>
                <input type="text" id="refundCustomer" readonly style="background: rgba(255, 255, 255, 0.05);">
            </div>
            
            <div class="form-group">
                <label>Session</label>
                <input type="text" id="refundSession" readonly style="background: rgba(255, 255, 255, 0.05);">
            </div>
            
            <div class="form-group">
                <label>Original Amount</label>
                <input type="text" id="refundOriginalAmount" readonly style="background: rgba(255, 255, 255, 0.05);">
            </div>
            
            <div class="form-group">
                <label>Refund Method</label>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px; border: 2px solid transparent;" onclick="selectMethod('refund')">
                        <input type="radio" name="method" value="refund" id="methodRefund" checked onchange="updateMethodFields()">
                        <div>
                            <strong>Refund to Payment Method</strong>
                            <p style="margin: 0; font-size: 0.85rem; color: rgba(255, 255, 255, 0.6);">Refund via Stripe (5-10 business days)</p>
                        </div>
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px; border: 2px solid transparent;" onclick="selectMethod('credit')">
                        <input type="radio" name="method" value="credit" id="methodCredit" onchange="updateMethodFields()">
                        <div>
                            <strong>Issue Store Credit</strong>
                            <p style="margin: 0; font-size: 0.85rem; color: rgba(255, 255, 255, 0.6);">Credit for future bookings (faster, 365 days expiry)</p>
                        </div>
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 6px; border: 2px solid transparent;" onclick="selectMethod('exchange')">
                        <input type="radio" name="method" value="exchange" id="methodExchange" onchange="updateMethodFields()">
                        <div>
                            <strong>Exchange for Different Session</strong>
                            <p style="margin: 0; font-size: 0.85rem; color: rgba(255, 255, 255, 0.6);">Move booking to another session</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Refund Amount Field (for refund/credit) -->
            <div class="form-group" id="amountField">
                <label id="amountLabel">Refund Amount</label>
                <input type="number" name="refund_amount" id="refundAmount" step="0.01" min="0.01">
            </div>
            
            <!-- Credit Expiry Preview -->
            <div class="form-group" id="creditExpiryField" style="display: none;">
                <label>Credit Expiry</label>
                <input type="text" id="creditExpiry" readonly style="background: rgba(255, 255, 255, 0.05); color: rgba(255, 255, 255, 0.7);" value="365 days from now">
            </div>
            
            <!-- Exchange Session Selector -->
            <div class="form-group" id="exchangeSessionField" style="display: none;">
                <label>Exchange to Session</label>
                <select name="exchange_session_id" id="exchangeSession">
                    <option value="">-- Select Session --</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Reason</label>
                <textarea name="reason" rows="3" required placeholder="Enter reason for refund/credit/exchange" style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; color: white; font-family: inherit;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;" id="submitBtn">
                    <i class="fas fa-check"></i> Process Refund
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeRefundModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const RefundSystem = {
    currentBookingAmount: 0,
    upcomingSessions: [],
    
    init: function() {
        this.searchBookings();
        this.loadUpcomingSessions();
    }
};

function switchTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
    
    if (tab === 'bookings') {
        document.querySelector('.tab:nth-child(1)').classList.add('active');
        document.getElementById('bookingsTab').classList.add('active');
    } else {
        document.querySelector('.tab:nth-child(2)').classList.add('active');
        document.getElementById('historyTab').classList.add('active');
        loadRefundHistory();
    }
}

async function searchBookings() {
    const email = document.getElementById('searchEmail').value;
    const sessionId = document.getElementById('searchSession').value;
    const startDate = document.getElementById('searchStartDate').value;
    const endDate = document.getElementById('searchEndDate').value;
    
    try {
        const params = new URLSearchParams({
            action: 'search_bookings',
            email: email,
            session_id: sessionId,
            start_date: startDate,
            end_date: endDate
        });
        
        const response = await fetch(`process_refunds.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            displayBookings(result.bookings);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error searching bookings: ' + error.message);
    }
}

function displayBookings(bookings) {
    const container = document.getElementById('bookingsResults');
    
    if (bookings.length === 0) {
        container.innerHTML = '<p style="color: rgba(255, 255, 255, 0.6); text-align: center; padding: 40px;">No bookings found</p>';
        return;
    }
    
    let html = '<table class="results-table"><thead><tr>';
    html += '<th>Date</th><th>Customer</th><th>Session</th><th>Athlete</th><th>Amount</th><th>Status</th><th>Actions</th>';
    html += '</tr></thead><tbody>';
    
    bookings.forEach(booking => {
        html += '<tr>';
        html += `<td>${new Date(booking.session_date).toLocaleDateString()}</td>`;
        html += `<td>${booking.first_name} ${booking.last_name}<br><small style="color: rgba(255,255,255,0.5)">${booking.email}</small></td>`;
        html += `<td>${booking.session_name}</td>`;
        html += `<td>${booking.athlete_name || 'N/A'}</td>`;
        html += `<td>$${parseFloat(booking.amount_paid).toFixed(2)}</td>`;
        html += `<td><span class="badge badge-success">${booking.payment_status}</span></td>`;
        html += `<td>
            <button class="btn btn-primary" style="padding: 8px 15px; font-size: 0.85rem;" onclick='openRefundModal(${JSON.stringify(booking)})'>
                <i class="fas fa-undo"></i> Refund
            </button>
        </td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

async function loadUpcomingSessions() {
    try {
        const response = await fetch('process_refunds.php?action=get_upcoming_sessions');
        const result = await response.json();
        if (result.success) {
            RefundSystem.upcomingSessions = result.sessions;
        }
    } catch (error) {
        console.error('Failed to load upcoming sessions:', error);
    }
}

function selectMethod(method) {
    document.getElementById('methodRefund').checked = (method === 'refund');
    document.getElementById('methodCredit').checked = (method === 'credit');
    document.getElementById('methodExchange').checked = (method === 'exchange');
    updateMethodFields();
}

function updateMethodFields() {
    const method = document.querySelector('input[name="method"]:checked').value;
    const amountField = document.getElementById('amountField');
    const amountLabel = document.getElementById('amountLabel');
    const creditExpiryField = document.getElementById('creditExpiryField');
    const exchangeSessionField = document.getElementById('exchangeSessionField');
    const refundAmount = document.getElementById('refundAmount');
    const submitBtn = document.getElementById('submitBtn');
    
    // Update button text and icon
    if (method === 'refund') {
        submitBtn.innerHTML = '<i class="fas fa-undo"></i> Process Refund';
        amountLabel.textContent = 'Refund Amount';
        amountField.style.display = 'block';
        creditExpiryField.style.display = 'none';
        exchangeSessionField.style.display = 'none';
        refundAmount.required = true;
        refundAmount.value = RefundSystem.currentBookingAmount.toFixed(2);
    } else if (method === 'credit') {
        submitBtn.innerHTML = '<i class="fas fa-coins"></i> Issue Store Credit';
        amountLabel.textContent = 'Credit Amount';
        amountField.style.display = 'block';
        creditExpiryField.style.display = 'block';
        exchangeSessionField.style.display = 'none';
        refundAmount.required = true;
        refundAmount.value = RefundSystem.currentBookingAmount.toFixed(2);
    } else if (method === 'exchange') {
        submitBtn.innerHTML = '<i class="fas fa-exchange-alt"></i> Process Exchange';
        amountField.style.display = 'none';
        creditExpiryField.style.display = 'none';
        exchangeSessionField.style.display = 'block';
        refundAmount.required = false;
        
        // Populate exchange session dropdown
        const select = document.getElementById('exchangeSession');
        select.innerHTML = '<option value="">-- Select Session --</option>';
        RefundSystem.upcomingSessions.forEach(session => {
            select.innerHTML += `<option value="${session.id}">
                ${session.title} - ${new Date(session.session_date).toLocaleDateString()} ${session.session_time}
            </option>`;
        });
    }
    
    // Update radio button styles
    document.querySelectorAll('label[onclick]').forEach(label => {
        label.style.borderColor = 'transparent';
    });
    document.querySelector(`label[onclick="selectMethod('${method}')"]`).style.borderColor = 'var(--primary)';
}

function openRefundModal(booking) {
    document.getElementById('refundBookingId').value = booking.id;
    document.getElementById('refundUserId').value = booking.user_id;
    document.getElementById('refundCustomer').value = `${booking.first_name} ${booking.last_name} (${booking.email})`;
    document.getElementById('refundSession').value = booking.session_name;
    document.getElementById('refundOriginalAmount').value = `$${parseFloat(booking.amount_paid).toFixed(2)}`;
    document.getElementById('refundAmount').value = parseFloat(booking.amount_paid).toFixed(2);
    
    RefundSystem.currentBookingAmount = parseFloat(booking.amount_paid);
    
    // Reset to refund method
    document.getElementById('methodRefund').checked = true;
    updateMethodFields();
    
    document.getElementById('refundModal').classList.add('active');
}

function closeRefundModal() {
    document.getElementById('refundModal').classList.remove('active');
    document.getElementById('refundForm').reset();
}

document.getElementById('refundForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const method = document.querySelector('input[name="method"]:checked').value;
    let confirmMsg = '';
    
    if (method === 'refund') {
        confirmMsg = 'Are you sure you want to process this refund? This action cannot be undone.';
    } else if (method === 'credit') {
        confirmMsg = 'Are you sure you want to issue store credit instead of a refund?';
    } else if (method === 'exchange') {
        confirmMsg = 'Are you sure you want to exchange this booking for a different session?';
    }
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('process_refunds.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const successMsg = method === 'refund' ? 'Refund processed successfully!' :
                             method === 'credit' ? 'Store credit issued successfully!' :
                             'Booking exchange completed successfully!';
            alert(successMsg);
            closeRefundModal();
            searchBookings();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error processing request: ' + error.message);
    }
});

async function loadRefundHistory() {
    const startDate = document.getElementById('historyStartDate').value;
    const endDate = document.getElementById('historyEndDate').value;
    
    try {
        const params = new URLSearchParams({
            action: 'list_refunds',
            start_date: startDate,
            end_date: endDate
        });
        
        const response = await fetch(`process_refunds.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            displayRefundHistory(result.refunds);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error loading refund history: ' + error.message);
    }
}

function displayRefundHistory(refunds) {
    const container = document.getElementById('historyResults');
    
    if (refunds.length === 0) {
        container.innerHTML = '<p style="color: rgba(255, 255, 255, 0.6); text-align: center; padding: 40px;">No refunds found</p>';
        return;
    }
    
    let html = '<table class="results-table"><thead><tr>';
    html += '<th>Date</th><th>Customer</th><th>Session</th><th>Method</th><th>Original</th><th>Amount</th><th>Status</th><th>Reason</th><th>Processed By</th>';
    html += '</tr></thead><tbody>';
    
    refunds.forEach(refund => {
        html += '<tr>';
        html += `<td>${new Date(refund.refund_date).toLocaleDateString()}</td>`;
        html += `<td>${refund.first_name} ${refund.last_name}<br><small style="color: rgba(255,255,255,0.5)">${refund.email}</small></td>`;
        html += `<td>${refund.session_name || 'N/A'}</td>`;
        
        // Method badge
        let methodBadge = '';
        if (refund.refund_type === 'refund') {
            methodBadge = '<span class="badge badge-info">Refund</span>';
        } else if (refund.refund_type === 'credit') {
            methodBadge = '<span class="badge badge-success">Credit</span>';
        } else if (refund.refund_type === 'exchange') {
            methodBadge = '<span class="badge badge-warning">Exchange</span>';
        }
        html += `<td>${methodBadge}</td>`;
        
        html += `<td>$${parseFloat(refund.original_amount).toFixed(2)}</td>`;
        
        // Amount (show credit amount if applicable)
        const displayAmount = refund.refund_type === 'credit' ? refund.credit_amount : refund.refund_amount;
        html += `<td>$${parseFloat(displayAmount).toFixed(2)}</td>`;
        
        html += `<td><span class="badge badge-success">${refund.status}</span></td>`;
        html += `<td>${refund.refund_reason}</td>`;
        html += `<td>${refund.processed_by_name}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function exportRefunds() {
    const startDate = document.getElementById('historyStartDate').value;
    const endDate = document.getElementById('historyEndDate').value;
    window.location.href = `process_refunds.php?action=export_refunds&start_date=${startDate}&end_date=${endDate}`;
}

// Load initial data
document.addEventListener('DOMContentLoaded', function() {
    RefundSystem.init();
});
</script>
