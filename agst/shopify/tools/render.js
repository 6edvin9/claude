// Local preview of the v30 catalog section and product content with liquidjs + mock Shopify data.
const { Liquid, Tag, Hash } = require('liquidjs');
const fs = require('fs');
const path = require('path');
const T = path.resolve(__dirname, '../theme_out');
const csvParse = (s) => { // minimal RFC4180 parser
  const rows = []; let row = [], f = '', q = false;
  for (let i = 0; i < s.length; i++) {
    const c = s[i];
    if (q) { if (c === '"') { if (s[i + 1] === '"') { f += '"'; i++; } else q = false; } else f += c; }
    else if (c === '"') q = true; else if (c === ',') { row.push(f); f = ''; }
    else if (c === '\n') { row.push(f); rows.push(row); row = []; f = ''; } else if (c !== '\r') f += c;
  }
  if (f || row.length) { row.push(f); rows.push(row); }
  const h = rows.shift(); return rows.filter(r => r.length > 1).map(r => Object.fromEntries(h.map((k, i) => [k, r[i] || ''])));
};
const rows = csvParse(fs.readFileSync(path.resolve(__dirname, '../out/shopify-products-all.csv'), 'utf8').replace(/^﻿/, ''));
const products = []; const byHandle = {};
for (const r of rows) {
  let p = byHandle[r.Handle];
  if (!p) {
    p = byHandle[r.Handle] = { handle: r.Handle, title: r.Title, description: r['Body (HTML)'], tags: r.Tags.split(',').map(s => s.trim()).filter(Boolean),
      status: r.Status, url: '/products/' + r.Handle, options: [], variants: [], images: [] };
    products.push(p);
  }
  if (r['Option1 Name']) p.options = [r['Option1 Name']];
  if (r['Variant Price']) p.variants.push({ price: Math.round(parseFloat(r['Variant Price']) * 100), o1: r['Option1 Value'] });
  if (r['Image Src']) p.images.push({ src: r['Image Src'] });
}
const live = products.filter(p => p.status === 'active');
for (const p of live) {
  const prices = p.variants.map(v => v.price);
  p.price = Math.min(...prices); p.price_min = p.price; p.price_varies = new Set(prices).size > 1; p.compare_at_price = 0;
  p.featured_image = p.images[0] ? { src: p.images[0].src } : null;
  p.has_only_default_variant = p.options[0] === 'Title';
  p.options_with_values = p.has_only_default_variant ? [] : [{ name: p.options[0], values: [...new Set(p.variants.map(v => v.o1))] }];
}
// the 7 Shopify pergola pages (kept as they are) -> mock them into the pergola system
for (const h of ['automatic-louver-pergola-kit', 'slat-roof-pergola-kits', 'beam-roof-patio-cover-kit']) {
  live.push({ handle: h, title: h.replace(/-/g, ' '), tags: ['Patio Covers & Pergolas'], url: '/products/' + h, price: 500000, price_min: 500000, price_varies: false, compare_at_price: 0,
    featured_image: null, has_only_default_variant: true, options_with_values: [] });
}

