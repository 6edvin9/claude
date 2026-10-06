# Aluglobus → Shopify catalog sync

Files in this folder:

| File | What it is |
|---|---|
| `shopify-products-test-3.csv` | 3 products only, for the first test import |
| `shopify-products-all.csv` | The full import (137 website products + 128 old products set to draft) |
| `shopify-change-report.csv` | One line per product: updated / new / hidden / kept, with groups and prices |
| `aluglobus-shopify-theme-v30-catalog.zip` | Your current theme plus the new shop pages and product content layout |

## What the import does

- **69 existing products updated** and **68 new products created**, matching the live website (aluglobusfence.com): titles, full product content, photo galleries, prices, options (Color / Configuration / Finish), SKUs.
- **128 old Shopify products set to Draft**, because they are no longer on the website. They are hidden, not deleted, and can be set back to Active at any time.
- **Your 7 pergola / patio cover pages are not in the file at all**, so nothing about them changes: automatic-louver, slat-roof, beam-roof, insulated, and the 3 cantilever kits.
- **Buy button on:** inventory is not tracked and "continue selling" is on, so every product can be added to the cart (before, stock was tracked at 0, which showed as sold out).
- **Vendor** is set to "Aluglobus Aluminum Systems".
- **Tags** drive the shop structure: the system (`Gates`, `Fences`, `Patio Covers & Pergolas`, `Cladding`), the group (for example `Gate Frame Kits`), `_order:NNNN` for the price-list order, and `Coming soon: …` where the website shows a coming-soon notice.
- **One new product is imported as Draft:** AeroLouver Max Rail 1/5 (Flat Angle L), because it has no price on the website ("Price on request").
- **Weights and box dimensions are left empty for now.** The columns `Variant Grams` and `Variant Packed Length / Width / Height` are in the file, ready to fill in later.

## Steps

1. **Back up.**
   - Products: your own export (`products_export_1.csv`) is the product backup. Keep it.
   - Theme: go to Online Store → Themes → … → **Duplicate** on the live theme.
2. **Upload the theme.** Go to Online Store → Themes → Add theme → Upload zip and choose `aluglobus-shopify-theme-v30-catalog.zip`. **Do not publish it yet.**
3. **Set up the 4 collections** as *smart* collections, each with one condition: **Product tag is equal to …**

   | Collection handle | Title | Tag condition |
   |---|---|---|
   | `gates` | Gates | `Gates` |
   | `fences` | Fences | `Fences` |
   | `pergola` | Patio Covers & Pergolas | `Patio Covers & Pergolas` |
   | `wall-cladding` | Cladding | `Cladding` |

   - These collections already exist (your home page uses them).
   - **If one is already smart:** just change its condition.
   - **If it's manual** (it shows a product list instead of conditions): delete it and create it again as smart. Then set the handle under "Search engine listing" to the one in the table.
   - **The 7 pergola pages:** in Products, select them → **Add tags** → `Patio Covers & Pergolas`. This only adds a tag; their content is not touched.
4. **Test import.**
   1. Go to Products → Import, choose `shopify-products-test-3.csv`, tick **"Overwrite products with matching handles"**, and import.
   2. Preview the new theme (Themes → … → Preview) and open these 3 products:
      - `aluminum-gate-kit-alu40-universal-diy-4-x-6-black`
      - `diy-only-gate-frame-kit-6-x-6-includes-1x-2x1-side-post` (Black / Bronze / White)
      - `box-alu-40-4x6-ft-diy-pedestrian-gate-kit-single-swing-gap-3-8` (new)
   3. Check the photos, content, price, colour buttons, and that Add to cart works.
5. **Full import.** Do the same with `shopify-products-all.csv` (overwrite ticked). Shopify emails you when it finishes.
   - **Keep globusgates.online online until the import is done.** Shopify copies the product photos from there.
6. **Check the theme preview:**
   - `/collections/all` is the shop overview (systems → groups), the same as the website's /shop/.
   - `/collections/gates`, `/collections/fences`, `/collections/pergola` and `/collections/wall-cladding` show every group with its products.
   - Group pages look like `/collections/gates/gate-frame-kits`.
7. **Publish the theme.** In Online Store → Navigation, point the menu items to:
   - Shop → `/collections/all`
   - Gates → `/collections/gates`
   - and so on for the other two collections.
8. **Shipping.** Products have no weight yet. Until weights and dimensions are added, use flat or manual shipping rates, not calculated or weight-based rates.

## Undo

- **Products:** import your original `products_export_1.csv` with "Overwrite products with matching handles" ticked. That puts back the old content, prices, stock settings and status.
  - The 68 new products then remain. Delete them by filtering Products by tag `_order` and status, or use the "new" lines in `shopify-change-report.csv`.
- **Theme:** publish your previous theme again.
- **The old collection layout:** it is kept in the theme as template `collection.legacy`. Any collection can be switched to it.

## Notes

- **Product content:** the long product content is HTML in the description. Its photos load from aluglobusfence.com, the same files the website uses. The product galleries themselves are copied into Shopify.
- **Page layout:** products whose description starts with `<div class="agd"` get the new layout. Every other product (including your 7 pergola pages) keeps its current layout.
