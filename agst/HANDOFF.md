# HANDOFF: Aluglobus Aluminum Systems shop rebuild (staging)

Written 2026-10-01 17:40 CET. Give this file to the next AI; it holds everything needed to continue.

---

## 0. Ground rules from the owner (must follow)

| Rule | Detail |
|---|---|
| Where to work | Staging only: **https://globusgates.online**. The live site **aluglobusfence.com** must not be touched. |
| Passwords | Never type passwords. Work only through a browser that is already logged in. |
| Deleting | Never delete permanently. Set things to draft and add a 301 redirect (Redirection plugin, group 5, "Price list cleanup 2026-09"). |
| URLs | Never change the slug or URL of an existing product, because rankings must be kept. |
| Brand name | **Aluglobus Aluminum Systems**. Not "Aluglobus Fence". |
| Content | Must be real. No invented specs, ratings, fire claims or prices. Use only the price list and the products' own pages. |
| Photos | Use only real photos from aluglobusfence.com/gallery, and only when it is 100% certain the photo shows that product. |
| Design | Every product page must look the same, and its body must stay editable with "Edit with Elementor". |
| Handoff | When the AI is near its usage limit, write a handoff .md file like this one. |

---

## 1. Platform

**Software:** WordPress, WooCommerce 9.5.4, Elementor 4.2.3 with Elementor Pro 3.26.1, Yoast SEO, Redirection plugin, WP Optimize.

**Theme:** lasa / lasa-child.

**Clearing the cache:** fetch `/wp-admin/?_wpo_purge=d2942c5514` while logged in.

**Staging plugin:** "Aluglobus Staging Catalog Test", folder `wp-content/plugins/aluglobus-staging-catalog/`. Its main files:
- `aluglobus-staging-catalog.php` contains `ASSET_VERSION`, currently `1.0.1`. Raise it after every CSS change so browsers reload the CSS.
- `storefront.php` holds all the front-end classes. The newest code is appended at the end of the file:
  - `AGST_Storefront`: the product template and its model.
  - `AGST_Content`: legacy content conversion.
  - `AGST_Fixes`
  - `AGST_Convert`
  - `AGST_Blocks`: legacy renderer.
  - `AGST_ShopNav`, `AGST_ShopTree`, `AGST_ShopFront`: the /shop and category pages.
  - The brand filter `agst_brand_fix`.
  - `AGST_ElBuild`: Elementor migration.
  - `AGST_Spec`: the design-system builder.
- `storefront.css` holds the styles. The newest block starts at `/* ===== agx product body design system`.
- `single-product.php` is the template file.

**How files are edited:** with no FTP or SSH access, files were changed through the WordPress plugin editor's AJAX save. In the logged-in admin tab, a JS helper `__saveFile(file, content | fn(old) => new)` was used. It POSTs `action=edit-theme-plugin-file` with the nonce taken from `plugin-editor.php`. Rebuild this helper the same way when needed.

**WooCommerce REST API:** call `/wp-json/wc/v3/...` with header `X-WP-Nonce`. Get the nonce from `/wp-admin/admin-ajax.php?action=rest-nonce`.

**Local copies of the plugin code** are in `/home/claude/ag/plugin/agst/` inside the old container. They are not reachable from a new session, so re-read the real code from the plugin editor:
- `spec.php` and `spec.css`: the current design system.
- `elbuild.php`
- `shopfront.php`
- `DESIGN-SYSTEM.md`

---

## 2. What is done (all sessions)

### Catalog and price list
- **Price list applied.** Products are imported, published and in stock, and their slugs are cleaned.
- **Prices:**
  - 37 existing products matched to list prices. The old price is kept in meta `_agst_extra_update`.
  - 11 more price fixes.
- **Off-list products:** 148 set to draft with meta `_agst_cleanup`, each with a 301 in redirect group 5.
- **Categories mirror the price list.** The top categories are:

  | ID | Category |
  |---|---|
  | 88 | Aluminum Gates |
  | 127 | Aluminum Fences |
  | 398 | Pergola (slug `pergola`) |
  | 101 | Wall cladding |

  New subcategories (617–632) were added. The Yoast primary category is pinned on every product, so **0 URLs changed**. Product order (menu_order) follows the price list.
- **Shop and category pages** are rendered by `AGST_ShopFront`: uniform cards, system sections and group chips.
- **Coming soon:** meta `_agst_coming_soon` shows badges and notices.
- **Safety net:** a 404 whose slug belongs to a drafted product follows that product's redirect.

### Round 2
- **Pergolas:**
  - The 7 live kits are restored: 57987, 58015, 58025, 58030, 58036, 58678, 58684.
  - The box kit 63803 is added.
  - All other patio products are drafts (`_agst_parked`).
- **Click System cladding:** 26 products drafted with 301s. Category 377 is empty and redirects.
- **Y-Corner (63460):** drafted.
- **T&G cladding merged by colour:**
  - 32975 Clad120 now has Black / Teak / White Oak / IPE.
  - 32907 Clad100 now has Teak / White Oak / IPE.
  - The old single-colour products 301 to the merged product with the colour preselected.
- **SEO content for 65 new products**, stored in meta: `_agst_seo_html`, `_agst_seo_lead`, `_agst_seo_faq`, `_agst_seo_specs`, plus the Yoast fields.
- **Brand fix:** product and category texts corrected. A filter on the Yoast title and description replaces the old name in the page head.
- **Redirects:** all 183 group-5 redirects lead to working pages.

### Round 3
- **Pergola category:**
  - The kits are back only in category 398. Its name is restored to "Pergola" and its headline is "Pergola & Patio Cover Kits".
  - Subcategory 626 was removed from the kits; redirect id 331 sends its URL to /shop/pergola/.
  - The 6 restored kits got `_agst_managed=1` so they use the new template.
- **Elementor migration:** all 144 published product bodies were turned into Elementor data. The original Elementor data is kept in meta `_agst_el_original`.
  - Rebuild a product: AJAX `action=agst_el_build`, `ids=1,2`, `force=1`, `nonce=<rest nonce>`.
  - Restore a product: `action=agst_el_restore`.
- **Content for older products:**
  - Spec lists for 49 older products and FAQs for 28 of them.
  - 59 leads (the short text next to the price) rewritten, stored in `_agst_seo_lead`. This meta now overrides the lead for every product.
  - 8 products whose text described the wrong product got new body copy: 60361, 60359, 60355, 60254, 60033, 38016, 37478, 26565.
  - "Lorem ipsum" removed from 26639.

### Round 4 (in progress when stopped)
Owner feedback:
- Buttons are grey.
- Patio and DIY gate pages lost their layout.
- First design a **template**, then rebuild every page and QA it.

