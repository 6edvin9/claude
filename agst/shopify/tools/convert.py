"""Convert rendered WordPress v2 product pages (staging) into clean Shopify description HTML + gallery lists."""
import json, re, sys, html, glob, os
from bs4 import BeautifulSoup, NavigableString, Tag

HERE = os.path.dirname(os.path.abspath(__file__))
LIVE = 'https://aluglobusfence.com'
SRC_HOSTS = ('globusgates.online', 'www.globusgates.online', 'aluglobusfence.com', 'www.aluglobusfence.com')

CTX = json.load(open(os.path.join(HERE, 'ctx.json')))  # handles, category links
PRODUCT_HANDLES = CTX['product_handles']   # wp slug -> shopify handle (only products live on Shopify)
CAT_LINKS = CTX['cat_links']               # wp category slug -> shopify path

ALLOWED = {'p', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'a', 'br', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
           'blockquote', 'h3', 'h4', 'sup', 'sub', 'small'}
CLASS_MAP = {'agx-check': 'agd-check', 'agx-check--2col': 'agd-check--2col', 'agx-bullets': 'agd-bullets',
             'agx-chips': 'agd-chips', 'agx-spec': 'agd-spec', 'agx-note': 'agd-note'}

warnings = []


def esc(s):
    return html.escape(s or '', quote=True).replace('&#x27;', "'")


def text_of(el):
    return re.sub(r'\s+', ' ', el.get_text(' ', strip=True)).strip()


def asset_url(u):
    """Media URL on the live WordPress site (same uploads path as staging)."""
    u = (u or '').strip()
    m = re.match(r'^(?:https?:)?//([^/]+)(/.*)$', u)
    if m and m.group(1) in SRC_HOSTS:
        return LIVE + m.group(2)
    if u.startswith('/wp-content/'):
        return LIVE + u
    return u


def link_url(u):
    """Map a WordPress link to its Shopify equivalent. Returns '' to drop the link (keep the text)."""
    u = (u or '').strip()
    if not u or u.startswith('#'):
        return ''
    if u.startswith(('tel:', 'mailto:')):
        return u
    m = re.match(r'^(?:https?:)?//([^/]+)(/.*)?$', u)
    if m:
        if m.group(1) not in SRC_HOSTS:
            return u
        u = m.group(2) or '/'
    path = u.split('?')[0].split('#')[0]
    if '/wp-content/' in path:
        return asset_url(path)
    parts = [p for p in path.split('/') if p]
    if not parts:
        return '/'
    if parts[0] in ('contacts', 'contact', 'contact-us', 'online-quote', 'get-a-quote'):
        return '/pages/contact'
    if parts[0] == 'shop':
        if len(parts) == 1:
            return '/collections/all'
        last = parts[-1]
        if last in PRODUCT_HANDLES:
            return '/products/' + PRODUCT_HANDLES[last]
        if last in CAT_LINKS:
            return CAT_LINKS[last]
        # a product that is not on Shopify (drafted on WordPress): link its category if any, else drop
        for p in reversed(parts[1:-1]):
            if p in CAT_LINKS:
                return CAT_LINKS[p]
        warnings.append('dropped link ' + u)
        return ''
    if parts[0] in ('product-category',) and parts[-1] in CAT_LINKS:
        return CAT_LINKS[parts[-1]]
    # other WordPress pages (gallery, blog, guides) stay on the main website
    return LIVE + path + ('' if path.endswith('/') else '')


def clean_alt(alt, fallback):
    a = (alt or '').strip()
    if not a or '| |' in a or 'aluglobusfence.com' in a.lower() or re.match(r'^(photo|img|image|dsc|whatsapp)[\s_-]*\d', a, re.I):
        return fallback
    return a


def inline_html(el):
    """Sanitised inner HTML of a rich-text element."""
    out = []
    for c in el.children:
        out.append(node_html(c))
    return ''.join(out)


