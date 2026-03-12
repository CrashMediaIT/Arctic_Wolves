import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. evaluations_skills.php - Direct SQL queries (no PDO fallbacks)
 * 2. workouts.php - Direct SQL queries (no PDO fallbacks)
 * 3. evaluations_skills.php - Parent viewing_athlete_id support
 * 4. workouts.php - Parent viewing_athlete_id support
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. evaluations_skills.php - No PDO fallbacks
// =====================================================

test.describe('evaluations_skills.php - no PDO fallback patterns', () => {
  test('no PDO fallback try-catch in PHP query section', () => {
    const content = readFile('views/evaluations_skills.php');
    // Extract PHP section (before the HTML/CSS)
    const phpSection = content.substring(0, content.indexOf('?>'));
    // Should not have any PDOException catch blocks with fallback queries
    expect(phpSection).not.toContain('using fallback');
    expect(phpSection).not.toContain('Deep fallback');
    expect(phpSection).not.toContain('column missing');
    expect(phpSection).not.toContain('may not exist');
  });

  test('queries reference correct schema columns directly', () => {
    const content = readFile('views/evaluations_skills.php');
    const phpSection = content.substring(0, content.indexOf('?>'));
    // Should use the actual schema column names
    expect(phpSection).toContain('ae.created_by');
    expect(phpSection).toContain('es.evaluation_id');
    expect(phpSection).toContain('ORDER BY created_at DESC');
  });

  test('score_id null coalesce handles nullable column', () => {
    const content = readFile('views/evaluations_skills.php');
    // score_id can be NULL by design (nullable FK), so ?? 0 is correct
    expect(content).toContain("$media['score_id'] ?? 0");
  });
});

// =====================================================
// 2. workouts.php - No PDO fallbacks
// =====================================================

test.describe('workouts.php - no PDO fallback patterns', () => {
  test('no PDO fallback try-catch in PHP query section', () => {
    const content = readFile('views/workouts.php');
    const phpSection = content.substring(0, content.indexOf('?>'));
    expect(phpSection).not.toContain('catch (PDOException');
    expect(phpSection).not.toContain('using fallback');
    expect(phpSection).not.toContain('Deep fallback');
    expect(phpSection).not.toContain('column missing');
  });

  test('query references correct schema columns directly', () => {
    const content = readFile('views/workouts.php');
    const workoutsSection = content.substring(
      content.indexOf('// Get workouts'),
      content.indexOf('// Get simple workouts')
    );
    expect(workoutsSection).toContain('uw.coach_id');
    expect(workoutsSection).toContain('uw.assigned_date');
    expect(workoutsSection).toContain('LEFT JOIN users coach ON uw.coach_id = coach.id');
  });
});

// =====================================================
// 3. evaluations_skills.php - Parent View As support
// =====================================================

test.describe('evaluations_skills.php - parent View As support', () => {
  test('checks session viewing_athlete_id for parents', () => {
    const content = readFile('views/evaluations_skills.php');
    expect(content).toContain("$isParent && !empty($_SESSION['viewing_athlete_id'])");
  });

  test('parent check is an elseif after coach check', () => {
    const content = readFile('views/evaluations_skills.php');
    const viewingSection = content.substring(
      content.indexOf('// Determine viewing athlete'),
      content.indexOf('// Get athlete list')
    );
    // Should be structured as: if (coach check) { } elseif (parent check) { }
    expect(viewingSection).toContain("if ($isAnyCoach && isset($_GET['athlete_id']))");
    expect(viewingSection).toContain("} elseif ($isParent && !empty($_SESSION['viewing_athlete_id']))");
  });
});

// =====================================================
// 4. workouts.php - Parent View As support
// =====================================================

test.describe('workouts.php - parent View As support', () => {
  test('checks session viewing_athlete_id for parents', () => {
    const content = readFile('views/workouts.php');
    expect(content).toContain("$isParent && !empty($_SESSION['viewing_athlete_id'])");
  });

  test('parent check is an elseif after coach check', () => {
    const content = readFile('views/workouts.php');
    const viewingSection = content.substring(
      content.indexOf('// Allow coaches to view athlete workouts'),
      content.indexOf('// Get workouts')
    );
    expect(viewingSection).toContain("if ($is_coach && isset($_GET['athlete_id']))");
    expect(viewingSection).toContain("} elseif ($isParent && !empty($_SESSION['viewing_athlete_id']))");
  });
});