Done so far:
1. **Design system defined.** See `DESIGN-SYSTEM.md`, summarised in section 4 below.
2. **`AGST_Spec` builder deployed.** It turns a page spec (JSON in meta **`_agst_page_spec`**) into Elementor containers and widgets with `agx-*` classes. When a product has no spec, it derives one from the converted content (`from_sections`). `AGST_ElBuild::migrate` now uses this builder and sets `_agst_el=2.0`.
3. **New CSS deployed** in storefront.css, version 1.0.1:
   - Buttons are now orange (fixed).
   - Chips row fixed.
   - Class names were renamed to avoid clashes with the template: use `agx-kicker`, not `agx-eyebrow`, and `agx-acc`, not `agx-faq`.
4. **Design template preview product:** draft product **63896** "AGST Design Template (do not publish)". Preview: `/?post_type=product&p=63896&preview=true`. It shows every component. The desktop QA looked good.
5. **QA helpers (admins only):**
   - `?agst_legacy=1` renders the old body from the original data.
   - `?agst_qa=N` hides the page header and the first N body sections, so a screenshot starts at section N.
6. **Original page HTML exported** for 71 older products. The specs brief is `SPEC_BRIEF.md` and the checker is `validate_spec.py`; their full text is in sections 5 and 6 below.
7. **Pilot specs written and validated** for 58015 (slat roof pergola) and 38472 (ALU20 4x6 DIY gate). **They are not uploaded yet.**

---

## 3. Next steps (do these in order)

1. **Upload the pilot specs and check them.**
   - Put the spec JSON into meta `_agst_page_spec` for 58015 and 38472.
   - Run `agst_el_build` with force.
   - Check desktop and mobile with screenshots, then fix any CSS or builder issues.
   - Check whether the AR shortcode `[ar-display id=…]` actually works on staging. If the plugin is not active, drop those shortcode parts.
2. **Write specs for the remaining 69 older products.** Follow the brief, using the original HTML in `_agst_el_original._elementor_data` and the short description. Validate each spec, upload it to `_agst_page_spec`, and rebuild with force.
   - The older product IDs are: 26547 26550 26551 26562 26587 26597 26603 26616 26639 26647 26649 26659 26665 26667 26675 26805 26830 26832 26834 26856 26860 26862 32907 32975 33037 37442 37472 37475 37481 37957 37960 37962 38089 38101 38112 38126 38130 38143 38146 38149 38152 38198 38201 38394 38397 38464 38472 38548 38571 38607 38615 38627 39288 39398 49286 49300 57987 58015 58025 58030 58036 58678 58684 60025 60062 60071 60092 60122 60357 60752 60784.
   - The 65 new products plus the 8 corrected ones can use the automatic spec from `_agst_seo_html`. Spot-check them.
3. **Rebuild all 144 products** (`agst_el_build`, force), purge the cache, and **visually QA every product type** on desktop (1366) and mobile (390): pergola kits, DIY gate kits, sliding gates, fence kits, posts and caps, cladding, hardware.
   - The built-in browser pane often fails to draw. A reliable alternative: in the browser, fetch the page HTML, inline the CSS, turn images into data URLs, gzip and base64 it, bring it into the workspace, and render it with Playwright.
4. **Add verified gallery photos.**
   - Use only photos from aluglobusfence.com/gallery, and only with certainty.
   - Already added: ALU 40 black photos on 63717, 63221, 63276, 63283, 63299 and 63302.
   - Ideas: product-specific project photos for the gate and fence kits, matched by gallery tag and colour.
5. **Before go-live:**
   - Package everything as a real plugin with a migration for live: categories, assignments, Yoast primary categories, menu_order, drafts and redirects, content meta, Elementor rebuild, and the WPCode edits.
   - Products managed by the plugin are still non-purchasable (they show a quote button). Switch purchasing on for live.
   - Re-check everything against the final price list from logistics.

**Open decisions for the owner:**
1. Cladding colour names: Teak / White Oak / IPE (live) versus Ipe / Walnut / Yellow Oak (price list).
2. 29 "Price on request" items show prices on staging.
3. 67 old URLs from the Search Console export are 404 on both sites; should they get 301s?
4. 9 products have no image.
5. Sitewide settings that still say "Aluglobus Fence": the WPCode schema snippet, the Google Merchant brand snippet and the chat widget greeting.
6. Base plate 26597: the page says 4x4 posts, the price list says 2x2.
7. Price-list typos: the ALU 60 18x6 gate row lists "43pcs Alu 40 slats", and the 3" / 3.15" cap rows say they fit 2" posts.
8. Some older pages carry "Zone 0" / fire claims that were already on live.
9. On pergola pages, a few original lines read like editor notes, e.g. "Use this section to help contractors…". Should they be removed?

---

## 4. Design system (summary)

**Fixed frame (outside Elementor), top to bottom:**
1. Breadcrumb
2. Hero: gallery plus buy panel
3. Trust bar
4. Section navigation
5. **Elementor body**
6. Package details
7. Specifications
8. More images
9. FAQ (from `_agst_seo_faq`)
10. Closing call to action

**Tokens:**

| Token | Value |
|---|---|
| Page background | #0b0d0e |
| Dark section | #101415 |
| Light section | #f4f2ed |
| Accent | #ff792d |
| Kicker on light sections | #a34113 |
| Font | Inter |
| Card radius | 16px |
| Button radius | 8px |
| Content width | 1320px max, side padding 3.5% (the same as the frame) |

**Spec format:** a list of sections. Each section has:
- `layout`: `stack`, `split` or `cta`
- optional `eyebrow`, `title`, `text`, `media`, `reverse`
- `parts`: a list of parts

**Part types:** text, chips, checklist, buttons, stats, gallery, cards, packages, steps, specs, table, video, faq, note, shortcode.

Section tones alternate dark and light automatically; the cta section uses an accent tone. The full schema is in SPEC_BRIEF.md (section 5).


## 5. SPEC_BRIEF.md (full)

# Brief: convert an existing product page into a design-system page spec

We are rebuilding product pages for Aluglobus Aluminum Systems (aluminum fences, gates, pergolas, cladding).
Each product page body must be expressed ONLY with the components of our design system, so every page looks the same
and the owner can edit it later in Elementor. Read /home/claude/ag/plugin/agst/DESIGN-SYSTEM.md first.

## Input
`/home/claude/ag/content/orig/<id>.html` — the product's ORIGINAL page body (cleaned HTML: classes/styles removed, so you must
infer the structure from headings, lists, figures, links and text order). First lines are comments with id, name, short description.