def node_html(n):
    if isinstance(n, NavigableString):
        if n.__class__.__name__ in ('Comment', 'Doctype', 'Declaration'):
            return ''
        return esc(str(n)).replace('&#x27;', "'")
    if not isinstance(n, Tag):
        return ''
    name = n.name.lower()
    if name in ('script', 'style', 'svg', 'noscript', 'iframe', 'video', 'img', 'figure'):
        return ''
    inner = inline_html(n)
    if name not in ALLOWED:
        return inner
    attrs = ''
    if name == 'a':
        href = link_url(n.get('href', ''))
        if not href:
            return inner
        attrs = ' href="%s"' % esc(href)
        if href.startswith('http') and not href.startswith(LIVE + '/wp-content'):
            attrs += ' target="_blank" rel="noopener"'
    cls = [CLASS_MAP[c] for c in (n.get('class') or []) if c in CLASS_MAP]
    if cls:
        attrs += ' class="%s"' % ' '.join(cls)
    if name in ('th', 'td') and n.get('colspan'):
        attrs += ' colspan="%s"' % esc(n['colspan'])
    if name == 'br':
        return '<br>'
    return '<%s%s>%s</%s>' % (name, attrs, inner, name)


def agx(el):
    return [c for c in (el.get('class') or []) if c.startswith('agx')]


def is_container(el):
    return isinstance(el, Tag) and 'e-con' in (el.get('class') or [])


def is_widget(el):
    return isinstance(el, Tag) and 'elementor-widget' in (el.get('class') or [])


def kids(el):
    """Direct container/widget children (looking through .e-con-inner wrappers)."""
    out = []
    for c in el.children:
        if not isinstance(c, Tag):
            continue
        if is_container(c) or is_widget(c):
            out.append(c)
        elif 'e-con-inner' in (c.get('class') or []):
            out.extend(kids(c))
    return out


def wcontent(w):
    return w.select_one('.elementor-widget-container') or w


