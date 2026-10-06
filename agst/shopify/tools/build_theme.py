"""Copy the exported Shopify theme, apply the v30 catalog/product changes, and zip it for upload."""
import json, os, shutil, re, zipfile, html

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, 'theme')
NEW = os.path.join(HERE, 'theme_new')
DST = os.path.join(HERE, 'theme_out')
ctx = json.load(open(os.path.join(HERE, 'ctx.json')))

shutil.rmtree(DST, ignore_errors=True)
shutil.copytree(SRC, DST)
for root, _, files in os.walk(NEW):
    for f in files:
        rel = os.path.relpath(os.path.join(root, f), NEW)
        os.makedirs(os.path.dirname(os.path.join(DST, rel)), exist_ok=True)
        shutil.copy(os.path.join(root, f), os.path.join(DST, rel))


def liq(s):
    """Text safe inside a single-quoted Liquid string that is later split on | and ~."""
    s = html.unescape(s).replace("'", '’').replace('|', '/').replace('~', '-')
    return s


systems = ctx['systems']
cfg = {
    '@@SYS_HANDLES@@': '|'.join(s['handle'] for s in systems),
    '@@SYS_TITLES@@': '|'.join(liq(s['title']) for s in systems),
    '@@SYS_LEADS@@': '|'.join(liq(s['lead']) for s in systems),
    '@@SYS_GROUPS@@': '|'.join('~'.join(liq(g['tag']) for g in s['groups']) or '-' for s in systems),
    '@@SYS_GDESCS@@': '|'.join('~'.join(liq(g['description']) or '-' for g in s['groups']) or '-' for s in systems),
}
tpl = open(os.path.join(HERE, 'ag-catalog.liquid.tpl')).read()
for k, v in cfg.items():
    tpl = tpl.replace(k, v)
assert '@@' not in tpl
open(os.path.join(DST, 'sections', 'ag-catalog.liquid'), 'w').write(tpl)

# Collection template: the catalog section; the previous template is kept as "collection.legacy".
shutil.copy(os.path.join(SRC, 'templates', 'collection.json'), os.path.join(DST, 'templates', 'collection.legacy.json'))
json.dump({'sections': {'main': {'type': 'ag-catalog', 'settings': {}}}, 'order': ['main']},
          open(os.path.join(DST, 'templates', 'collection.json'), 'w'), indent=2)

# Product page: buy-panel header + website content for synced products (description has class="agd").
p = os.path.join(DST, 'sections', 'main-product.liquid')
s = open(p).read()
old = "{%- render 'ag-product-header-v14', product: product, ag_group: ag_v14_group -%}"
assert s.count(old) == 1
s = s.replace(old, "{%- render 'ag-product-header-v30', product: product, ag_group: ag_v14_group -%}")
old = "  {{ 'ag-product-v14.css' | asset_url | stylesheet_tag }}\n"
assert s.count(old) == 1
s = s.replace(old, old + "  {{ 'ag-desc-v30.css' | asset_url | stylesheet_tag }}\n")
open(p, 'w').write(s)

p = os.path.join(DST, 'sections', 'ag-product-catalog.liquid')
s = open(p).read()
i = s.index('{% schema %}')
body, schema = s[:i], s[i:]
s = ("{%- if product.description contains 'class=\"agd\"' -%}\n"
     "  {{ 'ag-desc-v30.css' | asset_url | stylesheet_tag }}\n"
     "  <div class=\"agd-wrap\" id=\"agd-details\">{{ product.description }}</div>\n"
     "{%- else -%}\n" + body.rstrip() + "\n{%- endif -%}\n\n" + schema)
open(p, 'w').write(s)

# Theme name so it is easy to tell apart in Online Store > Themes.
p = os.path.join(DST, 'config', 'settings_schema.json')
sc = json.load(open(p))
sc[0]['theme_name'] = sc[0].get('theme_name', 'Dawn')
json.dump(sc, open(p, 'w'), indent=2)

# Scope the v30 CSS under #MainContent with repeated classes so the store-wide dark theme rules
# (body:not(.template-index) #MainContent p / a:not(.button) ...) cannot override it.
def scope(path, cls, reset):
    css = open(path).read()
    css = re.sub(r'(^|[,{}]\s*)\.%s(?=[\s{.:\[,)])' % re.escape(cls), lambda m: m.group(1) + '#MainContent .%s.%s.%s' % (cls, cls, cls), css, flags=re.M)
    css = re.sub(r'(^|[,{}]\s*)\.(agd-buyhead|agd-soon)', lambda m: m.group(1) + '#MainContent [id^=ProductInfo] .' + m.group(2), css, flags=re.M)
    open(path, 'w').write(reset + css)
scope(os.path.join(DST, 'assets', 'ag-catalog-v30.css'), 'agc',
      '#MainContent .agc.agc.agc :is(h1,h2,h3,p,a,span,strong,em,li,del,ins,small,label,nav){color:inherit}\n')
scope(os.path.join(DST, 'assets', 'ag-desc-v30.css'), 'agd-wrap',
      '#MainContent .agd-wrap.agd-wrap.agd-wrap :is(h2,h3,h4,p,a,span,strong,em,li,td,th,figcaption,summary,blockquote){color:inherit}\n'
      '')

out = os.path.join(HERE, 'out', 'aluglobus-shopify-theme-v30-catalog.zip')
if os.path.exists(out):
    os.remove(out)
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, _, files in os.walk(DST):
        for f in files:
            full = os.path.join(root, f)
            z.write(full, os.path.relpath(full, DST))
print(out, os.path.getsize(out))