## Output
`/home/claude/ag/content/spec/<id>.json`:
```json
{"id": 58015, "spec": [ SECTION, ... ]}
```
SECTION:
```json
{"layout": "stack" | "split" | "cta",
 "eyebrow": "short kicker text (optional)",
 "title": "section H2 (optional, plain text, max ~80 chars)",
 "text": "<p>intro paragraph(s) under the title</p> (optional)",
 "media": {"src": "...", "alt": "..."},      // split only: the one image
 "reverse": true,                            // split only: image on the left (alternate splits)
 "parts": [ PART, ... ]}
```
PART types (use exactly these keys):
- `{"type":"text","html":"<p>…</p><h3>…</h3><ul><li>…</li></ul>"}` — allowed tags: p, h3, h4, ul, ol, li, strong, em, a(href), br, table/thead/tbody/tr/th/td
- `{"type":"chips","items":["10×10","12×12"]}` — short tags/options (≤ 28 chars each)
- `{"type":"checklist","items":["…"]}` — included items / benefits with ✓
- `{"type":"buttons","items":[{"text":"Request a quote","href":"/online-quote/","style":"primary"|"secondary"}]}`
- `{"type":"stats","items":[{"label":"Material","value":"Architectural aluminum"}]}` — 2–4 key facts
- `{"type":"gallery","images":[{"src":"…","alt":"…","caption":"optional"}],"cols":2|3|4}`
- `{"type":"cards","cols":2|3|4,"items":[{"title":"…","text":"<p>…</p>","list":["…"],"image":{"src":"…","alt":"…"}}]}`
- `{"type":"packages","items":[{"badge":"Most popular","title":"Starter Package","price":"Starting at $5,720.00","subtitle":"10 × 10 ft pergola","chips":["10×10"],"text":"…","list":["…"],"button":{"text":"Request starter quote","href":"/online-quote/"}}]}`
- `{"type":"steps","items":[{"title":"…","text":"…"}]}` — numbered process
- `{"type":"specs","rows":[["Label","Value"]]}` — label/value technical data
- `{"type":"table","head":["…"],"rows":[["…"]]}` — real multi-column tables
- `{"type":"video","videos":[{"url":"https://www.youtube.com/watch?v=…" or an .mp4 url}]}`
- `{"type":"faq","items":[{"q":"…","a":"<p>…</p>"}]}`
- `{"type":"note","html":"<p>…</p>"}` — disclaimers / callouts
- `{"type":"shortcode","code":"[ar-display id=58702]"}` — only for real plugin shortcodes like the AR viewer

## Rules
1. KEEP THE CONTENT. Every meaningful sentence, list item, table row, image, video and price from the original must appear in the spec, in the original order and wording (you may fix obvious typos, "o ers"→"offers", and remove junk: HTML comments, "Lorem ipsum", stray icons/emoji, duplicated text). Do not add facts, claims or prices. Brand: "Aluglobus Aluminum Systems" (replace "Aluglobus Fence"/"ALU Globus Fence"/"AluGlobus Fence"; never change URLs).
2. Pick the component that matches the original intent: hero/intro → `split` with the hero image + chips/buttons; trust bar label/value pairs → `stats`; image grids → `gallery`; package/option boxes → `packages`; benefit or part boxes → `cards`; numbered process → `steps`; Q&A → `faq`; final call to action → `cta`; image+text blocks → `split` (alternate `reverse`).
3. One H2 per section (`title`). Sub-headings inside a section go into text html as h3, or as card titles.
4. Buttons: keep original link text. href: keep real URLs and `#elementor-action:…popup…` links as-is; links to on-page anchors (`#something`) → use `/online-quote/` for quote/price buttons, otherwise drop that button. Contact forms/inputs in the original → replace with a `cta` section with a "Request a quote" button to `/online-quote/`.
5. Images: use the exact src URLs from the original (they are media-library files). alt: keep original alt or write a short factual one.
6. Raw shortcodes: `[ar-display id=…]` → a `shortcode` part; any other shortcode text → drop.
7. 4–14 sections typically. No empty sections. Do not repeat the product name as every title.
8. Validate: `python3 /home/claude/ag/content/validate_spec.py <id>` must print OK (it checks schema, image coverage and text coverage). Fix until OK.
9. The second comment line `short:` is the product SHORT description. Its first paragraph is already shown next to the price — do not repeat it. Include any other short-description content (feature lists, key specs) in the spec if it is not already in the body (usually as an early `checklist`/`cards`/`specs` section).
10. Quality bar: a designer should be happy with the result — balanced sections, no walls of single-line paragraphs (turn label/value lines into `specs`/`stats`, short repeated lines into `chips`/`checklist`), packages/options as `packages`, benefits as `cards` (2–4 per row), images grouped into galleries or splits rather than left alone.

## 6. validate_spec.py

```python
import json,re,sys,html
from html.parser import HTMLParser
B='/home/claude/ag/content/'
TYPES={'text','chips','checklist','buttons','stats','gallery','cards','packages','steps','specs','table','video','faq','note','shortcode'}
def words(s):
    s=html.unescape(re.sub(r'<[^>]+>',' ',s or '')); return re.findall(r"[a-z0-9$][a-z0-9$.,×'’%/-]*",s.lower())
def collect(o,out):
    if isinstance(o,dict):
        for k,v in o.items():
            if k in('src','href','type','layout','style','cols','reverse','id','code'): 
                if k=='src': out['img'].append(v)
                continue
            collect(v,out)
    elif isinstance(o,list):
        for v in o: collect(v,out)
    elif isinstance(o,str): out['txt'].append(o)
def check(i):
    errs=[]
    orig=open(B+f'orig/{i}.html').read()
    body=re.sub(r'<!--.*?-->','',orig,flags=re.S)
    body=re.sub(r'<p>[^<]*(?:lorem ipsum)[\s\S]*?</p>','',body,flags=re.I)
    try: d=json.load(open(B+f'spec/{i}.json'))
    except Exception as e: return [f'json: {e}']
    if d.get('id')!=int(i): errs.append('id mismatch')
    spec=d.get('spec')
    if not isinstance(spec,list) or not spec: return errs+['spec empty']
    for n,s in enumerate(spec):
        if s.get('layout','stack') not in('stack','split','cta'): errs.append(f'sec{n} bad layout')
        if s.get('layout')=='split' and not (s.get('media') or {}).get('src'): errs.append(f'sec{n} split without media')
        for p in s.get('parts',[]):
            if p.get('type') not in TYPES: errs.append(f"sec{n} bad part type {p.get('type')}")
        if not (s.get('title') or s.get('text') or s.get('parts')): errs.append(f'sec{n} empty')
    out={'img':[],'txt':[]}; collect(spec,out)
    oimgs=set(re.findall(r'<img[^>]+src="([^"]+)"',body))
    simgs=set(out['img'])
    miss=[u for u in oimgs if u and u not in simgs and not u.startswith('data:')]
    if miss: errs.append(f'missing {len(miss)} images e.g. {miss[:2]}')
    ow=words(re.sub(r'\[[^\]]+\]',' ',body)); sw=set(words(' '.join(out['txt'])))
    if ow:
        cov=sum(1 for w in ow if w in sw)/len(ow)
        if cov<0.9: errs.append(f'text coverage {cov:.2f} < 0.90 (missing e.g. {[w for w in ow if w not in sw][:15]})')
    j=json.dumps(spec)
    if re.search(r'Aluglobus Fence|ALU Globus Fence|AluGlobus Fence',j,re.I): errs.append('old brand name')
    if re.search(r'lorem ipsum',j,re.I): errs.append('lorem')
    return errs
ids=sys.argv[1:]
for i in ids:
    e=check(i); print(i,'OK' if not e else 'FAIL: '+'; '.join(e))
```

