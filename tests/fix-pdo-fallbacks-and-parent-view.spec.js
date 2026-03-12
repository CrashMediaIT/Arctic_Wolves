import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for PDO fallback fixes and parent "View As" support:
 * 1. evaluations_skills.php - Deep fallback for created_by column
 * 2. evaluations_skills.php - Eval load query wrapped in try-catch
 * 3. evaluations_skills.php - Media query fallback for created_at/uploaded_at
 * 4. evaluations_skills.php - Safe media score_id access with null coalescing
 * 5. workouts.php - Deep fallback for assigned_date column
 * 6. evaluations_skills.php - Parent viewing_athlete_id support
 * 7. workouts.php - Parent viewing_athlete_id support
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. evaluations_skills.php - Deep fallback for created_by
// =====================================================

test.describe('evaluations_skills.php - created_by deep fallback', () => {
  test('evaluations list has nested try-catch for created_by fallback', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalListSection = content.substring(
      content.indexOf('// Get all evaluations list'),
      content.indexOf('// Get historical evaluations')
    );
    // Should have a deep fallback that doesn't use created_by
    expect(evalListSection).toContain('Deep fallback: created_by');
    expect(evalListSection).toContain('NULL as creator_first_name');
    expect(evalListSection).toContain('NULL as creator_last_name');
  });

  test('deep fallback query does not reference created_by', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalListSection = content.substring(
      content.indexOf('// Get all evaluations list'),
      content.indexOf('// Get historical evaluations')
    );
    // Find the deep fallback section (after the second catch)
    const deepFallbackIdx = evalListSection.indexOf('Deep fallback');
    const deepFallback = evalListSection.substring(deepFallbackIdx);
    expect(deepFallback).not.toContain('ae.created_by');
    expect(deepFallback).not.toContain('LEFT JOIN users u');
  });
});

// =====================================================
// 2. evaluations_skills.php - Eval load query try-catch
// =====================================================

test.describe('evaluations_skills.php - eval load query protection', () => {
  test('evaluation load query is wrapped in try-catch', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalLoadSection = content.substring(
      content.indexOf('// Load evaluation'),
      content.indexOf('if ($evaluation) {')
    );
    expect(evalLoadSection).toContain('try {');
    expect(evalLoadSection).toContain('} catch (PDOException $e) {');
  });

  test('evaluation load fallback does not use created_by', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalLoadSection = content.substring(
      content.indexOf('// Load evaluation'),
      content.indexOf('if ($evaluation) {')
    );
    // The fallback should not reference created_by
    const fallbackSection = evalLoadSection.substring(
      evalLoadSection.indexOf('catch (PDOException')
    );
    expect(fallbackSection).not.toContain('ae.created_by');
    expect(fallbackSection).toContain('NULL as creator_first_name');
  });
});

// =====================================================
// 3. evaluations_skills.php - Media query fallback
// =====================================================

test.describe('evaluations_skills.php - media query fallback', () => {
  test('media query is wrapped in try-catch', () => {
    const content = readFile('views/evaluations_skills.php');
    const mediaSection = content.substring(
      content.indexOf('// Load media for this evaluation'),
      content.indexOf('// Index media by score_id')
    );
    expect(mediaSection).toContain('try {');
    expect(mediaSection).toContain('} catch (PDOException $e) {');
  });

  test('media query has fallback using uploaded_at', () => {
    const content = readFile('views/evaluations_skills.php');
    const mediaSection = content.substring(
      content.indexOf('// Load media for this evaluation'),
      content.indexOf('// Index media by score_id')
    );
    expect(mediaSection).toContain('ORDER BY created_at DESC');
    expect(mediaSection).toContain('ORDER BY uploaded_at DESC');
  });
});

// =====================================================
// 4. evaluations_skills.php - Safe score_id access
// =====================================================

test.describe('evaluations_skills.php - safe score_id access', () => {
  test('score_id access uses null coalescing operator', () => {
    const content = readFile('views/evaluations_skills.php');
    // The media indexing should safely handle missing score_id column
    expect(content).toContain("$media['score_id'] ?? 0");
  });
});

// =====================================================
// 5. workouts.php - Deep fallback for assigned_date
// =====================================================

test.describe('workouts.php - assigned_date deep fallback', () => {
  test('workouts query has nested try-catch for assigned_date fallback', () => {
    const content = readFile('views/workouts.php');
    const workoutsSection = content.substring(
      content.indexOf('// Get workouts'),
      content.indexOf('// Get simple workouts')
    );
    // Should have a deep fallback that doesn't use assigned_date in the SQL
    expect(workoutsSection).toContain('Deep fallback');
    // Deep fallback SQL should use workout_date only, not assigned_date in ORDER BY
    const deepFallbackIdx = workoutsSection.indexOf('Deep fallback');
    const deepFallback = workoutsSection.substring(deepFallbackIdx);
    expect(deepFallback).toContain('ORDER BY uw.workout_date DESC');
    expect(deepFallback).not.toContain('COALESCE(uw.assigned_date');
  });

  test('template uses null coalescing for assigned_date display', () => {
    const content = readFile('views/workouts.php');
    // The date display should handle missing assigned_date
    expect(content).toContain("['assigned_date'] ?? ");
  });
});

// =====================================================
// 6. evaluations_skills.php - Parent View As support
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
// 7. workouts.php - Parent View As support
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
