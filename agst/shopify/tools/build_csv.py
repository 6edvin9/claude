"""Build the Shopify product import CSV from the live WordPress catalog (release bundle) + converted page content."""
import csv, json, html, re, os, collections
from structure import load, structure, KEEP_SHOPIFY, handleize

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'out')
os.makedirs(OUT, exist_ok=True)
STAGING = 'https://globusgates.online/wp-content/uploads/'
VENDOR = 'Aluglobus Aluminum Systems'

b = load()
systems, by_slug = structure(b)
conv = json.load(open(os.path.join(HERE, 'converted.json')))
media = {m['sid']: m for m in b['media']}
terms = {(tax, t['slug']): t for tax, lst in b['terms'].items() for t in lst}

rows = list(csv.DictReader(open(os.path.join(HERE, 'products_export.csv'), encoding='utf-8-sig')))
COLS = list(rows[0].keys())
shop = collections.OrderedDict()
for r in rows:
    shop.setdefault(r['Handle'], []).append(r)

pub = [p for p in b['products'] if p['post']['post_status'] == 'publish']
pub_by_slug = {p['slug']: p for p in pub}

group_of = {}  # wp cat slug -> (system, group)
for s in systems:
    for g in s['groups']:
        group_of[g['wp']] = (s, g)
sys_by_wp = {s['wp']: s for s in systems}

CATEGORY = {
    'gate-frame-kits': 'Hardware > Fencing & Barriers > Gates',
    'complete-diy-gate-kits': 'Hardware > Fencing & Barriers > Gates',
    'complete-hd-gate-kits': 'Hardware > Fencing & Barriers > Gates',
    'sliding-gate-systems': 'Hardware > Fencing & Barriers > Gates > Driveway Gates',
    'gate-slat-infill-packs': 'Hardware > Fencing & Barriers > Fence Pickets',
    'fence-slat-infill-packs': 'Hardware > Fencing & Barriers > Fence Pickets',
    'gate-post-kits-post-options': 'Hardware > Fencing & Barriers > Fence Posts & Rails',
    'aluminum-fences-diy-fence-kit-post': 'Hardware > Fencing & Barriers > Fence Posts & Rails',
    'fence-c-channel-systems-components': 'Hardware > Fencing & Barriers > Fence Posts & Rails',
    'fence-rails-structural-components-parts': 'Hardware > Fencing & Barriers > Fence & Gate Accessories',
    'fence-spacers-infill-accessories': 'Hardware > Fencing & Barriers > Fence & Gate Accessories',
    'fence-base-plates-post-mounting': 'Hardware > Fencing & Barriers > Fence & Gate Accessories',
    'gate-frame-components-replacement-parts': 'Hardware > Fencing & Barriers > Fence & Gate Accessories',
    'complete-fence-kits': 'Hardware > Fencing & Barriers > Fence Panels > Privacy Panels',
    'wall-cladding-t-and-g': 'Hardware > Building Materials > Siding',
    'pergola': 'Home & Garden > Lawn & Garden > Outdoor Living > Outdoor Structures > Garden Arches, Trellises, Arbors & Pergolas > Pergolas',
}


def money(v):
    return '%.2f' % float(v) if str(v).strip() not in ('', 'None') else ''


def img_url(sid):
    m = media.get(int(sid or 0))
    return STAGING + m['file'] if m else ''


def placement(p):
    """Systems and groups for a product (price-list order), plus primary system."""
    cats = p['wc']['cats']
    syss, grps = [], []
    for c in cats:
        if c in group_of:
            s, g = group_of[c]
            if g not in grps:
                grps.append(g)
            if s not in syss:
                syss.append(s)
    for c in cats:
        if c in sys_by_wp and sys_by_wp[c] not in syss:
            syss.append(sys_by_wp[c])
    syss.sort(key=lambda s: [x['wp'] for x in systems].index(s['wp']))
    prim = p['meta'].get('_yoast_wpseo_primary_product_cat')
    prim = prim.get('term_slug') if isinstance(prim, dict) else ''
    primary = None
    if prim in group_of:
        primary = group_of[prim][0]
    elif prim in sys_by_wp:
        primary = sys_by_wp[prim]
    if primary not in syss:
        primary = syss[0] if syss else None
    return syss, grps, primary


def seo_title(t):
    t = re.sub(r'\s*[-–|]\s*Aluglobus Aluminum Systems\s*$', '', t or '').strip()
    return t[:70] if len(t) > 8 else ''


