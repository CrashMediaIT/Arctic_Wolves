import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { join } from 'path';

/**
 * Development Program Enhancement Tests
 * Validates:
 * 1. Coach name displayed next to athlete name in dev program views
 * 2. Personal drill thumbnails and position selector
 * 3. Rich drill cards in coach detail view with full-page drill detail link
 * 4. Decryption fixes for personal drills and coach names
 */

const ROOT = join(__dirname, '..');

function readFile(relativePath) {
  return readFileSync(join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Coach name next to athlete name in dev programs
// =====================================================

test.describe('Coach Name Display in Development Programs', () => {

  test('active athletes query joins users table for athlete coach name', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('athlete_coach_first');
    expect(content).toContain('athlete_coach_last');
    expect(content).toContain('LEFT JOIN users c ON u.assigned_coach_id = c.id');
  });

  test('active athlete cards display coach name', () => {
    const content = readFile('views/development_programs.php');
    // Find the card section
    const cardSection = content.indexOf('dev-active-card');
    const afterCard = content.indexOf('endforeach', cardSection);
    const cardContent = content.substring(cardSection, afterCard);
    expect(cardContent).toContain('athlete_coach_first');
    expect(cardContent).toContain('Coach:');
    expect(cardContent).toContain('fa-user-tie');
  });

  test('history query includes athlete coach name', () => {
    const content = readFile('views/development_programs.php');
    const historyQuery = content.indexOf('COMPLETED/historical');
    const queryEnd = content.indexOf('fetchAll', historyQuery);
    const queryContent = content.substring(historyQuery, queryEnd);
    expect(queryContent).toContain('athlete_coach_first');
    expect(queryContent).toContain('athlete_coach_last');
  });

  test('history table has Coach column header', () => {
    const content = readFile('views/development_programs.php');
    const historyTable = content.indexOf('dev-history-table');
    const tableEnd = content.indexOf('</table>', historyTable);
    const tableContent = content.substring(historyTable, tableEnd);
    expect(tableContent).toContain('<th>Coach</th>');
  });

  test('detail view header shows athlete coach name', () => {
    const content = readFile('views/development_programs.php');
    const detailHeader = content.indexOf('page-header');
    const headerEnd = content.indexOf('page-description', detailHeader);
    const headerContent = content.substring(detailHeader, headerEnd);
    expect(headerContent).toContain('athlete_coach_first');
    expect(headerContent).toContain('Coach:');
  });

  test('selected enrollment query includes coach name', () => {
    const content = readFile('views/development_programs.php');
    const selQuery = content.indexOf('Verify access');
    const queryEnd = content.indexOf('sel_stmt->execute', selQuery);
    const queryContent = content.substring(selQuery, queryEnd);
    expect(queryContent).toContain('athlete_coach_first');
    expect(queryContent).toContain('LEFT JOIN users c ON u.assigned_coach_id');
  });
});

// =====================================================
// 2. Decryption: athlete_coach_first/last in decryptUserRow
// =====================================================

test.describe('Decryption for athlete coach fields', () => {

  test('decryptUserRow includes athlete_coach_first alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'athlete_coach_first'");
  });

  test('decryptUserRow includes athlete_coach_last alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'athlete_coach_last'");
  });

  test('drills_personal.php decrypts user rows', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('decryptUserRows');
  });
});

// =====================================================
// 3. Personal drill thumbnails and position selector
// =====================================================

test.describe('Personal Drill Thumbnails and Position', () => {

  test('personal drill form has position selector', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('pd-position');
    expect(content).toContain('value="player"');
    expect(content).toContain('value="goalie"');
  });

  test('personal drill cards have thumbnail section', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('drill-thumbnail');
    expect(content).toContain('position-icon');
  });

  test('personal drill cards show goalie icon for goalie position', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('icon-hockey-goalie position-icon goalie');
  });

  test('personal drill cards show player icon for player position', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('icon-hockey-player position-icon player');
  });

  test('personal drill cards show position label badge', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('position-label');
  });

  test('personal drill cards show video thumbnail when video exists', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('<video preload="metadata"');
  });

  test('JS form submission includes position field', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain("formData.append('position'");
    expect(content).toContain("pd-position");
  });

  test('database schema has position field on personal_drills', () => {
    const schema = readFile('database_schema.sql');
    // Find the CREATE TABLE for personal_drills
    const pdCreate = schema.indexOf('CREATE TABLE IF NOT EXISTS `personal_drills`');
    const tableEnd = schema.indexOf('ENGINE=InnoDB', pdCreate);
    const tableContent = schema.substring(pdCreate, tableEnd);
    expect(tableContent).toContain("`position`");
    expect(tableContent).toContain("'player'");
    expect(tableContent).toContain("'goalie'");
  });

  test('process handler accepts and stores position field', () => {
    const content = readFile('process_development_programs.php');
    const fnStart = content.indexOf('function handleCreatePersonalDrill');
    expect(fnStart).toBeGreaterThan(-1);
    const fnEnd = content.indexOf('\nfunction ', fnStart + 10);
    const fnBody = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 2000);
    expect(fnBody).toContain("position");
    expect(fnBody).toContain("personal_drills");
  });
});

