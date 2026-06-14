# Helpefi Marketing SEO

Production SEO architecture for the English-only central marketing site at `https://helpefi.com`.

## Architecture

| Layer | Responsibility |
| --- | --- |
| `config/marketing_seo.php` | Site URL, org info, analytics env keys, robots disallow list, static page registry |
| `config/marketing_features.php` | Feature landing page slugs and accent colors |
| `config/marketing_blog.php` | Blog post registry (dates, reading time, related posts) |
| `MarketingSeoContext` | Maps Laravel route names to SEO page keys |
| `CentralSeoService` | Meta titles/descriptions, canonicals, sitemap, robots.txt, JSON-LD orchestration |
| `MarketingJsonLd` | Organization, WebSite, SoftwareApplication, WebPage, BreadcrumbList, FAQPage, BlogPosting |
| `resources/views/partials/central-seo.blade.php` | Server-rendered meta, OG/Twitter, verification tags, GA4 |
| `resources/js/locales/en/central.json` | SEO copy, feature/static/blog page content |

All metadata is server-rendered in `app.blade.php` before Inertia hydrates. Do not re-add client-side `<Head>` title tags for marketing pages.

## URL map

| URL | SEO key | Sitemap |
| --- | --- | --- |
| `/` | `home` | Yes |
| `/register` | `register` | Yes |
| `/login` | `login` | No (noindex) |
| `/pricing` | `static_pricing` | Yes |
| `/about` | `static_about` | Yes |
| `/contact` | `static_contact` | Yes |
| `/privacy` | `static_privacy` | Yes |
| `/terms` | `static_terms` | Yes |
| `/features/{slug}` | `feature_{slug}` | Yes |
| `/blog` | `blog` | Yes |
| `/blog/{slug}` | `blog_{slug}` | Yes |
| `/for/{vertical}` | `vertical_{slug}` | Yes |
| `/vs/{competitor}` | `compare_{slug}` | Yes |

## Structured data

- **Home**: Organization, WebSite, SoftwareApplication, FAQPage
- **Feature / vertical pages**: Organization, WebSite, WebPage, BreadcrumbList, FAQPage (when FAQs exist)
- **Blog index**: Organization, WebSite, WebPage, BreadcrumbList
- **Blog posts**: Organization, WebSite, WebPage, BlogPosting, BreadcrumbList

## Environment variables

```env
MARKETING_SITE_URL=https://helpefi.com
MARKETING_CONTACT_EMAIL=hello@helpefi.com
GOOGLE_ANALYTICS_ID=G-XXXXXXXXXX
GOOGLE_SITE_VERIFICATION=your-token
BING_SITE_VERIFICATION=your-token
MARKETING_TWITTER_SITE=@helpefi
MARKETING_ORG_NAME=Helpefi
CENTRAL_OG_IMAGE_URL=https://helpefi.com/og-image.png
```

## Adding content

### New feature page

1. Add slug to `config/marketing_features.php`
2. Add `central.feature_pages.{slug}` and `central.seo.feature_{slug}_title/description` to `central.json`
3. Route is automatic via `/features/{feature}`

### New blog post

Manage posts from **Platform admin → Blog** at `/admin/blog`.

Each post supports title, slug, excerpt, body, draft/published status, SEO fields, related posts, and optional OG image URL. Published posts appear automatically on `/blog`, in the sitemap, and with Article JSON-LD.

For local setup after deploy:

```bash
php artisan migrate
php artisan db:seed --class=PlatformPermissionSeeder
php artisan db:seed --class=MarketingBlogPostSeeder
```

### New static page

1. Add entry to `config/marketing_seo.static_pages`
2. Add `central.static_pages.{page}` and SEO strings
3. Register route in `routes/web.php`

## SEO checklist

- [ ] Set `MARKETING_SITE_URL` to production domain
- [ ] Upload `public/og-image.png` (1200×630) or set `CENTRAL_OG_IMAGE_URL`
- [ ] Configure GA4 and Search Console / Bing verification env vars
- [ ] Submit `https://helpefi.com/sitemap.xml` in Google Search Console
- [ ] Verify canonical URLs on staging vs production
- [ ] Confirm `robots.txt` blocks `/admin`, `/login`, `/api/`
- [ ] Run Lighthouse on `/`, `/pricing`, `/features/ai` (target SEO 95+, Performance 90+)
- [ ] Add new blog posts with internal links to feature pages

## Keyword focus (semantic, not stuffed)

Primary themes:

- AI helpdesk software
- AI customer support
- Helpdesk software / SaaS helpdesk
- Ticket management system
- ITSM platform / Service Desk
- Support automation
- Customer support software
- Knowledge base software
- Live chat for customer support

Map keywords to dedicated landing pages rather than repeating them on every page.

## Lighthouse optimization notes

- Meta tags are in initial HTML (no client-side SEO flash)
- GA4 loads async via gtag.js
- Sticky header uses CSS only (minimal CLS)
- Images: add `loading="lazy"` on below-fold marketing images
- Fonts: use `font-display: swap` in Tailwind/font config
- Preconnect to `googletagmanager.com` when GA is enabled (in `app.blade.php`)

Run locally:

```bash
npx lighthouse https://helpefi.com --only-categories=seo,performance,best-practices,accessibility --view
```

## Technical audit summary

| Item | Status |
| --- | --- |
| Dynamic meta titles/descriptions | Server-rendered via `CentralSeoService` |
| Canonical tags | Per-page via `MarketingSeoContext` |
| Open Graph + Twitter cards | `central-seo.blade.php` |
| robots.txt | Dynamic, blocks admin/auth/API |
| sitemap.xml | Dynamic, all public marketing URLs |
| JSON-LD | Organization, SoftwareApplication, FAQ, Breadcrumbs, BlogPosting |
| HTTPS / security headers | `MarketingSecurityHeaders` middleware |
| Analytics | GA4 when `GOOGLE_ANALYTICS_ID` set |
| Internal linking | Footer feature/company links, related features on landing pages |
| Blog architecture | `/blog`, `/blog/{slug}` with Article schema |

## Recommended next steps

1. Publish 2–4 blog posts linking to feature pages
2. Add FAQ schema to pricing and about pages if content expands
3. Create per-page OG images for top landing pages
4. Monitor Search Console for index coverage after launch
