// Captures the screenshots used in docs/journey/README.md, walking the demo
// story from a freshly loaded database (every company received and analysed,
// no cases worked yet). Prepare with:
//   php artisan rsl:demo --fresh --all   (demo machines only: wipes the database)
//   php artisan serve --port=8123 &
//   php artisan queue:work --tries=1 --timeout=360 &   (drains the queued AI briefs; wait until case_briefs has rows for Thaba/Katse/Qiloane/Lebelo before running this script)
//   node scripts/journey-screenshots.cjs http://127.0.0.1:8123 <password>
// The password is used for every *@rsl.test user and admin@system.local.
const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const [base = 'http://127.0.0.1:8123', password = 'demo-pass'] = process.argv.slice(2);
  const out = path.join(__dirname, '..', 'docs', 'journey', 'img');
  const browser = await chromium.launch();

  const session = async (email) => {
    const context = await browser.newContext({ viewport: { width: 1200, height: 860 } });
    const page = await context.newPage();
    page.on('dialog', (d) => d.accept());
    page.shot = (name, full = false) => page.screenshot({ path: path.join(out, name), fullPage: full });
    if (!email) return page;
    await page.goto(base + '/login');
    await page.fill('#email', email);
    await page.fill('#password', email === 'admin@system.local' ? (process.env.SUPER_ADMIN_PASSWORD || 'password') : password);
    await page.click('button[type=submit]');
    await page.waitForURL('**/home');
    return page;
  };

  // --- Checkpoint 0: front page (the way in) and sign-in ---
  const guest = await session(null);
  await guest.goto(base + '/welcome');
  await guest.waitForLoadState('networkidle');
  await guest.shot('00-front-page.png', true);
  await guest.goto(base + '/login');
  await guest.shot('00-sign-in.png');

  // --- Checkpoints 1-3: records, findings, memory (pages unchanged by the desk redesign) ---
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

  // --- Checkpoint 4 + 6: data intake, the desk (lanes replace the old flat worklist) ---
  const admin = await session('admin@system.local');
  await admin.goto(base + '/intake');
  await admin.shot('07-data-intake.png', true);

  const analyst = await session('l.thamae@rsl.test');
  await analyst.goto(base + '/worklist');
  await analyst.shot('08-desk-lanes.png', true);
  await analyst.click('article:has-text("Thaba Trading") a:has-text("Open case")');
  await analyst.waitForLoadState('networkidle');
  await analyst.shot('09-case-page.png', true);

  // --- Checkpoint 5: the AI case brief, cited and reviewed ---
  await analyst.locator('section', { hasText: 'AI case brief' }).screenshot({ path: path.join(out, '10-ai-case-brief.png') });
  await analyst.click('section:has-text("AI case brief") button:has-text("Approve")');
  await analyst.waitForSelector('text=Approved by');
  await analyst.locator('section', { hasText: 'AI case brief' }).screenshot({ path: path.join(out, '11-ai-case-brief-approved.png') });

  await analyst.click('button:has-text("Take this case")');
  await analyst.waitForSelector('text=took the case');
  await analyst.selectOption('select[wire\\:model="referTo"]', 'special_sectors');
  await analyst.fill('input[wire\\:model="referReason"]', 'Fees tripled while revenue grew 20%: needs a specialist review of the group charges.');
  await analyst.click('button:has-text("Refer")');
  await analyst.waitForSelector('text=referred it from Risk analysis to Special Sectors');
  await analyst.locator('section', { hasText: 'Timeline' }).screenshot({ path: path.join(out, '12-case-referred-timeline.png') });

  // --- Checkpoint 6: the officer's desk ---
  // Act as, from the admin header menu.
  await admin.goto(base + '/home');
  await admin.click('summary:has-text("Act as…")');
  await admin.shot('13-act-as-menu.png');
  await admin.click('button:has-text("L. Thamae")');
  await admin.waitForLoadState('networkidle');
  await admin.shot('14-acting-as-banner.png');
  await admin.click('button:has-text("Return to admin")');
  await admin.waitForLoadState('networkidle');

  // Special Sectors' desk: the hand-off, with its note.
  const specialist = await session('p.mokoena@rsl.test');
  await specialist.goto(base + '/worklist');
  await specialist.shot('15-handed-to-us.png', true);
  await specialist.click('article:has-text("Thaba Trading") a:has-text("Open case")');
  await specialist.waitForLoadState('networkidle');
  await specialist.shot('16-hand-off-note.png', true);

  // Request documents.
  await specialist.locator('section:has-text("Requests and documents")').scrollIntoViewIfNeeded();
  await specialist.click('section:has-text("Requests and documents") button:has-text("Request documents")');
  await specialist.locator('section:has-text("Requests and documents")').screenshot({ path: path.join(out, '17-request-drafted.png') });
  await specialist.click('section:has-text("Requests and documents") button:has-text("Send request")');
  await specialist.waitForSelector('text=sent by');
  await specialist.locator('section:has-text("Requests and documents")').screenshot({ path: path.join(out, '18-request-sent.png') });

  // Katse also needs its request sent, in the same lane, before the clock can release anything for it.
  await specialist.goto(base + '/worklist');
  await specialist.waitForLoadState('networkidle');
  await specialist.click('article:has-text("Katse") a:has-text("Open case")');
  await specialist.waitForLoadState('networkidle');
  await specialist.click('section:has-text("Requests and documents") button:has-text("Request documents")');
  await specialist.click('section:has-text("Requests and documents") button:has-text("Send request")');
  await specialist.waitForSelector('text=sent by');

  // Advance the clock so both cases' requested documents are released.
  await admin.goto(base + '/home');
  await admin.fill('input[wire\\:model="days"]', '14');
  await admin.click('button:has-text("Advance")');
  await admin.waitForURL('**/home');
  await admin.shot('19-clock-advanced.png', true);

  // Katse: documents arrived, a new finding (P09) at the top of the evidence ladder.
  await specialist.goto(base + '/worklist');
  await specialist.waitForLoadState('networkidle');
  const katseCard = specialist.locator('article', { hasText: 'Katse' }).first();
  if (await katseCard.count()) {
    await katseCard.locator('a:has-text("Open case")').click();
  } else {
    await specialist.goto(base + '/findings');
    await specialist.locator('section', { hasText: 'Katse' }).first().locator('a').first().click();
  }
  await specialist.waitForLoadState('networkidle');
  await specialist.shot('20-katse-documents-and-finding.png', true);

  // Record an outcome: the five-part decision.
  const outcomeSection = specialist.locator('article', { hasText: 'P09' }).first();
  if (await outcomeSection.locator('button:has-text("Record an outcome")').count()) {
    await outcomeSection.locator('button:has-text("Record an outcome")').click();
    await outcomeSection.locator('select[wire\\:model="outcome"]').selectOption({ label: 'More information needed' });
    await outcomeSection.locator('textarea[wire\\:model="observed_pattern"]').fill('Fixed monthly payments to the parent described as management fees, unchanged for years.');
    await outcomeSection.locator('textarea[wire\\:model="conclusion"]').fill('The arrangement needs to be checked against payroll and the services agreement before any conclusion.');
    await outcomeSection.locator('textarea[wire\\:model="evidence"]').fill('Services agreement and payroll records now on file.');
    await outcomeSection.locator('textarea[wire\\:model="conditions"]').fill('Holds while the arrangement is unchanged.');
    await outcomeSection.locator('input[wire\\:model="reassessment_point"]').fill('Next annual filing.');
    await outcomeSection.screenshot({ path: path.join(out, '21-outcome-form.png') });
    await outcomeSection.locator('button:has-text("Submit outcome")').click();
    await specialist.waitForTimeout(1000);
    await outcomeSection.screenshot({ path: path.join(out, '22-outcome-recorded.png') });
  }

  // Notifications sent: no taxpayer details, ever. Gated to roles with view_audit_log (team leads, audit viewers).
  const teamLead = await session('m.sello@rsl.test');
  await teamLead.goto(base + '/notifications');
  await teamLead.waitForLoadState('networkidle');
  await teamLead.shot('23-notifications-sent.png', true);

  // Results tab.
  await specialist.goto(base + '/results');
  await specialist.waitForLoadState('networkidle');
  await specialist.shot('24-results.png', true);

  // The guided tour panel, from a fresh guest session.
  const tourGuest = await session(null);
  await tourGuest.goto(base + '/welcome');
  await tourGuest.waitForLoadState('networkidle');
  await tourGuest.click('button:has-text("Take the guided tour")');
  await tourGuest.waitForLoadState('networkidle');
  await tourGuest.shot('25-guided-tour.png', true);

  await browser.close();
  console.log('Screenshots written to', out);
})();