def seo_desc(p, lead):
    d = p['meta'].get('_yoast_wpseo_metadesc') or ''
    if isinstance(d, str) and d and '%%' not in d:
        return d.strip()[:320]
    return (lead or '')[:300]


def option_values(p):
    """[(option name, [values...])] and per-variation value tuples."""
    attrs = [a for a in p['wc']['attributes'] if a.get('variation')]
    names, value_names = [], []
    for a in attrs:
        if a['name'].startswith('pa_'):
            label = 'Color' if 'color' in a['name'] else a['name'][3:].replace('-', ' ').title()
        else:
            label = a['name'] if a['name'][0].isupper() else a['name'].title()
        names.append(label)
    return attrs, names


def value_label(attr, raw):
    if attr['name'].startswith('pa_'):
        t = terms.get((attr['name'], raw))
        return html.unescape(t['name']) if t else raw.replace('-', ' ').title()
    for o in attr['options']:
        if o.lower() == str(raw).lower() or handleize(o) == handleize(str(raw)):
            return o
    return raw


def blank_row():
    return {c: '' for c in COLS}


def product_rows(p):
    slug = p['slug']
    c = conv[str(p['sid'])]
    syss, grps, primary = placement(p)
    old = shop.get(slug, [None])[0]
    w = p['wc']
    soon = p['meta'].get('_agst_coming_soon') or ''
    tags = [s['tag'] for s in syss] + [g['tag'] for g in grps]
    tags.append('_order:%04d' % int(p['post']['menu_order'] or 0))
    if soon:
        tags.append('Coming soon: ' + ('all colors' if soon == 'all' else soon))
    status = 'active'
    notes = []
    head = blank_row()
    if old:  # keep Shopify-only fields as they are
        for k in COLS:
            if '(product.metafields' in k or k.startswith('Google Shopping') or k == 'Gift Card':
                head[k] = old[k]
    head.update({
        'Handle': slug,
        'Title': html.unescape(c['title'] or p['post']['post_title']),
        'Body (HTML)': c['body'],
        'Vendor': VENDOR,
        'Product Category': (old['Product Category'] if old and old['Product Category'] else '') or
                            next((CATEGORY[x] for x in [g['wp'] for g in grps] + w['cats'] if x in CATEGORY), 'Hardware > Fencing & Barriers > Fence & Gate Accessories'),
        'Type': primary['title'] if primary else '',
        'Tags': ', '.join(dict.fromkeys(tags)),
        'Published': 'true',
        'Gift Card': 'false',
        'SEO Title': seo_title(c['seo_title']),
        'SEO Description': seo_desc(p, c['lead']),
        'Status': status,
    })
    # images: page gallery first, then any variation images not already in it
    gallery = [(u.replace('https://globusgates.online/wp-content/uploads/', STAGING), alt) for u, alt in c['gallery']]
    variants = []
    if p['type'] == 'variable':
        attrs, names = option_values(p)
        for v in sorted(p['variations'], key=lambda v: v['menu_order']):
            if v['status'] != 'publish':
                continue
            vals = []
            for a in attrs:
                key = a['name'] if a['name'].startswith('pa_') else a['name'].lower()
                raw = v['wc']['attributes'].get(key) or v['wc']['attributes'].get(a['name'], '')
                vals.append(value_label(a, raw))
            vimg = img_url(v['wc'].get('image'))
            if vimg and vimg not in [g[0] for g in gallery]:
                gallery.append((vimg, '%s - %s' % (head['Title'], ' / '.join(vals))))
            variants.append({'names': names, 'vals': vals, 'sku': v['wc']['sku'] or '', 'regular': v['wc']['regular_price'],
                             'sale': v['wc']['sale_price'], 'image': vimg})
        # default option first (as on the website)
        d = w.get('default_attributes') or {}
        if d:
            want = [value_label(a, d.get(a['name'] if a['name'].startswith('pa_') else a['name'].lower(), '')) for a in attrs]
            variants.sort(key=lambda x: 0 if x['vals'] == want else 1)
    else:
        variants.append({'names': ['Title'], 'vals': ['Default Title'], 'sku': w['sku'] or '', 'regular': w['regular_price'], 'sale': w['sale_price'], 'image': ''})
    if not any(money(v['regular']) or money(v['sale']) for v in variants):
        status = 'draft'
        head['Status'] = 'draft'
        notes.append('no price on the website (shown as "Price on request") - imported as draft')
    out = []
    for i, v in enumerate(variants):
        r = head if i == 0 else blank_row()
        r['Handle'] = slug
        for n in range(3):
            if n < len(v['names']):
                if i == 0:
                    r['Option%d Name' % (n + 1)] = v['names'][n]
                r['Option%d Value' % (n + 1)] = v['vals'][n]
        regular, sale = money(v['regular']), money(v['sale'])
        price = sale or regular or '0.00'
        r.update({
            'Variant SKU': v['sku'] or (old['Variant SKU'] if old and i == 0 and len(variants) == 1 else '') or 'AG-%d%s' % (p['sid'], '-%d' % (i + 1) if len(variants) > 1 else ''),
            'Variant Grams': '0',
            'Variant Inventory Tracker': '',
            'Variant Inventory Qty': '',
            'Variant Inventory Policy': 'continue',
            'Variant Fulfillment Service': 'manual',
            'Variant Price': price,
            'Variant Compare At Price': regular if sale and regular and float(regular) > float(sale) else '',
            'Variant Requires Shipping': 'true',
            'Variant Taxable': 'true',
            'Variant Image': v['image'],
            'Variant Weight Unit': 'lb',
            'Included / United States': 'true',
        })
        out.append(r)
    for n, (u, alt) in enumerate(gallery):
        if n < len(out):
            r = out[n]
        else:
            r = blank_row(); r['Handle'] = slug; out.append(r)
        r['Image Src'] = u
        r['Image Position'] = str(n + 1)
        r['Image Alt Text'] = alt
    return out, notes, syss, grps, old is not None


