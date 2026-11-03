// Captures the screenshots used in docs/journey/README.md, walking the demo
// story from an empty system. Prepare with:
//   php artisan rsl:demo --fresh      (demo machines only: wipes the database)
//   php artisan serve --port=8123 &
//   node scripts/journey-screenshots.cjs http://127.0.0.1:8123 <password>
// The password is used for admin@system.local, l.thamae@rsl.test and p.mokoena@rsl.test.
const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const [base = 'http://127.0.0.1:8123', password = 'demo-pass'] = process.argv.slice(2);
  const out = path.join(__dirname, '..', 'docs', 'journey', 'img');
  const browser = await chromium.launch();

  const session = async (email) => {
    const context = await browser.newContext({ viewport: { width: 1200, height: 860 } });
    const page = await context.newPage();
    page.shot = (name, full = false) => page.screenshot({ path: path.join(out, name), fullPage: full });
    await page.goto(base + '/login');
    if (email === 'admin@system.local') await page.shot('00-sign-in.png');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type=submit]');
    await page.waitForURL('**/home');
    return page;
  };

  // Checkpoint 4: a company's records arrive through Data intake.
  const admin = await session('admin@system.local');
  await admin.goto(base + '/intake');
  await admin.selectOption('select[wire\\:model="tin"]', { label: 'Thaba Trading (Pty) Ltd' });
  await admin.click('button:has-text("Receive records")');
  // Opening Thaba's case queues one AI brief, generated synchronously here.
  await admin.waitForSelector('text=records received', { timeout: 300000 });
  await admin.shot('07-data-intake-company-arrives.png', true);
  await admin.click('button:has-text("Receive every company")');
  // Each company opened queues an AI brief, generated synchronously against
  // the real model in this demo setup: allow minutes, not the 30s default.
  await admin.waitForSelector('text=All mock records received', { timeout: 600000 });

  // Checkpoints 1-3 pages, now that everything has arrived.
  const officer = await session('p.mokoena@rsl.test');
  await officer.goto(base + '/home');
  await officer.shot('01-home-records-received.png', true);
  await officer.goto(base + '/records/CIT-00036');
  await officer.shot('02-record-as-received.png', true);
  await officer.goto(base + '/findings');
  await officer.shot('03-findings-by-company.png');
  const openFinding = async (company, pattern) => {
    await officer.goto(base + '/findings');
    await officer.locator('section', { hasText: company }).first().locator('a', { hasText: pattern }).first().click();
    await officer.waitForLoadState('networkidle');
  };
  await openFinding('Thaba Trading', 'P03');
  await officer.shot('04-finding-five-parts.png', true);
  await officer.goto(base + '/findings');
  await officer.locator('section', { hasText: 'Tsenola Distributors' }).first().locator('a', { hasText: 'all 14 checks' }).click();
  await officer.waitForLoadState('networkidle');
  await officer.shot('05-decoy-explains-itself.png', true);
  await openFinding('Qiloane Hardware', 'P12');
  await officer.shot('06-memory-outside-conditions.png', true);

  // Checkpoint 4: the analyst's worklist, triage and referral.
  const analyst = await session('l.thamae@rsl.test');
  await analyst.goto(base + '/worklist');
  await analyst.shot('08-worklist-ranked.png', true);
  await analyst.click('a:has-text("Thaba Trading")');
  await analyst.waitForLoadState('networkidle');
  await analyst.shot('09-case-page.png', true);

  // Checkpoint 5: the AI case brief, cited and reviewed.
  await analyst.locator('section', { hasText: 'AI case brief' }).screenshot({ path: path.join(out, '12-ai-case-brief.png') });
  await analyst.click('section:has-text("AI case brief") button:has-text("Approve")');
  await analyst.waitForSelector('text=Approved by');
  await analyst.locator('section', { hasText: 'AI case brief' }).screenshot({ path: path.join(out, '13-ai-case-brief-approved.png') });

  await analyst.click('button:has-text("Take this case")');
  await analyst.waitForSelector('text=took the case');
  await analyst.selectOption('select[wire\\:model="referTo"]', 'special_sectors');
  await analyst.fill('input[wire\\:model="referReason"]', 'Fees tripled while revenue grew 20%: needs a specialist review of the group charges.');
  await analyst.click('button:has-text("Refer")');
  await analyst.waitForSelector('text=referred it from Risk analysis to Special Sectors');
  await analyst.locator('section', { hasText: 'Timeline' }).screenshot({ path: path.join(out, '10-case-referred-timeline.png') });

  await officer.goto(base + '/worklist');
  await officer.shot('11-special-sectors-worklist.png');

  await browser.close();
})();
