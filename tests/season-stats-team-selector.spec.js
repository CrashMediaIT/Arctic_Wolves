import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for Season Stats Team Selector:
 * The "Add Season Stats" modal should show a dropdown of teams the athlete
 * is already assigned to, with a fallback "Enter manually..." option that
 * reveals a text input for custom team entry.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

test.describe('Season Stats - Team Selector Dropdown', () => {

  test('stats.php has a select dropdown for team when userTeams is not empty', () => {
    const content = readFile('views/stats.php');
    // The team form group in the Add Season Stats modal should have a select
    expect(content).toContain('id="teamSelect"');
    expect(content).toContain('onchange="toggleManualTeamEntry(this)"');
  });

  test('stats.php populates team dropdown from $userTeams with deduplication', () => {
    const content = readFile('views/stats.php');
    // Should iterate over userTeams and build unique team list
    expect(content).toContain('$uniqueTeams');
    expect(content).toContain("foreach ($userTeams as $ut)");
    expect(content).toContain("in_array($tName, $uniqueTeams)");
  });

  test('stats.php includes "Enter manually..." option in team dropdown', () => {
    const content = readFile('views/stats.php');
    expect(content).toContain('value="__manual__"');
    expect(content).toContain('Enter manually...');
  });

  test('stats.php has a hidden manual team text input that toggles visibility', () => {
    const content = readFile('views/stats.php');
    expect(content).toContain('id="manualTeamInput"');
    expect(content).toContain('style="display:none; margin-top:6px;"');
  });

  test('stats.php uses a hidden input to send the team value to the backend', () => {
    const content = readFile('views/stats.php');
    expect(content).toContain('name="team" id="teamHiddenValue"');
  });

  test('stats.php falls back to text input when userTeams is empty', () => {
    const content = readFile('views/stats.php');
    // When no teams exist, a plain text input should be shown
    const modalSection = content.substring(
      content.indexOf('id="addStatsModal"'),
      content.indexOf('id="skaterStatsFields"')
    );
    expect(modalSection).toContain('<?php else: ?>');
    expect(modalSection).toContain('name="team" class="form-input" placeholder="e.g. Arctic Wolves U18"');
  });

  test('stats.php has toggleManualTeamEntry JavaScript function', () => {
    const content = readFile('views/stats.php');
    expect(content).toContain('function toggleManualTeamEntry(select)');
    // Should toggle visibility of manual input
    expect(content).toContain("manualInput.style.display = ''");
    expect(content).toContain("manualInput.style.display = 'none'");
    // Should update hidden input value
    expect(content).toContain('hiddenInput.value = select.value');
  });

  test('toggleManualTeamEntry syncs manual input to hidden field via oninput', () => {
    const content = readFile('views/stats.php');
    expect(content).toContain('manualInput.oninput');
    expect(content).toContain('hiddenInput.value = this.value');
  });

  test('backend process_goals.php still reads team from POST', () => {
    const content = readFile('process_goals.php');
    const statsSection = content.substring(
      content.indexOf("case 'add_season_stats'"),
      content.indexOf("case 'add_performance_metric'")
    );
    expect(statsSection).toContain("$_POST['team']");
  });
});
