<?php
/**
 * Arctic Wolves - Demo Data Seeder
 * 
 * Generates realistic demo data for testing purposes across all database tables.
 * All demo entries are marked with is_demo = 1 for easy cleanup.
 * 
 * @version 1.0
 * @date January 23, 2026
 */

class DemoDataSeeder {
    private $pdo;
    private $demo_ids = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    /**
     * Add is_demo column to all tables if it doesn't exist
     */
    public function addDemoColumns() {
        $tables = $this->getAllTables();
        
        foreach ($tables as $table) {
            try {
                // Check if column already exists
                $stmt = $this->pdo->query("SHOW COLUMNS FROM `$table` LIKE 'is_demo'");
                if ($stmt->rowCount() == 0) {
                    // Add is_demo column
                    $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `is_demo` TINYINT(1) DEFAULT 0 AFTER `id`");
                    echo "✓ Added is_demo column to $table\n";
                }
            } catch (PDOException $e) {
                // Some tables might not have id column at position 1, try at end
                try {
                    $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `is_demo` TINYINT(1) DEFAULT 0");
                    echo "✓ Added is_demo column to $table (at end)\n";
                } catch (PDOException $e2) {
                    echo "⚠ Warning: Could not add is_demo to $table: " . $e2->getMessage() . "\n";
                }
            }
        }
    }
    
