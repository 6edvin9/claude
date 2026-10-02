// node secshot.js id mode selectorStart selectorEnd out.png [clickSel]
const { chromium } = require('playwright');
(async () => { const [id, mode, a, b, out, click] = process.argv.slice(2);
 const browser = await chromium.launch({ args: ['--disable-dev-shm-usage'] });
 const ctx = await browser.newContext(mode === 'm' ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' } : { viewport: { width: 1366, height: 900 } });
 await ctx.route('**/*', async r => { const u = new URL(r.request().url()); if (r.request().resourceType() === 'media') return r.abort(); if (!(/globusgates\.online$/.test(u.hostname) || /fonts\.(googleapis|gstatic)\.com$/.test(u.hostname))) return r.abort();
  for (let t = 0; t < 3; t++) { try { await r.fulfill({ response: await r.fetch({ timeout: 60000 }) }); return; } catch (e) {} } try { await r.abort(); } catch (_) {} });
 const page = await ctx.newPage(); await page.goto(`https://globusgates.online/?p=${id}&nc=${Date.now()}`, { waitUntil: 'load', timeout: 180000 });
 await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 80)); } });
 await page.waitForTimeout(1500);
 if (click) { await page.click(click); await page.waitForTimeout(1500); await page.screenshot({ path: out }); await browser.close(); return; }
 const box = await page.evaluate(([a, b]) => { const A = document.querySelector(a).getBoundingClientRect(), B = document.querySelector(b).getBoundingClientRect(); return { y: A.top + scrollY, h: B.bottom - A.top }; }, [a, b]);
 await page.screenshot({ path: out, fullPage: true, clip: { x: 0, y: box.y, width: mode === 'm' ? 390 : 1366, height: Math.min(box.h, 6000) } });
 await browser.close(); })();