def draft_rows(h):
    out = []
    for i, r in enumerate(shop[h]):
        r = dict(r)
        if i == 0:
            r['Status'] = 'draft'
            r['Published'] = 'false'
        out.append(r)
    return out


report = []
all_rows, test_rows = [], []
TEST = {'aluminum-gate-kit-alu40-universal-diy-4-x-6-black', 'diy-only-gate-frame-kit-6-x-6-includes-1x-2x1-side-post', 'box-alu-40-4x6-ft-diy-pedestrian-gate-kit-single-swing-gap-3-8'}
for p in sorted(pub, key=lambda p: ([s['wp'] for s in systems].index(placement(p)[2]['wp']) if placement(p)[2] else 9, p['post']['menu_order'])):
    if p['slug'] in KEEP_SHOPIFY:
        report.append(['kept as is (your Shopify page)', p['slug'], p['post']['post_title'], '', '', ''])
        continue
    rr, notes, syss, grps, existed = product_rows(p)
    all_rows += rr
    if p['slug'] in TEST:
        test_rows += rr
    vr = [r for r in rr if r['Variant Price']]
    report.append(['updated' if existed else 'new', p['slug'], html.unescape(rr[0]['Title']), ' | '.join(g['title'] for g in grps) or ' | '.join(s['title'] for s in syss),
                   ', '.join('%s %s' % ('/'.join(x for x in (r['Option1 Value'],) if x != 'Default Title'), r['Variant Price']) for r in vr).strip(),
                   '; '.join(notes) + ('' if rr[0]['Status'] == 'active' else ' [draft]')])
for h in shop:
    if h in pub_by_slug or h in KEEP_SHOPIFY:
        continue
    all_rows += draft_rows(h)
    report.append(['hidden (draft) - not on the website any more', h, shop[h][0]['Title'], '', '', 'was ' + shop[h][0]['Status']])


def write(path, rs):
    with open(path, 'w', newline='', encoding='utf-8') as f:
        wr = csv.DictWriter(f, fieldnames=COLS)
        wr.writeheader()
        for r in rs:
            wr.writerow(r)


write(os.path.join(OUT, 'shopify-products-all.csv'), all_rows)
write(os.path.join(OUT, 'shopify-products-test-3.csv'), test_rows)
with open(os.path.join(OUT, 'shopify-change-report.csv'), 'w', newline='', encoding='utf-8') as f:
    wr = csv.writer(f)
    wr.writerow(['Action', 'Handle', 'Title', 'Shop groups', 'Options / prices', 'Notes'])
    wr.writerows(report)
print(collections.Counter(r[0] for r in report))
print('rows', len(all_rows), 'test rows', len(test_rows))
for r in report:
    if r[5].strip():
        print(r)
