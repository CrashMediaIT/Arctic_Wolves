const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  Fix 1: Back-button navigation – coaches_reviews links must pass   */
/*  from=coaches_reviews so the detail page returns to the right view */
/* ------------------------------------------------------------------ */
test.describe('video_coach_reviews.php passes from=coaches_reviews to detail links', () => {

  test('all video_review_detail links include from=coaches_reviews', () => {
    const c = readFile('views/video_coach_reviews.php');
    // Find every href that navigates to video_review_detail
    const linkPattern = /href="[^"]*page=video_review_detail[^"]*"/g;
    const links = c.match(linkPattern);
    expect(links).not.toBeNull();
    expect(links.length).toBeGreaterThanOrEqual(4); // pending thumb, pending title, reviewed thumb, reviewed title, reviewed action
    for (const link of links) {
      expect(link).toContain('from=coaches_reviews');
    }
  });

  test('no video_review_detail link is missing the from parameter', () => {
    const c = readFile('views/video_coach_reviews.php');
    // There should be no link to video_review_detail without a from= param
    const linksWithoutFrom = c.match(/href="[^"]*page=video_review_detail(?![^"]*from=)[^"]*"/g);
    expect(linksWithoutFrom).toBeNull();
  });
});

/* ------------------------------------------------------------------ */
/*  video_review_detail.php back link respects from= parameter        */
/* ------------------------------------------------------------------ */
test.describe('video_review_detail.php respects from= query parameter for back link', () => {

  test('desktop: defaults back_page from GET[from] parameter', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toMatch(/\$back_page\s*=\s*\$_GET\['from'\]/);
  });

  test('desktop: builds back URL using back_page', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain("'?page=' . urlencode($back_page)");
  });

  test('desktop: back label differentiates coaches_reviews from coach_video_reviews', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toMatch(/\$back_label.*coaches_reviews/);
  });

  test('PWA: defaults back_page from GET[from] parameter', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toMatch(/\$back_page\s*=\s*\$_GET\['from'\]/);
  });
});

/* ------------------------------------------------------------------ */
/*  Fix 2: Entire video card is clickable via data-detail-url         */
/* ------------------------------------------------------------------ */
test.describe('video_coach_reviews.php cards are fully clickable', () => {

  test('video-list-item elements have data-detail-url attribute', () => {
    const c = readFile('views/video_coach_reviews.php');
    // Check that lines with video-list-item also have data-detail-url
    const lines = c.split('\n').filter(l => l.includes('class="video-list-item"'));
    expect(lines.length).toBeGreaterThanOrEqual(2); // pending + reviewed sections
    for (const line of lines) {
      expect(line).toContain('data-detail-url=');
    }
  });

  test('CSS sets cursor:pointer on video-list-item', () => {
    const c = readFile('views/video_coach_reviews.php');
    // The CSS rule for .video-list-item should include cursor: pointer
    const itemRule = c.match(/\.video-list-item\s*\{[^}]*cursor:\s*pointer/);
    expect(itemRule).not.toBeNull();
  });

  test('JS click handler navigates to data-detail-url on card click', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('data-detail-url');
    expect(c).toContain('card.dataset.detailUrl');
    // Must not navigate when clicking interactive children
    expect(c).toMatch(/e\.target\.closest\(['"]a,\s*button/);
  });

  test('card click handler skips buttons and links', () => {
    const c = readFile('views/video_coach_reviews.php');
    // The handler should check for interactive elements before navigating
    expect(c).toContain("e.target.closest('a, button, [data-action]')");
  });
});

/* ------------------------------------------------------------------ */
/*  Fix 3: Save Changes button for title/description edits            */
/* ------------------------------------------------------------------ */
test.describe('video_review_detail.php has a Save Changes button for title/description', () => {

  test('desktop: contains saveMetaBtn button', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('id="saveMetaBtn"');
  });

  test('desktop: saveMetaBtn is hidden by default (display:none)', () => {
    const c = readFile('views/video_review_detail.php');
    // The button should start hidden and only appear on change
    const btnMatch = c.match(/id="saveMetaBtn"[^>]*style="[^"]*display:\s*none/);
    expect(btnMatch).not.toBeNull();
  });

  test('desktop: JS shows saveMetaBtn when title or description changes', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain("getElementById('saveMetaBtn')");
    expect(c).toContain('checkMetaChanged');
    // Listens to input events on title and description
    expect(c).toContain("titleInput.addEventListener('input'");
    expect(c).toContain("descInput.addEventListener('input'");
  });

  test('desktop: saveMetaBtn sends update_video action to process_video.php', () => {
    const c = readFile('views/video_review_detail.php');
    // Find the saveMetaBtn click handler section
    const metaBtnSection = c.match(/saveMetaBtn\.addEventListener\('click'[\s\S]*?'process_video\.php'/);
    expect(metaBtnSection).not.toBeNull();
    // Should send action=update_video
    expect(metaBtnSection[0]).toContain("'update_video'");
  });

  test('desktop: saveMetaBtn updates the heading after save', () => {
    const c = readFile('views/video_review_detail.php');
    // After successful save, the video title heading should be updated
    expect(c).toContain("getElementById('videoTitle')");
    expect(c).toContain('h2.textContent = titleInput.value.trim()');
  });

  test('PWA: contains saveMetaBtn button', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('id="saveMetaBtn"');
  });

  test('PWA: saveMetaBtn is hidden by default', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    const btnMatch = c.match(/id="saveMetaBtn"[^>]*style="[^"]*display:\s*none/);
    expect(btnMatch).not.toBeNull();
  });

  test('PWA: JS shows saveMetaBtn when title or description changes', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain("getElementById('saveMetaBtn')");
    expect(c).toContain('checkMetaChanged');
  });
});

/* ------------------------------------------------------------------ */
/*  process_video.php handles title updates in update_video action     */
/* ------------------------------------------------------------------ */
test.describe('process_video.php update_video action saves title to DB', () => {

  test('handleVideoUpdate updates title and description in DB', () => {
    const c = readFile('process_video.php');
    const func = c.match(/function handleVideoUpdate\(\)[\s\S]*?^}/m);
    expect(func).not.toBeNull();
    // Should update title in videos table
    expect(func[0]).toContain('UPDATE videos SET title');
    expect(func[0]).toContain('description');
  });

  test('handleVideoUpdate checks ownership before updating title', () => {
    const c = readFile('process_video.php');
    const func = c.match(/function handleVideoUpdate\(\)[\s\S]*?^}/m);
    expect(func).not.toBeNull();
    // Should verify athlete or coach role before allowing title update
    expect(func[0]).toContain('can_edit_meta');
    expect(func[0]).toContain('athlete_id');
  });
});
