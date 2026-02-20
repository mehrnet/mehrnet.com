# FossBilling Static Website Generator

A fully static website powered by FossBilling as the single source of truth.

- Data source: FossBilling API
- Data builder: `gen.php`
- Static output: `data.json`
- Frontend: `index.html`, `style.css`, `script.js`

The website contains no hardcoded branding or pricing. Everything renders dynamically from `/data.json`.

## How It Works

1. `gen.php` calls FossBilling APIs (products, categories, addons, domains, currencies, hosting plans, gateways).
2. Hosting plan limitations are fetched and merged into products to populate feature data.
3. Data is normalized and filtered to **UI-safe public fields only**.
4. Output is written to `data.json`.
5. `script.js` fetches `/data.json` at runtime and renders all sections dynamically with proper formatting.

## File Overview

- `gen.php`: Fetches and compiles data from FossBilling.
- `data.json`: Generated dataset consumed by frontend.
- `index.html`: Static shell and section placeholders.
- `style.css`: Local-only styles, CSS light/dark system (`prefers-color-scheme`).
- `script.js`: Client renderer for `/data.json`.
- `.env.example`: Generator configuration template.

## Setup

### 1. Environment Configuration

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Edit `.env` with your FossBilling credentials and site URL:

```ini
BILLING_BASE_URL=https://billing.example.com
BILLING_API_KEY=your_api_key_here
PUBLIC_SITE_URL=https://example.com
```

#### Getting Your FossBilling API Key

1. Log into your FossBilling admin panel
2. Navigate to **Settings → API** (or **System → API Tokens**)
3. Create a new API token with these permissions:
   - `guest:system:company`
   - `guest:product:get_list`
   - `guest:product:get`
   - `guest:extension:theme`
   - `guest:domain:get_list`
   - `guest:currency:get_list`
   - `admin:extension:config_get`
4. Copy the API key to `BILLING_API_KEY` in `.env`

### 2. Required Environment Variables

- `BILLING_BASE_URL`: Your FossBilling dashboard URL
- `BILLING_API_KEY`: API token from FossBilling (see above)
- `PUBLIC_SITE_URL`: Your public website URL (used for asset links)

### 3. Optional Variables

```ini
# Branding customizations (override FossBilling settings)
SITE_LOGO_URL=https://example.com/logo.svg
SITE_LOGO_DARK_URL=https://example.com/logo-dark.svg
SITE_FAVICON_URL=https://example.com/favicon.svg
SITE_MOTTO="Your company motto"
SITE_BRAND_MARK="™"  # Displays next to company name

# Generation options
PRETTY_JSON=1          # Pretty-print data.json (default: 1)
SHOW_ERRORS=0          # Show verbose errors (default: 0)
STRICT_TLS=1           # Verify HTTPS certificates (default: 1)
```

## Generate Data

### Manual Generation

```bash
php gen.php
```

This generates `data.json` in the current directory. Customize output path:

```bash
php gen.php --out=./public/data.json
```

### Available Flags

| Flag | Default | Example |
|------|---------|---------|
| `--out` | `./data.json` | `--out=./public/data.json` |
| `--pretty` | `1` | `--pretty=0` (minified JSON) |
| `--show-errors` | `0` | `--show-errors=1` (verbose output) |
| `--timeout` | `25` | `--timeout=30` (seconds per request) |
| `--max-pages` | `25` | `--max-pages=50` |
| `--per-page` | `100` | `--per-page=50` |
| `--base-url` | `.env` value | `--base-url=https://billing.example.com` |
| `--public-url` | `.env` value | `--public-url=https://example.com` |
| `--strict-tls` | `1` | `--strict-tls=0` (allow self-signed certs) |
| `--exclude-patterns` | none | `--exclude-patterns=tld,domain registration` |

### Automatic Updates via Cronjob

Add a cron job to run the generator periodically:

```bash
# Edit crontab
crontab -e

# Run every 5 minutes (every */5)
*/5 * * * * php /path/to/gen.php --out=/path/to/public/data.json
```

Adjust the interval as needed:
- `*/5` = every 5 minutes
- `*/15` = every 15 minutes
- `*/30` = every 30 minutes
- `0 * * * *` = every hour
- `0 */4 * * *` = every 4 hours

## UI Behavior & Edge Cases

### Product Filtering

- **Category Filters**: Users can filter products by category using buttons in the header
- **Single Category**: If only one category exists, it displays automatically
- **No Products**: Shows "No active products in this category" message

### Billing Periods

- **Multiple Periods**: Products with multiple billing periods show a dropdown selector
- **Single Period**: If product has only one period option, no selector appears
- **Per-Product**: Each product maintains its own selected period (not global)

### Pricing Display

- **Currency Switch**: Currency changes update all prices instantly
- **Missing Pricing**: Displays "N/A" if product has no pricing for selected currency
- **Domain Pricing**: Only shown if domain TLDs are available
- **Exchange Rates**: Only displayed if 2+ currencies exist in the system