## 7. Pilot spec 58015 (example of good output)

```json
{
 "id": 58015,
 "spec": [
  {
   "layout": "split",
   "eyebrow": "Factory Direct Aluminum Patio Cover System",
   "title": "Aluminum Slat Roof Pergola Kit",
   "text": "<p>A modern fixed aluminum slat roof pergola kit built for clean architectural shade, filtered sunlight, and low-maintenance outdoor living. Designed for homeowners, contractors, designers, and builders who want a factory-direct aluminum patio cover system.</p>",
   "media": {
    "src": "http://globusgates.online/wp-content/uploads/2026/05/IMG-5160-1024x768-1.webp",
    "alt": "Aluminum slat roof pergola kit"
   },
   "parts": [
    {
     "type": "chips",
     "items": [
      "Fixed Slat Roof",
      "ALU-20 / ALU-40 / ALU-60",
      "Custom Sizes",
      "Contractor Pricing"
     ]
    },
    {
     "type": "buttons",
     "items": [
      {
       "text": "Request Final Quote",
       "href": "#elementor-action:action=popup:open&settings=eyJpZCI6IjUyMDI0IiwidG9nZ2xlIjpmYWxzZX0=",
       "style": "primary"
      }
     ]
    },
    {
     "type": "stats",
     "items": [
      {
       "label": "Material",
       "value": "Architectural Aluminum"
      },
      {
       "label": "Roof Type",
       "value": "Slat Roof Patio Cover"
      },
      {
       "label": "Finish",
       "value": "Black, Woodgrain, Custom Colors"
      },
      {
       "label": "Pricing",
       "value": "Quote-Based Packages"
      }
     ]
    }
   ]
  },
  {
   "layout": "stack",
   "eyebrow": "Installed Project Views",
   "title": "Aluminum Slat Roof Pergolas in Real Outdoor Spaces",
   "text": "<p>Finished project views showing the fixed slat roof, structural frame, shade pattern, and architectural finish.</p>",
   "parts": [
    {
     "type": "gallery",
     "cols": 3,
     "images": [
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/09/1-8.webp",
       "alt": "Installed aluminum slat roof pergola shading a residential patio"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/09/3-8.webp",
       "alt": "Aluminum pergola with a fixed slat roof over an outdoor living space"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/09/4-9.webp",
       "alt": "Close project view of aluminum roof slats and the supporting pergola frame"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/09/5-7.webp",
       "alt": "Residential patio covered by a factory-direct aluminum slat pergola"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/09/6-8.webp",
       "alt": "Finished aluminum slat roof pergola with posts and perimeter beams"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/09/2-9.webp",
       "alt": "Architectural aluminum slat patio cover integrated with a modern home"
      }
     ]
    }
   ]
  },
  {
   "layout": "stack",
   "eyebrow": "Package Options",
   "title": "Choose Your Starting Package",
   "text": "<p>These slat roof packages give customers a clear starting point. Final pricing depends on size, profile selection, spacing, layout, color, engineering, freight, installation, permits, and project-specific requirements.</p>",
   "parts": [
    {
     "type": "packages",
     "items": [
      {
       "badge": "Most Popular Starter",
       "title": "Starter Package",
       "price": "Starting at $5,720.00",
       "subtitle": "10 x 10 ft. Pergola",
       "image": {
        "src": "http://globusgates.online/wp-content/uploads/2026/06/10x10-Slat-Roor-Pergola.jpg",
        "alt": "10 x 10 ft. aluminum slat roof pergola package"
       },
       "chips": [
        "10x10",
        "10x12",
        "12x12"
       ],
       "text": "Best for compact patios, small seating areas, side yards, and entry-level modern shade projects.",
       "list": [
        "Fixed aluminum slat roof system",
        "Aluminum post and beam package",
        "Standard material package",
        "Factory-direct quote support"
       ],
       "button": {
        "text": "Request Starter Quote",
        "href": "#elementor-action:action=popup:open&settings=eyJpZCI6IjUyMDI0IiwidG9nZ2xlIjpmYWxzZX0="
       }
      },
      {
       "badge": "Larger Patio Coverage",
       "title": "Standard Package",
       "price": "Starting at $7,865.00",
       "subtitle": "12 x 16 ft. Pergola",
       "image": {
        "src": "http://globusgates.online/wp-content/uploads/2026/06/12x16-Slat-Roor-Pergola.jpg",
        "alt": "12 x 16 ft. aluminum slat roof pergola package"
       },
       "chips": [
        "12x16",
        "12x20",
        "16x16"
       ],
       "text": "Best for medium-size patios, poolside shade, outdoor dining zones, and modern backyard layouts.",
       "list": [
        "Expanded slat roof coverage",
        "ALU-20, ALU-40, or ALU-60 profile options",
        "Post and beam layout planning",
        "Optional lighting and fan planning"
       ],
       "button": {
        "text": "Request Standard Quote",
        "href": "#elementor-action:action=popup:open&settings=eyJpZCI6IjUyMDI0IiwidG9nZ2xlIjpmYWxzZX0="
       }
      },
      {
       "badge": "Premium Large Layouts",
       "title": "Large Package",
       "price": "Starting at $9,750.00",
       "subtitle": "16 x 20 ft. Pergola",
       "image": {
        "src": "http://globusgates.online/wp-content/uploads/2026/06/16x20-Slat-Roor-Pergola-v2.jpg",
        "alt": "16 x 20 ft. aluminum slat roof pergola package"
       },
       "chips": [
        "16x20",
        "20x20",
        "24x24"
       ],
       "text": "Best for large outdoor living areas, commercial patios, hospitality seating, and premium architectural shade projects.",
       "list": [
        "Large-span aluminum slat roof system",
        "Project-specific slat spacing and layout",
        "Engineering review may be required",
        "Contractor and builder pricing available"
       ],
       "button": {
        "text": "Request Large Quote",
        "href": "#elementor-action:action=popup:open&settings=eyJpZCI6IjUyMDI0IiwidG9nZ2xlIjpmYWxzZX0="
       }
      }
     ]
    },
    {
     "type": "shortcode",
     "code": "[ar-display id=58702]"
    },
    {
     "type": "shortcode",
     "code": "[ar-display id=58703]"
    },
    {
     "type": "shortcode",
     "code": "[ar-display id=58704]"
    },
    {
     "type": "note",
     "html": "<p>Starting price shown is for a standard material package only. Final pricing depends on size, roof type, post layout, color, engineering, freight, installation, permits, and project-specific requirements. Request a quote for final package pricing.</p>"
    }
   ]
  },
  {
   "layout": "split",
   "reverse": true,
   "eyebrow": "Modern Fixed Shade System",
   "title": "Clean Shade Lines Without a Heavy Solid Roof.",
   "text": "<p>Slat roof patio covers create a clean architectural shade line without fully closing the roof. The system is ideal for modern homes, outdoor walkways, poolside shade, and commercial spaces where filtered light is preferred over a heavy solid ceiling.</p><p>Choose the ALU-40 heavy-duty system for stronger presence and wider visual scale, or the ALU-20 slim profile for a lighter minimalist shade structure. ALU-60 tongue-and-groove options can also be planned depending on the project direction.</p>",
   "media": {
    "src": "http://globusgates.online/wp-content/uploads/2026/05/Alu-40-Wood-Grain-Slat-Pergola-Image-v1.png-scaled-1.webp",
    "alt": "Aluminum slat roof pergola woodgrain finish"
   },
   "parts": [
    {
     "type": "checklist",
     "items": [
      "Modern fixed slat roof",
      "Filtered sunlight and shade",
      "ALU-20 slim profile option",
      "ALU-40 heavy-duty option",
      "Black, woodgrain, and custom colors",
      "Contractor-friendly supply support"
     ]
    }
   ]
  },
  {
   "layout": "split",
   "eyebrow": "Frame and Roof Detail",
   "title": "Designed as One Coordinated Aluminum System.",
   "text": "<p>Slats, beams, posts, spacing, and finish work together to create clean filtered shade.</p>",
   "media": {
    "src": "http://globusgates.online/wp-content/uploads/2026/09/10-5.webp",
    "alt": "Aluminum slat roof pergola frame and roof-line detail"
   }
  },
  {
   "layout": "stack",
   "eyebrow": "System Views",
   "title": "Aluminum Slat Roof Gallery",
   "text": "<p>Show customers the ALU-20, ALU-40, ALU-60, woodgrain, black, custom color, top view, and section view options.</p>",
   "parts": [
    {
     "type": "gallery",
     "cols": 4,
     "images": [
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/IMG-5160-1024x768-1.webp",
       "alt": "ALU-40 heavy-duty slat roof system",
       "caption": "ALU-40 Heavy-Duty Slat System"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/IMG-2457-1024x768-1.webp",
       "alt": "ALU-20 slim slat roof system",
       "caption": "ALU-20 Slim Profile"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Alu-40-Wood-Grain-Slat-Pergola-Image-v1.png-scaled-1.webp",
       "alt": "ALU-40 woodgrain slat pergola",
       "caption": "ALU-40 Woodgrain Slat System"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Top-View-Alu-40-Slats-Pergola-1-scaled-1.webp",
       "alt": "Top view ALU-40 slat pergola",
       "caption": "ALU-40 Top View Layout"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Section-View-Alu-40-Slat-1-scaled-1.webp",
       "alt": "ALU-40 slat section view",
       "caption": "ALU-40 Section View"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Alu-20-Slat.webp",
       "alt": "ALU-20 black slat profile",
       "caption": "ALU-20 Black Slat"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Alu-40-Slat.webp",
       "alt": "ALU-40 black slat profile",
       "caption": "ALU-40 Black Slat"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Alu-60-TG-Slat.webp",
       "alt": "ALU-60 tongue and groove black slat profile",
       "caption": "ALU-60 T&G Black Slat"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Alu-40-WoodGrain.webp",
       "alt": "ALU-40 woodgrain slat profile",
       "caption": "ALU-40 Woodgrain Finish"
      },
      {
       "src": "http://globusgates.online/wp-content/uploads/2026/05/Alu-40-Custom-Colors.webp",
       "alt": "ALU-40 custom color slat profile",
       "caption": "ALU-40 Custom Colors"
      }
     ]
    }
   ]
  },
  {
   "layout": "split",
   "reverse": true,
   "eyebrow": "Filtered Shade",
   "title": "Consistent Shade With an Open Architectural Feel.",
   "text": "<p>Fixed aluminum slats create dependable coverage while allowing light and airflow through the roof.</p>",
   "media": {
    "src": "http://globusgates.online/wp-content/uploads/2026/09/8-5.webp",
    "alt": "Fixed aluminum roof slats creating filtered shade over a patio"
   }
  },
  {
   "layout": "stack",
   "eyebrow": "Product Specs",
   "title": "Main Parts, Roof Options, and Accessories",
   "text": "<p>Use this section to help contractors and serious buyers understand the material package before requesting a quote.</p>",
   "parts": [
    {
     "type": "text",
     "html": "<h3>Main Parts Sizes</h3>"
    },
    {
     "type": "table",
     "head": [
      "Name",
      "Size"
     ],
     "rows": [
      [
       "6 in. x 6 in. Post",
       "6' x 6' x 10'/12'/14'"
      ],
      [
       "6 in. Post Base Plate",
       "5-3/4\" x 5-3/4\""
      ],
      [
       "7 in. x 7 in. Post",
       "7' x 7' x 10'/12'/14'"
      ],
      [
       "8 in. x 8 in. Post",
       "8' x 8' x 10'/12'/14'"
      ],
      [
       "7 in. Post Inner Base Plate",
       "6-1/2\" x 6-1/2\""
      ],
      [
       "2 in. x 8 in. Beam",
       "2\" x 8\" x 16'/20'/24'"
      ],
      [
       "2 in. x 10 in. Beam",
       "2\" x 10\" x 16'/20'/24'"
      ],
      [
       "Channel Receiver",
       "18 ft"
      ]
     ]
    },
    {
     "type": "text",
     "html": "<h3>Roofing Style Options</h3>"
    },
    {
     "type": "specs",
     "rows": [
      [
       "ALU-20 Slat",
       "Black, Ipe, Teak, Yellow Oak, and custom colors"
      ],
      [
       "ALU-40 Slat",
       "Black, Ipe, Teak, Yellow Oak, and custom colors"
      ],
      [
       "ALU-60 T&G",
       "Black, Ipe, Teak, Yellow Oak, and custom colors"
      ]
     ]
    },
    {
     "type": "text",
     "html": "<h3>Optional Accessories</h3>"
    },
    {
     "type": "checklist",
     "items": [
      "LED Channel",
      "LED Strip Lights",
      "LED Recessed Lights",
      "Ceiling Fan Planning",
      "Beam for LED Recessed Lights",
      "Custom slat spacing review",
      "Custom color powder coating"
     ]
    }
   ]
  },
  {
   "layout": "split",
   "eyebrow": "Completed Patio Cover",
   "title": "Built Around the Outdoor Space.",
   "text": "<p>Custom dimensions and layout planning help the pergola coordinate with the property.</p>",
   "media": {
    "src": "http://globusgates.online/wp-content/uploads/2026/09/7-6.webp",
    "alt": "Completed aluminum slat patio cover viewed from the outdoor area"
   }
  },
  {
   "layout": "stack",
   "eyebrow": "How Quote-Based Pricing Works",
   "title": "Send the Project Details. We Help Build the Package.",
   "parts": [
    {
     "type": "steps",
     "items": [
      {
       "title": "Choose Package Level",
       "text": "Select Starter, Standard, or Large based on the closest project size."
      },
      {
       "title": "Choose Slat Profile",
       "text": "Pick ALU-20, ALU-40, or ALU-60 depending on the look, spacing, strength, and finish direction."
      },
      {
       "title": "Send Plans or Measurements",
       "text": "Upload dimensions, site photos, attachment style, color preference, and project notes."
      },
      {
       "title": "Get Final Package Pricing",
       "text": "Receive a quote based on material, options, freight, installation needs, and project requirements."
      }
     ]
    }
   ]
  },
  {
   "layout": "stack",
   "eyebrow": "See the System",
   "title": "Aluminum Slat Roof Patio Cover Video",
   "text": "<p>Use this video section to show customers how the slat roof system looks on modern aluminum patio cover projects.</p>",
   "parts": [
    {
     "type": "video",
     "videos": [
      {
       "url": "https://www.youtube.com/watch?v=89bpO8BIY6E"
      }
     ]
    },
    {
     "type": "buttons",
     "items": [
      {
       "text": "Get Factory-Direct Pricing",
       "href": "#elementor-action:action=popup:open&settings=eyJpZCI6IjUyMDI0IiwidG9nZ2xlIjpmYWxzZX0=",
       "style": "primary"
      }
     ]
    }
   ]
  },
  {
   "layout": "split",
   "reverse": true,
   "eyebrow": "Finished Outdoor Living",
   "title": "Clean Lines From Every View.",
   "text": "<p>A fixed slat roof adds shade and architectural definition without the visual weight of a solid roof.</p>",
   "media": {
    "src": "http://globusgates.online/wp-content/uploads/2026/09/9-5.webp",
    "alt": "Aluminum pergola with evenly spaced fixed roof slats"
   }
  },
  {
   "layout": "stack",
   "eyebrow": "Questions",
   "title": "Aluminum Slat Roof Pergola Kit FAQs",
   "parts": [
    {
     "type": "faq",
     "items": [
      {
       "q": "Is this a standard kit or custom package?",
       "a": "<p>This is a quote-based material package. The package sizes give a starting point, but final pricing depends on actual dimensions, slat profile, spacing, post layout, color, engineering, freight, installation, and project needs.</p>"
      },
      {
       "q": "What is the difference between ALU-20, ALU-40, and ALU-60?",
       "a": "<p>ALU-20 is a slimmer profile for a lighter minimalist look. ALU-40 creates a stronger and wider architectural slat appearance. ALU-60 T&amp;G can be used when the project needs a larger profile or different coverage style.</p>"
      },
      {
       "q": "Does a slat roof provide full rain protection?",
       "a": "<p>No. A slat roof is mainly for shade, airflow, and architectural design. If the customer needs stronger rain protection, an automatic louver, insulated panel, or polycarbonate roof may be a better option.</p>"
      },
      {
       "q": "Can this system be attached to the house?",
       "a": "<p>Yes. The slat roof system can be planned as attached or freestanding depending on the structure, attachment condition, engineering, and project layout.</p>"
      },
      {
       "q": "Can contractors request special pricing?",
       "a": "<p>Yes. Contractors, builders, designers, and project teams can request factory-direct package pricing based on project scope, profile selection, and material volume.</p>"
      }
     ]
    }
   ]
  },
  {
   "layout": "cta",
   "title": "Ready to Price an Aluminum Slat Roof Pergola Kit?",
   "text": "<p>Send your size, city, project photos, preferred slat profile, preferred color, and whether the system is attached or freestanding. We will help prepare the right factory-direct package.</p>",
   "parts": [
    {
     "type": "buttons",
     "items": [
      {
       "text": "Request Final Quote",
       "href": "#elementor-action:action=popup:open&settings=eyJpZCI6IjUyMDI0IiwidG9nZ2xlIjpmYWxzZX0=",
       "style": "primary"
      },
      {
       "text": "View Gallery",
       "href": "https://globusgates.online/gallery/",
       "style": "secondary"
      }
     ]
    }
   ]
  }
 ]
}```

