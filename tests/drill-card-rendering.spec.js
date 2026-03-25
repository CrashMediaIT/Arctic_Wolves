/**
 * Comprehensive drill card rendering test - loads ALL real CSS files
 * and uses the exact DOM hierarchy from the live page.
 */
const { test, expect, chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE = path.join(__dirname, '..');

// Load ALL CSS files that pwa.php includes
const styleGuideCss = fs.readFileSync(path.join(BASE, 'css', 'style-guide.css'), 'utf8');
const componentsCss = fs.readFileSync(path.join(BASE, 'css', 'components.css'), 'utf8');
const sharedStylesCss = fs.readFileSync(path.join(BASE, 'views', 'shared_styles.css'), 'utf8');
const pwaCss = fs.readFileSync(path.join(BASE, 'css', 'pwa.css'), 'utf8');

// Extract component CSS from the PHP view
const phpContent = fs.readFileSync(
  path.join(BASE, 'views', 'pwa', 'personal_development_my_program.php'), 'utf8'
);
const styleMatch = phpContent.match(/<style>([\s\S]*?)<\/style>/);
const componentCss = styleMatch ? styleMatch[1] : '';

function buildTestPage() {
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* === style-guide.css === */
${styleGuideCss}

/* === components.css === */
${componentsCss}

/* === shared_styles.css === */
${sharedStylesCss}

/* === pwa.css (loaded last, highest precedence) === */
${pwaCss}

/* === Component CSS from personal_development_my_program.php === */
${componentCss}
</style>
</head>
<body class="pwa-body">

<!-- Exact same wrapper structure as pwa.php -->
<div class="pwa-content" id="pwaContent">

  <!-- Exact same structure as the PHP view -->
  <div class="m-myprog m-detail-active">

    <!-- LIST VIEW (hidden when detail active) -->
    <div class="m-myprog-list" style="display:none;"></div>

    <!-- DETAIL VIEW (visible when m-detail-active) -->
    <div class="m-myprog-detail" id="mProgDetail">

      <!-- Detail Header -->
      <div class="m-myprog-detail-header">
        <button class="m-myprog-back" id="mProgBack" aria-label="Back to programs">
          <i class="fas fa-chevron-left"></i>
        </button>
        <div class="m-myprog-detail-title">
          <h3 id="mProgDetailName">Player Development Program</h3>
          <span id="mProgDetailMeta">4 wks left · Enrolled Jan 15, 2026</span>
        </div>
      </div>

      <!-- Tabs -->
      <div class="m-myprog-tabs" id="mProgTabs">
        <button class="m-myprog-tab" data-tab="overview"><i class="fas fa-info-circle"></i> Overview</button>
        <button class="m-myprog-tab active" data-tab="drills"><i class="fas fa-dumbbell"></i> Drills</button>
        <button class="m-myprog-tab" data-tab="videos"><i class="fas fa-video"></i> Videos</button>
        <button class="m-myprog-tab" data-tab="chat"><i class="fas fa-comments"></i> Chat</button>
      </div>

      <!-- Tab Content -->
      <div class="m-myprog-tab-content" id="mProgTabContent">
        <!-- This is what JS injects as a .m-myprog-tab-pane wrapper -->
        <div class="m-myprog-tab-pane active" data-pane="drills">

          <!-- ========= DRILL CARD 1 (Collapsed) ========= -->
          <div class="m-myprog-drill" onclick="this.classList.toggle('expanded')" tabindex="0" role="button" aria-expanded="false">
            <div class="m-myprog-drill-header">
              <div class="m-myprog-drill-title">Crossover Step Power Skating</div>
              <span class="m-myprog-drill-status assigned">
                <i class="fas fa-clock"></i> Assigned
              </span>
            </div>
            <div class="m-myprog-drill-desc">Focus on deep knee bend and full extension through each crossover. Maintain proper edge control throughout the drill.</div>
            <div class="m-myprog-drill-expand"><i class="fas fa-chevron-down"></i> Tap for details</div>
            <div class="m-myprog-drill-detail">
              <div class="m-myprog-drill-detail-section">
                <div class="m-myprog-drill-detail-label">Description</div>
                <p>Focus on deep knee bend and full extension through each crossover. Maintain proper edge control throughout.</p>
              </div>
              <div class="m-myprog-drill-detail-section">
                <div class="m-myprog-drill-detail-label">Setup</div>
                <p>Place 5 cones in a circle, 10 feet apart. Start at center ice.</p>
              </div>
              <div class="m-myprog-drill-detail-section">
                <div class="m-myprog-drill-detail-label">Coaching Points</div>
                <p>Stay low, drive with outside edge, full arm extension on each stride.</p>
              </div>
              <div class="coach-name"><i class="fas fa-user-tie"></i> Assigned by: Coach Mike Johnson</div>
            </div>
          </div>

          <!-- ========= DRILL CARD 2 (Expanded) ========= -->
          <div class="m-myprog-drill expanded" tabindex="0" role="button" aria-expanded="true">
            <div class="m-myprog-drill-header">
              <div class="m-myprog-drill-title">Backward to Forward Pivot Transitions</div>
              <span class="m-myprog-drill-status in_progress">
                <i class="fas fa-spinner"></i> In Progress
              </span>
            </div>
            <div class="m-myprog-drill-desc">Work on smooth pivot transitions from backward skating to forward skating at full speed.</div>
            <div class="m-myprog-drill-expand"><i class="fas fa-chevron-down"></i> Tap for details</div>
            <div class="m-myprog-drill-detail">
              <div class="m-myprog-drill-detail-section">
                <div class="m-myprog-drill-detail-label">Description</div>
                <p>Work on smooth pivot transitions from backward skating to forward skating at full speed. Focus on maintaining balance and speed through the transition.</p>
              </div>
              <div class="m-myprog-drill-detail-section">
                <div class="m-myprog-drill-detail-label">Coach Notes</div>
                <p class="coach-note"><i class="fas fa-sticky-note"></i> Great improvement this week! Keep working on the left side transition.</p>
              </div>
              <div class="m-myprog-drill-detail-section">
                <a href="https://example.com/video" target="_blank" rel="noopener noreferrer" class="m-myprog-drill-video-link" onclick="event.stopPropagation();">
                  <i class="fas fa-play-circle"></i> Watch Drill Video
                </a>
              </div>
              <div class="coach-name"><i class="fas fa-user-tie"></i> Assigned by: Coach Mike Johnson</div>
            </div>
          </div>

          <!-- ========= DRILL CARD 3 (Completed) ========= -->
          <div class="m-myprog-drill" tabindex="0" role="button" aria-expanded="false">
            <div class="m-myprog-drill-header">
              <div class="m-myprog-drill-title">Puck Control Stickhandling Series</div>
              <span class="m-myprog-drill-status completed">
                <i class="fas fa-check"></i> Completed
              </span>
            </div>
            <div class="m-myprog-drill-desc">Advanced stickhandling patterns with rapid hand movements and toe drags.</div>
            <div class="m-myprog-drill-expand"><i class="fas fa-chevron-down"></i> Tap for details</div>
            <div class="m-myprog-drill-detail">
              <div class="m-myprog-drill-detail-section">
                <div class="m-myprog-drill-detail-label">Description</div>
                <p>Advanced stickhandling patterns with rapid hand movements and toe drags. Use both forehand and backhand.</p>
              </div>
              <div class="coach-name"><i class="fas fa-user-tie"></i> Assigned by: Coach Sarah Williams</div>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>`;
}

test.describe('Drill card rendering with ALL CSS', () => {

  test('drill cards render correctly at 375px mobile', async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const page = await context.newPage();

    await page.setContent(buildTestPage(), { waitUntil: 'load' });
    await page.waitForTimeout(500);

    // Check key computed styles on the drill card and children
    const results = await page.evaluate(() => {
      const checks = {};
      const elements = {
        'drill-card': document.querySelector('.m-myprog-drill'),
        'drill-header': document.querySelector('.m-myprog-drill-header'),
        'drill-title': document.querySelector('.m-myprog-drill-title'),
        'drill-desc': document.querySelector('.m-myprog-drill-desc'),
        'drill-expand': document.querySelector('.m-myprog-drill-expand'),
        'detail-visible': document.querySelector('.m-myprog-drill.expanded .m-myprog-drill-detail'),
        'detail-label': document.querySelector('.m-myprog-drill.expanded .m-myprog-drill-detail-label'),
        'detail-p': document.querySelector('.m-myprog-drill.expanded .m-myprog-drill-detail p'),
        'coach-name': document.querySelector('.m-myprog-drill.expanded .coach-name'),
        'video-link': document.querySelector('.m-myprog-drill-video-link'),
      };

      for (const [name, el] of Object.entries(elements)) {
        if (!el) { checks[name] = 'NOT FOUND'; continue; }
        const cs = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        checks[name] = {
          display: cs.display,
          overflow: cs.overflow,
          overflowX: cs.overflowX,
          maxWidth: cs.maxWidth,
          width: Math.round(rect.width),
          height: Math.round(rect.height),
          textOverflow: cs.textOverflow,
          whiteSpace: cs.whiteSpace,
          writingMode: cs.writingMode,
          flexDirection: cs.flexDirection,
        };
      }
      return checks;
    });

    // Log all results
    for (const [name, styles] of Object.entries(results)) {
      console.log(`\n=== ${name} ===`);
      if (typeof styles === 'string') { console.log(`  ${styles}`); continue; }
      console.log(`  display: ${styles.display} | overflow: ${styles.overflow}`);
      console.log(`  width: ${styles.width}px | height: ${styles.height}px`);
      console.log(`  maxWidth: ${styles.maxWidth} | textOverflow: ${styles.textOverflow}`);
      console.log(`  whiteSpace: ${styles.whiteSpace} | writingMode: ${styles.writingMode}`);
      console.log(`  flexDirection: ${styles.flexDirection} | overflowX: ${styles.overflowX}`);
    }

    // CRITICAL ASSERTIONS
    // Drill card must be block, not inline-flex
    expect(results['drill-card'].display).toBe('block');
    // Drill card must NOT clip content
    expect(results['drill-card'].overflow).not.toBe('hidden');
    // Title must have reasonable width (>100px on 375px screen)
    expect(results['drill-title'].width).toBeGreaterThan(100);
    // Description must be visible
    expect(results['drill-desc'].height).toBeGreaterThan(0);
    // Expanded detail must be visible
    expect(results['detail-visible'].display).toBe('block');
    expect(results['detail-visible'].height).toBeGreaterThan(0);
    // Text must not be clipped
    expect(results['detail-p'].overflow).not.toBe('hidden');

    // Take screenshot
    const screenshotDir = path.join(__dirname, '..', 'screenshots');
    if (!fs.existsSync(screenshotDir)) fs.mkdirSync(screenshotDir, { recursive: true });
    await page.screenshot({
      path: path.join(screenshotDir, 'drills-tab-fixed.png'),
      fullPage: true
    });
    console.log('\nScreenshot saved to screenshots/drills-tab-fixed.png');

    await browser.close();
  });
});
