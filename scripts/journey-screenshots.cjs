// Captures the screenshots used in docs/journey/README.md.
// Usage: php artisan serve --port=8123 &  then
//   node scripts/journey-screenshots.cjs http://127.0.0.1:8123 <email> <password>
// Needs Playwright (available in the dev container). Finding and review
// references are looked up by visible text, so re-runs stay stable.
const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const [base = 'http://127.0.0.1:8123', email = 'p.mokoena@rsl.test', password = 'demo-pass'] = process.argv.slice(2);
  const out = path.join(__dirname, '..', 'docs', 'journey', 'img');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 860 }, deviceScaleFactor: 1 });
  const shot = (name, full = false) => page.screenshot({ path: path.join(out, name), fullPage: full });

  await page.goto(base + '/login');
  await shot('00-sign-in.png');
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('button[type=submit]');
  await page.waitForURL('**/home');
  await shot('01-home-records-received.png', true);

  await page.goto(base + '/records/CIT-00036');
  await shot('02-record-as-received.png', true);

  await page.goto(base + '/findings');
  await shot('03-findings-by-company.png');

  const open = async (company, pattern) => {
    await page.goto(base + '/findings');
    const section = page.locator('section', { hasText: company }).first();
    await section.locator('a', { hasText: pattern }).first().click();
    await page.waitForLoadState('networkidle');
  };
  await open('Thaba Trading', 'P03');
  await shot('04-finding-five-parts.png', true);

  await page.goto(base + '/findings');
  await page.locator('section', { hasText: 'Tsenola Distributors' }).first().locator('a', { hasText: 'all 14 checks' }).click();
  await page.waitForLoadState('networkidle');
  await shot('05-decoy-explains-itself.png', true);

  await open('Qiloane Hardware', 'P12');
  await shot('06-memory-outside-conditions.png', true);

  await browser.close();
})();
