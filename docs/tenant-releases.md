# Tenant release upgrades

Helpefi separates **schema migrations** from **versioned data releases** for multi-tenant workspaces.

## Layers

| Layer | Command | Tracking |
|-------|---------|----------|
| Central schema | `php artisan migrate` | `migrations` (central DB) |
| Tenant schema | `php artisan tenants:migrate` | `migrations` (each tenant DB) |
| Tenant data releases | `php artisan tenants:upgrade` | `tenant_release_migrations` (each tenant DB) |

Application release version comes from `APP_VERSION` or the root `VERSION` file (currently `1.0.1`).

## Deploy sequence

```bash
php artisan migrate --force
php artisan tenants:migrate --force
php artisan tenants:upgrade
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
```

## Release registry

Defined in `config/tenant-releases.php`:

- `0.0.1` — baseline marker for workspaces provisioned before release tracking (no steps)
- `1.0.0` — production readiness data upgrades
- `1.0.1` — patch release (no tenant data steps)

### 1.0.0 steps

1. `backfill_ticket_number_sequences`
2. `normalize_handbook_metadata`
3. `sync_platform_handbook`
4. `sync_agent_permissions`
5. `clear_workspace_caches`

## Adding release 1.1.0

1. Create step classes under `app/Domains/Tenancy/Releases/V1_1_0/`
2. Register the release in `config/tenant-releases.php`
3. Bump `VERSION` / `APP_VERSION`
4. Deploy — `tenants:upgrade` runs only new steps per workspace

## Commands

```bash
php artisan tenants:upgrade                  # all workspaces
php artisan tenants:upgrade demo             # one workspace
php artisan tenants:upgrade --status         # show pending steps
php artisan helpdesk:upgrade-workspaces      # deprecated alias
php artisan helpdesk:seed-handbook           # handbook only
php artisan permissions:sync                 # permissions only
```

## Central metadata

Each workspace row tracks:

- `tenants.release_version` — highest fully completed release
- `tenants.release_upgraded_at` — last upgrade timestamp

Pending schema + release upgrades are checked daily via `platform:check-pending-tenant-migrations`.
