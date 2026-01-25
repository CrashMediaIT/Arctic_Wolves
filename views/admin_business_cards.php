<!-- Admin Business Card Generator View -->
<?php
// Permission check - only admins can access this page
if (!isset($isAdmin) || !$isAdmin) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Access denied. Admin privileges required.</div>';
    return;
}

// Company website URL for QR code - can be configured via settings
$company_website = 'https://arcticwolves.ca';

// Fetch users from database for selection
try {
    // Get filter values
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build query
    $where = [];
    $params = [];
    
    if (!empty($role_filter)) {
        $where[] = "u.role = ?";
        $params[] = $role_filter;
    }
    
    if (!empty($status_filter)) {
        if ($status_filter === 'active') {
            $where[] = "u.is_verified = 1";
        } elseif ($status_filter === 'inactive') {
            $where[] = "u.is_verified = 0";
        }
    }
    
    if (!empty($search)) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               CONCAT(u.first_name, ' ', u.last_name) as full_name
        FROM users u
        $where_clause
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users = count($users);
} catch (PDOException $e) {
    error_log("Users fetch error: " . $e->getMessage());
    $users = [];
    $total_users = 0;
}

// Get selected user data if provided
$selected_user = null;
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$_GET['user_id']]);
        $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Selected user fetch error: " . $e->getMessage());
    }
}
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-id-card"></i> Business Card Generator
    </h1>
    <p class="page-description">Create professional business cards for team members</p>
</div>

