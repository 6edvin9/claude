// node probe.js <id> <m|d> <file-with-js-expression-fn>
const { chromium } = require('playwright'); const fs = require('fs');
(async () => { const [id, mode, f] = process.argv.slice(2);
 const browser = await chromium.launch({ args: ['--disable-dev-shm-usage'] });
 const ctx = await browser.newContext(mode === 'm' ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' } : { viewport: { width: 1366, height: 900 } });
 await ctx.route('**/*', async r => { const u = new URL(r.request().url()); if (r.request().resourceType() === 'media' || /\.(mp4|mov|webm|m4v)(\?|$)/i.test(u.pathname)) return r.abort(); if (!(/globusgates\.online$/.test(u.hostname) || /fonts\.(googleapis|gstatic)\.com$/.test(u.hostname))) return r.abort();
  try { await r.fulfill({ response: await r.fetch({ timeout: 90000 }) }); } catch (e) { try { await r.abort(); } catch (_) {} } });
 const page = await ctx.newPage(); const url = /^\d+$/.test(id) ? `https://globusgates.online/?p=${id}` : id;
 await page.goto(url + (url.includes('?') ? '&' : '?') + 'nc=' + Date.now(), { waitUntil: 'load', timeout: 180000 }); await page.waitForTimeout(1500);
 console.log(JSON.stringify(await page.evaluate(eval(fs.readFileSync(f, 'utf8'))), null, 1)); await browser.close(); })();
