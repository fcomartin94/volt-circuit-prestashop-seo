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
- Docker Compose (no XAMPP — this project uses Docker for local environment, unlike
  the earlier `brew-co-prestashop` project)

## Progress Log

This project is being built and documented step by step. Each step below records what
was done, why, and how to reproduce it.

### Step 1 — Project design

Defined the store concept and the specific SEO problems to seed intentionally, so the
audit has concrete, realistic issues to find and fix:

- **Store**: "Volt & Circuit" — tech accessories (headphones, chargers, phone cases,
  smartwatches, peripherals), 3–4 categories, ~15–20 products with variants
  (color/capacity) to generate realistic filter combinations.
- **Seeded SEO issues**:
  1. Duplicate URLs from filters and pagination (e.g. `?color=black&capacity=128gb`)
     without canonical tags, indexable, competing with each other.
  2. No `schema.org` Product/Offer structured data on product pages.
  3. Generic or empty meta titles/descriptions on several product pages.
  4. Incomplete or broken sitemap (empty categories or filter URLs leaking in).
  5. Unoptimized images: no compression, no `alt` text, no lazy loading (hurts LCP /
     Core Web Vitals).
  6. Misconfigured `robots.txt` (wrongly blocking or allowing filter/admin routes).

### Step 2 — Local environment setup (Docker)

Decided to use Docker Compose instead of XAMPP for the local environment (lesson learned
from the port conflicts and PHP extension issues documented in `brew-co-prestashop`).

`docker-compose.yml` created with two services:

- `mysql`: MySQL 8.0, with a healthcheck so PrestaShop only starts once the database is
  actually ready to accept connections (avoids the classic "database connection failed"
  error on first boot).
- `prestashop`: official `prestashop/prestashop:8.1-apache` image, manual install mode
  (`PS_INSTALL_AUTO: 0`) so the setup wizard runs in the browser and gives full control
  over shop name, language, and admin credentials.

