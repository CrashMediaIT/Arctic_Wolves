<?php
/**
 * Registration Page
 * Allows users to register as Parent or Athlete
 * Parents can add multiple athletes during registration
 */
session_start();
require 'db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';

// Detect POS subdomain (pos.arcticwolves.ca) - redirect to kiosk login
$host = $_SERVER['HTTP_HOST'] ?? '';
$isPosSubdomain = (strpos($host, 'pos.') === 0);
if ($isPosSubdomain) {
    header("Location: pos_kiosk.php");
    exit();
}

// Generate CSRF token
generateCSRFToken();

// Check database connection
if (!$db_connected || !$pdo) {
    die("Database connection failed. Please check your configuration. Error: " . ($db_error ?? 'Unknown error'));
}

// Handle session intent from public sessions page
$sessionIntent = $_GET['session_intent'] ?? $_SESSION['session_intent'] ?? null;
if (isset($_GET['session_intent'])) {
    $_SESSION['session_intent'] = $_GET['session_intent'];
}

// If already logged in, redirect to dashboard or session intent
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Check if there's a session intent to complete
    if ($sessionIntent && $db_connected) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM session_registration_intents 
                WHERE intent_token = ? AND status = 'pending' AND expires_at > NOW()
            ");
            $stmt->execute([$sessionIntent]);
            $intent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($intent) {
                // Mark intent as completed and link to user
                $pdo->prepare("UPDATE session_registration_intents SET user_id = ?, status = 'completed' WHERE id = ?")
                    ->execute([$_SESSION['user_id'], $intent['id']]);
                
                // Clear session intent
                unset($_SESSION['session_intent']);
                
                // Redirect to booking page with session pre-selected
                if ($intent['template_id']) {
                    header("Location: dashboard.php?page=booking&session=" . $intent['template_id']);
                    exit();
                } elseif ($intent['package_id']) {
                    header("Location: dashboard.php?page=booking&package=" . $intent['package_id']);
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Session intent processing error: " . $e->getMessage());
        }
    }
    header("Location: dashboard.php");
    exit();
}

$error = "";

