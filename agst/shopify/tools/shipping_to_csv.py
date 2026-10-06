"""Filled shipping sheet -> Shopify CSV that only sets weight and packed box size per variant.

Usage: python3 shipping_to_csv.py filled.xlsx out.csv
Only these columns are in the CSV, so Shopify keeps every other value as it is
("Existing values will be replaced for all columns included in the CSV").
A product is included when at least one of its rows is filled; all its variants are then listed
so no variant is left out of the overwrite.
"""
import csv, sys, collections
from openpyxl import load_workbook

LB_TO_G = 453.59237
src, dst = sys.argv[1], sys.argv[2]
ws = load_workbook(src, data_only=True)['Shipping data']
hdr = [c.value for c in ws[1]]
col = {h: i for i, h in enumerate(hdr)}

# option names per handle, from the files that created / hold the products
opt_name = {}
for path, enc in (('products_export.csv', 'utf-8-sig'), ('out/shopify-products-all.csv', 'utf-8')):
    for r in csv.DictReader(open(path, encoding=enc)):
        if r['Title']:
            opt_name[r['Handle']] = (r['Title'], r['Option1 Name'] or 'Title')

def num(v):
    try:
        f = float(v)
        return f if f > 0 else None
    except (TypeError, ValueError):
        return None

by = collections.OrderedDict()
problems = []
for row in ws.iter_rows(min_row=2, values_only=True):
    h = row[col['Handle (do not edit)']]
    if not h:
        continue
    w = num(row[col['Weight (lb)']])
    dims = [num(row[col[k]]) for k in ('Box length (in)', 'Box width (in)', 'Box height (in)')]
    boxes = int(num(row[col['Boxes per order unit']]) or 1)
    if (w or any(dims)) and not (w and all(dims)):
        problems.append('%s %s: fill weight and all three box sizes' % (h, row[col['Option']] or ''))
    by.setdefault(h, []).append({'option': row[col['Option']] or 'Default Title', 'sku': row[col['SKU']] or '', 'w': w, 'dims': dims, 'boxes': boxes,
                                 'ships': row[col['Ships by']] or ''})

fields = ['Handle', 'Title', 'Option1 Name', 'Option1 Value', 'Variant SKU', 'Variant Grams', 'Variant Weight Unit',
          'Variant Packed Length', 'Variant Packed Width', 'Variant Packed Height', 'Variant Packed Dimension Unit']
out, n_prod, n_var = [], 0, 0
for h, vs in by.items():
    if not any(v['w'] for v in vs):
        continue
    title, oname = opt_name.get(h, ('', 'Title'))
    n_prod += 1
    for i, v in enumerate(vs):
        r = dict.fromkeys(fields, '')
        r.update({'Handle': h, 'Option1 Value': v['option'], 'Variant SKU': v['sku']})
        if i == 0:
            r.update({'Title': title, 'Option1 Name': oname})
        if v['w'] and all(v['dims']):
            r.update({'Variant Grams': str(round(v['w'] * LB_TO_G)), 'Variant Weight Unit': 'lb',
                      'Variant Packed Length': '%g' % v['dims'][0], 'Variant Packed Width': '%g' % v['dims'][1],
                      'Variant Packed Height': '%g' % v['dims'][2], 'Variant Packed Dimension Unit': 'in'})
            n_var += 1
        out.append(r)
with open(dst, 'w', newline='', encoding='utf-8') as f:
    wr = csv.DictWriter(f, fieldnames=fields); wr.writeheader(); wr.writerows(out)
print('%d products, %d variants with shipping data -> %s' % (n_prod, n_var, dst))
for p in problems:
    print('CHECK:', p)
