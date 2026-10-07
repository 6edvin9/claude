"""Shop structure for Shopify, derived from the live WordPress catalog (release bundle)."""
import gzip, json, html, re, os

HERE = os.path.dirname(os.path.abspath(__file__))
BUNDLE = '/home/user/claude/agst/release/aluglobus-release/data/bundle.json.gz'

# WordPress system category -> Shopify collection (handle, title, lead). Order = shop order.
SYSTEMS = [
    ('aluminum-gates', 'gates', 'Gates', 'Gate frame kits, slat packs, complete DIY and heavy-duty kits, posts and sliding systems.'),
    ('aluminum-fence', 'fences', 'Fences', 'Complete fence kits, slats, posts, C-channels, spacers, base plates and rails.'),
    ('pergola', 'pergola', 'Patio Covers & Pergolas', 'Complete DIY pergola and patio cover kits: automatic louver, slat, beam and insulated roofs, freestanding or cantilever.'),
    ('wall-cladding', 'wall-cladding', 'Cladding', 'T&G cladding panels, L-shapes and trims, and the Click System.'),
]
# Shopify products that stay exactly as they are (custom pages built on Shopify).
KEEP_SHOPIFY = {'automatic-louver-pergola-kit', 'slat-roof-pergola-kits', 'beam-roof-patio-cover-kit', 'insulated-patio-cover-kit',
                'cantilever-aluminum-patio-cover-kit-slats-roofing', 'cantilever-aluminum-patio-cover-kit-automatic-louver',
                'cantilever-patio-cover-kit-sirp'}


def handleize(s):
    s = html.unescape(s).lower()
    s = re.sub(r"['’]", '', s)
    s = re.sub(r'[^a-z0-9]+', '-', s)
    return s.strip('-')


def load():
    return json.loads(gzip.decompress(open(BUNDLE, 'rb').read()))


def structure(b):
    cats = b['terms']['product_cat']
    by_slug = {t['slug']: t for t in cats}
    pub = [p for p in b['products'] if p['post']['post_status'] == 'publish']
    systems = []
    for wp, h, title, lead in SYSTEMS:
        groups = []
        kids = [t for t in cats if t['parent'] == wp and int(t['meta'].get('order') or 0) >= 1]
        kids.sort(key=lambda t: int(t['meta'].get('order') or 0))
        for t in kids:
            n = sum(1 for p in pub if t['slug'] in p['wc']['cats'])
            if not n:
                continue
            name = html.unescape(t['name']).replace(', ', ' · ').replace(',', ' ')  # Shopify tags cannot contain commas
            groups.append({'wp': t['slug'], 'wp_name': html.unescape(t['name']), 'tag': name, 'handle': handleize(name), 'title': name,
                           'description': re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', html.unescape(t['description'] or ''))).strip()})
        systems.append({'wp': wp, 'handle': h, 'title': title, 'tag': title, 'lead': lead, 'groups': groups})
    return systems, by_slug


def cat_links(systems, by_slug):
    links = {}
    sys_by_wp = {s['wp']: s for s in systems}
    for s in systems:
        links[s['wp']] = '/collections/' + s['handle']
        for g in s['groups']:
            links[g['wp']] = '/collections/%s/%s' % (s['handle'], g['handle'])
    for slug, t in by_slug.items():
        if slug in links:
            continue
        # walk up to a system
        cur, seen = t, 0
        while cur and cur['slug'] not in sys_by_wp and cur['parent'] and seen < 6:
            cur = by_slug.get(cur['parent']); seen += 1
        if cur and cur['slug'] in sys_by_wp:
            links[slug] = '/collections/' + sys_by_wp[cur['slug']]['handle']
        elif slug == 'gates' or slug.startswith('aluminum-gates'):
            links[slug] = '/collections/gates'
        elif slug == 'fences' or slug.startswith('aluminum-fence'):
            links[slug] = '/collections/fences'
        elif slug in ('patio-covers',) or 'patio' in slug or 'pergola' in slug:
            links[slug] = '/collections/pergola'
        else:
            links[slug] = '/collections/all'
    return links


if __name__ == '__main__':
    b = load()
    systems, by_slug = structure(b)
    pub = [p for p in b['products'] if p['post']['post_status'] == 'publish']
    ctx = {'product_handles': {p['slug']: p['slug'] for p in pub}, 'cat_links': cat_links(systems, by_slug), 'systems': systems}
    json.dump(ctx, open(os.path.join(HERE, 'ctx.json'), 'w'), indent=1)
    for s in systems:
        print(s['handle'], s['title'], len(s['groups']))
        for g in s['groups']:
            print('   ', g['handle'], '|', g['title'], '|', g['description'][:80])