<div class="business-cards-content">
    <!-- Step 1: User Selection -->
    <div class="card" id="user-selection-section">
        <div class="card-header">
            <h3><i class="fas fa-user-check"></i> Step 1: Select a User</h3>
        </div>
        <div class="card-body">
            <!-- Filter and Search -->
            <form method="GET" action="" class="filter-form" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
                <input type="hidden" name="page" value="business_cards">
                <?php if ($selected_user): ?>
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($selected_user['id']); ?>">
                <?php endif; ?>
                <div class="search-input-wrapper" style="flex: 1; min-width: 200px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="form-input" placeholder="Search users by name or email..." 
                           value="<?php echo htmlspecialchars($search); ?>" id="userSearch">
                </div>
                <select name="role" class="form-select" id="roleFilter" style="min-width: 150px;">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="coach" <?php echo $role_filter === 'coach' ? 'selected' : ''; ?>>Coach</option>
                    <option value="health_coach" <?php echo $role_filter === 'health_coach' ? 'selected' : ''; ?>>Health Coach</option>
                    <option value="team_coach" <?php echo $role_filter === 'team_coach' ? 'selected' : ''; ?>>Team Coach</option>
                    <option value="athlete" <?php echo $role_filter === 'athlete' ? 'selected' : ''; ?>>Athlete</option>
                    <option value="parent" <?php echo $role_filter === 'parent' ? 'selected' : ''; ?>>Parent</option>
                </select>
                <select name="status" class="form-select" id="statusFilter" style="min-width: 150px;">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
            </form>
            
            <!-- Users Grid -->
            <div class="users-grid">
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-card <?php echo ($selected_user && $selected_user['id'] == $user['id']) ? 'selected' : ''; ?>" 
                             onclick="selectUser(<?php echo $user['id']; ?>)"
                             data-user-id="<?php echo $user['id']; ?>"
                             data-first-name="<?php echo htmlspecialchars($user['first_name']); ?>"
                             data-last-name="<?php echo htmlspecialchars($user['last_name']); ?>"
                             data-email="<?php echo htmlspecialchars($user['email']); ?>"
                             data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                             data-role="<?php echo htmlspecialchars($user['role']); ?>">
                            <div class="user-avatar-large">
                                <?php 
                                    $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                                    echo htmlspecialchars($initials);
                                ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                <span class="role-badge <?php echo $user['role']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </div>
                            <div class="select-indicator">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="placeholder-text">No users found matching your criteria.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Step 2: Business Card Form -->
    <div class="card" id="card-form-section" style="<?php echo $selected_user ? '' : 'display: none;'; ?>">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Step 2: Customize Business Card</h3>
        </div>
        <div class="card-body">
            <form id="business-card-form">
                <!-- Contact Information Section -->
                <div class="form-section">
                    <h4 class="form-section-title"><i class="fas fa-user"></i> Contact Information</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" id="bc_first_name" class="form-input" required
                                   value="<?php echo $selected_user ? htmlspecialchars($selected_user['first_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" id="bc_last_name" class="form-input" required
                                   value="<?php echo $selected_user ? htmlspecialchars($selected_user['last_name']) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Job Title</label>
                            <input type="text" name="job_title" id="bc_job_title" class="form-input" 
                                   placeholder="e.g., Head Coach, Assistant Coach"
                                   value="<?php echo $selected_user ? ucfirst(str_replace('_', ' ', $selected_user['role'])) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" name="phone" id="bc_phone" class="form-input" required
                                   placeholder="(555) 123-4567"
                                   value="<?php echo $selected_user ? htmlspecialchars($selected_user['phone'] ?? '') : ''; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" id="bc_email" class="form-input" required
                               value="<?php echo $selected_user ? htmlspecialchars($selected_user['email']) : ''; ?>">
                    </div>
                </div>
                
                <!-- Background Images Section -->
                <div class="form-section">
                    <h4 class="form-section-title"><i class="fas fa-image"></i> Card Background Images</h4>
                    <div class="background-upload-grid">
                        <!-- Front Background -->
                        <div class="file-upload-zone" id="frontBgDropZone">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <p class="upload-text">Drag & drop front background or click to browse</p>
                            <span class="upload-hint">Front side background image (PNG, JPG, WebP)</span>
                            <input type="file" id="front-bg-input" accept="image/*" style="display: none;" onchange="previewBackground('front', this)">
                            <div class="upload-buttons">
                                <button type="button" class="btn-secondary btn-small" onclick="document.getElementById('front-bg-input').click()">
                                    <i class="fas fa-folder-open"></i> Choose File
                                </button>
                                <button type="button" class="btn-secondary btn-small" onclick="removeBackground('front')" id="remove-front-bg" style="display: none;">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                            <div id="front-bg-preview" class="upload-preview"></div>
                        </div>
                        
                        <!-- Back Background -->
                        <div class="file-upload-zone" id="backBgDropZone">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <p class="upload-text">Drag & drop back background or click to browse</p>
                            <span class="upload-hint">Back side background image (PNG, JPG, WebP)</span>
                            <input type="file" id="back-bg-input" accept="image/*" style="display: none;" onchange="previewBackground('back', this)">
                            <div class="upload-buttons">
                                <button type="button" class="btn-secondary btn-small" onclick="document.getElementById('back-bg-input').click()">
                                    <i class="fas fa-folder-open"></i> Choose File
                                </button>
                                <button type="button" class="btn-secondary btn-small" onclick="removeBackground('back')" id="remove-back-bg" style="display: none;">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                            <div id="back-bg-preview" class="upload-preview"></div>
                        </div>
                    </div>
                    <p class="form-help"><i class="fas fa-info-circle"></i> Recommended size: 1050x600 pixels (3.5" x 2" at 300 DPI). Supports PNG, JPG, WebP.</p>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" onclick="updatePreview()">
                        <i class="fas fa-eye"></i> Update Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 3: Business Card Preview -->
    <div class="card" id="card-preview-section" style="<?php echo $selected_user ? '' : 'display: none;'; ?>">
        <div class="card-header">
            <h3><i class="fas fa-id-badge"></i> Step 3: Business Card Preview</h3>
            <div class="card-actions">
                <button class="btn btn-secondary" onclick="flipCard()">
                    <i class="fas fa-sync-alt"></i> Flip Card
                </button>
                <div class="export-dropdown">
                    <button class="btn btn-primary dropdown-toggle" onclick="toggleExportMenu()">
                        <i class="fas fa-download"></i> Export <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="export-menu">
                        <button class="dropdown-item" onclick="exportCardSide('front')">
                            <i class="fas fa-arrow-right"></i> Export Front Side (PNG)
                        </button>
                        <button class="dropdown-item" onclick="exportCardSide('back')">
                            <i class="fas fa-arrow-left"></i> Export Back Side (PNG)
                        </button>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item" onclick="printBusinessCard()">
                            <i class="fas fa-print"></i> Print Both Sides
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="business-card-container">
                <div class="business-card-wrapper" id="business-card-wrapper">
                    <!-- Front of Card -->
                    <div class="business-card front" id="card-front">
                        <div class="card-bg-overlay" id="front-overlay"></div>
                        <div class="card-content">
                            <div class="card-logo">
                                <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Arctic Wolves Logo">
                            </div>
                            <div class="card-main-info">
                                <h2 class="card-name" id="preview-name"><?php echo $selected_user ? htmlspecialchars($selected_user['full_name']) : 'Full Name'; ?></h2>
                                <p class="card-title" id="preview-title"><?php echo $selected_user ? ucfirst(str_replace('_', ' ', $selected_user['role'])) : 'Job Title'; ?></p>
                            </div>
                            <div class="card-contact-info">
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span id="preview-phone"><?php echo $selected_user && $selected_user['phone'] ? htmlspecialchars($selected_user['phone']) : '(555) 123-4567'; ?></span>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span id="preview-email"><?php echo $selected_user ? htmlspecialchars($selected_user['email']) : 'email@example.com'; ?></span>
                                </div>
                            </div>
                            <div class="card-qr">
                                <div id="qrcode"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Back of Card -->
                    <div class="business-card back" id="card-back">
                        <div class="card-bg-overlay" id="back-overlay"></div>
                        <div class="card-content back-content">
                            <div class="back-logo">
                                <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Arctic Wolves Logo">
                            </div>
                            <div class="back-company-name">
                                <h1>ARCTIC WOLVES</h1>
                                <p class="tagline">Hockey Training Academy</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="preview-instructions">
                <p><i class="fas fa-info-circle"></i> Click "Flip Card" to see the back. Use "Export" to download individual sides as PNG images or print both sides together.</p>
            </div>
        </div>
    </div>
