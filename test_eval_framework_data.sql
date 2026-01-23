-- Test data for evaluation framework drag-and-drop testing
-- Run this after database setup to test the drag-and-drop functionality

-- Insert test categories
INSERT INTO eval_categories (name, description, display_order) VALUES
('Skating', 'Fundamental skating skills and techniques', 0),
('Shooting', 'Shooting accuracy and power', 1),
('Passing', 'Passing accuracy and decision making', 2),
('Defense', 'Defensive positioning and stick handling', 3);

-- Get category IDs (will vary by database, so we use subqueries)
-- Insert test skills for Skating category
INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Forward Stride', 'Ability to perform forward skating with proper technique', 0
FROM eval_categories WHERE name = 'Skating';

INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Crossovers', 'Ability to perform crossovers in both directions', 1
FROM eval_categories WHERE name = 'Skating';

INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Edge Work', 'Control and balance on inside and outside edges', 2
FROM eval_categories WHERE name = 'Skating';

-- Insert test skills for Shooting category
INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Wrist Shot Accuracy', 'Accuracy with wrist shots from various distances', 0
FROM eval_categories WHERE name = 'Shooting';

INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Shot Release Speed', 'Speed of shot release', 1
FROM eval_categories WHERE name = 'Shooting';

INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'One-Timer', 'Ability to execute one-timer shots', 2
FROM eval_categories WHERE name = 'Shooting';

-- Insert test skills for Passing category
INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Forehand Pass', 'Accuracy and speed of forehand passes', 0
FROM eval_categories WHERE name = 'Passing';

INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Backhand Pass', 'Accuracy and speed of backhand passes', 1
FROM eval_categories WHERE name = 'Passing';

-- Insert test skills for Defense category
INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Gap Control', 'Maintaining proper gap on offensive players', 0
FROM eval_categories WHERE name = 'Defense';

INSERT INTO eval_skills (category_id, name, description, display_order)
SELECT id, 'Stick Position', 'Proper stick positioning for defense', 1
FROM eval_categories WHERE name = 'Defense';