Exposed on `localhost:8080` (Brew & Co. uses 8080 too, but via PHP's built-in server —
no conflict since they're not run at the same time).

**Reproduce**: from the project root, run:

```bash
docker compose up -d
```

Then open `http://localhost:8080` and follow the PrestaShop install wizard.

**Result**: `docker compose up -d` started both containers successfully. Verified with
`docker compose ps`:

```
NAME                      IMAGE                              STATUS                        PORTS
volt-circuit-mysql        mysql:8.0                          Up (healthy)                  3306/tcp, 33060/tcp
volt-circuit-prestashop   prestashop/prestashop:8.1-apache   Up                             0.0.0.0:8080->80/tcp
```

MySQL healthcheck passed, PrestaShop is up and listening on port 8080.

### Step 3 — PrestaShop install wizard

Walked through the install wizard at `http://localhost:8080`:

- Language: English
- Store name: **Volt & Circuit**
- Main activity: Computer Hardware and Software
- Country: Spain
- SSL: disabled (local environment, no certificate)
- Admin account created with a local-only password
- Demo products: **No** — a custom tech-accessories catalog will be built manually to
  control the seeded SEO issues precisely
- Modules: install all (recommended) — needed later for sitemap/SEO-related modules

**Database configuration issue (diagnosed and fixed)**

The install wizard's database step failed with:

> Database Server is not found. Please verify the login, password and server fields (DbPDO)

despite using the correct values matching `docker-compose.yml` (`server: mysql`,
`database: voltcircuit`, `user: voltcircuit`, `password: voltcircuit`).

Diagnosis process:
1. Confirmed Docker network resolution was fine: `docker exec volt-circuit-prestashop
   getent hosts mysql` resolved correctly to the MySQL container's internal IP.
2. Confirmed PHP's `pdo_mysql` extension was present: `docker exec volt-circuit-prestashop
   php -m | grep -i mysql` → `mysqlnd`, `pdo_mysql`.
3. Tested the exact connection PrestaShop would use directly via PDO from inside the
   container:
   ```bash
   docker exec volt-circuit-prestashop php -r "try { \$p = new PDO('mysql:host=mysql;dbname=voltcircuit', 'voltcircuit', 'voltcircuit'); echo 'OK'; } catch (Exception \$e) { echo 'ERROR: ' . \$e->getMessage(); }"
   ```
   Result: `OK` — connection succeeded with those exact credentials.

Since networking, PHP extensions, and credentials were all confirmed working, the
remaining explanation was a form input issue — a typo introduced in one of the fields
in the browser (likely via autocomplete). Clearing all database fields and manually
retyping them resolved the issue.

**Takeaway for the audit narrative**: this is a good example of methodical debugging —
isolating network, then runtime, then credentials/config before assuming user error,
rather than guessing.

**Result**: installation completed successfully. Admin credentials created:
- Email: `admin@voltcircuit.local`
- Password: local-only, documented privately (not committed)

Removed the `install` folder as required for security before going further:

```bash
docker exec volt-circuit-prestashop rm -rf /var/www/html/install
```

### Step 4 — Catalog structure

Created 4 categories under Home via **Catalog → Categories**:

| ID | Category            | Position |
|----|----------------------|----------|
| 3  | Headphones & Audio   | 1        |
| 4  | Chargers & Cables    | 2        |
| 5  | Phone Cases          | 3        |
| 6  | Smartwatches         | 4        |

### Step 5 — Product creation (Headphones & Audio)

**Product 1: Aether Wireless Headphones** (`layers Product with combinations`)

- Category: Headphones & Audio
- Summary: "Over-ear wireless headphones with active noise cancellation and 30-hour battery life."
- Description: full paragraph covering ANC, memory-foam cushions, 30h battery, Bluetooth 5.3, foldable design.
- Reference: `AWH-001`
- Condition: New
- Attribute created (Catalog → Attributes & Features → Attributes): **Color** (public name "Color", type "Color or texture"), values **Black** and **White**.
- Combinations generated for Color = Black / White:
  - Black → reference `AWH-001-BLK`, qty 50, price impact €0
  - White → reference `AWH-001-WHT`, qty 50, price impact €0
  - Total stock: 100 units
- Pricing: retail price €79.99 tax excl., tax rule "ES Standard rate (21%)" → €96.79 tax incl.
- **SEO tab — intentionally left blank** (seeded issue #3, missing meta tags):
  - Meta title: empty
  - Meta description: empty
  - Friendly URL: left as auto-generated (`aether-wireless-headphones`)
  - Tags: empty
- Status: set to **Online**

This is the first deliberately-seeded SEO problem in the catalog: a live, indexable
product page with no custom meta title/description, which will show up in the
Lighthouse/SEO audit later as relying entirely on PrestaShop's fallback (product name
as title, short description excerpt as meta description) — a common real-world issue
when store owners bulk-import products without filling in SEO fields.

### Step 6 — Bulk product seeding via PHP script

Creating the remaining 12 products by hand through the back office would have taken
a long time, so the rest of the catalog was seeded with a PHP script
(`scripts/seed_products.php`) run inside the PrestaShop container, using PrestaShop's
own bootstrap (`config/config.inc.php` + `init.php`) and ORM classes (`Product`,
`Combination`, `AttributeGroup`, `ProductAttribute`) — the same code path the back
office itself uses, so every record is created with full validation and the same
fields the admin UI exposes (including the SEO tab fields).

The script is idempotent: it looks up each product by `reference` before creating it,
so it can be re-run safely.

**Debugging: `Attribute` class not found**

First run failed on every product with combinations:

```
FAIL  PSE-002 — Attribute::__construct() expects at most 1 argument, 2 given
FAIL  GCC-009 — Call to undefined method Attribute::add()
```

Diagnosis:
1. Assumed `Attribute` was the legacy PrestaShop class for combination values
   (color/size swatches), as in older PrestaShop versions (1.6/1.7).
2. Wrote a standalone debug script (`scripts/debug_attribute.php`) to reflect on the
   class at runtime inside the container. It revealed `Attribute` only had one public
   property (`flags`) and no `add()` method — clearly not a PrestaShop `ObjectModel`.
3. Checked `class_exists('\\Attribute')` — true. This meant PHP was resolving the bare
   `Attribute` symbol to **PHP 8's own native `#[Attribute]` class** (used for
   annotations), not a PrestaShop class, since PrestaShop 8.1 no longer defines a
   class with that exact name in the global namespace.
