const { chromium } = require('playwright');
const fs = require('fs');
const STATE = __dirname + '/state.json';
const BASE = 'https://globusgates.online';
async function open({ mobile = false, login = false } = {}) {
  const browser = await chromium.launch();
  const opts = mobile
    ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true,
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' }
    : { viewport: { width: 1366, height: 900 } };
  if (fs.existsSync(STATE) && !login) opts.storageState = STATE;
  const ctx = await browser.newContext(opts);
  // keep third-party noise out (chat widgets, analytics) so pages settle
  await ctx.route('**/*', async r => { const u = new URL(r.request().url()); if (r.request().resourceType() === 'media' || /\.(mp4|mov|webm|m4v)(\?|$)/i.test(u.pathname)) return r.abort(); if (!(/globusgates\.online$/.test(u.hostname) || /fonts\.(googleapis|gstatic)\.com$/.test(u.hostname))) return r.abort();
    // Chromium does not trust the egress proxy CA; fetch through Playwright's Node side (NODE_EXTRA_CA_CERTS) instead
    try { const resp = await r.fetch({ timeout: 90000 }); await r.fulfill({ response: resp }); }
    catch (e) { try { await r.abort(); } catch (_) {} } });
  const page = await ctx.newPage();
  return { browser, ctx, page };
}
async function login() {
  const { browser, ctx, page } = await open({ login: true });
  await ctx.request.get(BASE + '/wp-login.php'); // sets test cookie
  await ctx.addCookies([{ name: 'wordpress_test_cookie', value: 'WP%20Cookie%20check', domain: 'globusgates.online', path: '/', secure: true }]);
  await ctx.request.post(BASE + '/wp-login.php', { form: { log: process.env.WP_USER, pwd: process.env.WP_PASS, rememberme: 'forever', 'wp-submit': 'Log In', redirect_to: BASE + '/wp-admin/', testcookie: '1' }, maxRedirects: 0 });
  await page.goto(BASE + '/wp-admin/', { waitUntil: 'domcontentloaded' });
  const ok = /wp-admin/.test(page.url());
  if (ok) await ctx.storageState({ path: STATE });
  await browser.close();
  return ok;
}
// run fn inside a logged-in wp-admin page
async function admin(fn, arg) {
  const { browser, page } = await open();
  await page.goto(BASE + '/wp-admin/', { waitUntil: 'domcontentloaded' });
  if (!/wp-admin/.test(page.url()) || /wp-login/.test(page.url())) { await browser.close(); throw new Error('not logged in'); }
  page.setDefaultTimeout(600000);
  const r = await page.evaluate(fn, arg);
  await browser.close();
  return r;
}
module.exports = { open, login, admin, BASE };