    /**
     * Get all table names from database
     */
    private function getAllTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }
    
    /**
     * Seed all demo data
     */
    public function seedAll() {
        echo "\n=== Starting Demo Data Seeding ===\n\n";
        
        // Seed in order to respect foreign key constraints
        $this->seedUsers();
        $this->seedLocations();
        $this->seedTeams();
        $this->seedSessionTypes();
        $this->seedAgeGroups();
        $this->seedSkillLevels();
        $this->seedPlayerPositions();
        $this->seedEquipment();
        $this->seedExpenseCategories();
        $this->seedDrillCategories();
        $this->seedPracticePlanCategories();
        $this->seedDrills();
        $this->seedPracticePlans();
        $this->seedSessions();
        $this->seedPackages();
        $this->seedDiscountCodes();
        $this->seedEvalCategories();
        $this->seedEvalSkills();
        $this->seedGoals();
        $this->seedExerciseLibrary();
        $this->seedFoodLibrary();
        $this->seedVideos();
        $this->seedExpenses();
        $this->seedNotifications();
        $this->seedSystemNotifications();
        $this->seedAuditLogs();
        $this->seedWorkoutPlans();
        $this->seedNutritionPlans();
        $this->seedCreditsRefunds();
        $this->seedEmployeeTerminations();
        $this->seedInvoices();
        $this->seedPayments();
        $this->seedMileageRecords();
        $this->seedScheduledReports();
        
        echo "\n=== Demo Data Seeding Complete! ===\n";
        echo "Total demo records created: " . $this->getTotalDemoRecords() . "\n\n";
    }
    
    /**
     * Seed demo users (athletes, coaches, admin, parents)
     */
    private function seedUsers() {
        echo "Seeding Users...\n";
        
        $users = [
            // Coaches
            ['demo_coach@example.com', 'Demo', 'Coach', 'coach', 1, 1],
            ['demo_health_coach@example.com', 'Demo Health', 'Coach', 'health_coach', 1, 1],
            ['demo_team_coach@example.com', 'Demo Team', 'Coach', 'team_coach', 1, 1],
            
            // Athletes
            ['demo_athlete1@example.com', 'Alex', 'Johnson', 'athlete', 1, 1],
            ['demo_athlete2@example.com', 'Jamie', 'Smith', 'athlete', 1, 1],
            ['demo_athlete3@example.com', 'Taylor', 'Brown', 'athlete', 1, 1],
            ['demo_athlete4@example.com', 'Jordan', 'Davis', 'athlete', 1, 1],
            ['demo_athlete5@example.com', 'Morgan', 'Wilson', 'athlete', 1, 1],
            
            // Parents
            ['demo_parent1@example.com', 'Parent', 'Johnson', 'parent', 1, 1],
            ['demo_parent2@example.com', 'Parent', 'Smith', 'parent', 1, 1],
        ];
        
        $password = password_hash('DemoPass123!', PASSWORD_DEFAULT);
        
        foreach ($users as $user) {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password, first_name, last_name, role, is_active, is_verified, is_demo, birth_date, phone, position, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())
            ");
            
            $birth_date = date('Y-m-d', strtotime('-' . rand(15, 35) . ' years'));
            $phone = sprintf('555-%03d-%04d', rand(100, 999), rand(1000, 9999));
            $position = $user[3] === 'athlete' ? ['Forward', 'Defense', 'Goalie'][rand(0, 2)] : null;
            
            $stmt->execute([
                $user[0], $password, $user[1], $user[2], $user[3], $user[4], $user[5],
                $birth_date, $phone, $position
            ]);
            
            $this->demo_ids['users'][$user[3]][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($users) . " demo users\n";
    }
    
    /**
     * Seed locations
     */
    private function seedLocations() {
        echo "Seeding Locations...\n";
        
        $locations = [
            ['Demo Arena North', '123 Hockey Way', 'North City', 'NS', 'B1A 1A1', '555-0101'],
            ['Demo Arena South', '456 Ice Lane', 'South City', 'NS', 'B2B 2B2', '555-0102'],
            ['Demo Training Center', '789 Practice Rd', 'Central City', 'NS', 'B3C 3C3', '555-0103'],
        ];
        
        foreach ($locations as $location) {
            $stmt = $this->pdo->prepare("
                INSERT INTO locations (name, address, city, province, postal_code, phone, is_demo)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($location);
            $this->demo_ids['locations'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($locations) . " demo locations\n";
    }
    
    /**
     * Seed teams
     */
    private function seedTeams() {
        echo "Seeding Teams...\n";
        
        $teams = [
            ['Demo Wolves U15', 'U15', '2024-2025'],
            ['Demo Wolves U18', 'U18', '2024-2025'],
            ['Demo Elite Squad', 'Elite', '2024-2025'],
        ];
        
        foreach ($teams as $team) {
            $stmt = $this->pdo->prepare("
                INSERT INTO teams (name, division, season, is_active, is_demo)
                VALUES (?, ?, ?, 1, 1)
            ");
            $stmt->execute($team);
            $this->demo_ids['teams'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($teams) . " demo teams\n";
    }
    
    /**
     * Seed session types
     */
    private function seedSessionTypes() {
        echo "Seeding Session Types...\n";
        
        $types = [
            ['Demo Group Training', 'Group training session', 60, 50.00, 10],
            ['Demo Private Coaching', 'One-on-one coaching', 45, 100.00, 1],
            ['Demo Team Practice', 'Full team practice', 90, 0.00, 20],
        ];
        
        foreach ($types as $type) {
            $stmt = $this->pdo->prepare("
                INSERT INTO session_types (name, description, duration_minutes, price, max_participants, is_demo)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($type);
            $this->demo_ids['session_types'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($types) . " demo session types\n";
    }
    
    /**
     * Seed age groups
     */
    private function seedAgeGroups() {
        echo "Seeding Age Groups...\n";
        
        $groups = [
            ['Demo U10', 8, 10],
            ['Demo U13', 11, 13],
            ['Demo U15', 14, 15],
            ['Demo U18', 16, 18],
        ];
        
        foreach ($groups as $group) {
            $stmt = $this->pdo->prepare("
                INSERT INTO age_groups (name, min_age, max_age, is_demo)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute($group);
            $this->demo_ids['age_groups'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($groups) . " demo age groups\n";
    }
    
    /**
     * Seed skill levels
     */
    private function seedSkillLevels() {
        echo "Seeding Skill Levels...\n";
        
        $levels = [
            ['Demo Beginner', 'Just starting out', 1],
            ['Demo Intermediate', 'Developing skills', 2],
            ['Demo Advanced', 'Highly skilled', 3],
            ['Demo Elite', 'Top tier performance', 4],
        ];
        
        foreach ($levels as $level) {
            $stmt = $this->pdo->prepare("
                INSERT INTO skill_levels (name, description, level_order, is_demo)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute($level);
            $this->demo_ids['skill_levels'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($levels) . " demo skill levels\n";
    }
    
    /**
     * Seed player positions
     */
    private function seedPlayerPositions() {
        echo "Seeding Player Positions...\n";
        
        $positions = [
            ['Demo Forward', 'FW'],
            ['Demo Defense', 'DF'],
            ['Demo Goalie', 'G'],
            ['Demo Center', 'C'],
        ];
        
        foreach ($positions as $position) {
            $stmt = $this->pdo->prepare("
                INSERT INTO player_positions (name, abbreviation, is_demo)
                VALUES (?, ?, 1)
            ");
            $stmt->execute($position);
            $this->demo_ids['player_positions'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($positions) . " demo player positions\n";
    }
    
    /**
     * Seed equipment
     */
    private function seedEquipment() {
        echo "Seeding Equipment...\n";
        
        $equipment = [
            ['Demo Stick', 'Hockey stick for practice'],
            ['Demo Puck', 'Standard practice puck'],
            ['Demo Cones', 'Training cones set'],
        ];
        
        foreach ($equipment as $item) {
            $stmt = $this->pdo->prepare("
                INSERT INTO equipment (name, description, is_demo)
                VALUES (?, ?, 1)
            ");
            $stmt->execute($item);
            $this->demo_ids['equipment'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($equipment) . " demo equipment items\n";
    }
    
    /**
     * Seed expense categories
     */
    private function seedExpenseCategories() {
        echo "Seeding Expense Categories...\n";
        
        $categories = [
            ['Demo Equipment', 'Equipment purchases'],
            ['Demo Travel', 'Travel expenses'],
            ['Demo Facilities', 'Facility rental costs'],
        ];
        
        foreach ($categories as $category) {
            $stmt = $this->pdo->prepare("
                INSERT INTO expense_categories (name, description, is_demo)
                VALUES (?, ?, 1)
            ");
            $stmt->execute($category);
            $this->demo_ids['expense_categories'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($categories) . " demo expense categories\n";
    }
    
    /**
     * Seed drill categories
     */
    private function seedDrillCategories() {
        echo "Seeding Drill Categories...\n";
        
        $categories = [
            ['Demo Skating', 'Skating drills'],
            ['Demo Shooting', 'Shooting drills'],
            ['Demo Passing', 'Passing drills'],
        ];
        
        foreach ($categories as $category) {
            $stmt = $this->pdo->prepare("
                INSERT INTO drill_categories (name, description, is_demo)
                VALUES (?, ?, 1)
            ");
            $stmt->execute($category);
            $this->demo_ids['drill_categories'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($categories) . " demo drill categories\n";
    }
    
    /**
     * Seed practice plan categories
     */
    private function seedPracticePlanCategories() {
        echo "Seeding Practice Plan Categories...\n";
        
        $categories = [
            ['Demo Skills', 'Skills development', 1],
            ['Demo Conditioning', 'Physical conditioning', 2],
            ['Demo Tactics', 'Game tactics', 3],
        ];
        
        foreach ($categories as $category) {
            $stmt = $this->pdo->prepare("
                INSERT INTO practice_plan_categories (name, description, display_order, is_demo)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute($category);
            $this->demo_ids['practice_plan_categories'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($categories) . " demo practice plan categories\n";
    }
    
    /**
     * Seed drills
     */
    private function seedDrills() {
        echo "Seeding Drills...\n";
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        // Check which columns exist in drills table
        $columns_stmt = $this->pdo->query("SHOW COLUMNS FROM drills");
        $existing_columns = [];
        while ($col = $columns_stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $col['Field'];
        }
        
        $has_title = in_array('title', $existing_columns);
        $has_name = in_array('name', $existing_columns);
        $has_created_by = in_array('created_by', $existing_columns);
        $has_coach_id = in_array('coach_id', $existing_columns);
        
        // Whitelist of allowed column names for security
        $allowed_title_cols = ['title', 'name'];
        $allowed_coach_cols = ['created_by', 'coach_id'];
        
        $title_col = $has_title ? 'title' : ($has_name ? 'name' : 'title');
        $coach_col = $has_created_by ? 'created_by' : ($has_coach_id ? 'coach_id' : 'created_by');
        
        // Validate column names are in whitelist
        if (!in_array($title_col, $allowed_title_cols) || !in_array($coach_col, $allowed_coach_cols)) {
            echo "  ⚠ Invalid column names detected, skipping drills\n";
            return;
        }
        
        // More comprehensive demo drills data
        $drills = [
            // Skating drills
            ['Demo Figure 8 Skating', 'Basic skating drill in figure 8 pattern. Players work on edge control, crossovers, and transitioning between forward and backward skating. Duration: 10-15 minutes.'],
            ['Demo Power Skating Drill', 'High-intensity skating drill focusing on explosive starts, stops, and tight turns. Excellent for conditioning and agility.'],
            ['Demo Edge Work Circuit', 'Circuit training for inside and outside edge control. Includes slaloms, tight turns, and glide exercises.'],
            
            // Shooting drills
            ['Demo Wrist Shot Practice', 'Practice wrist shots from the slot. Focus on quick release and accuracy. Set up targets in corners of the net.'],
            ['Demo One-Timer Drill', 'Partners practice one-timers with emphasis on timing, positioning, and shot placement. Great for power play scenarios.'],
            ['Demo Slap Shot Fundamentals', 'Basic slap shot technique drill. Focus on weight transfer, stick flex, and follow-through.'],
            
            // Passing drills
            ['Demo 3-Man Weave', 'Classic passing drill with three players weaving down the ice, focusing on timing and tape-to-tape passes.'],
            ['Demo Breakout Passing', 'Defensive zone breakout patterns with various passing options. Includes D-to-D, rim, and up-the-middle options.'],
            ['Demo Cross-Ice Passing', 'Two groups face each other across the neutral zone practicing long accurate passes under pressure.'],
            
            // Team play drills
            ['Demo 3-on-2 Rush', 'Offensive rush drill with 3 forwards against 2 defensemen. Focus on quick puck movement and creating scoring chances.'],
            ['Demo 2-on-1 Break', 'Classic odd-man rush drill emphasizing shot/pass decision making and defensive positioning.'],
            ['Demo Defensive Zone Coverage', 'Full team drill practicing defensive zone positioning, stick placement, and clearing rebounds.'],
        ];
        
        foreach ($drills as $drill) {
            try {
                // Get a random category if available
                $category_id = null;
                if (!empty($this->demo_ids['drill_categories'])) {
                    $category_id = $this->demo_ids['drill_categories'][array_rand($this->demo_ids['drill_categories'])];
                }
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO drills ($title_col, description, category_id, $coach_col, is_demo, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$drill[0], $drill[1], $category_id, $coach_id]);
                $this->demo_ids['drills'][] = $this->pdo->lastInsertId();
            } catch (PDOException $e) {
                echo "  ⚠ Error creating drill '{$drill[0]}': " . $e->getMessage() . "\n";
            }
        }
        
        echo "  ✓ Created " . count($this->demo_ids['drills']) . " demo drills\n";
    }
    
    /**
     * Seed practice plans
     */
    private function seedPracticePlans() {
        echo "Seeding Practice Plans...\n";
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        // Check which columns exist in practice_plans table
        $columns_stmt = $this->pdo->query("SHOW COLUMNS FROM practice_plans");
        $existing_columns = [];
        while ($col = $columns_stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $col['Field'];
        }
        
        // Determine if we have extended columns
        $has_title = in_array('title', $existing_columns);
        $has_name = in_array('name', $existing_columns);
        $has_total_duration = in_array('total_duration', $existing_columns);
        $has_age_group = in_array('age_group', $existing_columns);
        $has_focus_area = in_array('focus_area', $existing_columns);
        $has_category_id = in_array('category_id', $existing_columns);
        
        $title_col = $has_title ? 'title' : ($has_name ? 'name' : 'title');
        
        // Demo practice plans data
        $plans = [
            ['Demo Basic Skills Session', 'Introduction to basic skating and stick handling. Perfect for beginners and young players.', 60, 'U10', 'Skills Development'],
            ['Demo Advanced Shooting', 'Advanced shooting techniques and drills for competitive players.', 90, 'U14', 'Shooting'],
            ['Demo Team Tactics', 'Offensive and defensive team strategies and positioning.', 120, 'U16', 'Team Play'],
            ['Demo Power Play Practice', 'Full power play practice with multiple zone entries and shooting options.', 75, 'U14', 'Special Teams'],
            ['Demo Penalty Kill Drill', 'Defensive zone penalty kill formations and clears.', 60, 'U16', 'Special Teams'],
            ['Demo Goalie Training Session', 'Comprehensive goalie training including angles, rebounds, and lateral movement.', 90, 'All Ages', 'Goaltending'],
        ];
        
        foreach ($plans as $plan) {
            try {
                // Build dynamic insert based on available columns
                $columns = [$title_col, 'description', 'created_by', 'is_demo'];
                $values = [$plan[0], $plan[1], $coach_id, 1];
                
                if ($has_total_duration) {
                    $columns[] = 'total_duration';
                    $values[] = $plan[2];
                }
                
                if ($has_age_group) {
                    $columns[] = 'age_group';
                    $values[] = $plan[3];
                }
                
                if ($has_focus_area) {
                    $columns[] = 'focus_area';
                    $values[] = $plan[4];
                }
                
                if ($has_category_id && !empty($this->demo_ids['practice_plan_categories'])) {
                    $columns[] = 'category_id';
                    $values[] = $this->demo_ids['practice_plan_categories'][array_rand($this->demo_ids['practice_plan_categories'])];
                }
                
                $columns[] = 'created_at';
                $values[] = date('Y-m-d H:i:s');
                
                $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                $columns_str = implode(', ', $columns);
                
                $stmt = $this->pdo->prepare("INSERT INTO practice_plans ($columns_str) VALUES ($placeholders)");
                $stmt->execute($values);
                $plan_id = $this->pdo->lastInsertId();
                $this->demo_ids['practice_plans'][] = $plan_id;
                
                // Add drills to the practice plan
                if (!empty($this->demo_ids['drills'])) {
                    $num_drills = min(rand(2, 4), count($this->demo_ids['drills']));
                    $selected_drills = array_rand($this->demo_ids['drills'], $num_drills);
                    if (!is_array($selected_drills)) {
                        $selected_drills = [$selected_drills];
                    }
                    
                    $order = 0;
                    foreach ($selected_drills as $drill_index) {
                        $drill_id = $this->demo_ids['drills'][$drill_index];
                        $duration = rand(10, 20);
                        
                        // Check practice_plan_drills columns
                        $ppd_cols = $this->pdo->query("SHOW COLUMNS FROM practice_plan_drills")->fetchAll(PDO::FETCH_COLUMN);
                        $has_plan_id = in_array('plan_id', $ppd_cols);
                        $has_practice_plan_id = in_array('practice_plan_id', $ppd_cols);
                        $has_order_index = in_array('order_index', $ppd_cols);
                        $has_drill_order = in_array('drill_order', $ppd_cols);
                        
                        $plan_col = $has_plan_id ? 'plan_id' : 'practice_plan_id';
                        $order_col = $has_order_index ? 'order_index' : ($has_drill_order ? 'drill_order' : 'order_index');
                        
                        $stmt = $this->pdo->prepare("
                            INSERT INTO practice_plan_drills ($plan_col, drill_id, $order_col, duration_minutes)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$plan_id, $drill_id, $order, $duration]);
                        $order++;
                    }
                }
            } catch (PDOException $e) {
                echo "  ⚠ Error creating practice plan '{$plan[0]}': " . $e->getMessage() . "\n";
            }
        }
        
        echo "  ✓ Created " . count($this->demo_ids['practice_plans']) . " demo practice plans\n";
    }
    
    /**
     * Seed sessions
     */
    private function seedSessions() {
        echo "Seeding Sessions...\n";
        
        if (empty($this->demo_ids['session_types']) || empty($this->demo_ids['locations'])) {
            echo "  ⚠ Skipping sessions - dependencies not available\n";
            return;
        }
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        for ($i = 0; $i < 5; $i++) {
            $session_type_id = $this->demo_ids['session_types'][array_rand($this->demo_ids['session_types'])];
            $location_id = $this->demo_ids['locations'][array_rand($this->demo_ids['locations'])];
            
            $date = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
            $time = sprintf('%02d:00:00', rand(9, 18));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO sessions (session_type_id, coach_id, location_id, session_date, session_time, status, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, 'scheduled', 1, NOW())
            ");
            $stmt->execute([$session_type_id, $coach_id, $location_id, $date, $time]);
            $this->demo_ids['sessions'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created 5 demo sessions\n";
    }
    
    /**
     * Seed packages
     */
    private function seedPackages() {
        echo "Seeding Packages...\n";
        
        $packages = [
            ['Demo Starter Package', '5 session starter package', 5, 225.00, 30],
            ['Demo Premium Package', '10 session premium package', 10, 400.00, 60],
            ['Demo Elite Package', '20 session elite package', 20, 750.00, 90],
        ];
        
        foreach ($packages as $package) {
            $stmt = $this->pdo->prepare("
                INSERT INTO packages (name, description, session_count, price, validity_days, is_demo)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($package);
            $this->demo_ids['packages'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($packages) . " demo packages\n";
    }
    
    /**
     * Seed discount codes
     */
    private function seedDiscountCodes() {
        echo "Seeding Discount Codes...\n";
        
        $discounts = [
            ['DEMO10', 10.00, 'percentage', date('Y-m-d', strtotime('+30 days'))],
            ['DEMO25', 25.00, 'percentage', date('Y-m-d', strtotime('+60 days'))],
            ['DEMO50OFF', 50.00, 'fixed', date('Y-m-d', strtotime('+90 days'))],
        ];
        
        foreach ($discounts as $discount) {
            $stmt = $this->pdo->prepare("
                INSERT INTO discount_codes (code, discount_value, discount_type, expiry_date, is_active, is_demo)
                VALUES (?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute($discount);
            $this->demo_ids['discount_codes'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($discounts) . " demo discount codes\n";
    }
    
    /**
     * Seed evaluation categories
     */
    private function seedEvalCategories() {
        echo "Seeding Evaluation Categories...\n";
        
        $categories = [
            ['Demo Skating Skills', 'Skating technique and speed', 1],
            ['Demo Stick Handling', 'Puck control and handling', 2],
            ['Demo Game IQ', 'Understanding and decision making', 3],
        ];
        
        foreach ($categories as $category) {
            $stmt = $this->pdo->prepare("
                INSERT INTO eval_categories (name, description, display_order, is_demo)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute($category);
            $this->demo_ids['eval_categories'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($categories) . " demo evaluation categories\n";
    }
    
    /**
     * Seed evaluation skills
     */
    private function seedEvalSkills() {
        echo "Seeding Evaluation Skills...\n";
        
        if (empty($this->demo_ids['eval_categories'])) {
            echo "  ⚠ Skipping eval skills - no categories available\n";
            return;
        }
        
        $skills = [
            ['Demo Forward Skating', 'Forward skating speed and form', 1],
            ['Demo Backward Skating', 'Backward skating technique', 2],
            ['Demo Crossovers', 'Crossover technique both directions', 3],
        ];
        
        foreach ($skills as $skill) {
            $category_id = $this->demo_ids['eval_categories'][array_rand($this->demo_ids['eval_categories'])];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO eval_skills (name, description, category_id, display_order, is_demo)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute(array_merge($skill, [$category_id]));
            $this->demo_ids['eval_skills'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($skills) . " demo evaluation skills\n";
    }
    
    /**
     * Seed goals
     */
    private function seedGoals() {
        echo "Seeding Goals...\n";
        
        if (empty($this->demo_ids['users']['athlete'])) {
            echo "  ⚠ Skipping goals - no athletes available\n";
            return;
        }
        
        $athlete_id = $this->demo_ids['users']['athlete'][0];
        $coach_id = (!empty($this->demo_ids['users']['coach']) && isset($this->demo_ids['users']['coach'][0])) 
            ? $this->demo_ids['users']['coach'][0] 
            : null;
        
        $goals = [
            ['title' => 'Improve skating speed', 'description' => 'Increase top skating speed by 10%', 'target_date' => date('Y-m-d', strtotime('+60 days')), 'category' => 'Skating', 'tags' => 'speed,skating'],
            ['title' => 'Master wrist shot', 'description' => 'Perfect wrist shot accuracy to 80%', 'target_date' => date('Y-m-d', strtotime('+90 days')), 'category' => 'Shooting', 'tags' => 'shooting,accuracy'],
            ['title' => 'Build endurance', 'description' => 'Complete 3 periods without fatigue', 'target_date' => date('Y-m-d', strtotime('+120 days')), 'category' => 'Fitness', 'tags' => 'endurance,conditioning'],
        ];
        
        foreach ($goals as $goal) {
            $stmt = $this->pdo->prepare("
                INSERT INTO goals (athlete_id, created_by, title, description, target_date, category, tags, status, completion_percentage, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW())
            ");
            $stmt->execute([$athlete_id, $coach_id, $goal['title'], $goal['description'], $goal['target_date'], $goal['category'], $goal['tags']]);
            $this->demo_ids['goals'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($goals) . " demo goals\n";
    }
    
    /**
     * Seed exercise library
     */
    private function seedExerciseLibrary() {
        echo "Seeding Exercise Library...\n";
        
        $exercises = [
            ['Demo Squats', 'Bodyweight squats for leg strength', 'legs', 'beginner'],
            ['Demo Push-ups', 'Standard push-ups for upper body', 'chest', 'beginner'],
            ['Demo Planks', 'Core strengthening plank hold', 'core', 'beginner'],
        ];
        
        foreach ($exercises as $exercise) {
            $stmt = $this->pdo->prepare("
                INSERT INTO exercise_library (name, description, muscle_group, difficulty, is_demo)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute($exercise);
            $this->demo_ids['exercise_library'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($exercises) . " demo exercises\n";
    }
    
    /**
     * Seed food library
     */
    private function seedFoodLibrary() {
        echo "Seeding Food Library...\n";
        
        $foods = [
            ['Demo Chicken Breast', 165, 31, 3.6, 0],
            ['Demo Brown Rice', 110, 2.6, 0.9, 23],
            ['Demo Broccoli', 34, 2.8, 0.4, 7],
        ];
        
        foreach ($foods as $food) {
            $stmt = $this->pdo->prepare("
                INSERT INTO food_library (name, calories, protein_g, fat_g, carbs_g, is_demo)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($food);
            $this->demo_ids['food_library'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($foods) . " demo foods\n";
    }
    
    /**
     * Seed videos
     */
    private function seedVideos() {
        echo "Seeding Videos...\n";
        
        if (empty($this->demo_ids['users']['coach']) || empty($this->demo_ids['users']['athlete'])) {
            echo "  ⚠ Skipping videos - no coaches or athletes available\n";
            return;
        }
        
        $coach_id = $this->demo_ids['users']['coach'][0];
        $athlete_id = $this->demo_ids['users']['athlete'][0];
        
        $videos = [
            [
                'title' => 'Crossover Drill - Skating',
                'description' => 'Review of basic crossover technique during practice',
                'video_url' => 'videos/demo_video_skating_001.mp4',
                'video_type' => 'coach_review',
                'status' => 'pending_review',
                'coach_notes' => 'Watch your inside edge on the turns'
            ],
            [
                'title' => 'Wrist Shot Form - Shooting',
                'description' => 'Analysis of shooting mechanics and follow-through',
                'video_url' => 'videos/demo_video_shooting_002.mp4',
                'video_type' => 'coach_review',
                'status' => 'reviewed',
                'coach_notes' => 'Great improvement on weight transfer! Keep working on follow-through.',
                'reviewed_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ],
            [
                'title' => 'Zone Entry Practice - Offensive',
                'description' => 'Drill review for offensive zone entries',
                'video_url' => 'videos/demo_video_offensive_003.mp4',
                'video_type' => 'coach_review',
                'status' => 'pending_review',
                'coach_notes' => null
            ],
        ];
        
        foreach ($videos as $video) {
            $stmt = $this->pdo->prepare("
                INSERT INTO videos (
                    athlete_id, coach_id, title, description, video_url,
                    video_type, status, coach_notes, reviewed_at,
                    upload_date, is_demo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->execute([
                $athlete_id,
                $coach_id,
                $video['title'],
                $video['description'],
                $video['video_url'],
                $video['video_type'],
                $video['status'],
                $video['coach_notes'],
                $video['reviewed_at'] ?? null
            ]);
            $this->demo_ids['videos'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($videos) . " demo videos\n";
    }
    
    /**
     * Seed expenses
     */
    private function seedExpenses() {
        echo "Seeding Expenses...\n";
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        // Use category names as the expenses table has VARCHAR category, not category_id FK
        $categories = ['Equipment', 'Facility', 'Travel', 'Supplies', 'Other'];
        
        $expenses = [
            ['Demo Equipment Purchase', 150.00, date('Y-m-d', strtotime('-10 days'))],
            ['Demo Arena Rental', 300.00, date('Y-m-d', strtotime('-5 days'))],
            ['Demo Travel Expenses', 75.00, date('Y-m-d', strtotime('-2 days'))],
        ];
        
        foreach ($expenses as $expense) {
            $category_name = $categories[array_rand($categories)];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO expenses (description, amount, expense_date, category, user_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute(array_merge($expense, [$category_name, $coach_id]));
            $this->demo_ids['expenses'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($expenses) . " demo expenses\n";
    }
    
    /**
     * Seed notifications
     */
    private function seedNotifications() {
        echo "Seeding Notifications...\n";
        
        if (empty($this->demo_ids['users']['athlete'])) {
            echo "  ⚠ Skipping notifications - no users available\n";
            return;
        }
        
        $athlete_id = $this->demo_ids['users']['athlete'][0];
        
        $notifications = [
            ['Demo Session Reminder', 'Your training session starts in 1 hour'],
            ['Demo Goal Update', 'Great progress on your skating goal!'],
            ['Demo New Message', 'Your coach sent you a new message'],
        ];
        
        foreach ($notifications as $notification) {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, title, message, is_read, is_demo, created_at)
                VALUES (?, ?, ?, 0, 1, NOW())
            ");
            $stmt->execute(array_merge([$athlete_id], $notification));
            $this->demo_ids['notifications'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($notifications) . " demo notifications\n";
    }
    
    /**
     * Seed system notifications (admin-created global notifications)
     */
    private function seedSystemNotifications() {
        echo "Seeding System Notifications...\n";
        
        if (empty($this->demo_ids['users']['admin'])) {
            echo "  ⚠ Skipping system notifications - no admin user available\n";
            return;
        }
        
        $admin_id = $this->demo_ids['users']['admin'][0];
        
        $system_notifications = [
            [
                'title' => 'Scheduled Maintenance Window',
                'message' => 'The system will undergo scheduled maintenance on Sunday from 2:00 AM to 4:00 AM EST. During this time, some features may be temporarily unavailable.',
                'notification_type' => 'maintenance',
                'is_active' => 1
            ],
            [
                'title' => 'New Video Review Feature',
                'message' => 'We are excited to announce the launch of our new video review tools! Coaches can now easily annotate and share video feedback with athletes.',
                'notification_type' => 'info',
                'is_active' => 1
            ],
            [
                'title' => 'Registration Deadline Reminder',
                'message' => 'Reminder: Spring session registration closes in 3 days. Make sure to complete your athlete registration before the deadline.',
                'notification_type' => 'warning',
                'is_active' => 1
            ]
        ];
        
        foreach ($system_notifications as $notif) {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_notifications (title, message, notification_type, start_date, is_active, created_by)
                VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $notif['title'],
                $notif['message'],
                $notif['notification_type'],
                $notif['is_active'],
                $admin_id
            ]);
            $this->demo_ids['system_notifications'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($system_notifications) . " demo system notifications\n";
    }
    
    /**
     * Seed audit logs
     */
    private function seedAuditLogs() {
        echo "Seeding Audit Logs...\n";
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        $is_demo = 1;
        
        // Demo audit log entries with action_type and action columns
        $logs = [
            ['LOGIN', 'user_login', 'Demo coach logged in', '127.0.0.1'],
            ['INSERT', 'session_created', 'Demo training session created', '127.0.0.1'],
            ['UPDATE', 'profile_updated', 'Demo profile information updated', '127.0.0.1'],
        ];
        
        foreach ($logs as $log) {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (user_id, action_type, action, details, ip_address, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute(array_merge([$coach_id], $log, [$is_demo]));
            $this->demo_ids['audit_logs'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($logs) . " demo audit logs\n";
    }
    
    /**
     * Seed workout plans and assignments
     */
    private function seedWorkoutPlans() {
        echo "Seeding Workout Plans...\n";
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        $athlete_ids = $this->demo_ids['users']['athlete'] ?? [];
        $exercise_ids = $this->demo_ids['exercise_library'] ?? [];
        
        // Create workout plans
        $plans = [
            [
                'Off-Season Strength Program',
                'Building foundational strength for hockey players during off-season',
                12,
                'intermediate'
            ],
            [
                'Pre-Season Conditioning',
                'High-intensity conditioning to prepare for season start',
                8,
                'advanced'
            ],
            [
                'In-Season Maintenance',
                'Maintain fitness during competitive season without overtraining',
                16,
                'intermediate'
            ]
        ];
        
        foreach ($plans as $plan) {
            $stmt = $this->pdo->prepare("
                INSERT INTO workout_plans (name, description, created_by, duration_weeks, difficulty_level, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$plan[0], $plan[1], $coach_id, $plan[2], $plan[3]]);
            $plan_id = $this->pdo->lastInsertId();
            $this->demo_ids['workout_plans'][] = $plan_id;
            
            // Add exercises to each plan
            if (!empty($exercise_ids)) {
                $exercises_to_add = array_slice($exercise_ids, 0, min(5, count($exercise_ids)));
                $day = 1;
                foreach ($exercises_to_add as $exercise_id) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, day_number, sets, reps, rest_seconds, is_demo)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$plan_id, $exercise_id, $day, rand(3, 4), rand(8, 12), rand(60, 90)]);
                    $day = ($day % 5) + 1; // Rotate through days 1-5
                }
            }
        }
        
        // Assign workout plans to athletes
        if (!empty($athlete_ids) && !empty($this->demo_ids['workout_plans'])) {
            foreach (array_slice($athlete_ids, 0, 3) as $index => $athlete_id) {
                $plan_id = $this->demo_ids['workout_plans'][$index % count($this->demo_ids['workout_plans'])];
                $stmt = $this->pdo->prepare("
                    INSERT INTO athlete_workout_assignments (athlete_id, workout_plan_id, assigned_by, start_date, status, is_demo, created_at)
                    VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY), 'active', 1, NOW())
                ");
                $stmt->execute([$athlete_id, $plan_id, $coach_id, rand(1, 30)]);
                
                // Add some feedback with variety
                $feedback_messages = [
                    'Great workout plan! Feeling stronger already.',
                    'Love the structured approach. Seeing real progress!',
                    'Challenging but achievable. Perfect intensity level.',
                    'This plan has improved my on-ice performance significantly.',
                    'Well-balanced exercises. Recovery time is just right.'
                ];
                $stmt = $this->pdo->prepare("
                    INSERT INTO athlete_workout_feedback (athlete_id, workout_plan_id, rating, feedback, is_demo, created_at)
                    VALUES (?, ?, ?, ?, 1, DATE_SUB(NOW(), INTERVAL ? DAY))
                ");
                $stmt->execute([
                    $athlete_id,
                    $plan_id,
                    rand(4, 5),
                    $feedback_messages[$index % count($feedback_messages)],
                    rand(1, 20)
                ]);
            }
        }
        
        echo "  ✓ Created " . count($plans) . " demo workout plans with exercises and assignments\n";
    }
    
    /**
     * Seed nutrition plans and assignments
     */
    private function seedNutritionPlans() {
        echo "Seeding Nutrition Plans...\n";
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        $athlete_ids = $this->demo_ids['users']['athlete'] ?? [];
        $food_ids = $this->demo_ids['food_library'] ?? [];
        
        // Create nutrition plans
        $plans = [
            [
                'High Performance Meal Plan',
                'Optimized nutrition for peak athletic performance',
                3200,
                180,
                350,
                100
            ],
            [
                'Recovery & Muscle Building',
                'High protein plan for muscle recovery and growth',
                2800,
                200,
                280,
                80
            ],
            [
                'Game Day Nutrition',
                'Strategic meal timing for competition days',
                3000,
                160,
                400,
                90
            ]
        ];
        
        foreach ($plans as $plan) {
            $stmt = $this->pdo->prepare("
                INSERT INTO nutrition_plans (name, description, created_by, target_calories, target_protein_g, target_carbs_g, target_fat_g, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$plan[0], $plan[1], $coach_id, $plan[2], $plan[3], $plan[4], $plan[5]]);
            $plan_id = $this->pdo->lastInsertId();
            $this->demo_ids['nutrition_plans'][] = $plan_id;
            
            // Add meals to each plan
            $meals = ['Breakfast', 'Lunch', 'Dinner', 'Pre-Workout Snack', 'Post-Workout Snack'];
            $meal_times = ['07:00:00', '12:00:00', '18:00:00', '15:00:00', '20:30:00']; // 7am, 12pm, 6pm, 3pm, 8:30pm
            foreach ($meals as $index => $meal_name) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO nutrition_plan_meals (nutrition_plan_id, meal_name, meal_time, calories, protein_g, carbs_g, fat_g, is_demo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $meal_calories = $plan[2] / 5; // Divide total calories by 5 meals
                $stmt->execute([
                    $plan_id,
                    $meal_name,
                    $meal_times[$index],
                    $meal_calories,
                    $plan[3] / 5,
                    $plan[4] / 5,
                    $plan[5] / 5
                ]);
                $meal_id = $this->pdo->lastInsertId();
                
                // Add foods to each meal
                if (!empty($food_ids)) {
                    $foods_to_add = array_slice($food_ids, 0, min(3, count($food_ids)));
                    foreach ($foods_to_add as $food_id) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO nutrition_plan_meal_foods (meal_id, food_id, quantity, is_demo)
                            VALUES (?, ?, ?, 1)
                        ");
                        $stmt->execute([$meal_id, $food_id, rand(1, 2)]);
                    }
                }
            }
        }
        
        // Assign nutrition plans to athletes
        if (!empty($athlete_ids) && !empty($this->demo_ids['nutrition_plans'])) {
            foreach (array_slice($athlete_ids, 0, 3) as $index => $athlete_id) {
                $plan_id = $this->demo_ids['nutrition_plans'][$index % count($this->demo_ids['nutrition_plans'])];
                $stmt = $this->pdo->prepare("
                    INSERT INTO athlete_nutrition_assignments (athlete_id, nutrition_plan_id, assigned_by, start_date, status, is_demo, created_at)
                    VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY), 'active', 1, NOW())
                ");
                $stmt->execute([$athlete_id, $plan_id, $coach_id, rand(1, 30)]);
                
                // Add some feedback with variety
                $feedback_messages = [
                    'Easy to follow meal plan. Seeing great results!',
                    'Meals are delicious and I have more energy on the ice.',
                    'Perfect balance of nutrients. Recovery time has improved.',
                    'Love the variety! Never feel like I\'m on a restrictive diet.',
                    'Noticing better endurance and faster recovery between games.'
                ];
                $stmt = $this->pdo->prepare("
                    INSERT INTO athlete_nutrition_feedback (athlete_id, nutrition_plan_id, rating, feedback, is_demo, created_at)
                    VALUES (?, ?, ?, ?, 1, DATE_SUB(NOW(), INTERVAL ? DAY))
                ");
                $stmt->execute([
                    $athlete_id,
                    $plan_id,
                    rand(4, 5),
                    $feedback_messages[$index % count($feedback_messages)],
                    rand(1, 20)
                ]);
            }
        }
        
        echo "  ✓ Created " . count($plans) . " demo nutrition plans with meals and assignments\n";
    }
    
    /**
     * Seed credits and refunds
     */
    private function seedCreditsRefunds() {
        echo "Seeding Credits and Refunds...\n";
        
        $user_ids = array_merge(
            $this->demo_ids['users']['athlete'] ?? [],
            $this->demo_ids['users']['parent'] ?? []
        );
        $admin_id = $this->demo_ids['users']['admin'][0] ?? 1;
        
        if (empty($user_ids)) {
            echo "  ⚠ Skipping credits/refunds - no users available\n";
            return;
        }
        
        $transactions = [
            ['credit', 25.00, 'Account credit for session cancellation', 'completed'],
            ['refund', 50.00, 'Refund for overpayment', 'completed'],
            ['credit', 15.00, 'Good faith credit for scheduling issue', 'completed'],
            ['refund', 75.00, 'Season package refund', 'approved'],
            ['credit', 30.00, 'Promotional credit', 'pending']
        ];
        
        foreach ($transactions as $index => $transaction) {
            $user_id = $user_ids[$index % count($user_ids)];
            $stmt = $this->pdo->prepare("
                INSERT INTO credits_refunds (user_id, transaction_type, amount, reason, status, processed_by, processed_at, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, DATE_SUB(NOW(), INTERVAL ? DAY))
            ");
            $days_ago = rand(1, 60);
            $processed_at = in_array($transaction[3], ['completed', 'approved']) ? date('Y-m-d H:i:s', strtotime("-$days_ago days")) : null;
            $processed_by = $processed_at ? $admin_id : null;
            
            $stmt->execute([
                $user_id,
                $transaction[0],
                $transaction[1],
                $transaction[2],
                $transaction[3],
                $processed_by,
                $processed_at,
                $days_ago
            ]);
            $this->demo_ids['credits_refunds'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($transactions) . " demo credits and refunds\n";
    }
    
    /**
     * Seed employee terminations
     */
    private function seedEmployeeTerminations() {
        echo "Seeding Employee Terminations...\n";
        
        $coach_ids = $this->demo_ids['users']['coach'] ?? [];
        $admin_id = $this->demo_ids['users']['admin'][0] ?? 1;
        
        if (empty($coach_ids)) {
            echo "  ⚠ Skipping terminations - no coaches available\n";
            return;
        }
        
        // Only terminate one coach for demo purposes
        if (count($coach_ids) > 1) {
            $terminated_coach = $coach_ids[count($coach_ids) - 1]; // Use last coach
            
            $stmt = $this->pdo->prepare("
                INSERT INTO employee_terminations (
                    user_id, termination_date, termination_type, reason,
                    notice_period_days, final_pay_date, final_pay_amount,
                    exit_interview_completed, exit_interview_notes,
                    equipment_returned, access_revoked, processed_by, status,
                    notes, is_demo, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $termination_date = date('Y-m-d', strtotime('+30 days'));
            $final_pay_date = date('Y-m-d', strtotime('+45 days'));
            
            $stmt->execute([
                $terminated_coach,
                $termination_date,
                'voluntary',
                'Relocating to another city for family reasons',
                30,
                $final_pay_date,
                2500.00,
                0,
                null,
                0,
                0,
                $admin_id,
                'pending',
                'Notice provided. Need to reassign athletes and sessions.'
            ]);
            
            $this->demo_ids['employee_terminations'][] = $this->pdo->lastInsertId();
            echo "  ✓ Created 1 demo employee termination record\n";
        } else {
            echo "  ⚠ Skipping terminations - need at least 2 coaches\n";
        }
    }
    
    /**
     * Seed invoices for billing dashboard
     */
    private function seedInvoices() {
        echo "Seeding Invoices...\n";
        
        $user_ids = array_merge(
            $this->demo_ids['users']['athlete'] ?? [],
            $this->demo_ids['users']['parent'] ?? []
        );
        
        if (empty($user_ids)) {
            echo "  ⚠ Skipping invoices - no users available\n";
            return;
        }
        
        // [invoice_number, subtotal, tax, total, status, days_ago]
        $invoices = [
            ['INV-DEMO-001', 225.00, 29.25, 254.25, 'paid', -45],
            ['INV-DEMO-002', 400.00, 52.00, 452.00, 'paid', -30],
            ['INV-DEMO-003', 750.00, 97.50, 847.50, 'paid', -15],
            ['INV-DEMO-004', 150.00, 19.50, 169.50, 'sent', -7],
            ['INV-DEMO-005', 300.00, 39.00, 339.00, 'sent', -3],
            ['INV-DEMO-006', 175.00, 22.75, 197.75, 'overdue', -45],
            ['INV-DEMO-007', 450.00, 58.50, 508.50, 'sent', -5],
            ['INV-DEMO-008', 125.00, 16.25, 141.25, 'draft', 0],
        ];
        
        // Valid status values for mapping
        $validStatuses = ['paid', 'sent', 'overdue', 'draft'];
        
        foreach ($invoices as $index => $invoice) {
            $user_id = $user_ids[$index % count($user_ids)];
            $days_ago = $invoice[5];
            $status = $invoice[4];
            $invoice_date = date('Y-m-d', strtotime("$days_ago days"));
            $due_date = date('Y-m-d', strtotime("$days_ago days + 30 days"));
            $paid_date = ($status === 'paid') ? date('Y-m-d', strtotime("$days_ago days + 7 days")) : null;
            
            // Ensure status is valid
            $final_status = in_array($status, $validStatuses) ? $status : 'sent';
            
            $stmt = $this->pdo->prepare("
                INSERT INTO invoices (invoice_number, user_id, invoice_date, due_date, subtotal, tax_amount, total_amount, status, paid_date, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $invoice[0],
                $user_id,
                $invoice_date,
                $due_date,
                $invoice[1],
                $invoice[2],
                $invoice[3],
                $final_status,
                $paid_date
            ]);
            $this->demo_ids['invoices'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($invoices) . " demo invoices\n";
    }
    
    /**
     * Seed payments for billing/accounting dashboard
     */
    private function seedPayments() {
        echo "Seeding Payments...\n";
        
        $user_ids = array_merge(
            $this->demo_ids['users']['athlete'] ?? [],
            $this->demo_ids['users']['parent'] ?? []
        );
        
        if (empty($user_ids)) {
            echo "  ⚠ Skipping payments - no users available\n";
            return;
        }
        
        $invoice_ids = $this->demo_ids['invoices'] ?? [];
        
        $payments = [
            [254.25, 'credit_card', 'completed', -40],
            [452.00, 'credit_card', 'completed', -25],
            [847.50, 'credit_card', 'completed', -10],
            [169.50, 'bank_transfer', 'pending', -2],
            [75.00, 'credit_card', 'completed', -35],
            [150.00, 'debit_card', 'completed', -20],
            [225.00, 'credit_card', 'completed', -15],
            [95.00, 'credit_card', 'completed', -8],
            [320.00, 'bank_transfer', 'completed', -5],
            [180.00, 'credit_card', 'completed', -3],
        ];
        
        foreach ($payments as $index => $payment) {
            $user_id = $user_ids[$index % count($user_ids)];
            $invoice_id = !empty($invoice_ids) ? $invoice_ids[$index % count($invoice_ids)] : null;
            $payment_date = date('Y-m-d H:i:s', strtotime("{$payment[3]} days"));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO payments (user_id, invoice_id, amount, payment_method, payment_date, payment_status, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $user_id,
                $invoice_id,
                $payment[0],
                $payment[1],
                $payment_date,
                $payment[2]
            ]);
            $this->demo_ids['payments'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($payments) . " demo payments\n";
    }
    
    /**
     * Seed mileage records for travel tracking
     */
    private function seedMileageRecords() {
        echo "Seeding Mileage Records...\n";
        
        $coach_ids = $this->demo_ids['users']['coach'] ?? [];
        $athlete_ids = $this->demo_ids['users']['athlete'] ?? [];
        $session_ids = $this->demo_ids['sessions'] ?? [];
        $location_ids = $this->demo_ids['locations'] ?? [];
        
        if (empty($coach_ids)) {
            echo "  ⚠ Skipping mileage - no coaches available\n";
            return;
        }
        
        // Check if mileage table exists
        try {
            $this->pdo->query("SELECT 1 FROM mileage_records LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist, try to create it
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS mileage_records (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        athlete_id INT NULL,
                        session_id INT NULL,
                        trip_date DATE NOT NULL,
                        start_location VARCHAR(255) NOT NULL,
                        end_location VARCHAR(255) NOT NULL,
                        distance_km DECIMAL(10,2) NOT NULL,
                        distance_miles DECIMAL(10,2) NOT NULL,
                        purpose VARCHAR(255),
                        notes TEXT,
                        reimbursement_rate DECIMAL(5,2),
                        total_reimbursement DECIMAL(10,2),
                        is_demo TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX (user_id),
                        INDEX (trip_date)
                    )
                ");
            } catch (PDOException $e2) {
                echo "  ⚠ Could not create mileage_records table: " . $e2->getMessage() . "\n";
                return;
            }
        }
        
        $mileage_records = [
            ['Home', 'Arctic Arena', 25.5, 15.8, 'Training session', -1],
            ['Home', 'Sports Complex', 18.2, 11.3, 'Private lesson', -3],
            ['Arctic Arena', 'Away Game Facility', 85.0, 52.8, 'Away game travel', -5],
            ['Home', 'Arctic Arena', 25.5, 15.8, 'Team practice', -7],
            ['Home', 'Sports Complex', 18.2, 11.3, 'Evaluation session', -10],
            ['Arctic Arena', 'Team Headquarters', 12.0, 7.5, 'Coaches meeting', -12],
            ['Home', 'Arctic Arena', 25.5, 15.8, 'Weekend tournament', -14],
            ['Home', 'Regional Ice Center', 42.0, 26.1, 'Regional competition', -20],
        ];
        
        $rate_per_km = 0.68;
        
        foreach ($mileage_records as $index => $record) {
            $coach_id = $coach_ids[$index % count($coach_ids)];
            $athlete_id = !empty($athlete_ids) ? $athlete_ids[$index % count($athlete_ids)] : null;
            $session_id = !empty($session_ids) ? $session_ids[$index % count($session_ids)] : null;
            $trip_date = date('Y-m-d', strtotime("{$record[5]} days"));
            $reimbursement = $record[2] * $rate_per_km;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO mileage_records 
                (user_id, athlete_id, session_id, trip_date, start_location, end_location, 
                 distance_km, distance_miles, purpose, reimbursement_rate, total_reimbursement, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $coach_id,
                $athlete_id,
                $session_id,
                $trip_date,
                $record[0],
                $record[1],
                $record[2],
                $record[3],
                $record[4],
                $rate_per_km,
                $reimbursement
            ]);
            $this->demo_ids['mileage'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($mileage_records) . " demo mileage records\n";
    }
    
    /**
     * Seed scheduled reports for reporting dashboard
     */
    private function seedScheduledReports() {
        echo "Seeding Scheduled Reports...\n";
        
        $admin_ids = $this->demo_ids['users']['admin'] ?? [];
        $coach_ids = $this->demo_ids['users']['coach'] ?? [];
        
        if (empty($admin_ids) && empty($coach_ids)) {
            echo "  ⚠ Skipping scheduled reports - no users available\n";
            return;
        }
        
        // Check if scheduled_reports table exists
        try {
            $this->pdo->query("SELECT 1 FROM scheduled_reports LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist, try to create it
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS scheduled_reports (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        report_type VARCHAR(100) NOT NULL,
                        schedule_frequency ENUM('daily', 'weekly', 'monthly', 'quarterly') NOT NULL,
                        recipients TEXT,
                        filters TEXT,
                        format ENUM('pdf', 'csv', 'excel') DEFAULT 'pdf',
                        is_active TINYINT(1) DEFAULT 1,
                        last_run DATETIME NULL,
                        next_run DATETIME NULL,
                        created_by INT NOT NULL,
                        is_demo TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX (report_type),
                        INDEX (is_active)
                    )
                ");
            } catch (PDOException $e2) {
                echo "  ⚠ Could not create scheduled_reports table: " . $e2->getMessage() . "\n";
                return;
            }
        }
        
        $user_ids = array_merge($admin_ids, $coach_ids);
        
        $reports = [
            ['Weekly Revenue Summary', 'revenue', 'weekly', 'admin@arcticwolves.com', '{"date_range":"last_7_days"}', 'pdf'],
            ['Monthly Athlete Progress', 'athlete_progress', 'monthly', 'coach@arcticwolves.com', '{"include_goals":true}', 'pdf'],
            ['Quarterly Financial Report', 'financial', 'quarterly', 'admin@arcticwolves.com', '{"include_expenses":true}', 'excel'],
            ['Daily Session Bookings', 'bookings', 'daily', 'admin@arcticwolves.com', '{"status":"confirmed"}', 'csv'],
            ['Weekly Coach Activity', 'coach_activity', 'weekly', 'admin@arcticwolves.com', '{}', 'pdf'],
        ];
        
        foreach ($reports as $index => $report) {
            $user_id = $user_ids[$index % count($user_ids)];
            $next_run = date('Y-m-d 08:00:00', strtotime('+' . ($index + 1) . ' days'));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO scheduled_reports 
                (name, report_type, schedule_frequency, recipients, filters, format, is_active, next_run, created_by, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $report[0],
                $report[1],
                $report[2],
                $report[3],
                $report[4],
                $report[5],
                $next_run,
                $user_id
            ]);
            $this->demo_ids['scheduled_reports'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($reports) . " demo scheduled reports\n";
    }
    
    /**
     * Get total count of demo records
     */
    private function getTotalDemoRecords() {
        $total = 0;
        $tables = $this->getAllTables();
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table` WHERE is_demo = 1");
                $count = $stmt->fetchColumn();
                $total += $count;
            } catch (PDOException $e) {
                // Table might not have is_demo column
            }
        }
        
        return $total;
    }
    
    /**
     * Remove all demo data from database
     * @param bool $silent If true, suppresses all echo output (for AJAX/JSON contexts)
     */
    public function cleanupDemoData($silent = false) {
        if (!$silent) {
            echo "\n=== Cleaning Up Demo Data ===\n\n";
        }
        
        $tables = $this->getAllTables();
        $deleted_total = 0;
        
        // Disable foreign key checks temporarily for cleanup
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE is_demo = 1");
                $stmt->execute();
                $count = $stmt->rowCount();
                if ($count > 0) {
                    if (!$silent) {
                        echo "  ✓ Deleted $count records from $table\n";
                    }
                    $deleted_total += $count;
                }
            } catch (PDOException $e) {
                // Table might not have is_demo column or other error
                // Skip silently
            }
        }
        
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        if (!$silent) {
            echo "\n=== Demo Data Cleanup Complete! ===\n";
            echo "Total demo records deleted: $deleted_total\n\n";
        }
        
        return $deleted_total;
    }
}

// If run directly from command line
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/db_config.php';
    
    if (!$db_connected || $pdo === null) {
        die("ERROR: Database connection failed.\n");
    }
    
    $seeder = new DemoDataSeeder($pdo);
    
    // Parse command line arguments
    $action = $argv[1] ?? 'seed';
    
    if ($action === 'seed') {
        $seeder->addDemoColumns();
        $seeder->seedAll();
    } elseif ($action === 'cleanup') {
        $seeder->cleanupDemoData();
    } elseif ($action === 'columns') {
        $seeder->addDemoColumns();
    } else {
        echo "Usage: php demo_data_seeder.php [seed|cleanup|columns]\n";
        echo "  seed    - Add demo columns and seed demo data\n";
        echo "  cleanup - Remove all demo data\n";
        echo "  columns - Only add demo columns to tables\n";
    }
}
