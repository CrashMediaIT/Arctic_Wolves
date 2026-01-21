<?php
/**
 * Manage Athletes View
 * Interface for parents to add, create, and remove athletes
 */

require_once __DIR__ . '/../security.php';

// Get all managed athletes
$athletes_stmt = $pdo->prepare("
    SELECT u.*, ma.relationship, ma.can_book, ma.can_view_stats, ma.id as managed_id
    FROM managed_athletes ma
    INNER JOIN users u ON ma.athlete_id = u.id
    WHERE ma.parent_id = ?
    ORDER BY u.first_name, u.last_name
");
$athletes_stmt->execute([$user_id]);
$athletes = $athletes_stmt->fetchAll();

// Get success/error messages
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
        margin: 0;
    }
    .back-link {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
    }
    .content-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 25px;
    }
    .card-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-description {
        color: #94a3b8;
        font-size: 14px;
        margin-bottom: 20px;
        line-height: 1.6;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .form-input {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
    }
    .form-input:focus {
        outline: none;
        border-color: var(--primary);
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    .btn-primary:hover {
        background: #e64500;
    }
    .btn-secondary {
        background: #1e293b;
        color: #fff;
    }
    .btn-secondary:hover {
        background: #334155;
    }
    .alert {
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    .alert-success {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    .athletes-list {
        margin-top: 20px;
    }
    .athlete-item {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .athlete-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .athlete-avatar {
        width: 40px;
        height: 40px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 16px;
        color: #fff;
    }
    .athlete-details {
        flex: 1;
    }
    .athlete-name {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 3px;
    }
    .athlete-meta {
        font-size: 13px;
        color: #64748b;
    }
    .btn-remove {
        padding: 8px 16px;
        background: transparent;
        border: 1px solid #ef4444;
        color: #ef4444;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }
    .btn-remove:hover {
        background: rgba(239, 68, 68, 0.1);
    }
    .empty-list {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
        font-size: 14px;
    }
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        .athlete-item {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user-cog"></i> Manage Athletes
    </h1>
    <a href="?page=home" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php
        switch ($success) {
            case 'athlete_added':
                echo 'Athlete successfully linked to your account';
                break;
            case 'athlete_created':
                echo 'New athlete account created and linked';
                break;
            case 'athlete_removed':
                echo 'Athlete removed from your managed list';
                break;
            default:
                echo 'Operation completed successfully';
        }
        ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php
        switch ($error) {
            case 'athlete_not_found':
                echo 'No athlete found with that email address';
                break;
            case 'already_managed':
                echo 'This athlete is already in your managed list';
                break;
            case 'invalid_data':
                echo 'Please fill in all required fields';
                break;
            case 'email_exists':
                echo 'An account with this email already exists';
                break;
            case 'permission_denied':
                echo 'You do not have permission to perform this action';
                break;
            default:
                echo 'An error occurred. Please try again.';
        }
        ?>
    </div>
<?php endif; ?>

<!-- Add Existing Athlete -->
<div class="content-card">
    <h2 class="card-title">
        <i class="fas fa-link"></i> Link Existing Athlete
    </h2>
    <p class="card-description">
        Add an existing athlete account to your managed list by their email address.
    </p>
    
    <form method="POST" action="process_manage_athletes.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="add_athlete">
        
        <div class="form-group">
            <label class="form-label">Athlete Email Address</label>
            <input type="email" name="athlete_email" class="form-input" placeholder="athlete@example.com" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">Relationship</label>
            <input type="text" name="relationship" class="form-input" value="Parent" placeholder="e.g., Parent, Guardian, Manager">
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-link"></i> Link Athlete
        </button>
    </form>
</div>

<!-- Create New Athlete -->
<div class="content-card">
    <h2 class="card-title">
        <i class="fas fa-user-plus"></i> Create New Athlete Account
    </h2>
    <p class="card-description">
        Create a new athlete account and automatically link it to your parent account.
    </p>
    
    <form method="POST" action="process_manage_athletes.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create_athlete">
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-input" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-input" required>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Birth Date</label>
                <input type="date" name="birth_date" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Position</label>
                <input type="text" name="position" class="form-input" placeholder="e.g., Forward, Defense, Goalie">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Relationship</label>
            <input type="text" name="relationship" class="form-input" value="Parent" placeholder="e.g., Parent, Guardian, Manager">
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Create Athlete Account
        </button>
    </form>
</div>

<!-- Current Managed Athletes -->
<div class="content-card">
    <h2 class="card-title">
        <i class="fas fa-users"></i> Current Managed Athletes (<?= count($athletes) ?>)
    </h2>
    
    <?php if (empty($athletes)): ?>
        <div class="empty-list">
            <i class="fas fa-user-slash" style="font-size: 48px; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
            No athletes in your managed list yet
        </div>
    <?php else: ?>
        <div class="athletes-list">
            <?php foreach ($athletes as $athlete): ?>
                <div class="athlete-item">
                    <div class="athlete-info">
                        <div class="athlete-avatar">
                            <?= strtoupper(substr($athlete['first_name'], 0, 1)) ?>
                        </div>
                        <div class="athlete-details">
                            <div class="athlete-name">
                                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            </div>
                            <div class="athlete-meta">
                                <?= htmlspecialchars($athlete['email']) ?>
                                <?php if ($athlete['relationship']): ?>
                                    • <?= htmlspecialchars($athlete['relationship']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="process_manage_athletes.php" style="display: inline;" 
                          onsubmit="return confirm('Are you sure you want to remove this athlete from your managed list?');">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="action" value="remove_athlete">
                        <input type="hidden" name="managed_id" value="<?= $athlete['managed_id'] ?>">
                        <button type="submit" class="btn-remove">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