// Check for error from process_register.php
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'email_taken':
            $error = "An account with this email address already exists.";
            break;
        case 'invalid_data':
            $error = "Please fill in all required fields correctly.";
            break;
        case 'password_mismatch':
            $error = "Passwords do not match.";
            break;
        case 'database_error':
            $error = "A database error occurred. Please try again later.";
            break;
        case 'csrf_invalid':
            $error = "Security token expired. Please refresh and try again.";
            break;
        default:
            $error = "An error occurred during registration. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join the Team | Arctic Wolves</title>
    
    <link rel="icon" type="image/png" href="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png">
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body { 
            margin: 0; 
            min-height: 100vh; 
            display: flex; 
            background: #06080b; 
            font-family: 'Inter', sans-serif; 
            overflow-x: hidden;
        }

        /* LEFT SIDE: HERO / BRANDING */
        .split-left {
            flex: 1;
            background: linear-gradient(135deg, rgba(107, 70, 193, 0.1), rgba(6, 8, 11, 0.9)), url('https://images.unsplash.com/photo-1580748141549-71748dbe0bdc?q=80&w=2574&auto=format&fit=crop'); 
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 40px;
            color: #fff;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        
        .split-left::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-content h1 {
            font-size: 3rem;
            font-weight: 900;
            margin: 10px 0;
            letter-spacing: -1px;
        }
        
        .brand-content p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* RIGHT SIDE: REGISTRATION FORM */
        .split-right {
            flex: 1.2;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #06080b;
            padding: 40px;
            overflow-y: auto;
            min-height: 100vh;
        }

        .register-card {
            width: 100%;
            max-width: 500px;
            padding: 20px 0;
        }

        /* ROLE SELECTOR */
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .role-option {
            background: #0d1116;
            border: 2px solid #1e293b;
            border-radius: 12px;
            padding: 25px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-option:hover {
            border-color: #6B46C1;
            transform: translateY(-2px);
        }

        .role-option.selected {
            border-color: var(--neon);
            background: rgba(107, 70, 193, 0.1);
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-icon {
            font-size: 36px;
            color: #64748b;
            margin-bottom: 12px;
            transition: color 0.3s;
        }

        .role-option.selected .role-icon {
            color: var(--neon);
        }

        .role-title {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }

        .role-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* FORM SECTIONS */
        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--neon);
        }

        /* INPUT BOXES */
        .input-box {
            background: #0d1116;
            border: 1px solid #1e293b;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 15px;
            transition: 0.2s;
        }
        
        .input-box:focus-within {
            border-color: var(--neon);
            box-shadow: 0 0 0 2px rgba(107, 70, 193, 0.1);
        }

        .input-box label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 5px;
        }

        .input-box input, .input-box select {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
            font-size: 14px;
        }

        .input-box select {
            cursor: pointer;
        }

        .input-box select option {
            background: #0d1116;
            color: #fff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* ATHLETE CARDS */
        .athletes-container {
            margin-bottom: 20px;
        }

        .athlete-card {
            background: #0d1116;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }

        .athlete-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .athlete-number {
            font-size: 14px;
            font-weight: 700;
            color: var(--neon);
        }

        .remove-athlete-btn {
            background: transparent;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .remove-athlete-btn:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .add-athlete-btn {
            width: 100%;
            padding: 15px;
            background: transparent;
            border: 2px dashed #1e293b;
            border-radius: 8px;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .add-athlete-btn:hover {
            border-color: var(--neon);
            color: var(--neon);
            background: rgba(107, 70, 193, 0.05);
        }

        /* CHECKBOX */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(107, 70, 193, 0.05);
            border-radius: 6px;
            border: 1px solid #1e293b;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            accent-color: var(--neon);
            width: 16px;
            height: 16px;
        }

        .checkbox-label {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
        }

        .checkbox-label strong {
            color: #fff;
        }

        /* ERROR MESSAGE */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* MOBILE RESPONSIVENESS */
        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }
            .split-left { 
                display: none; 
            }
            .split-right { 
                flex: 1; 
                padding: 20px; 
                min-height: auto;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .role-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="split-left">
        <div class="brand-content">
            <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Logo" style="height: 80px; margin-bottom: 20px;">
            <h1>ARCTIC <span style="color: var(--neon);">WOLVES</span></h1>
            <p>Join our community of dedicated athletes and parents. Track progress, book sessions, and dominate the ice.</p>
        </div>
        
        <div style="position: absolute; bottom: 30px; z-index: 2; font-size: 12px; color: rgba(255,255,255,0.4);">
            &copy; <?php echo date('Y'); ?> Arctic Wolves Performance.
        </div>
    </div>

    <div class="split-right">
        <div class="register-card">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 24px; color: #fff; margin-bottom: 5px;">Create Your Account</h2>
                <p style="color: #64748b; font-size: 14px; margin: 0;">Select your role to get started</p>
            </div>

            <?php if($error): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="process_register.php" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <!-- Role Selection -->
                <div class="role-selector">
                    <label class="role-option" id="athleteOption">
                        <input type="radio" name="role" value="athlete" checked>
                        <div class="role-icon"><i class="fa-solid fa-skating"></i></div>
                        <div class="role-title">Athlete</div>
                        <div class="role-desc">I'm a player looking to improve my skills</div>
                    </label>
                    
                    <label class="role-option" id="parentOption">
                        <input type="radio" name="role" value="parent">
                        <div class="role-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="role-title">Parent</div>
                        <div class="role-desc">I want to manage athletes in my family</div>
                    </label>
                </div>

                <!-- Common Fields -->
                <div class="section-title">
                    <i class="fa-solid fa-user"></i> Your Information
                </div>

                <div class="form-row">
                    <div class="input-box">
                        <label>First Name</label>
                        <input type="text" name="first_name" required placeholder="John">
                    </div>
                    
                    <div class="input-box">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required placeholder="Smith">
                    </div>
                </div>

                <div class="input-box">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com">
                </div>

                <div class="input-box">
                    <label>Phone Number (Optional)</label>
                    <input type="tel" name="phone" placeholder="(555) 555-5555">
                </div>

                <!-- Athlete-only fields -->
                <div class="form-section active" id="athleteFields">
                    <div class="form-row">
                        <div class="input-box">
                            <label>Date of Birth</label>
                            <input type="date" name="birth_date">
                        </div>
                        
                        <div class="input-box">
                            <label>Position</label>
                            <select name="position">
                                <option value="">Select Position</option>
                                <option value="Forward">Forward</option>
                                <option value="Defense">Defense</option>
                                <option value="Goalie">Goalie</option>
                                <option value="Center">Center</option>
                                <option value="Left Wing">Left Wing</option>
                                <option value="Right Wing">Right Wing</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Parent-only fields: Athletes Management -->
                <div class="form-section" id="parentFields">
                    <div class="section-title">
                        <i class="fa-solid fa-child"></i> Add Your Athletes
                    </div>
                    
                    <div class="athletes-container" id="athletesContainer">
                        <!-- Athlete cards will be added here dynamically -->
                    </div>
                    
                    <button type="button" class="add-athlete-btn" onclick="addAthleteCard()">
                        <i class="fa-solid fa-plus"></i> Add Another Athlete
                    </button>
                </div>

                <!-- Password fields -->
                <div class="section-title" style="margin-top: 20px;">
                    <i class="fa-solid fa-lock"></i> Create Password
                </div>

                <div class="input-box">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••" minlength="8">
                </div>

                <div class="input-box">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••" minlength="8">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; font-size: 14px; border: none; cursor: pointer; border-radius: 6px; font-weight: 700; letter-spacing: 0.5px; margin-top: 10px;">
                    CREATE ACCOUNT
                </button>
            
            </form>

            <div style="margin-top: 30px; text-align: center; font-size: 13px; color: #64748b;">
                Already have an account? <a href="login.php" style="color: #fff; text-decoration: none; font-weight: 700;">Sign In</a>
            </div>

        </div>
    </div>

    <script>
        let athleteCount = 0;

        // Role selection handling
        const roleOptions = document.querySelectorAll('.role-option');
        const athleteFields = document.getElementById('athleteFields');
        const parentFields = document.getElementById('parentFields');
        const athletesContainer = document.getElementById('athletesContainer');

        roleOptions.forEach(option => {
            option.addEventListener('click', function() {
                roleOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                
                const role = this.querySelector('input').value;
                
                if (role === 'parent') {
                    athleteFields.classList.remove('active');
                    parentFields.classList.add('active');
                    
                    // Add first athlete card if none exist
                    if (athleteCount === 0) {
                        addAthleteCard();
                    }
                } else {
                    athleteFields.classList.add('active');
                    parentFields.classList.remove('active');
                }
            });
        });

        // Set initial state
        document.getElementById('athleteOption').classList.add('selected');

        function addAthleteCard() {
            athleteCount++;
            const card = document.createElement('div');
            card.className = 'athlete-card';
            card.id = `athlete-card-${athleteCount}`;
            
            card.innerHTML = `
                <div class="athlete-card-header">
                    <span class="athlete-number"><i class="fa-solid fa-user"></i> Athlete ${athleteCount}</span>
                    ${athleteCount > 1 ? `<button type="button" class="remove-athlete-btn" onclick="removeAthleteCard(${athleteCount})"><i class="fa-solid fa-trash"></i> Remove</button>` : ''}
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>First Name</label>
                        <input type="text" name="athletes[${athleteCount}][first_name]" required placeholder="First name">
                    </div>
                    
                    <div class="input-box">
                        <label>Last Name</label>
                        <input type="text" name="athletes[${athleteCount}][last_name]" required placeholder="Last name">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>Date of Birth</label>
                        <input type="date" name="athletes[${athleteCount}][birth_date]">
                    </div>
                    
                    <div class="input-box">
                        <label>Position</label>
                        <select name="athletes[${athleteCount}][position]">
                            <option value="">Select Position</option>
                            <option value="Forward">Forward</option>
                            <option value="Defense">Defense</option>
                            <option value="Goalie">Goalie</option>
                            <option value="Center">Center</option>
                            <option value="Left Wing">Left Wing</option>
                            <option value="Right Wing">Right Wing</option>
                        </select>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="athletes[${athleteCount}][use_alt_email]" id="alt-email-${athleteCount}" onchange="toggleAltEmail(${athleteCount})">
                    <label class="checkbox-label" for="alt-email-${athleteCount}">
                        <strong>Use alternate email for this athlete</strong><br>
                        By default, notifications will be sent to your (parent's) email address.
                    </label>
                </div>
                
                <div class="input-box" id="alt-email-box-${athleteCount}" style="display: none;">
                    <label>Alternate Email (Optional)</label>
                    <input type="email" name="athletes[${athleteCount}][alt_email]" placeholder="athlete@example.com">
                </div>
            `;
            
            athletesContainer.appendChild(card);
            updateAthleteNumbers();
        }

        function removeAthleteCard(id) {
            const card = document.getElementById(`athlete-card-${id}`);
            if (card) {
                card.remove();
                updateAthleteNumbers();
            }
        }

        function updateAthleteNumbers() {
            const cards = athletesContainer.querySelectorAll('.athlete-card');
            cards.forEach((card, index) => {
                const numberSpan = card.querySelector('.athlete-number');
                numberSpan.innerHTML = `<i class="fa-solid fa-user"></i> Athlete ${index + 1}`;
            });
        }

        function toggleAltEmail(id) {
            const checkbox = document.getElementById(`alt-email-${id}`);
            const emailBox = document.getElementById(`alt-email-box-${id}`);
            
            if (checkbox.checked) {
                emailBox.style.display = 'block';
            } else {
                emailBox.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            // If parent role is selected, ensure at least one athlete is added
            const role = document.querySelector('input[name="role"]:checked').value;
            if (role === 'parent') {
                const athleteCards = document.querySelectorAll('.athlete-card');
                if (athleteCards.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one athlete.');
                    return false;
                }
            }
        });
    </script>

</body>
</html>
