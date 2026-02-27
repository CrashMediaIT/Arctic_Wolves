<?php
/**
 * Manage Athletes View
 * Interface for parents to add, create, and remove athletes
 */

require_once __DIR__ . '/../security.php';

// Get all managed athletes - use managed_athletes table for both parents and coaches
$athletes = [];
if ($user_role === 'parent') {
    // For parents, use managed_athletes table (parent_id column)
    $athletes_stmt = $pdo->prepare("
        SELECT u.*, ma.relationship, ma.id as managed_id
        FROM managed_athletes ma
        INNER JOIN users u ON ma.athlete_id = u.id
        WHERE ma.parent_id = ?
        ORDER BY u.first_name, u.last_name
    ");
    $athletes_stmt->execute([$user_id]);
    $athletes = $athletes_stmt->fetchAll();
    $athletes = decryptUserRows($athletes);
} else {
    // For coaches/admins, use managed_athletes table (coach_id column)
    $athletes_stmt = $pdo->prepare("
        SELECT u.*, ma.status, ma.notes, ma.id as managed_id, 'coach' as relationship
        FROM managed_athletes ma
        INNER JOIN users u ON ma.athlete_id = u.id
        WHERE ma.coach_id = ? AND ma.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $athletes_stmt->execute([$user_id]);
    $athletes = $athletes_stmt->fetchAll();
    $athletes = decryptUserRows($athletes);
}

// Get success/error messages
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<style>
    .manage-athletes-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    @media (max-width: 768px) {
        .manage-athletes-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <h1 class="page-title">
        <i class="fas fa-user-cog"></i> Manage Athletes
    </h1>
    <a href="?page=home" style="color: var(--primary, #6B46C1); text-decoration: none; font-weight: 600; font-size: 14px;">
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
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-link"></i> Link Existing Athlete</h3>
    </div>
    <div class="card-body">
        <p style="color: var(--text-muted, #6B6B7B); font-size: 14px; margin-bottom: 20px; line-height: 1.6;">
            Add an existing athlete account to your managed list by their email address.
        </p>
        
        <form method="POST" action="process_manage_athletes.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="add_athlete">
            
            <div class="form-group">
                <label>Athlete Email Address</label>
                <input type="email" name="athlete_email" placeholder="athlete@example.com" required>
            </div>
            
            <div class="form-group">
                <label>Relationship</label>
                <input type="text" name="relationship" value="Parent" placeholder="e.g., Parent, Guardian, Manager">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-link"></i> Link Athlete
            </button>
        </form>
    </div>
</div>

<!-- Create New Athlete -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-plus"></i> Create New Athlete Account</h3>
    </div>
    <div class="card-body">
        <p style="color: var(--text-muted, #6B6B7B); font-size: 14px; margin-bottom: 20px; line-height: 1.6;">
            Create a new athlete account and automatically link it to your parent account.
        </p>
        
        <form method="POST" action="process_manage_athletes.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create_athlete">
            
            <div class="manage-athletes-grid">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="manage-athletes-grid">
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date" name="birth_date">
                </div>
                
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" placeholder="e.g., Forward, Defense, Goalie">
                </div>
            </div>
            
            <div class="form-group">
                <label>Relationship</label>
                <input type="text" name="relationship" value="Parent" placeholder="e.g., Parent, Guardian, Manager">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Create Athlete Account
            </button>
        </form>
    </div>
</div>

<!-- Current Managed Athletes -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> Current Managed Athletes (<?= count($athletes) ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($athletes)): ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted, #6B6B7B);">
                <i class="fas fa-user-slash" style="font-size: 48px; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                No athletes in your managed list yet
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($athletes as $athlete): ?>
                    <div class="card" style="margin-bottom: 0;">
                        <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <div style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--primary, #6B46C1), var(--primary-hover, #7C3AED)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0;">
                                    <?= strtoupper(substr($athlete['first_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: var(--text-white, #fff);">
                                        <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-muted, #6B6B7B);">
                                        <?= htmlspecialchars($athlete['email']) ?>
                                        <?php if ($athlete['relationship']): ?>
                                            &bull; <?= htmlspecialchars($athlete['relationship']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="process_manage_athletes.php" style="display: inline;" 
                                  data-confirm="Are you sure you want to remove this athlete from your managed list?">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="remove_athlete">
                                <input type="hidden" name="managed_id" value="<?= $athlete['managed_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