class Conv:
    def __init__(self, title):
        self.title = title
        self.images = []  # body images (for reference)

    def img(self, img, caption=''):
        src = img.get('src') or img.get('data-src') or ''
        if not src or src.startswith('data:'):
            return ''
        src = asset_url(src)
        self.images.append(src)
        alt = clean_alt(img.get('alt'), caption or self.title)
        w, h = img.get('width'), img.get('height')
        dims = ' width="%s" height="%s"' % (esc(w), esc(h)) if (w and h and w.isdigit() and h.isdigit()) else ''
        return '<img src="%s" alt="%s"%s loading="lazy">' % (esc(src), esc(alt), dims)

    def widget(self, w):
        c = set(agx(w))
        box = wcontent(w)
        t = text_of(box)
        if 'agx-kicker' in c:
            return '<p class="agd-kicker">%s</p>' % esc(t) if t else ''
        if 'agx-h2' in c:
            return '<h2>%s</h2>' % esc(t) if t else ''
        if 'agx-card-title' in c:
            return '<h3>%s</h3>' % esc(t) if t else ''
        if 'agx-num' in c:
            return '<span class="agd-num">%s</span>' % esc(t)
        if 'agx-stat-label' in c:
            return '<span class="agd-stat-label">%s</span>' % esc(t)
        if 'agx-stat-value' in c:
            return '<strong class="agd-stat-value">%s</strong>' % esc(t)
        if 'agx-band-cap' in c:
            return '<p class="agd-cap">%s</p>' % esc(t) if t else ''
        if 'agx-img' in c:
            img = box.find('img')
            if not img:
                return ''
            cap = box.find('figcaption')
            capt = text_of(cap) if cap else ''
            tag = self.img(img, capt)
            if not tag:
                return ''
            return '<figure class="agd-img">%s%s</figure>' % (tag, '<figcaption>%s</figcaption>' % esc(capt) if capt else '')
        if 'agx-video-w' in c:
            return self.video(w)
        if 'agx-btn' in c:
            a = box.find('a')
            if not a:
                return ''
            href = link_url(a.get('href', ''))
            if not href:
                return ''
            sec = ' agd-btn--secondary' if 'agx-btn--secondary' in c else ''
            return '<a class="agd-btn%s" href="%s">%s</a>' % (sec, esc(href), esc(t))
        if 'agx-acc' in c or box.select_one('.elementor-toggle, .elementor-accordion'):
            return self.toggle(box, 'agx-drawings' in c)
        if 'agx-text' in c or box.find(['p', 'ul', 'ol', 'table', 'blockquote']):
            inner = inline_html(box).strip()
            inner = re.sub(r'(<p>\s*</p>)', '', inner)
            if not inner:
                return ''
            cls = 'agd-text'
            if 'agx-intro' in c:
                cls += ' agd-intro'
            if 'agx-table' in c:
                cls += ' agd-table'
            return '<div class="%s">%s</div>' % (cls, inner)
        if w.find('img'):
            return ''.join('<figure class="agd-img">%s</figure>' % self.img(i) for i in w.find_all('img') if self.img(i))
        if t:
            warnings.append('%s: unknown widget %s "%s"' % (self.title, ' '.join(w.get('class') or [])[:80], t[:60]))
            return '<p>%s</p>' % esc(t)
        return ''

    def toggle(self, box, drawings):
        out = []
        for item in box.select('.elementor-toggle-item, .elementor-accordion-item'):
            title = item.select_one('.elementor-tab-title')
            body = item.select_one('.elementor-tab-content')
            if not title or not body:
                continue
            imgs = body.find_all('img')
            if drawings or (imgs and not text_of(body)):
                content = '<div class="agd-drawings">%s</div>' % ''.join(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>' % (esc(asset_url(i.get('src'))), self.img(i)) for i in imgs if i.get('src'))
            else:
                content = '<div class="agd-text">%s</div>' % inline_html(body).strip()
            out.append('<details class="agd-faq%s"><summary>%s</summary>%s</details>' % (' agd-faq--drawings' if drawings else '', esc(text_of(title)), content))
        return '<div class="agd-acc">%s</div>' % ''.join(out) if out else ''

    def video(self, w):
        settings = {}
        try:
            settings = json.loads(w.get('data-settings') or '{}')
        except Exception:
            pass
        v = w.find('video')
        if v and v.get('src'):
            poster = ''
            ov = settings.get('image_overlay') or {}
            if isinstance(ov, dict) and ov.get('url'):
                poster = asset_url(ov['url'])
            return '<div class="agd-video"><video controls playsinline preload="metadata" src="%s"%s></video></div>' % (
                esc(asset_url(v['src'])), ' poster="%s"' % esc(poster) if poster else '')
        url = settings.get('youtube_url') or ''
        if not url:
            f = w.find('iframe')
            url = f.get('src') or f.get('data-src') or '' if f else ''
        m = re.search(r'(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|watch\?v=))([A-Za-z0-9_-]{11})', url)
        if m:
            return '<div class="agd-video agd-video--yt"><iframe src="https://www.youtube-nocookie.com/embed/%s?rel=0" title="%s" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe></div>' % (m.group(1), esc(self.title))
        m = re.search(r'vimeo\.com/(?:video/)?(\d+)', url or (w.find('iframe') or {}).get('src', '') if w.find('iframe') else url)
        if m:
            return '<div class="agd-video agd-video--yt"><iframe src="https://player.vimeo.com/video/%s" title="%s" allow="fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>' % (m.group(1), esc(self.title))
        warnings.append('%s: video without source' % self.title)
        return ''

    def container(self, el, top=False):
        c = set(agx(el))
        inner = ''.join(self.node(k) for k in kids(el))
        if not inner.strip():
            return ''
        if top or 'agx-s' in c:
            tone = 'dark' if 'agx-tone-dark' in c else 'accent' if 'agx-tone-accent' in c else 'light'
            kind = next((x[7:] for x in c if x.startswith('agx-s--')), 'band' if 'agx-band' in c else 'text')
            if 'agx-band' in c:
                return '<section class="agd-s agd-band">%s</section>' % inner
            return '<section class="agd-s agd-s--%s agd-%s">%s</section>' % (kind, tone, inner)
        if 'agx-split' in c:
            return '<div class="agd-split%s">%s</div>' % (' agd-split--reverse' if 'agx-split--reverse' in c else '', inner)
        if 'agx-split-copy' in c:
            return '<div class="agd-split-copy">%s</div>' % inner
        if 'agx-split-media' in c:
            return '<div class="agd-split-media">%s</div>' % inner
        if 'agx-s-head' in c:
            return '<header class="agd-head">%s</header>' % inner
        if 'agx-grid' in c:
            kind = next((x[4:] for x in c if x in ('agx-cards', 'agx-steps', 'agx-stats', 'agx-gallery', 'agx-videos')), 'cards')
            cols = next((x[9:] for x in c if x.startswith('agx-cols-')), '3')
            return '<div class="agd-grid agd-%s agd-cols-%s">%s</div>' % (kind, cols, inner)
        if 'agx-card' in c:
            return '<div class="agd-card%s">%s</div>' % (' agd-step' if 'agx-step' in c else '', inner)
        if 'agx-stat' in c:
            return '<div class="agd-stat">%s</div>' % inner
        if 'agx-btns' in c:
            return '<div class="agd-btns">%s</div>' % inner
        if 'agx-cta-inner' in c:
            return '<div class="agd-cta">%s</div>' % inner
        return '<div class="agd-box">%s</div>' % inner

    def node(self, el):
        if is_container(el):
            return self.container(el)
        if is_widget(el):
            return self.widget(el)
        return ''


def full_size(u):
    return re.sub(r'-\d{2,4}x\d{2,4}(?=\.(?:webp|jpe?g|png|gif)$)', '', u, flags=re.I)


def convert(path):
    raw = open(path, encoding='utf-8').read()
    s = BeautifulSoup(raw, 'lxml')
    title_el = s.select_one('h1.agv-title')
    title = text_of(title_el) if title_el else ''
    conv = Conv(title)
    data = {'title': title}
    m = re.search(r'<title>(.*?)</title>', raw, re.S)
    data['seo_title'] = html.unescape(m.group(1)).strip() if m else ''
    k = s.select_one('.agv-kicker'); data['kicker'] = text_of(k) if k else ''
    l = s.select_one('.agv-lead'); data['lead'] = text_of(l) if l else ''
    data['chips'] = [text_of(li) for li in s.select('.agv-chips li')]
    gal = []
    for img in s.select('.agv-stage figure img'):
        btn = img.find_parent('button')
        src = (btn.get('data-full') if btn else '') or img.get('src') or img.get('data-src') or ''
        if not src:
            continue
        if src.startswith('/'):
            src = 'https://globusgates.online' + src
        u = src
        if u not in [g[0] for g in gal]:
            gal.append((u, clean_alt(img.get('alt'), title)))
    # hosted/YouTube videos in the hero gallery
    data['hero_videos'] = []
    for fig in s.select('.agv-stage figure'):
        v = fig.find('video')
        if v and v.get('src'):
            data['hero_videos'].append(asset_url(v['src']))
        f = fig.find('iframe')
        if f:
            data['hero_videos'].append(f.get('src') or f.get('data-src') or '')
    data['gallery'] = gal
    body = s.select_one('.agv-body .elementor') or s.select_one('.agv-body')
    parts = []
    if data['lead']:
        parts.append('<p class="agd-lead">%s</p>' % esc(data['lead']))
    if body:
        for sec in kids(body):
            parts.append(conv.container(sec, top=True) if is_container(sec) else conv.node(sec))
    data['body'] = '<div class="agd" data-kicker="%s" data-chips="%s">%s</div>' % (
        esc(data['kicker']).replace('|', '/'), esc('|'.join(c.replace('|', '/') for c in data['chips'])), ''.join(p for p in parts if p))
    data['body_images'] = conv.images
    return data


if __name__ == '__main__':
    out = {}
    for f in sorted(glob.glob(os.path.join(HERE, 'pages', '*.html'))):
        sid = os.path.basename(f).split('.')[0]
        out[sid] = convert(f)
    json.dump(out, open(os.path.join(HERE, 'converted.json'), 'w'), indent=1)
    print(len(out), 'pages;', len(warnings), 'warnings')
    from collections import Counter
    for w, n in Counter(re.sub(r'\d+', '#', w) if w.startswith('dropped') else w for w in warnings).most_common(60):
        print(n, w)