const engine = new Liquid({ root: [path.join(T, 'sections'), path.join(T, 'snippets')], extname: '.liquid', strictFilters: false, strictVariables: false });
engine.registerFilter('asset_url', (s) => '../theme_out/assets/' + s);
engine.registerFilter('stylesheet_tag', (s) => `<link rel="stylesheet" href="${s}">`);
engine.registerFilter('image_url', (img) => (img && (img.src || img)) || '');
engine.registerFilter('image_tag', (u, ...args) => { const alt = (args.find(a => Array.isArray(a) && a[0] === 'alt') || [])[1] || ''; return `<img src="${u}" alt="${alt}" loading="lazy">`; });
engine.registerFilter('money', (c) => '$' + (c / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
engine.registerFilter('handleize', (s) => String(s).toLowerCase().replace(/['’]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
engine.registerFilter('placeholder_svg_tag', () => '<svg viewBox="0 0 10 10"></svg>');
engine.registerFilter('t', (s) => s);
class Schema extends Tag { constructor(t, r, l) { super(t, r, l); this.tpls = []; for (let tok; (tok = r.shift());) { if (tok.name === 'endschema') return; } } * render() { } }
engine.registerTag('schema', Schema);
class Paginate extends Tag {
  constructor(t, r, l) { super(t, r, l); this.tpls = []; let tok; while ((tok = r.shift())) { if (tok.name === 'endpaginate') return; this.tpls.push(l.parser.parseToken(tok, r)); } }
  * render(ctx, emitter) { ctx.push({ paginate: { pages: 1 } }); yield this.liquid.renderer.renderTemplates(this.tpls, ctx, emitter); ctx.pop(); }
}
engine.registerTag('paginate', Paginate);

const sys = { gates: 'Gates', fences: 'Fences', pergola: 'Patio Covers & Pergolas', 'wall-cladding': 'Cladding' };
const coll = (handle, tag) => {
  let ps = handle === 'all' ? live : live.filter(p => p.tags.includes(sys[handle]));
  if (tag) ps = ps.filter(p => p.tags.some(t => engine.filters.handleize ? false : false) || p.tags.map(t => t.toLowerCase().replace(/['’]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')).includes(tag));
  return { handle, title: sys[handle] || 'All', url: '/collections/' + handle, products: ps, products_count: ps.length, description: '', image: null };
};
const base = { routes: { root_url: '/', all_products_collection_url: '/collections/all', collections_url: '/collections', search_url: '/search' }, section: { settings: { shop_heading: 'Everything on our current price list.', shop_lead: 'Choose your system, then the part you need. Prices shown are base prices — bundle and contractor discounts are available.' } }, search: { terms: '' }, collections: {} };
const A='../theme_out/assets/';const page = (body, tpl='collection') => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="${A}base.css"><link rel="stylesheet" href="${A}aluglobus-store.css"><link rel="stylesheet" href="${A}ag-grid-fix-v12.css">${tpl==='product'?`<link rel="stylesheet" href="${A}ag-product-page-v12.css"><link rel="stylesheet" href="${A}ag-product-v14.css">`:''}<style>body{margin:0;font-family:Assistant,Arial,sans-serif}</style></head><body class="template-${tpl}"><main id="MainContent">${body}</main></body></html>`;
(async () => {
  const out = path.resolve(__dirname, '../preview'); fs.mkdirSync(out, { recursive: true });
  const tpl = fs.readFileSync(path.join(T, 'sections', 'ag-catalog.liquid'), 'utf8');
  const ctx = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../ctx.json'), 'utf8'));
  const grpColl = {};
  for (const sy of ctx.systems) for (const g of sy.groups) {
    const ps = live.filter(p => p.tags.includes(g.tag));
    grpColl[g.handle] = { id: 1, handle: g.handle, title: g.wp_name, url: '/collections/' + g.handle, products: ps, products_count: ps.length, description: '' };
  }
  base.collections = grpColl;
  for (const gh of ['gate-frame-components-replacement-parts', 'fence-rails-structural-components-replacement-parts']) {
    const html = await engine.parseAndRender(tpl, { ...base, collection: grpColl[gh], current_tags: [] });
    fs.writeFileSync(path.join(out, 'gc-' + gh.slice(0, 20) + '.html'), page(html));
  }
  const jobs = [['shop', 'all', null], ['gates', 'gates', null], ['fences', 'fences', null], ['pergola', 'pergola', null], ['cladding', 'wall-cladding', null], ['group-gate-frame-kits', 'gates', 'gate-frame-kits']];
  for (const [name, h, tag] of jobs) {
    const c = coll(h, tag);
    const html = await engine.parseAndRender(tpl, { ...base, collection: c, current_tags: tag ? [tag] : [] });
    fs.writeFileSync(path.join(out, name + '.html'), page(html));
  }
  // product pages: buy head + description
  const hdr = fs.readFileSync(path.join(T, 'snippets', 'ag-product-header-v30.liquid'), 'utf8');
  const sec = fs.readFileSync(path.join(T, 'sections', 'ag-product-catalog.liquid'), 'utf8');
  for (const h of ['aluminum-gate-kit-alu40-universal-diy-4-x-6-black', 'box-alu-40-4x6-ft-diy-pedestrian-gate-kit-single-swing-gap-3-8', 'aluminum-wall-cladding-clad120-black', 'hd-double-sided-latch']) {
    const p = byHandle[h];
    const head = await engine.parseAndRender(hdr, { product: p });
    const body = await engine.parseAndRender(sec, { product: p, section: { id: 'x' } });
    fs.writeFileSync(path.join(out, 'product-' + h.slice(0, 30) + '.html'), page(`<link rel="stylesheet" href="../theme_out/assets/ag-desc-v30.css"><div id="ProductInfo-x" style="background:#0b0c0e;padding:30px;max-width:560px">${head}</div>${body}`,'product'));
  }
  console.log('ok');
})().catch(e => { console.error(e); process.exit(1); });