// =====================================================
// 4. Rich drill cards in coach detail view
// =====================================================

test.describe('Rich Drill Cards in Coach View', () => {

  test('coach detail view uses rich drill card layout', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('drill-card-rich');
    expect(content).toContain('drill-card-list');
  });

  test('rich drill cards show description', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('drill-card-rich-desc');
    expect(content).toContain('drill_description');
  });

  test('rich drill cards have thumbnail area', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('drill-card-rich-thumb');
  });

  test('rich drill cards show video indicator', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('drill-has-video');
    expect(content).toContain('drill_video_url');
  });

  test('rich drill cards link to full-page drill detail', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('page=dev_drill_detail');
    expect(content).toContain('coach_view=1');
    expect(content).toContain('drill-view-link');
    expect(content).toContain('View Full Details');
  });

  test('rich drill cards still have status management actions', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('drill-card-rich-actions');
    // Find the HTML usage (not CSS definition)
    const htmlUsage = content.indexOf('class="drill-card-rich-actions"');
    expect(htmlUsage).toBeGreaterThan(-1);
    const afterCard = content.substring(htmlUsage, htmlUsage + 600);
    expect(afterCard).toContain('updateDrillStatus');
    expect(afterCard).toContain('removeDrill');
  });

  test('CSS styles for rich drill cards exist', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('.drill-card-rich {');
    expect(content).toContain('.drill-card-rich-thumb');
    expect(content).toContain('.drill-card-rich-body');
    expect(content).toContain('.drill-view-link');
  });
});

// =====================================================
// 5. Coach access to full drill detail page
// =====================================================

test.describe('Coach Access to Drill Detail View', () => {

  test('dev_drill_detail.php supports coach_view parameter', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain("coach_view");
    expect(content).toContain("is_coach_view");
  });

  test('dev_drill_detail.php checks coach roles for access', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('goalie_dev');
    expect(content).toContain('player_dev');
    expect(content).toContain('isCoachAccess');
  });

  test('dev_drill_detail.php has separate query for coach access', () => {
    const content = readFile('views/dev_drill_detail.php');
    // Coach query fetches athlete name
    expect(content).toContain('athlete_first_name');
    expect(content).toContain('athlete_last_name');
  });

  test('dev_drill_detail.php shows back link to athlete program for coaches', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain("page=development_programs&enrollment_id=");
    expect(content).toContain("Back to");
  });

  test('dev_drill_detail.php hides upload section for coaches', () => {
    const content = readFile('views/dev_drill_detail.php');
    // Upload section should be wrapped in !$is_coach_view condition
    expect(content).toContain('!$is_coach_view');
    expect(content).toContain('dev-drill-upload-section');
  });

  test('dev_drill_detail.php hides status buttons for coaches', () => {
    const content = readFile('views/dev_drill_detail.php');
    // The dev-drill-actions div with status buttons should be inside !$is_coach_view
    // Find the HTML actions block (not CSS)
    const htmlActions = content.indexOf('Mark In Progress');
    const blockBefore = content.substring(Math.max(0, htmlActions - 500), htmlActions);
    expect(blockBefore).toContain('!$is_coach_view');
  });

  test('drill navigation links preserve coach_view param', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('coach_view_param');
  });
});

// =====================================================
// 7. Personal drill card CSS collision fixes
// =====================================================

