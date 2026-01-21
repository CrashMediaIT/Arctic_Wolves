<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Crash Hockey | Elite Development</title>
    <meta name="description" content="Professional hockey development for players and goalies.">
    
    <link rel="icon" type="image/png" href="https://images.crashmedia.ca/images/2026/01/18/logo.png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <nav class="container nav-flex">
            <div class="logo-area" style="display: flex; align-items: center; gap: 15px;">
                <img src="https://images.crashmedia.ca/images/2026/01/18/logo.png" alt="Crash Hockey Logo" style="height: 40px; width: auto;">
                
                <div>
                    <div class="logo-text">CRASH<span>HOCKEY</span></div>
                </div>
            </div>
            
            <div class="nav-menu">
                <a href="#programs">Programs</a>
                <a href="#standards">Standards</a>
                <a href="login.php" class="nav-btn">Athlete Login</a>
            </div>
        </nav>
    </header>

    <section class="hero">
        <div class="scanline"></div>
        <div class="container hero-grid">
            <div class="hero-content">
                <a href="register.php" class="status-link">
                    <div class="status-indicator">
                        <span class="dot"></span> 
                        <span class="status-text">2026 Registration Open</span>
                    </div>
                </a>

                <h1>Crash Hockey <br><span class="highlight">Development</span></h1>
                <p>Specialized on-ice and off-ice training protocols designed for competitive athletes seeking elite performance levels.</p>
                
                <div class="hero-actions">
                    <a href="#programs" class="btn-primary">View Programs</a>
                    <a href="register.php" class="btn-secondary">Register Now</a>
                </div>
            </div>
        </div>
    </section>

    <section id="programs" class="games-section">
        <div class="container">
            <div class="section-header">
                <h2>Training Programs</h2>
                <p>Four pillars of modern player development.</p>
            </div>

            <div class="programs-grid">
                
                <div class="game-card">
                    <div class="card-img" style="background-image: url('https://images.unsplash.com/photo-1580748141549-71748ddf0bdc?q=80&w=800');"></div>
                    <div class="card-body">
                        <h3>Player Dev</h3>
                        <div class="tags"><span>Power Skating</span><span>Shooting</span></div>
                        <p>Forwards & Defense: Explosive edgework and shot mechanics.</p>
                    </div>
                </div>

                <div class="game-card">
                    <div class="card-img" style="background-image: url('https://images.unsplash.com/photo-1543326727-b5bf833b6c7a?q=80&w=800');"></div>
                    <div class="card-body">
                        <h3>Goalie Elite</h3>
                        <div class="tags"><span>Positioning</span><span>Tracking</span></div>
                        <p>Crease management, angle control, and rebound psychology.</p>
                    </div>
                </div>

                <div class="game-card">
                    <div class="card-img" style="background-image: url('https://images.unsplash.com/photo-1517836357463-d25dfeac3438?q=80&w=800');"></div>
                    <div class="card-body">
                        <h3>Conditioning</h3>
                        <div class="tags"><span>Strength</span><span>Power</span></div>
                        <p>Dryland training for endurance and explosive 60-minute power.</p>
                    </div>
                </div>

                <div class="game-card">
                    <div class="card-img" style="background-image: url('https://images.unsplash.com/photo-1490645935967-10de6ba17061?q=80&w=800');"></div>
                    <div class="card-body">
                        <h3>Nutrition</h3>
                        <div class="tags"><span>Protein</span><span>Recovery</span></div>
                        <p>Meal planning to fuel muscle growth and accelerate recovery.</p>
                    </div>
                </div>

            </div>

            <div class="library-footer">
                <a href="register.php">
                    View Full Schedule & Availability <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section id="standards" class="specs-section">
        <div class="container specs-grid">
            <div class="specs-content">
                <span class="eyebrow">Elite Standards</span>
                <h2>The Science Behind The Sport</h2>
                <div class="spec-table">
                    <div class="spec-row">
                        <span class="spec-label">Ice Ratio</span>
                        <span class="spec-value">4:1 Player/Coach</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Technology</span>
                        <span class="spec-value">Video Analysis</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Facility</span>
                        <span class="spec-value">Pro-Grade Gym</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Methodology</span>
                        <span class="spec-value">Periodization</span>
                    </div>
                </div>
            </div>
            <div class="panel-visual">
                <div class="panel-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Athlete Portal</h3>
                    <p>Track your workout progress, view ice schedules, and analyze video shifts through our custom dashboard.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="container footer-flex">
            <div class="footer-left">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <img src="https://images.crashmedia.ca/images/2026/01/18/logo.png" alt="Logo" style="height: 30px; opacity: 0.8;">
                    <div class="logo-text" style="font-size: 1.2rem;">CRASH<span>HOCKEY</span></div>
                </div>
                
                <p class="footer-desc">High-performance athletic development.</p>
                
                <div class="social-tray">
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="footer-right">
                <div class="footer-col">
                    <h4>Direct Contact</h4>
                    <a href="mailto:info@crashhockey.ca" class="footer-email-link">info@crashhockey.ca</a>
                </div>
                <div class="footer-col">
                    <h4>Account</h4>
                    <a href="login.php">Athlete Portal</a>
                    <a href="register.php">Registration</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container footer-bottom-flex">
                <p>&copy; 2026 Crash Hockey Development. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