## 8. Project notes log (earlier rounds, raw)

# Alu Globus — WooCommerce catalog update + redesign (handoff from ChatGPT thread "Update WooCommerce Products")

Source: https://chatgpt.com/share/6abc0f34-100c-83ec-b7ed-2aa0301817af (889 messages, read 2026-09-29).
Note: tool outputs, browser actions, uploaded files and `apply_patch` bodies were redacted in the share.

## Sites
- Live: aluglobusfence.com (WordPress + WooCommerce 9.5.4, Elementor, theme appears to be a "tbay" theme)
- Staging: globusgates.online (full All-in-One WP Migration clone), wp-admin at https://globusgates.online/wp-admin/
  - WP_ENVIRONMENT_TYPE='staging' added to wp-config; "Discourage search engines" on.
- Shopify reference design user loves: https://aluglobusaluminum.com/products/automatic-louver-pergola-kit
- Original "template" reference product (ID 26860): /shop/aluminum-gates/hd-s-g-gate-alu20-slat-contractor-line-12-x-6-horizontal-sliding-gate-kit/
- Other stores overlap (globusgates.com, aluglobusaluminum.com) — plugin blocks those hosts + aluglobusfence.com.
- SEO: Yoast SEO Premium + Yoast WooCommerce; BeRocket Permalink Manager for WooCommerce (prefix `shop`, product category hierarchy in URL). Do NOT change permalink settings.
- Currency USD. Staging has an "Export All URLs" plugin.