test.describe('Personal Drill Card CSS Collision Fixes', () => {

  test('form-row CSS is scoped to create-personal-drill-form to avoid global collisions', () => {
    const content = readFile('views/drills_personal.php');
    // Should use scoped selector, not bare .form-row
    expect(content).toContain('.create-personal-drill-form .form-row {');
    expect(content).toContain('.create-personal-drill-form .form-row label {');
    expect(content).toContain('.create-personal-drill-form .form-row input');
    expect(content).toContain('.create-personal-drill-form .form-row textarea');
    expect(content).toContain('.create-personal-drill-form .form-row select');
  });

  test('form-row CSS explicitly overrides global grid display', () => {
    const content = readFile('views/drills_personal.php');
    // Must set display:block to override global display:grid
    const formRowCSS = content.indexOf('.create-personal-drill-form .form-row {');
    const formRowEnd = content.indexOf('}', formRowCSS);
    const formRowBlock = content.substring(formRowCSS, formRowEnd);
    expect(formRowBlock).toContain('display: block');
  });

  test('thumbnail_path uses resolveRustfsUrl for image resolution', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('resolveRustfsUrl($pdo');
    expect(content).toContain('image_helper.php');
  });

  test('video thumbnail section has play indicator overlay', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('video-play-indicator');
    expect(content).toContain('fa-play-circle');
  });

  test('select element styling is handled by CSS not inline styles', () => {
    const content = readFile('views/drills_personal.php');
    // The select element should not have inline style for layout
    const selectTag = content.indexOf('<select id="pd-position"');
    const selectEnd = content.indexOf('>', selectTag);
    const selectElement = content.substring(selectTag, selectEnd);
    expect(selectElement).not.toContain('style=');
  });
});

// =====================================================
// 8. Personal drill edit/delete functionality
// =====================================================

test.describe('Personal Drill Edit and Delete', () => {

  test('personal drill cards have edit and delete buttons', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('editPersonalDrill(');
    expect(content).toContain('deletePersonalDrill(');
    expect(content).toContain('personal-drill-card-actions');
  });

  test('edit modal exists with all editable fields', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('edit-personal-drill-modal');
    expect(content).toContain('edit-pd-title');
    expect(content).toContain('edit-pd-description');
    expect(content).toContain('edit-pd-position');
    expect(content).toContain('edit-pd-video');
  });

  test('edit form submits update_personal_drill action', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain("formData.append('action', 'update_personal_drill')");
    expect(content).toContain("formData.append('drill_id'");
  });

  test('delete function sends delete_personal_drill action', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain("formData.append('action', 'delete_personal_drill')");
  });

  test('process handler has update_personal_drill case', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'update_personal_drill':");
    expect(content).toContain('handleUpdatePersonalDrill');
  });

  test('process handler has delete_personal_drill case', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'delete_personal_drill':");
    expect(content).toContain('handleDeletePersonalDrill');
  });

  test('update handler validates ownership before update', () => {
    const content = readFile('process_development_programs.php');
    const fnStart = content.indexOf('function handleUpdatePersonalDrill');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 10);
    const fnBody = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 3000);
    expect(fnBody).toContain('created_by');
    expect(fnBody).toContain('Access denied');
    expect(fnBody).toContain('UPDATE personal_drills');
  });

  test('delete handler validates ownership before delete', () => {
    const content = readFile('process_development_programs.php');
    const fnStart = content.indexOf('function handleDeletePersonalDrill');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 10);
    const fnBody = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 2000);
    expect(fnBody).toContain('created_by');
    expect(fnBody).toContain('Access denied');
    expect(fnBody).toContain('DELETE FROM personal_drills');
  });

  test('update handler allows updating title, description, and position', () => {
    const content = readFile('process_development_programs.php');
    const fnStart = content.indexOf('function handleUpdatePersonalDrill');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 10);
    const fnBody = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 3000);
    expect(fnBody).toContain("title = ?");
    expect(fnBody).toContain("description = ?");
    expect(fnBody).toContain("position = ?");
  });

  test('position badge SVG icon always shows in position label on all cards', () => {
    const content = readFile('views/drills_personal.php');
    // The position-label should contain the SVG icon class for ALL cards (not just ones without video)
    expect(content).toContain('position-badge-icon');
    // Verify the icon is inside the position-label span (always visible)
    const labelSpan = content.indexOf('position-label');
    const labelEnd = content.indexOf('</span>', labelSpan + 50);
    const labelContent = content.substring(labelSpan, labelEnd);
    expect(labelContent).toContain('icon-hockey-');
    expect(labelContent).toContain('position-badge-icon');
  });

  test('edit button uses json_encode with safe HTML flags for JS output', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('JSON_HEX_TAG');
    expect(content).toContain('JSON_HEX_AMP');
    expect(content).toContain("json_encode($pd['title']");
    expect(content).toContain("json_encode($pd['description']");
    expect(content).toContain("json_encode($pdPosition");
  });

  test('edit stays on personal drills page not ice canvas', () => {
    const content = readFile('views/drills_personal.php');
    // Edit should use modal, not redirect to create_drill page
    expect(content).toContain('edit-personal-drill-modal');
    expect(content).not.toContain('page=create_drill&edit=');
  });
});
