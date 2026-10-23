const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  page.on('pageerror', err => console.log('PAGE ERROR:', err.message));
  await page.goto('http://localhost/akademus-sis/public/login');
  await page.fill('input[name="email"]', 'chairperson2@test.com');
  await page.fill('input[name="password"]', 'Chairperson123!');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(600);
  await page.goto('http://localhost/akademus-sis/public/chairperson/subject-ownership');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(600);
  await page.evaluate(() => document.querySelector('.ac-content').scrollTop = 350);
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'C:/Users/THEODO~1.ABE/AppData/Local/Temp/claude/c--xampp-htdocs-akademus-sis/2cba11d0-8f40-4d3a-a649-e3390f448c08/images/so_filter_v2.png' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
  console.log('Overflow:', overflow);

  // check it still opens
  await page.click('#soCourseDept').catch(()=>{});
  await page.locator('.so-filter .cs-select__trigger').first().click();
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'C:/Users/THEODO~1.ABE/AppData/Local/Temp/claude/c--xampp-htdocs-akademus-sis/2cba11d0-8f40-4d3a-a649-e3390f448c08/images/so_filter_v2_open.png' });
  await browser.close();
})();
