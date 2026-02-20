# FossBilling Static Website Generator

Generates a single `data.json` from FossBilling, then serves a fully static frontend.

## Stack

- Generator: `gen.php`
- Frontend: `index.html`, `style.css`, `script.js`
- Output: `data.json`

## Requirements

- PHP 8+ CLI
- PHP cURL extension
- FossBilling API key with access to required `guest/*` and `admin/*` endpoints used by `gen.php`

## Quick Start

1. Copy env template:

```bash
cp .env.example .env
```

2. Set required values in `.env`:

```ini
BILLING_BASE_URL=https://billing.example.com
BILLING_API_KEY=your_api_key
PUBLIC_SITE_URL=https://example.com
```

3. Harden local file permissions (recommended):

```bash
chmod 600 .env gen.php
```

Keep `.env` and `gen.php` outside any web-served directory.

4. Generate data:

```bash
php gen.php
```

5. Serve the static files (`index.html`, `style.css`, `script.js`, `data.json`) from your web server.

## Common Commands

Generate to a custom path:

```bash
php gen.php --out=./public/data.json
```

Override API connection from CLI:

```bash
php gen.php --base-url=https://billing.example.com --api-key=xxx --public-url=https://example.com
```

Useful flags:

- `--pretty=0|1`
- `--show-errors=0|1`
- `--timeout=SECONDS`
- `--max-pages=N`
- `--per-page=N`
- `--strict-tls=0|1`
- `--exclude-patterns=csv,list`

## Data Flow

1. `gen.php` fetches products, categories, addons, currencies, domains, gateways, and branding/theme data.
2. It normalizes and filters data for public frontend use.
3. It writes `data.json`.
4. `script.js` fetches `/data.json` and renders the page dynamically.

## Minimal Deployment Loop

Regenerate `data.json` whenever catalog/pricing changes (or via cron), then keep serving the same static assets.

Example cron (every 5 minutes):

```bash
*/5 * * * * php /path/to/gen.php --out=/path/to/public/data.json
```

If your host defaults `php` to CGI/FastCGI in cron, use an explicit CLI binary path (for example `/usr/bin/php` or `/usr/local/bin/php-cli`).