4. Searched the actual codebase: `grep -rn "^class.*Attribute" /var/www/html/classes/`
   revealed the real class is **`ProductAttributeCore`** (`classes/ProductAttribute.php`)
   — PrestaShop renamed the combination-value entity from `Attribute` to
   `ProductAttribute` at some point in its move toward PS 8.x, freeing up the
   `Attribute` symbol, which PHP 8's native attributes feature then claimed.
5. Fixed the script to instantiate `ProductAttribute` instead of `Attribute`.
   Re-verified via the debug script that `add()` exists and populates `->id`
   correctly before re-running the full seed.

**Takeaway**: a naming collision between a language-level PHP 8 feature and a
same-named legacy class from an older PrestaShop version — worth checking with
`ReflectionClass` and `class_exists()` before assuming API compatibility across major
PrestaShop versions.

**Data cleanup between attempts**: two failed runs left orphaned partial products
(products created via `$product->add()` before their combinations failed). Verified
with a diagnostic query before and after cleanup:

```sql
SELECT p.id_product, p.reference, COUNT(pa.id_product_attribute) as combos
FROM ps_product p
LEFT JOIN ps_product_attribute pa ON pa.id_product = p.id_product
GROUP BY p.id_product ORDER BY p.id_product;
```

Cleaned orphaned rows across `ps_product`, `ps_product_lang`, `ps_product_shop`,
`ps_category_product`, `ps_stock_available`, `ps_product_attribute`, and
`ps_product_attribute_shop` (keeping `AWH-001`, created manually in Step 5, and
reusing already-created attribute groups/values instead of deleting them, since the
script's get-or-create logic handles that safely).

**Final result — full catalog seeded successfully:**

| id_product | reference | combinations |
|---|---|---|
| 1  | AWH-001 | 2 (Color: Black/White) |
| 27 | PSE-002 | 2 (Color: Black/Blue) |
| 28 | NSH-003 | 2 (Color: Black/Silver) |
| 29 | EMS-004 | 3 (Color: Black/Red/White) — no meta tags (seeded issue) |
| 30 | VGC-005 | 0 (standard product, no combinations) |
| 31 | FUC-006 | 2 (Color: Black/White) — no meta tags (seeded issue) |
| 32 | PWB-007 | 2 (Capacity: 10000mAh/20000mAh) |
| 33 | WCP-008 | 2 (Color: Black/White) |
| 34 | GCC-009 | 2 (Model: iPhone 15/iPhone 15 Pro) — no meta tags (seeded issue) |
| 35 | TLC-010 | 4 (Color x Model: 2x2) |
| 36 | SRC-011 | 2 (Color: Black/Green) |
| 37 | CFW-012 | 3 (Color: Black/Silver/Rose Gold) — no meta tags (seeded issue) |
| 38 | PHT-013 | 2 (Color: Black/White) |

13 products total, 26 product-attribute combinations, across all 4 categories.
5 products (AWH-001, EMS-004, FUC-006, GCC-009, CFW-012) were deliberately left
without meta title/description — the seeded SEO issue that the audit phase will
surface and quantify.

Product images were intentionally skipped at this stage to move directly into the SEO
audit; they can be added later for final screenshots if needed for the front-office
presentation.

### Step 7 — Technical SEO audit: "before" state

Inspected the rendered HTML `<head>` of a product page seeded with no meta title/
description (**Echo Mini Speaker**, `EMS-004`) via browser "View Page Source", to see
what actually ships to search engines rather than assuming from the back-office form.

**Finding 1 — meta tag fallback, not a blank page.**
PrestaShop does not leave `<title>`/`<meta description>` empty when the fields are
unset in the back office. It falls back automatically:
- `<title>Echo Mini Speaker</title>` — just the raw product name, no brand suffix,
  no differentiation between products beyond the name itself.
- `<meta name="description" content="Compact portable Bluetooth speaker with 12-hour
  battery and rich bass.">` — pulled verbatim from the product's short description.

This reframes the seeded issue: it's not "missing SEO metadata" (a blank page would
be an obvious, easy-to-spot problem). It's the more realistic and more common
real-world issue — **generic, unmanaged metadata that happens to look present**,
making it invisible to a superficial check and only catchable by comparing it against
a page that has custom meta title/description. This is a better, more honest case
study than the originally planned "empty tag" scenario.

