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
        $this->seedAuditLogs();
        $this->seedWorkoutPlans();
        $this->seedNutritionPlans();
        $this->seedCreditsRefunds();
        $this->seedEmployeeTerminations();
        
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
            ['Demo Arena North', '123 Hockey Way', 'North City', 'NS', '12345', '555-0101'],
            ['Demo Arena South', '456 Ice Lane', 'South City', 'NS', '12346', '555-0102'],
            ['Demo Training Center', '789 Practice Rd', 'Central City', 'NS', '12347', '555-0103'],
        ];
        
        foreach ($locations as $location) {
            $stmt = $this->pdo->prepare("
                INSERT INTO locations (name, address, city, state, zip_code, phone, is_demo)
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
            ['Demo Wolves U15', 'U15', 2024],
            ['Demo Wolves U18', 'U18', 2024],
            ['Demo Elite Squad', 'Elite', 2024],
        ];
        
        foreach ($teams as $team) {
            $stmt = $this->pdo->prepare("
                INSERT INTO teams (name, division, season, is_demo)
                VALUES (?, ?, ?, 1)
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
        
        if (empty($this->demo_ids['drill_categories'])) {
            echo "  ⚠ Skipping drills - no categories available\n";
            return;
        }
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        $drills = [
            ['Demo Figure 8 Skating', 'Basic skating drill in figure 8 pattern', 10, 'beginner'],
            ['Demo Wrist Shot Practice', 'Practice wrist shots from slot', 15, 'intermediate'],
            ['Demo 3-on-2 Rush', 'Offensive rush drill', 20, 'advanced'],
        ];
        
        foreach ($drills as $drill) {
            $category_id = $this->demo_ids['drill_categories'][array_rand($this->demo_ids['drill_categories'])];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO drills (name, description, duration_minutes, difficulty, category_id, coach_id, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute(array_merge($drill, [$category_id, $coach_id]));
            $this->demo_ids['drills'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($drills) . " demo drills\n";
    }
    
    /**
     * Seed practice plans
     */
    private function seedPracticePlans() {
        echo "Seeding Practice Plans...\n";
        
        if (empty($this->demo_ids['practice_plan_categories'])) {
            echo "  ⚠ Skipping practice plans - no categories available\n";
            return;
        }
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        $plans = [
            ['Demo Basic Skills Session', 'Introduction to basic skating and stick handling', 60],
            ['Demo Advanced Shooting', 'Advanced shooting techniques and drills', 90],
            ['Demo Team Tactics', 'Offensive and defensive team strategies', 120],
        ];
        
        foreach ($plans as $plan) {
            $category_id = $this->demo_ids['practice_plan_categories'][array_rand($this->demo_ids['practice_plan_categories'])];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO practice_plans (name, description, duration_minutes, category_id, coach_id, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute(array_merge($plan, [$category_id, $coach_id]));
            $this->demo_ids['practice_plans'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($plans) . " demo practice plans\n";
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
        
        $goals = [
            ['Improve skating speed', 'Increase top skating speed by 10%', date('Y-m-d', strtotime('+60 days'))],
            ['Master wrist shot', 'Perfect wrist shot accuracy to 80%', date('Y-m-d', strtotime('+90 days'))],
            ['Build endurance', 'Complete 3 periods without fatigue', date('Y-m-d', strtotime('+120 days'))],
        ];
        
        foreach ($goals as $goal) {
            $stmt = $this->pdo->prepare("
                INSERT INTO goals (user_id, title, description, target_date, status, is_demo, created_at)
                VALUES (?, ?, ?, ?, 'active', 1, NOW())
            ");
            $stmt->execute(array_merge([$athlete_id], $goal));
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
        
        if (empty($this->demo_ids['users']['coach'])) {
            echo "  ⚠ Skipping videos - no coaches available\n";
            return;
        }
        
        $coach_id = $this->demo_ids['users']['coach'][0];
        
        $videos = [
            ['Demo Skating Tutorial', 'Basic skating techniques tutorial', 'https://example.com/demo-video1.mp4'],
            ['Demo Shooting Mechanics', 'Proper shooting form and mechanics', 'https://example.com/demo-video2.mp4'],
            ['Demo Puck Control', 'Advanced puck control drills', 'https://example.com/demo-video3.mp4'],
        ];
        
        foreach ($videos as $video) {
            $stmt = $this->pdo->prepare("
                INSERT INTO videos (title, description, video_url, coach_id, is_demo, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute(array_merge($video, [$coach_id]));
            $this->demo_ids['videos'][] = $this->pdo->lastInsertId();
        }
        
        echo "  ✓ Created " . count($videos) . " demo videos\n";
    }
    
    /**
     * Seed expenses
     */
    private function seedExpenses() {
        echo "Seeding Expenses...\n";
        
        if (empty($this->demo_ids['expense_categories'])) {
            echo "  ⚠ Skipping expenses - no categories available\n";
            return;
        }
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        $expenses = [
            ['Demo Equipment Purchase', 150.00, date('Y-m-d', strtotime('-10 days'))],
            ['Demo Arena Rental', 300.00, date('Y-m-d', strtotime('-5 days'))],
            ['Demo Travel Expenses', 75.00, date('Y-m-d', strtotime('-2 days'))],
        ];
        
        foreach ($expenses as $expense) {
            $category_id = $this->demo_ids['expense_categories'][array_rand($this->demo_ids['expense_categories'])];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO expenses (description, amount, expense_date, category_id, submitted_by, is_demo, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute(array_merge($expense, [$category_id, $coach_id]));
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
     * Seed audit logs
     */
    private function seedAuditLogs() {
        echo "Seeding Audit Logs...\n";
        
        $coach_id = $this->demo_ids['users']['coach'][0] ?? 1;
        
        $logs = [
            ['user_login', 'Demo coach logged in', '127.0.0.1'],
            ['session_created', 'Demo training session created', '127.0.0.1'],
            ['profile_updated', 'Demo profile information updated', '127.0.0.1'],
        ];
        
        foreach ($logs as $log) {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address, is_demo, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute(array_merge([$coach_id], $log));
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
                
                // Add some feedback
                $stmt = $this->pdo->prepare("
                    INSERT INTO athlete_workout_feedback (athlete_id, workout_plan_id, rating, feedback, is_demo, created_at)
                    VALUES (?, ?, ?, ?, 1, DATE_SUB(NOW(), INTERVAL ? DAY))
                ");
                $stmt->execute([
                    $athlete_id,
                    $plan_id,
                    rand(4, 5),
                    'Great workout plan! Feeling stronger already.',
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
            foreach ($meals as $index => $meal_name) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO nutrition_plan_meals (nutrition_plan_id, meal_name, meal_time, calories, protein_g, carbs_g, fat_g, is_demo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $meal_calories = $plan[2] / 5; // Divide total calories by 5 meals
                $stmt->execute([
                    $plan_id,
                    $meal_name,
                    sprintf('%02d:00:00', 6 + ($index * 3)), // 6am, 9am, 12pm, 3pm, 6pm
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
                
                // Add some feedback
                $stmt = $this->pdo->prepare("
                    INSERT INTO athlete_nutrition_feedback (athlete_id, nutrition_plan_id, rating, feedback, is_demo, created_at)
                    VALUES (?, ?, ?, ?, 1, DATE_SUB(NOW(), INTERVAL ? DAY))
                ");
                $stmt->execute([
                    $athlete_id,
                    $plan_id,
                    rand(4, 5),
                    'Easy to follow meal plan. Seeing great results!',
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
     */
    public function cleanupDemoData() {
        echo "\n=== Cleaning Up Demo Data ===\n\n";
        
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
                    echo "  ✓ Deleted $count records from $table\n";
                    $deleted_total += $count;
                }
            } catch (PDOException $e) {
                // Table might not have is_demo column or other error
                // Skip silently
            }
        }
        
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "\n=== Demo Data Cleanup Complete! ===\n";
        echo "Total demo records deleted: $deleted_total\n\n";
        
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
