/* Aluglobus staging: export a design-QA bundle (read-only, changes nothing).
   Paste into the DevTools console of a logged-in tab on https://globusgates.online/wp-admin/
   Downloads agst-qa-bundle.json.gz -> upload that file to Claude. */
(async () => {
  if (location.host !== 'globusgates.online') { console.error('STOP: run this on globusgates.online only'); return; }
  // one product per type + the design template preview
  const PAGES = {
    'pergola-slat-58015': 58015, 'pergola-57987': 57987, 'pergola-58025': 58025,
    'diy-gate-38112': 38112, 'sliding-gate-26860': 26860, 'fence-63276': 63276,
    'post-38143': 38143, 'base-plate-26597': 26597, 'cladding-32975': 32975,
    'new-seo-63717': 63717, 'box-pergola-63803': 63803,
    'template-63896': '/?post_type=product&p=63896&preview=true' };
  const nonce = (await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })).text()).trim();
  const H = { 'X-WP-Nonce': nonce };
  const B = { when: new Date().toISOString(), pages: {}, css: {}, images: {}, fonts: {}, files: {}, errors: [] };
  const abs = (u, base) => { try { return new URL(u, base).href; } catch (e) { return null; } };
  const imgUrls = new Set(), cssUrls = new Set();

  for (const [name, ref] of Object.entries(PAGES)) {
    try {
      let url = ref;
      if (typeof ref === 'number') url = (await (await fetch(`/wp-json/wc/v3/products/${ref}`, { credentials: 'same-origin', headers: H })).json()).permalink;
      const html = await (await fetch(url, { credentials: 'same-origin' })).text();
      B.pages[name] = { url: abs(url, location.href), html };
      const doc = new DOMParser().parseFromString(html, 'text/html');
      doc.querySelectorAll('link[rel~="stylesheet"][href]').forEach(l => cssUrls.add(abs(l.getAttribute('href'), url)));
      doc.querySelectorAll('img').forEach(i => ['src', 'data-src', 'data-lazy-src'].forEach(a => { const v = i.getAttribute(a); if (v && !v.startsWith('data:')) imgUrls.add(abs(v, url)); }));
      (html.match(/url\(\s*['"]?([^'")]+\.(?:jpe?g|png|webp|gif|svg))/gi) || []).forEach(m => imgUrls.add(abs(m.replace(/^url\(\s*['"]?/i, ''), url)));
      console.log('page', name, html.length);
    } catch (e) { B.errors.push(['page', name, String(e)]); }
  }
  for (const u of cssUrls) {
    try {
      const t = await (await fetch(u)).text(); B.css[u] = t;
      (t.match(/url\(\s*['"]?([^'")]+)/gi) || []).forEach(m => {
        const v = abs(m.replace(/^url\(\s*['"]?/i, ''), u); if (!v || v.startsWith('data:')) return;
        if (/\.(woff2?|ttf|otf)(\?|$)/i.test(v)) B.fonts[v] = null; else if (/\.(jpe?g|png|webp|gif|svg)(\?|$)/i.test(v)) imgUrls.add(v);
      });
    } catch (e) { B.errors.push(['css', u, String(e)]); }
  }
  console.log('css files', Object.keys(B.css).length, 'images', imgUrls.size);
  const toData = blob => new Promise(r => { const fr = new FileReader(); fr.onload = () => r(fr.result); fr.readAsDataURL(blob); });
  for (const u of Object.keys(B.fonts)) {
    if (!/\.woff2(\?|$)/i.test(u)) { delete B.fonts[u]; continue; }
    try { B.fonts[u] = await toData(await (await fetch(u)).blob()); } catch (e) { delete B.fonts[u]; }
  }
  let n = 0;
  for (const u of imgUrls) {
    try {
      const blob = await (await fetch(u)).blob();
      if (/svg/.test(blob.type)) { B.images[u] = await toData(blob); continue; }
      const bmp = await createImageBitmap(blob);
      const s = Math.min(1, 800 / bmp.width);
      const c = document.createElement('canvas'); c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      B.images[u] = c.toDataURL('image/jpeg', 0.6) + '#' + bmp.width + 'x' + bmp.height;
    } catch (e) { B.errors.push(['img', u, String(e)]); }
    if (++n % 20 === 0) console.log('images', n, '/', imgUrls.size);
  }
  // current plugin source
  const P = 'aluglobus-staging-catalog/';
  for (const f of ['storefront.css', 'storefront.php', 'single-product.php', 'aluglobus-staging-catalog.php']) {
    try {
      const h = await (await fetch(`/wp-admin/plugin-editor.php?plugin=${encodeURIComponent(P + 'aluglobus-staging-catalog.php')}&file=${encodeURIComponent(P + f)}`, { credentials: 'same-origin' })).text();
      const ta = new DOMParser().parseFromString(h, 'text/html').querySelector('#newcontent');
      if (ta) B.files[f] = ta.value; else B.errors.push(['file', f, 'no editor textarea']);
    } catch (e) { B.errors.push(['file', f, String(e)]); }
  }
  console.log('files', Object.keys(B.files), 'errors', B.errors.length);
  const gz = await new Response(new Blob([JSON.stringify(B)]).stream().pipeThrough(new CompressionStream('gzip'))).blob();
  const a = document.createElement('a'); a.href = URL.createObjectURL(gz); a.download = 'agst-qa-bundle.json.gz'; document.body.appendChild(a); a.click();
  console.log('DONE. Downloaded agst-qa-bundle.json.gz (' + (gz.size / 1048576).toFixed(1) + ' MB). Upload it to Claude.');
})();