**Finding 2 — this PrestaShop theme (Classic) already ships full JSON-LD structured
data out of the box**: `Organization`, `WebSite`/`WebPage`, `BreadcrumbList`, and a
complete `Product` schema with nested `Offer` (price, currency, SKU, MPN, availability,
seller). This means the originally planned seeded issue #2 ("no schema.org data") does
not hold for a stock PrestaShop 8.1 + Classic theme install — the platform handles this
by default. The audit will pivot this axis toward **checking correctness of the
auto-generated schema** instead of its presence (see Finding 3).

**Finding 3 — stock/availability mismatch to investigate.**
The `Product` JSON-LD for Echo Mini Speaker's Black variant reports
`"availability": "https://schema.org/OutOfStock"`, and the front-end page itself
displays an "Out-of-Stock" flag and a disabled "Add to cart" button — despite the
seed script setting `qty: 30` for the Black combination. This needs to be verified
against the database directly (is the quantity actually stored against the right
`id_product_attribute`, or did `StockAvailable` not get updated per-combination) before
deciding whether this is a seeding bug to fix or something to note separately from
the SEO audit itself.

**Finding 4 — combination URLs use a hash fragment, not a query string.**
Color/variant switching shows up in the URL as
`.../29-27-echo-mini-speaker.html#/1-color-black` — a `#` fragment, which browsers
never send to the server and search engines don't generally index as separate URLs.
This means the classic "duplicate content from color/size filter URLs" problem, as
originally planned, **does not occur this way on individual product pages** in this
PrestaShop setup. The duplicate-URL angle of the audit will instead be tested on
**category listing pages** with PrestaShop's faceted search / layered navigation
filters (which do use real query-string URLs, e.g. `?q=Color-Black`), and on
pagination — both still to be verified.

### Step 8 — Bug fix: stock not propagating to combinations (Finding 3 resolved)

Verified Finding 3 directly against the database:

```sql
SELECT pa.id_product_attribute, pa.reference, sa.quantity
FROM ps_product_attribute pa
LEFT JOIN ps_stock_available sa ON sa.id_product_attribute = pa.id_product_attribute
WHERE pa.id_product = 29;
```

Result: all three Echo Mini Speaker combinations showed `quantity = 0` in
`ps_stock_available`, despite `seed_products.php` setting `$combination->quantity =
$row['qty']` (e.g. 30) before calling `$combination->add()`.

**Root cause**: `Combination->quantity` is a property inherited from `ObjectModel`,
but PrestaShop does not use it as the source of truth for availability — stock per
combination is tracked separately in `ps_stock_available`, managed through the
`StockAvailable` class. This is the same pattern already used correctly for
combination-less products (`StockAvailable::setQuantity($product->id, 0, 100)`), but
it was missed for the two combination-generation branches in the original script.

**Fix**: added `StockAvailable::setQuantity($product->id, $combination->id,
$row['qty'])` right after `$combination->setAttributes(...)` in both branches of
`seed_products.php` (single-axis and two-axis combinations).

**Applying the fix to already-created data**: rather than deleting and re-seeding
all 13 products again, wrote a small one-off script (`scripts/fix_stock.php`) that
maps each combination's `reference` to its intended quantity (taken from the original
seed data) and calls `StockAvailable::setQuantity()` directly. Ran it once:

```bash
docker cp scripts/fix_stock.php volt-circuit-prestashop:/var/www/html/fix_stock.php
docker exec volt-circuit-prestashop php /var/www/html/fix_stock.php
```

Result: 26 combinations fixed, 2 skipped (`AWH-001-BLK`/`WHT`, created manually
through the back office with correct stock already). Verified Echo Mini Speaker
specifically afterward — all three combinations now show `quantity = 30` as intended.

**Takeaway**: a good example of not trusting an ORM property name at face value —
`quantity` looked like the obvious field to set, but PrestaShop's stock model splits
"basic combination data" from "availability tracking" into two different tables/classes,
and only reading the actual `ps_stock_available` table (not just the back office UI,
which could have been rechecked instead) confirmed the real behavior.

### Step 9 — Finding 4 revisited: no layered navigation, so no filter-URL duplicates (yet)

Checked the Headphones & Audio category page (`/en/3-headphones-audio`) expecting to
find a Color filter in the sidebar (the layered navigation / faceted search feature),
which is what would generate the classic `?q=Color-Black` duplicate-URL problem.