## Inputs the user provided (need re-upload here)
- wc-product-export-17-9-2026-1789651037743.csv (781 rows, 248 cols; all columns + custom meta)
- ITEMIZED PRICE LIST 2026 rev 091726(2).pdf — DRAFT/incomplete, 105 pages, 256 raw entries (pages 2–101 parsed)
- https___aluglobusfence.com_-Performance-on-Search-2026-09-24.zip (GSC: Queries, Pages, Devices, Chart, Filters CSVs; Jun 22–Sep 21)
- Pasted markdown(20260924-135502).md (system status / plugin info)
- Screenshots (export screen, plugin warning, permalinks, staging pages, latest CSS issue screenshot image(20260929-185438).png)

## Export findings
- 323 parent products (244 published / 79 draft) + 458 variations. Simple 281, variable 22, grouped 20.
- Only 120/323 have SKU; 9 duplicated SKUs across 34 records (26546, 26551, 26834, 26571-1 on 16 posts...). Match by product ID, not SKU.
- 292 have _elementor_data (243 of 244 published). 94 = one container + one HTML widget (custom HTML/CSS pages, `ags-`/`ags20-` classes, orange accent); 27 mixed; 171 native widgets; 31 no layout.
- No sale prices; shipping fields empty; Yoast title 128/244, metadesc 111/244, focus kw 229/244.
- Content conflicts flagged: 60033 (3" cap says 2"), 37478 (4x4 post body = C-channel kit), 26565 (8ft C-channel body says 18ft), an ALU60 product referring to ALU50; ALU20 4x6 gate copy has conflicting slat qty / width-height.
- Later "fresh export" seen on staging: 334 parents, 481 variations. All 323 original products retain exported categories; 11 new test products used importer-created categories → must map to existing categories.