</div>

<!-- html2canvas Library for PNG Export -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
// Global variables
let isFlipped = false;
let qrcode = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($selected_user): ?>
    // Generate QR code on page load if user is selected
    generateQRCode();
    <?php endif; ?>
    
    // Initialize drag and drop for file upload zones
    initDragAndDrop('frontBgDropZone', 'front-bg-input', 'front');
    initDragAndDrop('backBgDropZone', 'back-bg-input', 'back');
});

// Initialize drag and drop event handlers
function initDragAndDrop(zoneId, inputId, side) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    
    if (!zone || !input) return;
    
    // Click to browse
    zone.addEventListener('click', function(e) {
        if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
            input.click();
        }
    });
    
    // Drag over
    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        zone.style.borderColor = 'var(--primary)';
        zone.style.background = 'rgba(107, 70, 193, 0.1)';
    });
    
    // Drag leave
    zone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        zone.style.borderColor = '';
        zone.style.background = '';
    });
    
    // Drop
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        zone.style.borderColor = '';
        zone.style.background = '';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Use DataTransfer for cross-browser compatibility
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(files[0]);
            input.files = dataTransfer.files;
            previewBackground(side, input);
        }
    });
}

// Select a user
function selectUser(userId) {
    // Update URL with selected user
    const url = new URL(window.location.href);
    url.searchParams.set('user_id', userId);
    window.location.href = url.toString();
}

// Update preview with form values
function updatePreview() {
    const firstName = document.getElementById('bc_first_name').value || 'First';
    const lastName = document.getElementById('bc_last_name').value || 'Last';
    const fullName = firstName + ' ' + lastName;
    const jobTitle = document.getElementById('bc_job_title').value || 'Team Member';
    const phone = document.getElementById('bc_phone').value || '(555) 123-4567';
    const email = document.getElementById('bc_email').value || 'email@example.com';
    
    // Update preview elements
    document.getElementById('preview-name').textContent = fullName;
    document.getElementById('preview-title').textContent = jobTitle;
    document.getElementById('preview-phone').textContent = phone;
    document.getElementById('preview-email').textContent = email;
    
    // Regenerate QR code
    generateQRCode();
    
    // Show success message
    showNotification('Preview updated!', 'success');
}