**Result**: the sidebar only shows empty "Brands" and "Suppliers" blocks — no
attribute-based filters at all. The `ps_facetedsearch` module is either not installed,
not active, or not configured to expose the Color/Model/Capacity attribute groups as
filters on this fresh install.

**Decision**: rather than enabling and configuring the module to manufacture the
originally-planned duplicate-URL scenario, this is being kept as-is and logged as a
finding in its own right: **a multi-variant tech catalog (color options across most
products) with no working layered navigation is itself a real, common ecommerce SEO/UX
issue** — customers can't filter by color, and search engines have no filter-based
duplicate-content risk only because the filtering feature itself isn't working. Both
sides of that trade-off are worth noting in the final report.

The duplicate-content audit will instead focus on what's actually present on this
install: pagination parameters, sorting parameters (`?order=...`), and the
combination-switching hash fragments already seen in Finding 4 (informational — no
SEO risk since fragments aren't crawled as separate URLs).

<!-- Next: sitemap.xml check. -->

### Step 10 — sitemap.xml check

Checked `http://localhost:8080/sitemap.xml` directly: **404, page not found.**

Checked `http://localhost:8080/en/sitemap` (the human-facing HTML sitemap page,
different from an XML sitemap): it exists and correctly lists all 4 categories
(Headphones & Audio, Chargers & Cables, Phone Cases, Smartwatches) plus static pages.
However, the top of that same page currently shows **"No products available yet"**,
despite the catalog having 13 active products — most likely a caching issue from the
bulk PHP seed script bypassing whatever cache invalidation the back office UI
normally triggers. Worth re-checking after a cache clear, separately from the main
sitemap finding.

**Cache issue resolved.** Cleared the cache from the back office
(**Advanced Parameters → Performance → Clear cache**, the standard supported way,
rather than manually deleting cache folders via the shell) and reloaded `/en/sitemap`:
the "No products available yet" notice was gone, confirming it was Smarty template
cache left stale after the bulk PHP seed script bypassed whatever cache invalidation
the back office UI normally triggers on product creation. Good reminder that
programmatic catalog changes via the ORM don't always trigger the same
side-effects (cache busting, search index updates) that the admin UI does.

**Finding 6 — no XML sitemap exists at all.** This is more severe than the originally
planned "incomplete sitemap" scenario: there is no `sitemap.xml` for search engines to
consume. PrestaShop does not generate one by default in 8.1 — this requires
installing/enabling a dedicated module (historically `gsitemap`, or handled via the
SEO & URLs section in newer versions). Combined with Finding 5 (no `Sitemap:` directive
in `robots.txt`), search engines have no structured way to discover the full catalog
short of following internal links — for a real store, this is a significant discovery
gap, especially on a subcategory-heavy catalog. This is one of the clearest, most
fixable findings in the whole audit.

<!-- Next: robots.txt check. -->

### Step 11 — robots.txt check

Fetched `http://localhost:8080/robots.txt` directly.

**Result: this file is actually well-configured out of the box.** PrestaShop
auto-generates a sensible default:
- Blocks private/account pages: cart, login, registration, checkout, order history,
  password recovery, my-account, guest tracking.
- Blocks irrelevant query-string variations that would otherwise create crawlable
  duplicate URLs: `?order=`, `?tag=`, `?id_currency=`, `?search_query=`, `?back=`,
  `?n=` (and their `&` equivalents).
- Blocks internal/technical directories: `/classes/`, `/config/`, `/vendor/`,
  `/cache/`, `/webservice/`, etc.
- Does **not** block anything that should be indexable — categories, product pages,
  and static content pages are left open, which is correct.

**Finding 5 — no `Sitemap:` directive.** The file has no `Sitemap:
http://localhost:8080/sitemap...xml` line pointing search engines to the sitemap.
This is a real, common, easy-to-miss issue: even a technically correct `robots.txt`
provides no help discovering the sitemap unless it's also submitted manually via
Search Console — adding the directive removes that dependency and is a one-line fix.

<!-- Next: image optimization check. -->

### Step 12 — Image optimization and Core Web Vitals (Lighthouse)

Ran Lighthouse (CLI) against the Echo Mini Speaker product page
(`/en/29-echo-mini-speaker.html`, mobile form factor) to get real, reproducible
Performance/SEO metrics instead of relying on visual impressions.

**Toolchain note**: no desktop Chrome browser was installed on the machine (by
choice), so Lighthouse was run headlessly using a Puppeteer-managed, isolated
"Chrome for Testing" binary (`npx puppeteer browsers install chrome`) — a build meant
for automation, separate from installing Chrome as a daily-use application.

```bash
npm install -g lighthouse
npx puppeteer browsers install chrome
export CHROME_PATH="<path to Chrome for Testing binary>"
lighthouse http://localhost:8080/en/29-echo-mini-speaker.html \
  --output=html --output=json \
  --output-path=./audit/before-product-EMS-004 \
  --form-factor=mobile --screenEmulation.mobile \
  --quiet --chrome-flags="--headless"
```

**"Before" scores (mobile) — Echo Mini Speaker product page:**

| Category | Score |
|---|---|
| Performance | 67 |
| Accessibility | 73 |
| Best Practices | 96 |
| SEO | 85 |
| Agentic-browsing | 50 |

**Key audit details:**

| Audit | Result |
|---|---|
| Largest Contentful Paint | 5.7s (score 0.17 — poor) |
| Image alt text | **Failed (score 0)** |
| Meta description | Passed (score 1) — confirms Finding 1: PrestaShop's fallback description is technically valid, just generic |
| Document title | Passed (score 1) — same caveat as above |
| Is crawlable | Passed |
| Canonical tag | Passed |
| Structured data | Not scored by this Lighthouse audit directly (validity confirmed manually via the JSON-LD seen in Step 7) |

**Two concrete, measurable problems to fix:**
1. **LCP of 5.7 seconds is poor** (Google's threshold for "good" is under 2.5s). The
   product page currently serves PrestaShop's default placeholder image
   (`en-default-large_default.jpg`) at full size with no real optimization, since no
   product images were uploaded yet (deferred earlier in Step 5/6 to prioritize the
   audit itself). This will be revisited once real images are added — expect LCP to
   improve or worsen depending on format/compression choices, which is exactly the
   kind of before/after comparison this audit is meant to produce.
2. **Missing `alt` attributes on images — confirmed, score 0.** This directly
   corroborates the originally seeded issue (#5, unoptimized images including missing
   alt text), now backed by a real automated audit score rather than a visual
   inspection.

<!-- Next: fix images (add alt text once product images are uploaded, evaluate compression/format), then move to Step 4 (apply SEO corrections) covering: custom meta title/description on the 5 flagged products, adding a Sitemap directive + generating an actual sitemap.xml, and re-running Lighthouse for the "after" comparison. -->

## Applying fixes

### Step 13 — Generating a real sitemap.xml (fixes Finding 6)

Went to **Modules → Module Manager**, searched "sitemap", found the **Google
Sitemap** module (`gsitemap` v4.4.0) already installed but never configured/run
("This shop has no sitemap yet.").

Configured it: update frequency set to `weekly`, left the default excluded pages as-is
(checkout/account/technical pages that shouldn't be in a sitemap), then generated.

**Result**: sitemaps created successfully — `1_index_sitemap.xml` as the master file,
referencing `1_en_0_sitemap.xml` and `1_es_0_sitemap.xml`. Verified
`1_en_0_sitemap.xml` directly: contains all 13 products (priority 0.9) and all 4
categories (priority 0.8), plus the homepage (priority 1.0), each with a correct
`lastmod` timestamp. This fully resolves Finding 6.

### Step 14 — Adding the `Sitemap:` directive to robots.txt (fixes Finding 5, with a caveat)

Went to **Shop Parameters → Traffic & SEO → SEO & URLs**, found the "Robots file
generation" section with a "Generate robots.txt" button. This regenerates the file
from PrestaShop's built-in template — there's no UI field to add custom lines here.

Clicked "Generate" to get a fresh, current `robots.txt`, then inspected it directly:

```bash
docker exec volt-circuit-prestashop find /var/www/html -maxdepth 1 -iname "robots.txt"
```

**Finding 7 (self-correcting mid-audit): PrestaShop does auto-generate a `Sitemap:`
directive once a sitemap module is installed — it just uses the wrong URL in a local
environment.** After regenerating, the file already contained:

```
# Sitemap
Sitemap: http://localhost/1_index_sitemap.xml
```

Note the missing `:8080` port. This happens because the URL is built from
`Configuration::get('PS_SHOP_DOMAIN')`, which stores just the hostname
(`localhost`) — correct behavior on a standard port-80 production domain, but wrong
in a local Docker setup exposed on a non-standard port.

Initially appended a second, manually-corrected line
(`Sitemap: http://localhost:8080/1_index_sitemap.xml`) via shell, which produced a
robots.txt with two duplicate `Sitemap:` lines pointing to two different URLs — a
worse state than either alone. Decided this was purely a local-environment port
artifact, not a real production issue worth "fixing" by hand, so removed the manual
line and kept only the natively-generated one:

```bash
docker exec volt-circuit-prestashop sed -i '/# Sitemap/,$d' /var/www/html/robots.txt
docker exec volt-circuit-prestashop sh -c 'echo "" >> /var/www/html/robots.txt && echo "# Sitemap" >> /var/www/html/robots.txt && echo "Sitemap: http://localhost/1_index_sitemap.xml" >> /var/www/html/robots.txt'
```

**Takeaway for the report**: on a real production domain (`https://voltcircuit.com`,
no custom port), this directive would be generated correctly out of the box once
`PS_SHOP_DOMAIN` is set properly during deployment — this is a local-dev-only quirk,
not a genuine SEO defect to report as a client-facing recommendation. Worth noting in
the final report as "verified expected behavior on production domains" rather than a
bug fix, to keep the audit honest about what's a real issue versus a local artifact.

### Step 15 — Adding custom meta title/description to the 5 flagged products (fixes Finding 1)

Done through the back office (**Catalog → Products → [product] → SEO tab**) rather
than by script, since editing 5 content fields by hand is quick and this is exactly
the kind of task the admin UI is built for — scripting only made sense earlier for
bulk catalog creation (13 products × several fields each).

| Product | Meta title | Meta description |
|---|---|---|
| Aether Wireless Headphones (AWH-001) | Aether Wireless Headphones \| Volt & Circuit | Shop the Aether Wireless Headphones: over-ear ANC headphones with 30-hour battery life and Bluetooth 5.3, in black or white. |
| Echo Mini Speaker (EMS-004) | Echo Mini Speaker \| Volt & Circuit | Shop the Echo Mini Speaker: a compact portable Bluetooth speaker with 12-hour battery life and rich bass, in black, red, or white. |
| Flexi USB-C Cable 2m (FUC-006) | Flexi USB-C Cable 2m \| Volt & Circuit | Shop the Flexi USB-C Cable: a braided 2-meter cable rated for 100W fast charging and 480Mbps data transfer, in black or white. |
| Guardian Clear Case (GCC-009) | Guardian Clear Case \| Volt & Circuit | Shop the Guardian Clear Case: a slim clear case with reinforced corners for drop protection, for iPhone 15 and iPhone 15 Pro. |
| Chrono Fit Watch (CFW-012) | Chrono Fit Watch \| Volt & Circuit | Shop the Chrono Fit Watch: a fitness smartwatch with heart-rate monitoring, GPS, and 7-day battery life, in black, silver, or rose gold. |

All 5 products previously relying on PrestaShop's generic fallback (bare product name
as title, short description as meta description — see Finding 1) now have
brand-consistent, differentiated meta tags. This will be re-verified against the
rendered HTML `<head>` and re-scored with Lighthouse in the "after" measurement phase.

### Step 16 — Adding product images with alt text (fixes Lighthouse `image-alt` finding)

No real product photography was available, so 13 simple placeholder images (colored
background + product name, 800×800 JPG) were generated with Python/Pillow — one per
product, distinct color per category to keep them visually distinguishable — purely
to have real, non-generic images to measure Core Web Vitals and alt-text compliance
against, rather than PrestaShop's shared default placeholder image.

Since the images were generated outside the Docker environment (in a separate sandbox),
they had to be moved to the Mac's filesystem first, then copied into the container:

```bash
docker cp ~/Desktop/github/fcomartin94/volt-circuit-prestashop-seo/scripts/product_images/. \
  volt-circuit-prestashop:/tmp/product_images/
```

Wrote `scripts/attach_images.php`, using PrestaShop's own `Image` class (not raw file
copies) so every configured thumbnail size gets generated correctly and the image is
registered in `ps_image`/`ps_image_lang` — including the **alt text** field
(`Image->legend`), set to `"<Product Name> — product photo"` for each of the 13
products. Idempotent: skips any product that already has an image.

```bash
docker cp scripts/attach_images.php volt-circuit-prestashop:/var/www/html/attach_images.php
docker exec volt-circuit-prestashop php /var/www/html/attach_images.php
```

Result: all 13 images attached successfully. Verified directly in the rendered HTML
(View Page Source on Echo Mini Speaker): the `alt` attribute is present and correct on
every rendering of the image — main product image, zoom modal, thumbnail, and even on
this product's card when it appears in "related products" listings on other product
pages. This resolves the Lighthouse `image-alt` finding (previously scored 0).

**Side confirmation**: while inspecting the updated HTML, also re-verified the Step 8
stock fix and Step 15 meta tag fix are both live and correct on this same page —
`quantity: 30`, `"availability": "https://schema.org/InStock"`, and the custom
`<title>`/`<meta description>` are all rendering as expected.

## Results: before vs. after

### Step 17 — Re-running Lighthouse for the "after" measurement

Ran the exact same Lighthouse command as the "before" measurement (Step 12), same URL,
same device emulation (mobile), same flags — the only variable changed is the site
itself, after all fixes from Steps 13–16:

```bash
lighthouse http://localhost:8080/en/29-echo-mini-speaker.html \
  --output=html --output=json \
  --output-path=./audit/after-product-EMS-004 \
  --form-factor=mobile --screenEmulation.mobile \
  --quiet --chrome-flags="--headless"
```

### Comparison — Echo Mini Speaker product page (mobile)

| Category | Before | After | Change |
|---|---|---|---|
| Performance | 67 | 83 | +16 |
| Accessibility | 73 | 77 | +4 |
| Best Practices | 96 | 92 | −4 |
| SEO | 85 | 92 | +7 |
| Agentic-browsing | 50 | 50 | no change |

| Audit | Before | After |
|---|---|---|
| Largest Contentful Paint | 5.7s (score 0.17 — poor) | 3.6s (score 0.60 — improved, still not "good" by Google's <2.5s threshold) |
| Image alt text | Failed (score 0) | **Passed (score 1)** |
| Meta description | Passed (already, generic fallback) | Passed (custom, brand-consistent) |
| Document title | Passed (already, generic fallback) | Passed (custom, brand-consistent) |
| Canonical tag | Passed | Passed |

**Reading the results honestly:**
- **SEO score improvement (85 → 92) and the `image-alt` fix (0 → 1) are the clearest,
  most direct wins** — both are a straight consequence of the fixes applied (custom
  meta tags, real images with alt text).
- **Performance improved substantially (67 → 83) and LCP dropped from 5.7s to 3.6s**,
  mainly because the page now loads a real, appropriately-sized 800×800 JPG instead of
  PrestaShop's shared, larger default placeholder image being requested fresh (no
  browser cache) on every Lighthouse run. LCP at 3.6s is still above Google's "good"
  threshold (2.5s) — the placeholder JPGs used here are simple and already fairly
  small (~20KB), so further LCP improvement in a real deployment would come from
  choosing modern formats (WebP/AVIF), proper responsive `srcset` sizing, and a CDN —
  not covered in this local Docker environment.
- **Best Practices dropped slightly (96 → 92)**, which is worth reporting rather than
  hiding: adding real images likely introduced a new Lighthouse best-practices flag
  (commonly related to image aspect ratio or explicit sizing attributes) that wasn't
  present when the page had no custom image at all. This is a good, honest reminder
  that fixing one category can surface a different, smaller issue — worth a follow-up
  investigation rather than an unqualified "everything improved" claim.
- **Accessibility improved modestly (73 → 77)**, consistent with adding alt text,
  though other accessibility issues (unrelated to this audit's scope) likely remain.

Full before/after Lighthouse reports (HTML + JSON) are saved in `audit/` for reference
and can be opened directly in a browser.

## Screenshots

See `/screenshots`:
- `home.png` — storefront homepage
- `category.png` — Headphones & Audio category listing
- `product_example.png` — Echo Mini Speaker product page (post-fix: custom meta tags,
  real image with alt text, in-stock combinations)
- `lighthouse_before.png` — Lighthouse scores before the audit fixes
- `lighthouse_after.png` — Lighthouse scores after the audit fixes

<!-- Next: record a short demo video, then push to GitHub and add the project to the portfolio site. -->
