# Volt & Circuit — PrestaShop Technical SEO Audit

Fictional tech-accessories store built with PrestaShop, deliberately seeded with common
ecommerce technical SEO issues, then audited and fixed as a documented case study.

## Demo
[Watch demo video](https://youtu.be/46dCulNnAhI)

## Goal

Demonstrate technical SEO auditing and remediation skills on a real ecommerce platform:
diagnose duplicate content from filters/pagination, missing structured data, broken
sitemaps, unoptimized images, and fix them with measurable before/after results.

## Stack

- PrestaShop 8.1 (Docker image `prestashop/prestashop:8.1-apache`)
- MySQL 8.0 (Docker)
- Docker Compose for the local environment
- Lighthouse (CLI, headless Chrome) for Core Web Vitals and SEO scoring

## The store

"Volt & Circuit" — a tech-accessories catalog (headphones, chargers, phone cases,
smartwatches) seeded across 4 categories with 13 products and 26 product-attribute
combinations (color/capacity variants), built to generate realistic conditions for a
technical SEO audit rather than a minimal demo catalog.

Local environment: `docker-compose.yml` runs `mysql` (with a healthcheck so PrestaShop
only starts once the database is ready) and the official
`prestashop/prestashop:8.1-apache` image in manual install mode, exposed on
`localhost:8080`.

```bash
docker compose up -d
```

Catalog seeding for 12 of the 13 products was scripted (`scripts/seed_products.php`)
using PrestaShop's own bootstrap and ORM classes rather than the admin UI, since
creating that many products with variant combinations by hand would have been slow —
the script goes through the same validation path the back office uses, including the
SEO tab fields, and is idempotent (safe to re-run).

## Findings and fixes

### Meta title/description: present but generic, not missing

Five products were deliberately left without custom meta title/description. Rather than
showing a blank `<title>`/`<meta description>`, PrestaShop falls back automatically to
the raw product name and an excerpt of the short description — meaning the page *looks*
covered on a superficial check, but carries no differentiated, brand-consistent
metadata. This is the more realistic version of the issue: generic, unmanaged metadata
that happens to be present is harder to catch than an obviously empty tag, and is a
common real-world pattern when stores bulk-import products without filling in SEO
fields.

Added custom meta title/description to all 5 flagged products via the back office SEO
tab — a brand-consistent title (`<Product Name> | Volt & Circuit`) and a description
covering the product's key differentiators, replacing the generic fallback.

### Structured data: present by default, correctness matters more than presence

The Classic PrestaShop theme ships full JSON-LD structured data out of the box —
`Organization`, `WebSite`/`WebPage`, `BreadcrumbList`, and a `Product` schema with a
nested `Offer` (price, currency, SKU, MPN, availability, seller). Presence isn't the
issue on a stock PrestaShop 8.1 install; correctness is, as the stock/availability
issue below shows.

### Stock not propagating to product combinations

The `Product` JSON-LD for one product's combinations reported `OutOfStock`, and the
storefront showed a disabled "Add to cart" button, despite the seed script setting a
quantity for each combination. `Combination->quantity` is a property inherited from
PrestaShop's base `ObjectModel`, but PrestaShop doesn't use it as the source of truth
for availability — stock per combination is tracked separately via the
`StockAvailable` class, writing to `ps_stock_available`. The seed script correctly used
`StockAvailable::setQuantity()` for simple products but missed it in the two
combination-generation branches.

Added the missing `StockAvailable::setQuantity($product->id, $combination->id, $qty)`
call to both combination branches in the seed script, then corrected the 26
already-created combinations directly (`scripts/fix_stock.php`) rather than re-seeding
the catalog from scratch.

### No duplicate URLs from color/variant filtering — because layered navigation isn't active

The classic "duplicate content from color/size filter URLs" scenario didn't occur on
this install: product-level variant switching uses a `#` hash fragment (never sent to
the server, not indexed as a separate URL), and the category pages show no
attribute-based filters at all — the faceted search module isn't active on this fresh
install. A multi-variant catalog with no working layered navigation is itself a real,
common issue in its own right: customers can't filter by color, which is arguably a
bigger UX/discoverability problem than the duplicate-URL scenario it was meant to
demonstrate.

### No XML sitemap

`sitemap.xml` returned 404. PrestaShop does not generate one by default — it requires
installing and configuring a dedicated module. This is a more significant gap than an
"incomplete" sitemap: with no sitemap and no `Sitemap:` directive in `robots.txt`
either, search engines have no structured way to discover the full catalog beyond
following internal links.

Configured the Google Sitemap module (already installed, never run), which generated a
correct sitemap set — an index file referencing per-language sitemaps covering all 13
products (priority 0.9), all 4 categories (priority 0.8), and the homepage (priority
1.0), each with a correct `lastmod`.

### `robots.txt`: well-configured by default, missing the sitemap directive

PrestaShop's auto-generated `robots.txt` correctly blocks private/account pages and
irrelevant query-string variations (`?order=`, `?search_query=`, etc.) without blocking
anything that should be indexable. The one gap was a missing `Sitemap:` directive.
Regenerating the file from the back office after installing the sitemap module added
this automatically. The generated URL is built from `PS_SHOP_DOMAIN`, so on a real
production domain it resolves correctly; the local Docker setup's non-standard port
isn't reflected in it, which only matters for local testing.

### Missing image alt text and unoptimized images

Lighthouse scored `image-alt` at 0, matching the seeded issue. Since no real product
photography existed, 13 placeholder product images were generated and attached via
PrestaShop's own `Image` class — which handles thumbnail generation and registers each
image's alt text field (`legend`) correctly — rather than copying files directly, which
would have skipped that metadata.

## Results: before vs. after

Measured with Lighthouse (CLI, mobile emulation) against the same product page
(`Echo Mini Speaker`) before and after the fixes above, changing nothing else between
runs.

| Category | Before | After | Change |
|---|---|---|---|
| Performance | 67 | 83 | +16 |
| Accessibility | 73 | 77 | +4 |
| Best Practices | 96 | 92 | −4 |
| SEO | 85 | 92 | +7 |

| Audit | Before | After |
|---|---|---|
| Largest Contentful Paint | 5.7s (poor) | 3.6s (improved, still above Google's 2.5s "good" threshold) |
| Image alt text | Failed | Passed |
| Meta description | Passed (generic fallback) | Passed (custom, brand-consistent) |
| Document title | Passed (generic fallback) | Passed (custom, brand-consistent) |
| Canonical tag | Passed | Passed |

The SEO score (+7) and the alt-text fix are the clearest, most direct wins — both are a
straight consequence of the fixes applied. Performance improved substantially (67 → 83,
LCP down from 5.7s to 3.6s) mainly because the page now loads a real, appropriately
sized image instead of PrestaShop's larger shared default placeholder. LCP is still
above the "good" threshold; closing that remaining gap in a real deployment would mean
modern image formats (WebP/AVIF), responsive `srcset` sizing, and a CDN — none of which
are meaningful to test in a local Docker environment. Best Practices dropped slightly
(96 → 92), consistent with the added image introducing a new sizing/aspect-ratio flag
that a page with no custom image simply didn't have.

Full before/after Lighthouse reports (HTML + JSON) are saved in `audit/`.

## Screenshots

See `/screenshots`:
- `home.png` — storefront homepage
- `category.png` — Headphones & Audio category listing
- `product_example.png` — product page after fixes (custom meta tags, real image with
  alt text, in-stock combinations)
- `lighthouse_before.png` / `lighthouse_after.png` — Lighthouse scores before and after
