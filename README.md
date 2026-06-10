# helpefi

Single-tenant helpdesk built with Laravel 13, Vue 3, and Inertia.js.

## Stack

- **Backend:** Laravel 13, Controller → Service → Repository pattern
- **Frontend:** Vue 3 + Inertia.js + Tailwind CSS 4
- **Auth:** Session-based login with Spatie roles

## Features

- Dashboard with ticket/contact/article stats
- Contacts CRUD
- Tickets with statuses, priorities, replies, and internal notes
- Knowledge base articles with categories
- Team invitations and member management (admin)
- Profile and password settings

## Plan

See [docs/plan.md](docs/plan.md) for the implementation roadmap.

## Local setup (Valet)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
valet link helpdesk
```

Open **http://helpdesk.test/login**

### Demo credentials

- Email: `admin@helpdesk.test`
- Password: `password`

## Development

```bash
npm run dev
php artisan serve   # optional if not using Valet
```

## Tests

```bash
php artisan test
```

## Project structure

```
app/Domains/
  Auth/         Login, register
  Contacts/     Customer contacts
  Tickets/      Ticket management
  Knowledge/    Help articles
  Dashboard/    Overview stats
```