### Sections Visibility

| Section | Visibility | Condition |
|---------|------------|-----------|
| Plans | Always | At least one product |
| Domains | Conditional | `enabled: true` on domain TLDs |
| Exchange Rates | Conditional | 2 or more currencies |
| Footer | Conditional | `branding.footer_content` has content |

### Language & Currency Persistence

- **localStorage**: Language and currency preferences saved automatically
- **Fallback**: Language defaults to "en", currency defaults to first available
- **Across Sessions**: Preferences restored on page reload
- **Manual Reset**: Clear localStorage to reset to defaults

### Dark Mode Support

- Automatic light/dark mode based on `prefers-color-scheme`
- Logo switches automatically (light logo ↔ dark logo)
- All colors use CSS custom properties for theme consistency
- RTL support for Persian (fa) language

## Backwards Compatibility

All new features maintain backwards compatibility with existing data structures:

- **New fields optional**: `branding.footer_content` defaults to empty string
- **No breaking changes**: All APIs remain the same
- **Graceful degradation**: Missing sections hide automatically
- **Safe for upgrades**: Existing `data.json` files work without modification
- **Fallback logic**: Missing data gracefully falls back to safe defaults

## `data.json` Structure

Top-level sections:

- `meta`: Metadata including URLs and generation info
- `branding`: Company information and UI labels
- `categories`: Product categories
- `products`: Hosting plans with pricing and features
- `addons`: Add-on products
- `currencies`: Available currencies with rates
- `domains`: Domain TLDs with pricing
- `currency_rates`: Currency conversion rates
- `gateways`: Payment gateways

### Meta

Contains:
- `generated_at`: Timestamp of last generation
- `generator`: Generator identifier
- `public_site_url`: Main website URL
- `billing_base_url`: FossBilling dashboard URL
- `default_currency`: Default currency code
- `custom_assets`: Optional ENV-based asset overrides (null if none set)
  - `logo_url`, `logo_dark_url`, `favicon_url`, `header_bg_url`, `footer_bg_url`
- `counts`: Counters for categories, products, addons, currencies, domains, gateways

### Branding

Contains company info and theme assets from FossBilling:
- `company`: Name, email, phone, website, address, city, country (from FossBilling Settings → System)
- `motto`: Website tagline (from FossBilling `signature` or ENV: `SITE_MOTTO`)
- `brand_mark`: Optional brand symbol (from ENV: `SITE_BRAND_MARK`)
- `clientarea_url`: FossBilling client area URL (for login links)
- `theme`: Theme metadata (name, code, version, URL)
- `assets`: URLs for logos, favicon, and background images (from FossBilling database)
  - `logo_url`: Primary logo (light mode)
  - `logo_dark_url`: Dark mode logo
  - `favicon_url`: Favicon URL
  - `header_bg_url`: Header background image
  - `footer_bg_url`: Footer background image
- `footer_content`: Footer HTML content fetched from theme settings (rendered at page bottom)

### Products

Each product includes:
- `id`, `title`, `description`, `slug`
- `type`: Product type (e.g., `hosting`)
- `order_url`: Link to order on FossBilling dashboard
- `pricing`: Per-currency pricing models (see below)
- `features`: Array of `{key, value}` pairs from hosting plan limits
- `category_id`, `category_title`

Feature keys (standardized):
- `bandwidth`, `disk`, `databases`, `email_accounts`, `ftp_accounts`
- `subdomains`, `addon_domains`, `cron_jobs`, `inodes`, `websites`, `ram`, `cpu_cores`

Values are raw integers/strings - formatting is done by frontend.

### Products/Addons Pricing

`products[*].pricing` and `addons[*].pricing` are per currency code:

- `type` (`recurrent` / `once`)
- `free` (`price`, `setup`, `enabled`)
- `once` (`price`, `setup`, `enabled`)
- `recurrent.<PERIOD>` (`price`, `setup`, `enabled`) e.g. `1M`, `3M`, `1Y`

### Domains

Each domain entry includes:

- `tld` (e.g. `.com`)
- `enabled`
- `allow_register`
- `allow_transfer`
- `pricing.<CURRENCY>.register|renew|transfer`

### Currency Rates

- `currencies[*].conversion_rate`
- `currency_rates.base_currency`
- `currency_rates.rates_to_base`
- `currency_rates.relations[from][to]`

## Branding & Theme Customization

The generator automatically fetches branding information and theme customizations from your FossBilling instance via API.

### How It Works

The generator fetches branding data from FossBilling's database using API calls:

1. **Company Settings** (`guest/system/company`): Logo URLs, favicon, and company info configured in FossBilling admin → Settings → System
2. **Theme Metadata** (`guest/extension/theme`): Active theme name, version, and base URL
3. **Theme Config** (`admin/extension/config_get`): Additional theme-specific customizations

All values come from the database (not static files), so changes made in the FossBilling admin panel are reflected immediately on the next generator run.

