<?php
/**
 * Personal Development Programs - Browse & Register
 * Shows available development programs (Goalie Dev, Player Dev)
 * Athletes can register, which triggers notifications to dev coaches
 */

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Get session types for development programs
$dev_types = $pdo->query("
    SELECT * FROM session_types 
    WHERE name IN ('Long Term Goalie Development', 'Long Term Player Development')
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Get user's current enrollments
$enrollments_stmt = $pdo->prepare("
    SELECT dpe.*, 
           (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
    FROM development_program_enrollments dpe
    WHERE dpe.athlete_id = ?
    ORDER BY dpe.enrolled_at DESC
");
$enrollments_stmt->execute([$user_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

$enrolled_types = array_column($enrollments, 'program_type');
?>

<style>
.dev-programs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 24px;
    margin-top: 20px;
}
.dev-program-card {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 12px;
    padding: 28px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.dev-program-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}
.dev-program-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 12px;
}
.dev-program-card .program-icon {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
}
.dev-program-card .program-icon.goalie { color: #3b82f6; }
.dev-program-card .program-icon.player { color: #10b981; }
.dev-program-card p {
    color: var(--text-dim, #94a3b8);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 20px;
}
.dev-program-card .btn-register {
    display: inline-block;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.dev-program-card .btn-register.available {
    background: var(--primary, #6B46C1);
    color: #fff;
}
.dev-program-card .btn-register.available:hover {
    opacity: 0.9;
}
.dev-program-card .btn-register.enrolled {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
    cursor: default;
}
.dev-enrollment-info {
    margin-top: 12px;
    padding: 12px 16px;
    background: rgba(16, 185, 129, 0.08);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-dim, #94a3b8);
}
.dev-enrollment-info strong {
    color: #10b981;
}
</style>

<div class="dev-programs-grid">
    <!-- Long Term Goalie Development -->
    <div class="dev-program-card">
        <i class="fas fa-shield-alt program-icon goalie"></i>
        <h3>Long Term Goalie Development</h3>
        <p>A comprehensive development program for goalies focusing on technique, positioning, movement, and game sense. Work directly with our goalie development coaches through personalized drill programs and video feedback.</p>
        <?php if (in_array('goalie_dev', $enrolled_types)): ?>
            <span class="btn-register enrolled"><i class="fas fa-check"></i> Enrolled</span>
            <?php
            $goalie_enrollment = array_filter($enrollments, fn($e) => $e['program_type'] === 'goalie_dev');
            $goalie_enrollment = reset($goalie_enrollment);
            ?>
            <div class="dev-enrollment-info">
                <strong>Status:</strong> <?= ucfirst(htmlspecialchars($goalie_enrollment['status'])) ?> &bull;
                <strong>Drills:</strong> <?= (int)$goalie_enrollment['drill_count'] ?> assigned &bull;
                <strong>Since:</strong> <?= date('M j, Y', strtotime($goalie_enrollment['enrolled_at'])) ?>
            </div>
        <?php else: ?>
            <button class="btn-register available" onclick="registerDevProgram('goalie_dev')">
                <i class="fas fa-plus"></i> Register for Program
            </button>
        <?php endif; ?>
    </div>

    <!-- Long Term Player Development -->
    <div class="dev-program-card">
        <i class="fas fa-hockey-puck program-icon player"></i>
        <h3>Long Term Player Development</h3>
        <p>A structured long-term development program for skaters focusing on skating technique, shooting, puck handling, hockey IQ, and on-ice decision making. Receive personalized coaching through drill programs and video analysis.</p>
        <?php if (in_array('player_dev', $enrolled_types)): ?>
            <span class="btn-register enrolled"><i class="fas fa-check"></i> Enrolled</span>
            <?php
            $player_enrollment = array_filter($enrollments, fn($e) => $e['program_type'] === 'player_dev');
            $player_enrollment = reset($player_enrollment);
            ?>
            <div class="dev-enrollment-info">
                <strong>Status:</strong> <?= ucfirst(htmlspecialchars($player_enrollment['status'])) ?> &bull;
                <strong>Drills:</strong> <?= (int)$player_enrollment['drill_count'] ?> assigned &bull;
                <strong>Since:</strong> <?= date('M j, Y', strtotime($player_enrollment['enrolled_at'])) ?>
            </div>
        <?php else: ?>
            <button class="btn-register available" onclick="registerDevProgram('player_dev')">
                <i class="fas fa-plus"></i> Register for Program
            </button>
        <?php endif; ?>
    </div>
</div>

<script>
function registerDevProgram(programType) {
    if (!confirm('Are you sure you want to register for this development program?')) return;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch('process_development_programs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ action: 'register', program_type: programType })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Registration failed. Please try again.');
        }
    })
    .catch(() => alert('An error occurred. Please try again.'));
}
</script>