## GSC findings
- /shop/ URLs = 1,494 clicks ≈ 44% of clicks in Pages table. Top: Gates category 90, Aluminum Gates category 86, Cantilever Slat Roof Patio 59, Insulated Patio Cover 58, CLAD120 Black 56. /shop/ landing 23.

## User requirements (firm)
- Uniform, modern, 100%-width, SEO-rich, user-friendly, responsive (desktop+mobile) product pages for ALL products; informative, attractive short descriptions.
- Keep EVERY existing image and video (critical for fences/gates). Top gallery max ~4–5 product images (1–3 typical), rest lower on page.
- Same product in different colors → ONE variable product with color selector (own price/image/SKU per color).
- Existing products: never change slug/URL, never delete, keep existing categories (may ADD categories only if full URL unchanged), keep Yoast fields & content. New products may get new categories/slugs.
- Use slugs/relative URLs, not full domains (staging vs live).
- Don't redo work twice: final PDF from logistics still pending; draft PDF used only as staging test.
- New products: 1 image extracted from PDF (enhanced conservatively), more later.
- Confirmed: 8" x 20ft louver blade = $526.13 for all six colors (White, Sand, Clay, Black, Bronze, Gray). 24ft blade colors unconfirmed.

## Plugin "Aluglobus Staging Catalog Test" (WooCommerce > Aluglobus Catalog Test)
Files: aluglobus-staging-catalog.php (AGST_Catalog), storefront.php (AGST_Storefront, full-width template override via template_include → single-product.php, `.agx` design), storefront.css/js, variations.php (AGST_Variations), preservation.php (AGST_Preservation — source never visible in share), admin.js, manifest.json, assets/pdf-*.{jpg,png}, INSTALLATION.md, BUILD-NOTES.md.
- Guards: manage_woocommerce, env staging/local/dev, blocked live hosts, blog_public=0, USD.
- Per-row Apply/Restore via AJAX with snapshot option `agst_backup_<key>`, lock `agst_import_lock`, meta `_agst_key`, `_agst_managed`, `_agst_version`, `_agst_media_inventory`.
- Managed products are NOT purchasable (woocommerce_is_purchasable filter) — launch blocker.
- Option `agst_full_shop_layout` applies new layout to all product pages.
- Versions: 0.1.1 (first, "amateur" styling) → 0.2.0 (full-width redesign, user: "literally how it was meant to be") → 0.3.0/0.3.1 (content+Yoast preservation, URL identity check, 6-color louver, Color/color key fix) → 0.3.2 (top gallery capped at 5 → 4 shown on ALU20 4x6; legacy content styled in dark cards; last refinement upload for spec/package blocks unconfirmed).
- Manifest (draft PDF): 64 existing updates (14 price changes), 90 new parent drafts, 18 variable families / 55 variations, 60 holds, 7 aliases. Keys like P002-001 (page-entry). Hold reasons listed in catalog.py.
- Staging IDs: 4x6 gate 38112, 6x6 gate 38101, louver 63164 (P078-160), new post-kit draft 63187 (P010-010). 245 published product URLs unchanged before/after.

## Where it stopped
User's last request (2026-09-29): "still finding CSS issues on all products — old content doesn't fit the new design. QA every single product, fix issues, make sure products go into the right existing categories, send back fully ready to insert; going live as soon as done."
ChatGPT had found: recovery kept text but discarded layout structure and sometimes picked flattened description over richer Elementor version; fixing renderer; mapping 11 new products' categories to existing ones; ALU20 section now two-column. Launch blockers: package still uses DRAFT price list, and purchasing disabled for managed products. Then usage limit hit — no v0.3.3 delivered.

