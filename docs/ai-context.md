# helpefi Helpdesk — AI Context Document

Complete reference for AI agents and developers working on this codebase. Read this before making changes to auth, tenancy, sessions, mail, or deployment.

**Product:** helpefi — multi-tenant SaaS helpdesk / ITSM platform  
**Repo path (local):** `/Users/saddamhmalik/Sites/helpdesk`  
**Production (EC2):** `/home/ubuntu/helpefi-helpdesk` on `helpefi.com`

---

## Table of Contents

1. [Tech Stack](#1-tech-stack)
2. [Architecture Overview](#2-architecture-overview)
3. [Central vs Tenant](#3-central-vs-tenant)
4. [URL & Login Matrix](#4-url--login-matrix)
5. [Directory Structure](#5-directory-structure)
6. [Routing](#6-routing)
7. [Authentication Flows](#7-authentication-flows)
8. [Sessions & Cookies](#8-sessions--cookies)
9. [Inertia Redirect Pattern](#9-inertia-redirect-pattern)
10. [Mail Architecture](#10-mail-architecture)
11. [Environment Variables](#11-environment-variables)
12. [Production Deployment](#12-production-deployment)
13. [Troubleshooting Runbooks](#13-troubleshooting-runbooks)
14. [Artisan Commands](#14-artisan-commands)
15. [Frontend (Inertia / Vue)](#15-frontend-inertia--vue)
16. [Testing](#16-testing)
17. [Coding Conventions](#17-coding-conventions)
18. [Key File Index](#18-key-file-index)
19. [Known Issues & Recent Fixes](#19-known-issues--recent-fixes)

---

## 1. Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.4+, Laravel 13 |
| Multi-tenancy | `stancl/tenancy` v3 — database-per-tenant |
| Frontend | Vue 3 + Inertia.js 3 + Tailwind CSS 4 |
| Build | Vite 8 |
| Auth / RBAC | Laravel guards + `spatie/laravel-permission` |
| SSO | OIDC (Socialite) + SAML |
| Payments | Stripe |
| Queue / Cache | Redis |
| Realtime | Node WebSocket (`realtime/server.mjs`) |
| AI | OpenAI (`openai-php/client`) |
| Observability | Laravel Telescope (central DB) |

**Architecture pattern:** Domain-driven design under `app/Domains/` with strict **Controller → Service → Repository** layering.

---

## 2. Architecture Overview

```
                    ┌─────────────────────────────────────┐
                    │         helpefi.com (central)        │
                    │  Platform admin, signup, marketing   │
                    │  DB: helpdesk_central                │
                    │  Users: platform_users (guard: platform) │
                    └─────────────────────────────────────┘
                                      │
                    Tenant provisioning creates:
                    ┌─────────────────┴─────────────────┐
                    │   {slug}.helpefi.com (platform domain) │
                    │   OR custom domain (e.g. help.codikal.com) │
                    │   DB: tenant_{slug}                  │
                    │   Users: users (guard: web)          │
                    └─────────────────────────────────────┘
```

- **One central MySQL database** (`helpdesk_central`) for tenants registry, domains, platform users, sessions, billing metadata.
- **One MySQL database per tenant** (`tenant_{slug}`) for all workspace data (tickets, users, settings, mail_settings, etc.).
- **Sessions always stored on central connection** (`SESSION_CONNECTION=central`) regardless of host.
- **Session cookies are host-scoped** (no wildcard `SESSION_DOMAIN`) to prevent cross-subdomain auth bleed.

---

## 3. Central vs Tenant

| Aspect | Central | Tenant |
|--------|---------|--------|
| Hosts | `helpefi.com`, `www.helpefi.com` | `help.helpefi.com`, custom domains |
| Routes file | `routes/web.php` | `routes/tenant.php`, `routes/tenant-api.php` |
| DB connection | `central` | Dynamic `tenant_{slug}` |
| User table | `platform_users` | `users` |
| Auth guard | `platform` | `web` |
| Migrations | `database/migrations/` | `database/migrations/tenant/` |
| Mail | `.env` SMTP → `PlatformMailService` | Tenant `mail_settings` → `OutboundMailService` |

### Domain identification

- `App\Domains\Tenancy\Support\CentralDomain::isCentralHost($host)` — true for apex + www + configured `central_domains`.
- Tenant web: `InitializeTenancyByDomain` middleware in `routes/tenant.php`.
- Global prepend: `InitializeTenancyWhenNotCentral` skips central hosts.

### Tenant lifecycle (`TenancyServiceProvider`)

On `TenantCreated`: ensure platform domain → create DB → migrate → finalize provisioning.  
On `TenantDeleted`: delete tenant database.

### Custom domains

- Customer CNAME → `TENANCY_CUSTOM_DOMAIN_CNAME` (default `tenants.helpefi.com`).
- TXT verification prefix: `_helpdesk-verify`.
- `RedirectToCustomDomain` middleware redirects GET/HEAD to primary custom domain (not POST — avoids breaking login).

---

## 4. URL & Login Matrix

| URL | Purpose | User table |
|-----|---------|------------|
| `helpefi.com/login` | Workspace finder (enter slug → redirect to tenant) | — |
| `helpefi.com/register` | New workspace signup | — |
| `helpefi.com/admin/login` | **Platform admin** login | `platform_users` |
| `helpefi.com/admin/*` | Platform admin dashboard | `platform_users` |
| `{slug}.helpefi.com/login` | **Tenant agent/admin** login | `users` |
| `{custom-domain}/login` | Same as tenant login | `users` |
| `{slug}.helpefi.com/forgot-password` | Agent password reset | `users` |
| `/portal/{brand}/login` | Customer portal login | `users` (customer role) |

**Important:** Platform admin email ≠ tenant user email. They are separate accounts in separate tables.

---

## 5. Directory Structure

### Top-level domains (`app/Domains/`)

```
Admin, Ai, Api, Assets, Assignment, Auth, Automation, Billing, Brands,
Channels, Chat, Contacts, Csat, Dashboard, Integrations, Knowledge, Macros,
Notifications, Performance, Platform, Realtime, Reports, Search, Security,
ServiceCatalog, ServiceDesk, Settings, SideConversations, Sla, Tenancy,
Tickets, TimeTracking, Workforce, Workspace
```

### Typical domain layout

```
app/Domains/{Domain}/
  Controllers/           # Web (Inertia)
  Controllers/Api/       # Tenant REST API
  Services/
  Repositories/
  Models/
  Jobs/
  Mail/
  Notifications/
```

### Shared models (`app/Models/`)

- `Tenant`, `TenantDomain` — central registry
- `User` — tenant agents/admins/customers
- `PlatformUser` — central platform admins

### HTTP layer (`app/Http/`)

- `Middleware/` — tenancy, auth, session, Inertia
- `app/Support/` — cross-cutting helpers (e.g. `InertiaAuthRedirect`)

---

## 6. Routing

### Registration (`app/Providers/TenancyServiceProvider.php`)

| File | Scope | Key middleware |
|------|-------|----------------|
| `routes/web.php` | Central domain only | `web` |
| `routes/tenant.php` | All non-central hosts | `web`, `PreventAccessFromCentralDomains`, `InitializeTenancyByDomain`, `tenant.not_blocked`, `tenant.custom_domain_redirect` |
| `routes/api.php` | `/api/*` on any host | `api` — public widget/inbound |
| `routes/tenant-api.php` | `/api/v1/*` on tenant hosts | `api`, tenancy init |

### Tenant auth routes (`routes/tenant.php`)

```
GET  /login                    → AuthController@showLogin
POST /login                    → AuthController@login
GET  /forgot-password          → PasswordResetController@showForgot
POST /forgot-password          → PasswordResetController@sendLink
GET  /reset-password/{token}   → PasswordResetController@showReset
POST /reset-password           → PasswordResetController@reset
GET  /auth/sso/*               → SsoController (OIDC/SAML)
GET  /two-factor-challenge     → TwoFactorController
```

### Central routes (`routes/web.php`)

```
GET  /admin/login              → AdminLoginController
POST /admin/login
GET  /admin/*                  → Platform admin (central.admin middleware)
POST /stripe/webhook
```

### Agent route stack (tenant)

```
auth → workspace.setup → subscription.active → agent → two-factor
```

---

## 7. Authentication Flows

### Guards (`config/auth.php`)

| Guard | Provider | Model | Connection |
|-------|----------|-------|------------|
| `web` (default) | `users` | `App\Models\User` | tenant DB |
| `platform` | `platform_users` | `App\Models\PlatformUser` | central DB |

### Tenant agent login (`AuthService`)

1. `Auth::attempt()` with email/password.
2. Reject users with `customer` role (must use portal).
3. If 2FA enabled → logout, store pending login, redirect to `/two-factor-challenge`.
4. Else → `session()->regenerate()`, set `two_factor_verified = true`.
5. Redirect: admin + incomplete setup → `/setup`; admin → `admin.hub`; else → `dashboard`.
6. `resolvePostLoginRedirect()` ignores cross-host `url.intended` (prevents redirect loops).

### Platform admin login (`PlatformAuthService`)

- Guard: `platform`.
- `session()->regenerate()` after successful login.
- Redirect to `central.admin.dashboard`.

### Password reset (tenant only)

- `PasswordResetController` → `PasswordResetService` → Laravel `Password` broker.
- Only sends to users who are **not** `customer` role.
- Unknown emails still show success (no enumeration).
- Mail via `User::sendPasswordResetNotification()` → `PasswordResetMailService`.
- Uses `OutboundMailService::resolveMailerName()` for mailer selection.
- Mail failures return **422** with `passwords.mail_failed` (not 500).

### SSO (tenant, billing feature `sso`)

- OIDC: `/auth/sso/redirect`, `/auth/sso/callback`
- SAML: `/auth/sso/acs` (POST), `/auth/sso/metadata`
- Services: `SsoService`, `OidcAuthService`, `SamlAuthService`

### 2FA

- TOTP + recovery codes via `TwoFactorService`.
- `EnsureTwoFactorVerified` middleware checks `two_factor_verified` session flag.
- Can force enrollment via security settings.

### API token auth

- `POST /api/v1/auth/login` → bearer token.
- `api.token` middleware on protected tenant API routes.

---

## 8. Sessions & Cookies

### Required production `.env`

```env
SESSION_DRIVER=database
SESSION_CONNECTION=central
SESSION_DOMAIN=              # EMPTY — host-only cookies (critical)
SESSION_SECURE_COOKIE=true     # when serving HTTPS
SESSION_LIFETIME=480
```

### Why host-only cookies matter

`SESSION_DOMAIN=.helpefi.com` causes the session cookie to be sent on **all** subdomains. This breaks central auth because:
- Central loads tenant `web` guard session keys.
- Central has no `users` table → 500 errors.
- Login appears to work but redirects loop.

### Session middleware stack

| Middleware | Purpose |
|------------|---------|
| `ConfigureApplicationSession` | Forces `session.connection=central`, strips wildcard `SESSION_DOMAIN`, auto-sets `secure` on HTTPS |
| `ExpireLegacySessionCookies` | Clears old `.helpefi.com` session/XSRF cookies |
| `ForgetTenantWebAuthOnCentral` | Removes tenant `web` guard keys from session on central host |
| `RedirectCentralWww` | Redirects `www` → apex |

### CSRF / Inertia

- 419 token mismatch on Inertia requests → `Inertia::location()` full page reload (`bootstrap/app.php`).
- Frontend: `resources/js/plugins/csrf.js` syncs meta token.

---

## 9. Inertia Redirect Pattern

**Problem:** Inertia XHR follows 302 redirect chains. Session cookie may not apply on follow-up requests → login loops (`login 302 → dashboard 302 → login 200`).

**Fix:** `App\Support\InertiaAuthRedirect::to($request, $url)` — uses `Inertia::location($url)` when `X-Inertia` header is present (forces full browser navigation).

**Used in:**
- `AuthController` (tenant login)
- `AdminLoginController` (platform login)
- `TwoFactorController`
- `SsoController`

```php
// app/Support/InertiaAuthRedirect.php
public static function to(Request $request, string $url): Response
{
    if ($request->header('X-Inertia')) {
        return Inertia::location($url);
    }
    return redirect()->to($url);
}
```

---

## 10. Mail Architecture

### Two mail contexts

| Context | Config source | Service | Mailer name |
|---------|--------------|---------|-------------|
| **Central / platform** | `.env` `MAIL_*` | `PlatformMailService` | `config('mail.default')` |
| **Tenant workspace** | `mail_settings` table | `OutboundMailService` | `helpdesk` (dynamic) |

### Tenant outbound pipeline

1. `OutboundMailService::applyGlobalConfig()` — bootstrapped in `AppServiceProvider`.
2. Reads `mail_settings` from **tenant DB**.
3. `OutboundSmtpResolver` — SMTP presets (Gmail, Outlook, Yahoo, Zoho, custom).
4. Registers Laravel mailer `helpdesk` if settings enabled and valid.
5. `resolveMailerName()` — returns `helpdesk` only if mailer is actually registered; else falls back to `MAIL_MAILER` from `.env`.

### Mail consumers (tenant)

- Ticket replies, auto-first-response, side conversations
- Password reset (`PasswordResetMailService`)
- Team invitations (`InvitationMailService`)
- CSAT surveys, approval notifications, ticket exports

### Inbound email

- Webhook: `POST /api/v1/channels/inbound/email` (central host, `tenancy.public-api:inbound`)
- Polling: `channels:poll-inboxes` (scheduled per-tenant)
- OAuth: Google, Microsoft, Zoho

### Password reset mail flow

```
POST /forgot-password
  → PasswordResetService::sendResetLink()
    → User::sendPasswordResetNotification($token)
      → PasswordResetMailService::send()
        → OutboundMailService::resolveMailerName()
        → Mail::mailer($mailer)->send(ResetPasswordMail)
```

### Common mail failures

| Symptom | Cause | Fix |
|---------|-------|-----|
| 500 on forgot-password | `helpdesk` mailer enabled but not registered | Fixed via `resolveMailerName()` fallback |
| 422 "could not send reset email" | SMTP auth failure (e.g. Zoho 535) | Fix SMTP credentials; use app password for Zoho |
| No email received | `MAIL_MAILER=log` or outbound disabled | Check tenant Settings → Email or `.env` |

---

## 11. Environment Variables

### Critical (production)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://helpefi.com
CENTRAL_APP_DOMAIN=helpefi.com

DB_CONNECTION=central
DB_DRIVER=mysql
CENTRAL_DB_DRIVER=mysql
DB_DATABASE=helpdesk_central
TENANCY_DATABASE_PREFIX=tenant_

SESSION_DRIVER=database
SESSION_CONNECTION=central
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true

TENANCY_CUSTOM_DOMAIN_CNAME=tenants.helpefi.com
REALTIME_WS_URL=wss://rt.helpefi.com
```

### Mail

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.zoho.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=
MAIL_PASSWORD=          # use app password for Zoho
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

### Stripe / Billing

```env
STRIPE_ENABLED=true
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
BILLING_TRIAL_DAYS=14
```

### Full template

See `.env.example` for all variables.

---

## 12. Production Deployment

### Server layout

- **Host:** EC2 (e.g. `13.202.3.204`)
- **App path:** `/home/ubuntu/helpefi-helpdesk`
- **Web server:** nginx → PHP 8.5-FPM
- **DB:** MySQL (`helpdesk_central` + `tenant_*` databases)
- **Cache/Queue:** Redis

### Install scripts

| Script | Purpose |
|--------|---------|
| `scripts/install-native.sh` | Full Ubuntu/Debian stack (nginx, PHP, MySQL, Redis, Supervisor) |
| `scripts/install-ssl-native.sh` | Certbot HTTPS + optional wildcard/realtime subdomain |
| `scripts/repair-central-site.sh` | Repair nginx site, validate `.env`, rebuild config cache |
| `scripts/issue-custom-domain-ssl.sh` | Per-tenant custom domain SSL |

### Post-deploy commands

```bash
cd /home/ubuntu/helpefi-helpdesk
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan tenants:migrate --force
php artisan config:clear && php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.5-fpm
sudo systemctl reload nginx
```

### DNS requirements

| Record | Target |
|--------|--------|
| `helpefi.com` A | Server IP |
| `*.helpefi.com` A | Server IP (tenant subdomains) |
| `rt.helpefi.com` A | Server IP (WebSocket) |
| Custom domain CNAME | `tenants.helpefi.com` |

### MySQL grants

```sql
GRANT ALL ON helpdesk_central.* TO 'helpdesk'@'localhost';
GRANT ALL ON `tenant_%`.* TO 'helpdesk'@'localhost';
GRANT CREATE ON *.* TO 'helpdesk'@'localhost';
```

### Nginx notes

- Document root must be `.../public`.
- PHP-FPM socket version must match installed PHP (502 if mismatch).
- WebSocket TLS terminated at nginx → proxy to `127.0.0.1:8080`.

See `docs/ssl-and-deployment.md` for full deployment guide.

---

## 13. Troubleshooting Runbooks

### Central site returns 500

```bash
php artisan platform:diagnose-central helpefi.com
tail -50 storage/logs/laravel.log
```

**Common causes:**
1. Stale `config:cache` with SQLite driver → `php artisan config:clear`, fix `.env`, `config:cache`
2. `CENTRAL_APP_DOMAIN` mismatch → host not recognized as central
3. `SESSION_DOMAIN=.helpefi.com` → clear it, reload PHP-FPM
4. Tenant session leaking to central → verify `ForgetTenantWebAuthOnCentral` middleware active

**Repair script:**
```bash
sudo DOMAIN=helpefi.com ./scripts/repair-central-site.sh
```

### Login redirect loop (302 chain)

**Symptom:** Network tab shows `login → dashboard → login`.

**Causes & fixes:**
1. Inertia XHR following 302 without cookie → deploy `InertiaAuthRedirect` fix
2. Cross-host `url.intended` → `AuthService::resolvePostLoginRedirect()` ignores foreign hosts
3. `SESSION_DOMAIN` wildcard → set empty
4. `SESSION_SECURE_COOKIE=false` on HTTPS → set `true`
5. Custom domain redirect on POST → `RedirectToCustomDomain` only redirects GET/HEAD

### Forgot-password returns 500

**Check log:**
```bash
tail -100 storage/logs/laravel.log | grep -A5 forgot-password
```

**Common causes:**
1. `helpdesk` mailer not registered but outbound enabled → fixed via `resolveMailerName()`
2. SMTP failure uncaught → now returns 422
3. Missing `password_reset_tokens` table on tenant → `php artisan tenants:migrate`
4. Missing `mail_settings` table → `php artisan tenants:migrate`

### Session lost after login

1. Verify `SESSION_SECURE_COOKIE=true` when on HTTPS
2. Verify `SESSION_DOMAIN` is empty (not `.helpefi.com`)
3. Check `helpdesk_central.sessions` table has new rows with `user_id` after login
4. Clear browser cookies for `.helpefi.com` and retry

### Tenant custom domain not working

1. Verify CNAME points to `tenants.helpefi.com`
2. Verify TXT record `_helpdesk-verify.{domain}`
3. Run `scripts/issue-custom-domain-ssl.sh` for SSL
4. Check `domains` table in central DB for domain record

### Mail not sending (Zoho SMTP)

- Use **app-specific password**, not account password
- `MAIL_HOST=smtp.zoho.com`, `MAIL_PORT=587`, `MAIL_SCHEME=tls`
- Test: configure in tenant Settings → Email → Send test email
- Or set `MAIL_MAILER=log` temporarily to verify flow without SMTP

---

## 14. Artisan Commands

### Diagnostics

| Command | Purpose |
|---------|---------|
| `platform:diagnose-central {host?}` | Central DB driver, session config, host recognition |
| `platform:send-test-mail {email}` | Test central platform mail |

### Tenancy

| Command | Purpose |
|---------|---------|
| `tenants:migrate` | Migrate all tenant databases |
| `tenants:run {cmd}` | Run artisan command in each tenant context |
| `tenants:provision-demo {slug}` | Create demo workspace |
| `tenants:purge-expired` | Delete expired workspaces |
| `tenants:sync-routes {tenant?}` | Sync tenant route mappings |

### Scheduled (per-tenant via `tenants:run`)

| Command | Purpose |
|---------|---------|
| `sla:check-breaches` | SLA monitoring |
| `channels:poll-inboxes` | IMAP/OAuth inbox polling |
| `security:purge-retention` | Data retention purge |
| `tickets:unsnooze` | Unsnooze tickets |
| `automation:process-scheduled` | Deferred automation |
| `knowledge:embed-articles` | KB vector embeddings |

### Platform

| Command | Purpose |
|---------|---------|
| `platform:run-backups` | Central database backups |
| `billing:backfill-stripe` | Sync Stripe subscriptions |
| `billing:enforce-grace` | Subscription grace enforcement |

---

## 15. Frontend (Inertia / Vue)

### Entry

- `resources/js/app.js` — `createInertiaApp`, lazy pages via `import.meta.glob('./Pages/**/*.vue')`
- Blade root: `resources/views/app.blade.php`

### Page organization

```
resources/js/Pages/
  Central/       # Marketing + platform admin
  Auth/          # Tenant login, forgot/reset password, 2FA
  Portal/        # Customer help center
  Settings/      # Admin settings (auto-wrapped in SettingsLayout)
  Tickets/, Contacts/, Knowledge/, etc.
  Error/         # NotFound, ServerError, Generic
```

### Shared Inertia props (`HandleInertiaRequests`)

`csrf_token`, `platformAuth`, `auth.user`, `locale`, `direction`, `billing`, `realtime`, `tenantId`, `portalBrand`, `setupWarnings`, `flash`

**Central host behavior:** `auth.user` is skipped on central (prevents loading tenant user).

### Locales

- `resources/js/locales/{en,de,fr,es,ar}/`
- Validate: `npm run validate:locales`

### Build

```bash
npm run build        # production
npm run dev          # Vite dev server
composer dev         # full stack (serve + queue + realtime + pail + vite)
```

---

## 16. Testing

### Run tests

```bash
composer test    # config:clear + php artisan test
```

### Test environment (`phpunit.xml`)

- SQLite in-memory central DB
- `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`
- `CENTRAL_APP_DOMAIN=helpdesk.test`

### Base classes

| Class | Purpose |
|-------|---------|
| `Tests\TestCase` | Disables CSRF middleware |
| `Tests\TenantTestCase` | Provisions tenant, provides `tenantGet/Post` helpers |

### Notable test files

- `CentralDomainAccessTest` — central host recognition
- `TenancyRouteTest` — tenant routing
- `AuthTest`, `SecurityTest`, `PasswordResetTest`
- `PlatformAdminTest`, `CustomDomainTest`, `EmailTest`

---

## 17. Coding Conventions

- **No code comments** unless explaining non-obvious business logic.
- **SOLID principles**, clean optimized queries.
- **Controller → Service → Repository** strictly — no business logic in controllers.
- **Minimize scope** — smallest correct diff; match existing naming and patterns.
- **Do not commit** unless explicitly asked.
- **Do not create docs** unless explicitly asked (this file is the exception).

---

## 18. Key File Index

| Area | Path |
|------|------|
| Tenancy config | `config/tenancy.php` |
| Auth guards | `config/auth.php` |
| Session config | `config/session.php` |
| Bootstrap / middleware | `bootstrap/app.php` |
| Route registration | `app/Providers/TenancyServiceProvider.php` |
| Central routes | `routes/web.php` |
| Tenant routes | `routes/tenant.php` |
| Tenant API routes | `routes/tenant-api.php` |
| Public API | `routes/api.php` |
| Central domain helper | `app/Domains/Tenancy/Support/CentralDomain.php` |
| Session middleware | `app/Http/Middleware/ConfigureApplicationSession.php` |
| Legacy cookie cleanup | `app/Http/Middleware/ExpireLegacySessionCookies.php` |
| Central auth isolation | `app/Http/Middleware/ForgetTenantWebAuthOnCentral.php` |
| Custom domain redirect | `app/Http/Middleware/RedirectToCustomDomain.php` |
| Inertia shared props | `app/Http/Middleware/HandleInertiaRequests.php` |
| Inertia auth redirect | `app/Support/InertiaAuthRedirect.php` |
| Tenant login | `app/Domains/Auth/Controllers/AuthController.php` |
| Tenant auth service | `app/Domains/Auth/Services/AuthService.php` |
| Platform login | `app/Domains/Platform/Controllers/Central/AdminLoginController.php` |
| Platform auth service | `app/Domains/Platform/Services/PlatformAuthService.php` |
| Password reset | `app/Domains/Auth/Controllers/PasswordResetController.php` |
| Password reset mail | `app/Domains/Auth/Services/PasswordResetMailService.php` |
| Outbound mail | `app/Domains/Channels/Services/OutboundMailService.php` |
| Central diagnose command | `app/Console/Commands/DiagnoseCentralCommand.php` |
| Repair script | `scripts/repair-central-site.sh` |
| Deployment docs | `docs/ssl-and-deployment.md` |
| Env template | `.env.example` |
| Central migrations | `database/migrations/` |
| Tenant migrations | `database/migrations/tenant/` |
| Frontend entry | `resources/js/app.js` |
| Product roadmap | `docs/plan.md` |

---

## 19. Known Issues & Recent Fixes

### Session / auth isolation (fixed)

| Issue | Root cause | Fix |
|-------|-----------|-----|
| Central 500 after tenant login | Wildcard `SESSION_DOMAIN` + tenant session on central | Host-only cookies, `ForgetTenantWebAuthOnCentral`, `ConfigureApplicationSession` |
| Central 500 with config cache | Stale cache with SQLite driver | `repair-central-site.sh`, `platform:diagnose-central` |
| Login redirect loop | Inertia XHR 302 chain without cookie | `InertiaAuthRedirect::to()` |
| Cross-host intended URL loop | `url.intended` from different host | `AuthService::resolvePostLoginRedirect()` |
| POST login broken on custom domain | Redirect middleware on POST | `RedirectToCustomDomain` GET/HEAD only |

### Mail (fixed)

| Issue | Root cause | Fix |
|-------|-----------|-----|
| Forgot-password 500 | `helpdesk` mailer enabled but not registered | `OutboundMailService::resolveMailerName()` with fallback |
| Uncaught mail errors → 500 | Only `TransportExceptionInterface` caught | Catch `Throwable`, return 422 ValidationException |

### Production tenant example

- **Slug:** `help`
- **Platform domain:** `help.helpefi.com`
- **Custom domain:** `help.codikal.com` (if configured)
- **Central:** `helpefi.com`

### When debugging any 500

1. `tail -100 storage/logs/laravel.log` — find exact exception + stack trace
2. `php artisan platform:diagnose-central` — if central host
3. `php artisan tenants:run "migrate:status"` — if tenant-specific table missing
4. Check `.env` against §11 required values
5. Verify middleware order in `routes/tenant.php` and `bootstrap/app.php`

---

*Last updated: June 2026. Update this document when adding new domains, middleware, deployment steps, or fixing production issues.*