### Theme Information from FossBilling

The generator automatically detects the active theme and includes:
- Theme name, code, and version
- Theme base URL for asset references
- Logo and favicon URLs from system settings

```json
"theme": {
  "name": "FOSSBilling Huraga",
  "code": "huraga",
  "version": "1.0.0",
  "url": "https://billing.example.com/themes/huraga/"
},
"assets": {
  "logo_url": "https://billing.example.com/logo.svg",
  "logo_dark_url": "https://billing.example.com/logo-dark.svg",
  "favicon_url": "https://billing.example.com/favicon.svg",
  "header_bg_url": "https://billing.example.com/themes/huraga/assets/header-bg.jpg",
  "footer_bg_url": "https://billing.example.com/themes/huraga/assets/footer-bg.jpg"
}
```

### Customizing Theme Assets (Logos, Favicons, etc.)

#### **Option 1: Configure in FossBilling Admin Panel (Recommended)**

1. Log into your FossBilling admin panel
2. Go to **Configuration → Settings → System**
3. Configure:
   - **Logo URL** (light mode logo)
   - **Logo URL Dark** (dark mode logo)
   - **Favicon URL** (browser tab icon)
4. Save the settings

When you run the generator, it automatically fetches these values from the FossBilling database and includes them in `data.json`. No code changes needed!

#### **Option 2: Override via Environment Variables**

For deployments that need different assets than FossBilling (e.g., staging, custom branding):

```bash
# Set specific asset URLs (any URL, local or remote)
SITE_LOGO_URL=https://yoursite.com/custom-logo.svg
SITE_LOGO_DARK_URL=https://yoursite.com/custom-logo-dark.svg
SITE_FAVICON_URL=https://yoursite.com/custom-favicon.svg
SITE_HEADER_BG_URL=https://yoursite.com/header-bg.jpg
SITE_FOOTER_BG_URL=https://yoursite.com/footer-bg.jpg
```

### Asset URL Precedence (Highest to Lowest)

The frontend (`script.js`) checks for assets in this order:

1. **`meta.custom_assets`** (from ENV variables)
   - `SITE_LOGO_URL`, `SITE_LOGO_DARK_URL`, `SITE_FAVICON_URL`, etc.
   - Use for staging, custom deployments, CDN hosting
   - Only included in data.json if ENV var is set
   
2. **`branding.assets`** (from FossBilling database)
   - Logo URLs from `guest/system/company` API
   - Updated when you change settings in FossBilling admin
   
3. **Default Fallback Paths** (theme assets)
   - `https://[theme-url]/assets/logo.svg`
   - Only used if no customization exists in FossBilling

This allows you to:
- Use FossBilling for all branding (set nothing, let it auto-fetch)
- Override for production/staging/CDN hosting (set ENV vars)
- Mix FossBilling settings with custom overrides (partial ENV vars)

## Security / Public Data Rules

Generator output is UI-safe by default:

- includes only active/enabled public catalog data
- excludes internal/raw config and server linkage
- domain/TLD product-type entries are removed from `products`
- domain catalog is exposed in dedicated `domains` section
- diagnostics are not written into `data.json`
- branding assets use the same URL validation as product icons

## Frontend Runtime Notes

- Frontend fetches `/data.json` at runtime (cache-busting enabled).
- **Fully dynamic content**: Page title, meta description, favicon, and logos are loaded from `data.json` (no hardcoded values in HTML).
- Logo handling with light/dark mode support:
  - `logo_url` shown in light mode, `logo_dark_url` shown in dark mode
  - CSS `prefers-color-scheme` media query handles automatic switching
- Asset precedence: `meta.custom_assets` (ENV overrides) → `branding.assets` (FossBilling)
- **Category filtering**: Users can filter products by category using tab buttons in the header (or view all).
- **Per-product billing periods**: Each product shows its available billing periods (monthly, yearly, etc.) in a dropdown. Only displayed if the product has multiple billing options.
- **Currency selector**: Single dropdown in header to switch currencies. Prices and domain operations update per currency.
- Displays formatted features with SVG icons based on feature keys.
- Currency conversion and unit formatting (GB, MB, etc.) handled client-side.
- "Client Area" link in header points to `branding.clientarea_url`.
- Product order buttons link to `product.order_url` (FossBilling dashboard).
- **Footer content**: Rendered from `branding.footer_content` (fetched from theme settings via FossBilling API). Only shown if content exists.
- **Currency exchange rates**: Only displayed if 2+ currencies exist (shows conversion table).
- **Domain pricing**: Shown only if domain TLDs are available.
- If JavaScript is disabled, `<noscript>` displays a message.
- All frontend code/assets are local (`/` paths only), no external font/style/script dependencies.

## Recommended Operation

Run generator on each catalog/rate update (or via cron), for example:

```bash
php /path/to/gen.php --out=/path/to/data.json
```

Then serve static files (`index.html`, `style.css`, `script.js`, `data.json`).
