// node shot.js [--mobile] [--desktop] url-or-id ...  -> shots/<id>-<d|m>.png + metrics json
const { chromium } = require('playwright'); const fs = require('fs');
const args = process.argv.slice(2); const modes = [];
if (args.includes('--desktop') || !args.includes('--mobile')) modes.push('d');
if (args.includes('--mobile') || !args.includes('--desktop')) modes.push('m');
const ids = args.filter(a => !a.startsWith('--'));
const fullOnly = args.includes('--nofull') ? false : true;
(async () => {
  const results = {};
  for (const mode of modes) for (const id of ids) {
    const browser = await chromium.launch({ args: ['--disable-dev-shm-usage'] });
    const ctx = await browser.newContext(mode === 'm'
      ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' }
      : { viewport: { width: 1366, height: 900 } });
    await ctx.route('**/*', async r => { const u = new URL(r.request().url()); if (r.request().resourceType() === 'media' || /\.(mp4|mov|webm|m4v)(\?|$)/i.test(u.pathname)) return r.abort();
      if (!(/globusgates\.online$/.test(u.hostname) || /fonts\.(googleapis|gstatic)\.com$/.test(u.hostname))) return r.abort();
      for (let t = 0; t < 3; t++) { try { const resp = await r.fetch({ timeout: 60000 }); await r.fulfill({ response: resp }); return; } catch (e) { if (/closed|fulfill/i.test(e.message)) return; } } try { await r.abort(); } catch (_) {} });
    const page = await ctx.newPage();
    {
      const url = /^\d+$/.test(id) ? `https://globusgates.online/?p=${id}` : id;
      const key = /^\d+$/.test(id) ? id : id.replace(/\W+/g, '_').slice(-60);
      try {
        for (let t = 0; t < 3; t++) {
          await page.goto(url + (url.includes('?') ? '&' : '?') + 'nc=' + Date.now(), { waitUntil: 'load', timeout: 180000 });
          const ok = await page.evaluate(() => [...document.styleSheets].some(s => /storefront\.css/.test(s.href || '') && (() => { try { return s.cssRules.length > 50; } catch (e) { return false; } })()));
          if (ok) break; console.log(key, mode, 'css missing, retry');
        }
        await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
        await page.waitForTimeout(1500);
        const m = await page.evaluate(() => {
          const vis = e => { const r = e.getBoundingClientRect(); const cs = getComputedStyle(e); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
          const nested = [...document.querySelectorAll('.agx-el .e-con.agx-s')].filter(e => e.parentElement.closest('.agx-s')).length;
          const overflow = [...document.querySelectorAll('body *')].filter(e => vis(e) && e.getBoundingClientRect().right > innerWidth + 1 && !e.closest('.swiper,.slick-slider,.flickity,.owl-carousel,[class*=carousel],.agx-gallery-main,.agx-thumbs')).slice(0, 6).map(e => e.tagName + '.' + [...e.classList].slice(0, 3).join('.'));
          const emptyHref = [...document.querySelectorAll('.agx a.elementor-button, .agx a.agx-btn, .agx-el a')].filter(a => vis(a) && !(a.getAttribute('href') || '').trim()).map(a => a.textContent.trim().slice(0, 40));
          const txt = document.querySelector('.agx') ? document.querySelector('.agx').innerText : document.body.innerText;
          const raw = [...new Set(txt.match(/\[[a-z][a-z0-9_-]+[^\]\n]{0,40}\]/gi) || [])];
          // low contrast text check
          const lum = c => { const m = c.match(/[\d.]+/g); if (!m) return null; const [r, g, b] = m.slice(0, 3).map(v => { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }); return { L: .2126 * r + .7152 * g + .0722 * b, a: m[3] === undefined ? 1 : +m[3] }; };
          const bgOf = e => { while (e) { const c = getComputedStyle(e).backgroundColor; const l = lum(c); if (l && l.a > .5) return l.L; e = e.parentElement; } return 1; };
          const low = [];
          for (const e of document.querySelectorAll('.agx p, .agx li, .agx h1, .agx h2, .agx h3, .agx h4, .agx span, .agx a, .agx td, .agx th, .agx label, .agx button')) {
            if (!vis(e) || !e.childNodes.length || ![...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) continue;
            const f = lum(getComputedStyle(e).color); if (!f) continue; const b = bgOf(e);
            const ratio = (Math.max(f.L, b) + .05) / (Math.min(f.L, b) + .05);
            if (ratio < 3) low.push(e.tagName + ' ' + ratio.toFixed(2) + ' "' + e.textContent.trim().slice(0, 40) + '"');
          }
          const broken = [...document.querySelectorAll('.agx img')].filter(i => vis(i) && i.complete && i.naturalWidth === 0).map(i => (i.currentSrc || i.src).split('/').pop()).slice(0, 5);
          const pkg = !!document.getElementById('agx-package'); const navPkg = !!document.querySelector('.agx-nav a[href="#agx-package"]');
          return { broken, pkgOk: pkg === navPkg, title: document.title, h1: document.querySelectorAll('h1').length, sections: document.querySelectorAll('.agx-el .agx-s').length, nested, overflow, emptyHref, raw, low: low.slice(0, 12), lowCount: low.length, height: document.body.scrollHeight, scrollW: document.documentElement.scrollWidth };
        });
        results[key + '-' + mode] = m;
        await page.screenshot({ path: `shots/${key}-${mode}.png`, fullPage: true, timeout: 120000 });
        console.log(key, mode, JSON.stringify(m));
      } catch (e) { console.log(key, mode, 'ERR', e.message.slice(0, 200)); results[key + '-' + mode] = { err: e.message.slice(0, 200) }; }
    }
    await browser.close().catch(() => {});
  }
  fs.writeFileSync('shots/metrics-' + Date.now() + '.json', JSON.stringify(results, null, 1));
})();