## 2026-09-30 staging full update (done)
- Applied all ready import rows (skipped P028-040, P031-043 box-kit conflicts, P045-071, P085-189 not on new list). Holds untouched.
- Published + visible all imported products; slugs cleaned (removed -pNNN-NNN / -coming-soon). All in stock.
- 37 extra matched products: price set to new list (meta _agst_extra_update holds old price), _agst_managed=1. 9 unconfirmed matches left unchanged (layout only).
- 11 price fixes to new list (63426, 63332, 63330, 63328, 63324, 63322, 63302, 63299, 63149, 49300, 26603).
- 148 off-list products -> draft (meta _agst_cleanup=price-list-2026-09) + 301s in Redirection group 5 "Price list cleanup 2026-09" (relative targets). Redirect 142 retargeted.
- menu_order = price-list order. WPCode 39281 edited (subcategory-ID ordering disabled). WPCode 39726 tiles updated (Laser Cut -> Pergolas & Patio Covers).
- storefront.php: AGST_ShopNav hides empty categories in Elementor sidebar widget (template 30518); quote button for non-purchasable products. ASSET_VERSION 0.7.0.
- Live shop: 196 products.
- 2026-09-30 later: categories mirror price list. Tops 88 Aluminum Gates, 127 Aluminum Fences, 398 renamed "Patio Covers & Pergolas", 101 Wall cladding. New subcats 617-632 (+ reused 367, 357, 614, 615, 616, 376, 377 renamed). All 196 products got list categories added (old kept), Yoast primary pinned to current URL category -> 0 URL changes. Sidebar rebuilt dynamically (AGST_ShopTree in storefront.php). WPCode 39726 now 4 tiles. Converter 1.0.1 drops old in-page section menus (nav / #-link lists).
- 2026-09-30 evening: AGST_ShopFront (local copy plugin/agst/shopfront.php + shopfront.css) appended to storefront.php/.css; single-product.php branches to it. /shop = 4 system cards with numbered groups; top category pages = groups in price-list order (term meta 'order' 1..7); subcategory pages = grid + sibling chips; product search results use the same cards. Old theme sidebar/tiles not shown on these pages. Banner fix: AGST_Storefront::css_only_images() drops images referenced only via CSS url()/<style> (9 products). ASSET_VERSION 0.8.1.
- For live: needs a real plugin with migration (create categories 617-632 equivalents by slug, rename 398/367/357/376/377/614-616, term descriptions/order, product category assignments, Yoast primary pins, menu_order, drafts + Redirection group, WPCode 39281/39726 edits).
- 2026-09-30 night (coming-soon + uniform shop):
  - Every price-list row now on the shop (271/273 covered; AG-0245 and AG-0258 are duplicate rows of AG-0243/AG-0257). Aliases AG-0014/15/16/18 covered by post-option products.
  - Imported via AGST_Fixes::promote(): 63712, 63717, 63722, 63730, 63738 (variable), 63746-63764 singles, 63768/63776 box Alu60 kits. REST-created: 63796 (Alu60 wood-look 6x6 gate), 63797 (Top Rail 6.5ft), 63798 (box sliding gate, 4 configs), 63803 (box patio cover kit, 4 finishes). 63221 now Black/Bronze; 33037 has Walnut (Teak=Ipe, White Oak=Yellow Oak in list names).
  - All published, visible, in stock, list categories + menu_order + Yoast primary.
  - Merged: 38436 -> 63768, 26850 -> 63776, 57987 -> 63803 (drafts + 301s, Redirection ids 296-298). Group 5 now 151 redirects.
  - Coming-soon meta _agst_coming_soon ('all' or 'Bronze & White') on 63730, 63768, 63776, 63798, 63235, 63201, 49286, 63276, 63253, 63738, 26830, 38571, 63803, 63460, 63722(partial). Card badge + notice above add-to-cart.
  - Prices set to list: 38571 -> 844.12, 26562 -> 28.40 (old in _agst_extra_update).
  - /shop redesigned: hero jump tiles per system, then each system as a section of subcategory tiles (image, count). Cards uniform (fixed media box, 3-line title clamp, fixed swatch/price rows): 515px desktop / 380px mobile everywhere. ASSET_VERSION 0.9.2.
  - Open: 29 products have prices on staging while the live list says "Price on request" (AT patio parts, Bond controls, AeroLouver, some Click System). Needs owner decision.
  - storefront.php: template_redirect handler — a 404 under /shop/ whose last segment is the slug of a product drafted by the cleanup (_agst_cleanup) follows that product's group-5 redirect. Fixes old redirects/links that used another category path (6 ranking URLs were 404 on staging, live OK).
  - Ranking-URL audit (GSC export, 316 shop URLs): 249 resolve (200 or 301→200). 67 still 404 — all already 404 on live (drafted long before this work, e.g. 3gen composite boards, AluVinyl kits, ALU40 vertical kits, Clad100 Black, laser cut). Candidate for extra 301s.
  - Images added from quote builder: 63322 (AG-0172), 63324 (AG-0171), 63328 (AG-0175). 9 products still without image (no list image either): 63460, 63385, 63340, 63338, 63336, 63334, 63332, 63330, 63326.

## 2026-09-30 late — round 2 changes (user request)
- Pergolas: only the 7 live pergola kits (57987, 58015, 58025, 58030, 58036, 58678, 58684; restored from draft, their cleanup redirects 252-257 + 298 DISABLED) + 63803 box kit. All in 398 + 626 (renamed "Patio Cover & Pergola Kits", slug patio-cover-pergola-kits). Other patio-only products drafted (meta _agst_parked=pergola-trim-2026-09-30, incl. 37483/37485/37487/37492 which are not public on live). Multi-system items (slats, screws) just lost the patio categories.
- Click System: 26 products drafted (_agst_parked=click-system-2026-09-30) + 301 to /shop/wall-cladding/; category 377 kept but empty (hidden), its archive /shop/wall-cladding-click-system/ 301 -> /shop/wall-cladding/.
- Y-Corner 63460 drafted.
- Cladding merged: 32975 Clad120 now Black/Teak/White Oak/IPE (new variations 63820-63822); 32907 Clad100 Teak/White Oak/IPE (63824). Drafted + 301 with ?attribute_pa_wall-cladding-color=<slug>: 32997, 33006, 32983 -> 32975; 32964, 37983 -> 32907. Colour names kept as on live (list uses Ipe/Walnut/Yellow Oak — mapping unconfirmed).
- New-product SEO content: storefront.php AGST_Content::get() now returns sections from meta _agst_seo_html (+ lead _agst_seo_lead); model() overrides FAQ from _agst_seo_faq and specs from _agst_seo_specs (JSON). Content JSON per product in /home/claude/ag/content/out/<id>.json (65 products), brief in content/BRIEF.md. Yoast title = "<seo_title> %%sep%% %%sitename%%", metadesc, focuskw set.
- Real-life photos: only verified ALU 40 black slat photos from the live gallery (tag "ALU 40"): 61648, 61646 (pedestrian gates) -> 63717, 63221; 31015, 31014, 30926 (fences) -> 63276, 63283, 63299; 61652 + 31015 -> 63302.
- 2026-10-01: SEO content uploaded for all 65 new products (verified: 200, one h1, FAQ block, no PHP notices, Yoast title ends "Aluglobus Aluminum Systems").
- Brand fix: "Aluglobus Fence / ALU Globus Fence / AluGlobus Fence" replaced with "Aluglobus Aluminum Systems" in 9 published products (desc, Elementor data, Yoast title/metadesc/focuskw) and 7 category descriptions (88, 124, 125, 126, 127, 75, 101). Generic "Aluglobus fence systems" kept. storefront.php: agst_brand_fix() filter on Yoast title/metadesc/OG/Twitter (skips "formerly Aluglobus Fence" and aluglobusfence.com).
- Not touched (site-wide settings): Yoast organisation alternateName "Aluglobus Fence", WPCode schema snippet (HomeAndConstructionBusiness name "Aluglobus Fence", description "Since 2016, AluGlobus Fence..."), WPCode snippet 48849 "Add Brand - Aluglobus Fence for google merchant", chat widget welcome text.
- Redirects 280 and 247 pointed at newly drafted products -> retargeted to /shop/wall-cladding/ and /shop/pergola/. All 54 group-5 redirect targets now return 200 (183 redirects).
- Counts: 144 published products, 284 drafts.

## 2026-10-01 — round 3 (pergola category + Elementor-editable design + content)
- Pergola: subcategory 626 removed from the 8 kits; they sit only in 398 (/shop/pergola/, name restored to live "Pergola"). Category page lists the 8 products directly (H1 "Pergola & Patio Cover Kits" via AGST_ShopFront::H1); /shop system section shows product cards when a system has no groups. Redirect 331: /shop/pergola/patio-cover-pergola-kits/ -> /shop/pergola/ (query passed). Empty subcats 626-632 kept (hidden, no products).
- 6 restored pergola kits (58015/58025/58030/58036/58678/58684) got _agst_managed=1 so they use the uniform template.
- AGST_ElBuild (appended to storefront.php; local copy plugin/agst/elbuild.php + elbuild.css): every published product's body is now standard Elementor widgets (heading/text-editor/image/video/button/toggle in containers with agx classes) generated from the converted content (or _agst_seo_html). Template renders it via Elementor get_builder_content() inside the agx design; in Elementor preview it calls the_content() so "Edit with Elementor" works inside the design. Original Elementor data backed up in _agst_el_original; AJAX agst_el_build (ids, force) / agst_el_restore (REST nonce). Hook clears _elementor_element_cache when _elementor_data changes. All 144 published products migrated.
- model(): _agst_seo_lead now overrides the lead for any product.
- Content: specs (all 49 that had <3) + FAQ (28 without FAQ in body) for old products (_agst_seo_specs/_agst_seo_faq; content/out_old). 59 poor leads rewritten (_agst_seo_lead; content/leads_new.json). 8 products whose body described another product got new body copy (content/out_fix; Yoast fields only filled where empty): 60361, 60359, 60355, 60254, 60033, 38016, 37478, 26565. Lorem ipsum removed from 26639.
- Audit: 144/144 200, one H1, Elementor body, hero image, >=3 specs, FAQ (template or body), no PHP notices.