// Generate QR Code
function generateQRCode() {
    const qrcodeContainer = document.getElementById('qrcode');
    qrcodeContainer.innerHTML = ''; // Clear previous QR code
    
    qrcode = new QRCode(qrcodeContainer, {
        text: '<?php echo htmlspecialchars($company_website, ENT_QUOTES, 'UTF-8'); ?>',
        width: 80,
        height: 80,
        colorDark: '#1a1a2e',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
}

// Flip the card
function flipCard() {
    const wrapper = document.getElementById('business-card-wrapper');
    isFlipped = !isFlipped;
    
    if (isFlipped) {
        wrapper.classList.add('flipped');
    } else {
        wrapper.classList.remove('flipped');
    }
}

// Print business card
function printBusinessCard() {
    const printWindow = window.open('', '_blank');
    const cardFront = document.getElementById('card-front').outerHTML;
    const cardBack = document.getElementById('card-back').outerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Arctic Wolves Business Card</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    font-family: 'Inter', sans-serif;
                }
                body {
                    padding: 20px;
                    background: #f5f5f5;
                }
                .print-container {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 20px;
                    justify-content: center;
                }
                .business-card {
                    width: 3.5in;
                    height: 2in;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                .business-card.front {
                    background: linear-gradient(135deg, #1a1a2e 0%, #16161f 100%);
                }
                .business-card.back {
                    background: linear-gradient(135deg, #6B46C1 0%, #8B5CF6 100%);
                }
                .card-content {
                    height: 100%;
                    padding: 16px;
                    display: flex;
                    flex-direction: column;
                }
                .card-logo img {
                    height: 35px;
                    width: auto;
                }
                .card-main-info {
                    margin-top: 8px;
                }
                .card-name {
                    color: #fff;
                    font-size: 16px;
                    font-weight: 700;
                    margin-bottom: 2px;
                }
                .card-title {
                    color: #8B5CF6;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .card-contact-info {
                    margin-top: auto;
                }
                .contact-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: #a8a8b8;
                    font-size: 10px;
                    margin-bottom: 4px;
                }
                .contact-item i {
                    color: #6B46C1;
                    width: 12px;
                }
                .card-qr {
                    position: absolute;
                    right: 16px;
                    bottom: 16px;
                }
                .card-qr canvas, .card-qr img {
                    width: 60px !important;
                    height: 60px !important;
                    border-radius: 4px;
                    background: #fff;
                    padding: 4px;
                }
                .back-content {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                }
                .back-logo img {
                    height: 60px;
                    width: auto;
                    margin-bottom: 12px;
                }
                .back-company-name h1 {
                    color: #fff;
                    font-size: 22px;
                    font-weight: 900;
                    letter-spacing: 2px;
                }
                .back-company-name .tagline {
                    color: rgba(255,255,255,0.8);
                    font-size: 11px;
                    font-weight: 500;
                    margin-top: 4px;
                }
                h2 { margin: 0; }
                p { margin: 0; }
                @media print {
                    body { padding: 0; background: #fff; }
                    .business-card {
                        box-shadow: none;
                        border: 1px solid #ddd;
                        page-break-inside: avoid;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-container">
                ${cardFront}
                ${cardBack}
            </div>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Show notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    notification.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 2000);
}

// Background image storage
let frontBackgroundImage = null;
let backBackgroundImage = null;

// Preview background image
function previewBackground(side, input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file type
    const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        showNotification('Please select a valid image file (PNG, JPG, WebP)', 'error');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageData = e.target.result;
        
        if (side === 'front') {
            frontBackgroundImage = imageData;
            document.getElementById('front-bg-preview').innerHTML = `<img src="${imageData}" alt="Front Background">`;
            document.getElementById('front-overlay').style.backgroundImage = `url(${imageData})`;
            document.getElementById('remove-front-bg').style.display = 'inline-flex';
        } else {
            backBackgroundImage = imageData;
            document.getElementById('back-bg-preview').innerHTML = `<img src="${imageData}" alt="Back Background">`;
            document.getElementById('back-overlay').style.backgroundImage = `url(${imageData})`;
            document.getElementById('remove-back-bg').style.display = 'inline-flex';
        }
        
        showNotification(`${side.charAt(0).toUpperCase() + side.slice(1)} background uploaded!`, 'success');
    };
    reader.readAsDataURL(file);
}

// Remove background image
function removeBackground(side) {
    if (side === 'front') {
        frontBackgroundImage = null;
        document.getElementById('front-bg-preview').innerHTML = '';
        document.getElementById('front-overlay').style.backgroundImage = 'none';
        document.getElementById('front-bg-input').value = '';
        document.getElementById('remove-front-bg').style.display = 'none';
    } else {
        backBackgroundImage = null;
        document.getElementById('back-bg-preview').innerHTML = '';
        document.getElementById('back-overlay').style.backgroundImage = 'none';
        document.getElementById('back-bg-input').value = '';
        document.getElementById('remove-back-bg').style.display = 'none';
    }
    
    showNotification(`${side.charAt(0).toUpperCase() + side.slice(1)} background removed!`, 'success');
}

// Toggle export dropdown menu
function toggleExportMenu() {
    const menu = document.getElementById('export-menu');
    menu.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.export-dropdown');
    const menu = document.getElementById('export-menu');
    if (menu && dropdown && !dropdown.contains(e.target)) {
        menu.classList.remove('show');
    }
});

// Export individual card side as PNG
function exportCardSide(side) {
    const cardElement = side === 'front' ? document.getElementById('card-front') : document.getElementById('card-back');
    const firstName = document.getElementById('bc_first_name').value || 'User';
    const lastName = document.getElementById('bc_last_name').value || 'Card';
    
    // Close dropdown
    document.getElementById('export-menu').classList.remove('show');
    
    // Show loading notification
    showNotification('Generating PNG...', 'info');
    
    // Use html2canvas to capture the card
    html2canvas(cardElement, {
        scale: 3, // Higher scale for better quality (equivalent to 300 DPI)
        useCORS: true,
        allowTaint: true,
        backgroundColor: null,
        logging: false
    }).then(canvas => {
        // Create download link
        const link = document.createElement('a');
        link.download = `${firstName}_${lastName}_BusinessCard_${side}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
        
        showNotification(`${side.charAt(0).toUpperCase() + side.slice(1)} side exported as PNG!`, 'success');
    }).catch(err => {
        console.error('Export error:', err);
        showNotification('Export failed. Please try again.', 'error');
    });
}
</script>

<style>
/* Business Card Generator Styles */
.business-cards-content {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Form Section Title */
.form-section {
    margin-bottom: 28px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.form-section-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary, #fff);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section-title i {
    color: var(--primary-light, #8B5CF6);
}

/* Background Upload Grid */
.background-upload-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

/* File Upload Zone - Matching Termination Page Style */
.file-upload-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 32px 24px;
    text-align: center;
    background: var(--bg-main);
    transition: all 0.3s;
    cursor: pointer;
}

.file-upload-zone:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.file-upload-zone .upload-icon i {
    font-size: 42px;
    color: var(--primary);
    opacity: 0.6;
    margin-bottom: 12px;
}

.file-upload-zone .upload-text {
    font-size: 15px;
    color: var(--text-white);
    font-weight: 600;
    margin-bottom: 6px;
}

.file-upload-zone .upload-hint {
    font-size: 12px;
    color: var(--text-dim);
    display: block;
    margin-bottom: 16px;
}

.upload-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.upload-preview {
    margin-top: 12px;
}

.upload-preview img {
    max-width: 100%;
    max-height: 100px;
    border-radius: 8px;
    object-fit: cover;
}

.form-help {
    font-size: 12px;
    color: var(--text-muted, #6B6B7B);
    margin-top: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-help i {
    color: var(--primary-light, #8B5CF6);
}

/* Export Dropdown */
.export-dropdown {
    position: relative;
}

.dropdown-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.dropdown-toggle i:last-child {
    font-size: 10px;
    transition: transform 0.3s ease;
}

.export-dropdown .dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    min-width: 220px;
    padding: 8px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 100;
}

.export-dropdown .dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 16px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: var(--text-secondary, #A8A8B8);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
}

.dropdown-item:hover {
    background: rgba(107, 70, 193, 0.1);
    color: var(--text-primary, #fff);
}

.dropdown-item i {
    width: 16px;
    text-align: center;
    color: var(--primary-light, #8B5CF6);
}

.dropdown-divider {
    height: 1px;
    background: var(--border, #2D2D3F);
    margin: 8px 0;
}

/* Card Background Overlay */
.card-bg-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    border-radius: inherit;
    z-index: 0;
}

.business-card .card-content {
    position: relative;
    z-index: 1;
}

/* Notification Info Type */
.notification-info {
    border-color: var(--primary, #6B46C1);
}

.notification-info i {
    color: var(--primary, #6B46C1);
}

.notification-error {
    border-color: var(--error, #EF4444);
}

.notification-error i {
    color: var(--error, #EF4444);
}

/* Filter Form */
.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-dim);
}

.search-input-wrapper input {
    padding-left: 40px;
}

/* Users Grid */
.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    max-height: 400px;
    overflow-y: auto;
    padding: 4px;
}

.user-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--bg);
    border: 2px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.user-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.2);
}

.user-card.selected {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

.user-card.selected .select-indicator {
    opacity: 1;
}

.select-indicator {
    position: absolute;
    top: 12px;
    right: 12px;
    color: var(--primary);
    font-size: 20px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.user-avatar-large {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 4px;
}

.user-email {
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 8px;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

/* Business Card Preview */
.business-card-container {
    display: flex;
    justify-content: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #1a1a2e 0%, #0d0d14 100%);
    border-radius: 12px;
    perspective: 1000px;
}

.business-card-wrapper {
    position: relative;
    width: 3.5in;
    height: 2in;
    transform-style: preserve-3d;
    transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.business-card-wrapper.flipped {
    transform: rotateY(180deg);
}

.business-card {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
}

.business-card.front {
    background: linear-gradient(135deg, #1a1a2e 0%, #16161f 100%);
    border: 1px solid rgba(107, 70, 193, 0.3);
}

.business-card.back {
    background: linear-gradient(135deg, #6B46C1 0%, #8B5CF6 100%);
    transform: rotateY(180deg);
}

.card-content {
    height: 100%;
    padding: 20px;
    display: flex;
    flex-direction: column;
    position: relative;
}

/* Front Card Elements */
.card-logo img {
    height: 40px;
    width: auto;
}

.card-main-info {
    margin-top: 12px;
}

.card-name {
    color: #fff;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 4px 0;
}

.card-title {
    color: #8B5CF6;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0;
}

.card-contact-info {
    margin-top: auto;
    padding-right: 90px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #a8a8b8;
    font-size: 11px;
    margin-bottom: 6px;
}

.contact-item i {
    color: #6B46C1;
    width: 14px;
    text-align: center;
}

.card-qr {
    position: absolute;
    right: 20px;
    bottom: 20px;
}

#qrcode {
    background: #fff;
    padding: 6px;
    border-radius: 6px;
}

#qrcode img, #qrcode canvas {
    display: block;
}

/* Back Card Elements */
.back-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.back-logo img {
    height: 70px;
    width: auto;
    margin-bottom: 16px;
}

.back-company-name h1 {
    color: #fff;
    font-size: 26px;
    font-weight: 900;
    letter-spacing: 3px;
    margin: 0;
}

.back-company-name .tagline {
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    font-weight: 500;
    margin-top: 6px;
}

/* Preview Instructions */
.preview-instructions {
    text-align: center;
    margin-top: 20px;
    color: var(--text-dim);
    font-size: 13px;
}

.preview-instructions i {
    color: var(--primary);
    margin-right: 6px;
}

/* Card Header with Actions */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.card-actions {
    display: flex;
    gap: 10px;
}

/* Role Badges */
.role-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-badge.admin {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.role-badge.coach, .role-badge.team_coach, .role-badge.health_coach {
    background: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
}

.role-badge.athlete {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.role-badge.parent {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

/* Notification */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    background: #16161f;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    z-index: 10000;
    opacity: 0;
    transform: translateX(100px);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification.show {
    opacity: 1;
    transform: translateX(0);
}

.notification-success {
    border-color: #10b981;
}

.notification-success i {
    color: #10b981;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .background-upload-grid {
        grid-template-columns: 1fr;
    }
    
    .users-grid {
        grid-template-columns: 1fr;
    }
    
    .business-card-container {
        padding: 20px 10px;
        overflow-x: auto;
    }
    
    .business-card-wrapper {
        transform-origin: top center;
    }
    
    .card-actions {
        width: 100%;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    
    .export-dropdown .dropdown-menu {
        right: auto;
        left: 0;
    }
}
</style>
