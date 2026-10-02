/* Aluglobus staging: Task 3 (audit all published products) + Task 4 (rebuild products still at _agst_el 1.0).
   Paste into the DevTools console of a logged-in tab on https://globusgates.online/wp-admin/
   Removes nothing. Only rebuilds _agst_el=1.0 products with agst_el_build force=1 (undo: agst_el_restore). */
(async () => {
  if (location.host !== 'globusgates.online') { console.error('STOP: run this on globusgates.online only'); return; }
  const SKIP = [58015, 38472, 63896];            // owner rule + design template draft
  const nonce = (await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })).text()).trim();
  if (!/^[0-9a-f]{10}$/.test(nonce)) { console.error('No REST nonce, are you logged in?', nonce); return; }
  const H = { 'X-WP-Nonce': nonce };
  const meta = (p, k) => { const m = (p.meta_data || []).find(x => x.key === k); return m ? m.value : null; };

  // all published products
  let prods = [];
  for (let page = 1; ; page++) {
    const r = await fetch(`/wp-json/wc/v3/products?status=publish&per_page=100&page=${page}&context=edit`, { credentials: 'same-origin', headers: H });
    const a = await r.json(); if (!Array.isArray(a) || !a.length) break;
    prods.push(...a.map(p => ({ id: p.id, name: p.name, url: p.permalink, el: meta(p, '_agst_el'), spec: !!meta(p, '_agst_page_spec'), seo: !!meta(p, '_agst_seo_html') })));
    if (a.length < 100) break;
  }
  console.log('published products:', prods.length);

  async function audit(p) {
    try {
      const r = await fetch(p.url + (p.url.includes('?') ? '&' : '?') + 'nocache=' + Date.now(), { credentials: 'same-origin' });
      const html = await r.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      doc.querySelectorAll('script,style,noscript,template,textarea').forEach(n => n.remove());
      const txt = doc.body ? doc.body.textContent : '';
      return { id: p.id, name: p.name, el: p.el, spec: p.spec, http: r.status,
        h1: doc.querySelectorAll('h1').length,
        agxS: doc.querySelectorAll('.agx-s').length,
        phpErr: /<b>(Fatal error|Warning|Notice|Deprecated|Parse error)<\/b>/.test(html),
        rawShortcode: [...new Set(txt.match(/\[(?:\/)?[a-z][a-z0-9_-]*(?:\s[^\]\n]{0,80})?\]/gi) || [])].slice(0, 5) };
    } catch (e) { return { id: p.id, name: p.name, http: 'ERR ' + e }; }
  }
  async function auditAll(list) {
    const out = [];
    for (let k = 0; k < list.length; k += 4) {
      out.push(...await Promise.all(list.slice(k, k + 4).map(audit)));
      console.log('audited', out.length, '/', list.length);
    }
    return out;
  }
  const isBad = a => a.http !== 200 || a.h1 !== 1 || !a.agxS || a.phpErr || (a.rawShortcode || []).length;

  // Task 3
  const audit1 = await auditAll(prods);
  const bad1 = audit1.filter(isBad);
  console.log('TASK 3: ok', audit1.length - bad1.length, 'problems', bad1.length); console.table(bad1);

  // Task 4
  const v1 = prods.filter(p => String(p.el) === '1.0' && !SKIP.includes(p.id)).map(p => p.id);
  console.log('TASK 4: products at _agst_el 1.0:', v1.length, v1.join(','));
  const build = [];
  for (let k = 0; k < v1.length; k += 8) {
    const batch = v1.slice(k, k + 8);
    const fd = new FormData();
    fd.append('action', 'agst_el_build'); fd.append('nonce', nonce); fd.append('ids', batch.join(',')); fd.append('force', '1');
    const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: fd });
    const t = await r.text(); build.push({ batch, status: r.status, resp: t.slice(0, 600) });
    console.log('build', batch.join(','), r.status, t.slice(0, 200));
  }
  await fetch('/wp-admin/?_wpo_purge=d2942c5514', { credentials: 'same-origin' });
  console.log('cache purged');
  // re-read _agst_el and re-audit the rebuilt ones
  const rebuilt = [];
  for (const id of v1) {
    const p = await (await fetch(`/wp-json/wc/v3/products/${id}?context=edit`, { credentials: 'same-origin', headers: H })).json();
    rebuilt.push({ id, name: p.name, url: p.permalink, el: meta(p, '_agst_el'), spec: !!meta(p, '_agst_page_spec') });
  }
  const audit2 = await auditAll(rebuilt);
  const bad2 = audit2.filter(a => isBad(a) || String(a.el) !== '2.0');
  console.log('TASK 4 audit: ok', audit2.length - bad2.length, 'problems', bad2.length); console.table(bad2);

  const out = JSON.stringify({ when: new Date().toISOString(), published: prods.length,
    task3: { problems: bad1, sectionsPerProduct: audit1.map(a => [a.id, a.agxS, a.el]) },
    task4: { ids: v1, build, problems: bad2 } });
  window.__agstResult = out;
  console.log('DONE. Report size', out.length, 'chars. Copying to clipboard...');
  try { await navigator.clipboard.writeText(out); console.log('Copied. Paste it back to Claude.'); }
  catch (e) { console.log('Clipboard blocked: run  copy(window.__agstResult)  then paste it back to Claude.'); }
})();
