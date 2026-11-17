# B2B Transportation ERP

Laravel 11 starting point for a B2B transportation platform with two separate
portals served from one codebase:

| Portal | Default domain | Prefix | Audience |
| --- | --- | --- | --- |
| Admin | `admin.localhost` | `/admin` | Platform operators (`SUPER_ADMIN`, `ADMIN`) |
| Company | `company.localhost` | `/company` | Client companies (`COMPANY_ADMIN`, `DISPATCHER`, `DRIVER`, `CLIENT`) |

The architecture source of truth lives in
[`B2B Transportation ERP Simple Architecture.md`](B2B%20Transportation%20ERP%20Simple%20Architecture.md).

## Status

The platform is feature-complete for the current scope:

- Laravel 11 skeleton with Sanctum, Spatie Permission, and Breeze dependencies.
- Three environment templates: base, admin, company.
- Portal-aware bootstrap, middleware, route files, service providers, and layouts.
- Multi-tenant migrations for companies, users, roles, `role_user`,
  `admin_permissions`, `trips`, `bookings`, `seat_requests`, and `audit_logs`.
- Portal authentication for platform administrators and company users.
- Admin company onboarding: create companies, add up to two company users,
  activate/deactivate accounts, and grant feature permissions.
- Trip scheduling/cancellation, seat booking/cancellation, seat-request approval,
  per-admin feature permissions, and audit logging.
- Reports dashboard (`VIEW_REPORTS`) with seat utilization, top companies,
  upcoming trip load, and recent audit activity.
- Trip completion for departed trips and a filterable audit log viewer.
- Company REST API with Sanctum tokens, scoped bookings/seat requests, and
  rate limiting.
- Deployment hardening: security headers, `admin:create`, CI workflow, and a
  Docker Compose stack with app, queue worker, scheduler, and MySQL.
- Feature tests covering portal routing, authentication, and booking/approval rules.

## Requirements

- PHP 8.2+ with PDO (MySQL/PostgreSQL for real deployments)
- Composer 2
- Node 18+ and npm
- Docker with Compose (optional, for the production stack)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Local development can use SQLite:
# set DB_CONNECTION=sqlite in .env and create database/database.sqlite

php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For the two-site setup, copy the portal templates and point your virtual hosts
(or `hosts` file / DNS) at the same public directory:

```bash
cp .env.admin.example .env        # admin.localhost   (PORTAL_TYPE=admin)
cp .env.company.example .env      # company.localhost (PORTAL_TYPE=company)
```

When both domains hit the same application instance, `ResolvePortal` selects the
portal from the request host; `PORTAL_TYPE` is the fallback for hosts that do not
match a configured portal domain (for example `localhost` during development).
Both portals share one database (`b2b_transport_erp`) because admin and company
records must meet through trips, bookings, and seat requests. The portal env
templates differ only in domain, portal type, mail identity, and cache prefix.

## Portal architecture

```
routes/web.php        -> guests go to the resolved portal login, members to the dashboard
routes/admin.php      -> /admin/*   (middleware: portal:admin)
routes/company.php    -> /company/* (middleware: portal:company)
routes/api.php        -> /api/*     (Sanctum token auth for company users)

app/Http/Middleware/ResolvePortal.php -> host/subdomain detection + enforce portal
app/Providers/AdminServiceProvider.php   -> admin routes + admin:: view namespace
app/Providers/CompanyServiceProvider.php -> company routes + company:: view namespace
config/portal.php     -> supported portals, default portal, portal domains
```

Controller layout sharing is handled by
`app/Http/Controllers/Admin/BaseController.php` and
`app/Http/Controllers/Company/BaseController.php`.

## Company API

The API uses Sanctum personal access tokens and accepts only active company
users. Send `Accept: application/json` and `Authorization: Bearer <token>`.

```
POST   /api/login                        email, password, optional device_name -> token
GET    /api/user
POST   /api/logout                       revokes the current token
GET    /api/trips                        upcoming bookable trips with remaining seats
GET    /api/bookings                     bookings for the authenticated user's company
POST   /api/bookings                     trip_id, seats_count
POST   /api/bookings/{booking}/cancel
GET    /api/seat-requests
POST   /api/seat-requests                requested_seats, optional reason
```

Requests are limited to 60 per minute per user or IP, and login attempts to 5
per minute. Booking and seat-request rules are shared with the web portals.

## Production deployment

### Docker Compose

The stack runs Apache/PHP 8.4, a database queue worker, the scheduler, and
MySQL 8.4.

```bash
export APP_KEY="$(php artisan key:generate --show)"
export DB_PASSWORD="$(openssl rand -hex 16)"
export DB_ROOT_PASSWORD="$(openssl rand -hex 16)"

docker compose up -d --build
docker compose exec app php artisan db:seed --force   # demo data only
```

Open `http://localhost:8080`. Migrations run automatically in the app container
(`AUTO_MIGRATE=true`); queue and scheduler containers wait for the app health
check. The `storage` and `dbdata` volumes persist data; `docker compose down -v`
removes them. Use `APP_PORT=8090 DOCKER_APP_URL=http://localhost:8090` for a
different port.

For real domains, pass the public hosts:

```bash
PUBLIC_ADMIN_DOMAIN=admin.example.com \
PUBLIC_COMPANY_DOMAIN=company.example.com \
DOCKER_APP_URL=https://admin.example.com \
docker compose up -d --build
```

Hosts that match neither configured domain fall back to prefix routing
(`/admin`, `/company`) for local development. Cross-portal paths return 404.

Create the first production administrator instead of seeding demo accounts:

```bash
docker compose exec app php artisan admin:create owner@example.com
```

The command prompts for a password (minimum 8 characters); pass `--password`
for automation.

### Manual checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, and an HTTPS
  `APP_URL`.
- Run `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
- Run `php artisan migrate --force`, then cache config, routes, and views.
- Use `SESSION_DRIVER=database`, `CACHE_STORE=database` (or Redis), and
  `QUEUE_CONNECTION=database` (or Redis).
- Supervise `php artisan queue:work` and run `php artisan schedule:work` (or the
  scheduler cron entry).
- Serve only `public/` over HTTPS. `/up` is the health endpoint, and security
  headers (including HSTS on secure requests) are applied by middleware.
- If the app sits behind a load balancer, configure trusted proxies in
  `bootstrap/app.php`.

### CI

`.github/workflows/ci.yml` runs Pint, the SQLite test suite, the Vite build, and
a Docker image build on every push and pull request.

## Directory highlights

```
app/
  Helpers/helpers.php              portal_type(), portal_is(), portal_route()
  Console/Commands/                admin:create provisioning command
  Http/Controllers/Admin/          dashboard, companies, company users, trips, seat requests, reports, audit logs, permissions
  Http/Controllers/Api/            auth, trips, bookings, seat requests
  Http/Controllers/Company/        dashboard, trips, bookings, seat requests
  Http/Middleware/                 ResolvePortal, EnsurePortalUser, EnsureFeature, EnsureSuperAdmin
  Models/                          Company, Role, User, Trip, Booking, SeatRequest, AdminPermission, AuditLog
  Services/                        BookingService, SeatRequestService, CompanySeatLimitService
  Providers/                       AdminServiceProvider, CompanyServiceProvider
config/portal.php
database/migrations/                companies, users, roles, role_user, admin_permissions,
                                    trips, bookings, seat_requests, audit_logs,
                                    personal_access_tokens
database/seeders/DatabaseSeeder.php roles, demo accounts, and demo trips
resources/
  css/admin, css/company            separate portal styles
  js/admin, js/company              separate portal scripts
  views/admin, views/company        namespaced Blade layouts + dashboards
routes/admin.php, routes/company.php, routes/web.php, routes/api.php
vite.config.js                     four Vite entry points
Dockerfile, docker-compose.yml     production app, queue, scheduler, MySQL
docker/php/entrypoint.sh           container bootstrap, cache warmup, migrations
.github/workflows/ci.yml           Pint, tests, assets, Docker build
```

## Current data model

- `companies` — name, contact, `seat_limit`, `is_active`.
- `users` — nullable `company_id` (null for platform admins), name, email,
  password, `is_active`; belongs to a company and many roles.
- `roles` — `SUPER_ADMIN`, `ADMIN`, `COMPANY_ADMIN`, `DISPATCHER`, `DRIVER`,
  `CLIENT`.
- `role_user` — many-to-many pivot between users and roles.
- `admin_permissions` — feature grants per administrator (`MANAGE_TRIPS`,
  `MANAGE_COMPANIES`, `MANAGE_SEAT_LIMITS`, `APPROVE_REQUESTS`, `VIEW_REPORTS`),
  with `granted_by` and `granted_at`.
- `trips` — route name, origin/destination, `departure_datetime`, `total_seats`,
  status (`SCHEDULED`, `CANCELLED`, `COMPLETED`).
- `bookings` — company and trip, `seats_count`, status (`CONFIRMED`, `CANCELLED`).
- `seat_requests` — company, `requested_seats`, reason, status (`PENDING`,
  `APPROVED`, `REJECTED`), reviewer, decision note.
- `audit_logs` — actor, action, auditable type/id, metadata.
- `personal_access_tokens` — Sanctum API tokens for company users.

## Business rules

1. A company's confirmed bookings never exceed its `seat_limit`.
2. A trip's confirmed bookings never exceed its `total_seats`.
3. A company may have at most two company users, enforced in `User`.
4. A company may have only one pending seat request at a time.
5. Seat requests increase `seat_limit` only after admin approval.
6. A seat limit cannot drop below the company's seats already in use.
7. Only a super admin may grant or revoke admin permissions.
8. Admins holding `MANAGE_COMPANIES` create companies and their portal accounts;
   `MANAGE_SEAT_LIMITS` controls seat-limit changes and `VIEW_REPORTS` gates the
   reports dashboard and audit log.
9. Seat-limit changes, request decisions, bookings, and trip events are audited;
   cancelling a trip cancels its confirmed bookings and releases the seats.
10. A scheduled trip can be marked completed only after its departure time, and
    completion is audited.

## Useful commands

```bash
php artisan route:list          # admin.* and company.* routes
php artisan route:list --path=api
php artisan migrate:fresh --seed
php artisan test                # feature + unit suites
php artisan admin:create owner@example.com
docker compose up -d --build    # production-style stack on :8080
npm run dev                     # Vite dev server
npm run build                   # production assets
```

Seeded demo accounts (password: `password`):

- `superadmin@example.com` — super administrator
- `manager@acme.example` — company administrator for Acme Logistics

Seeding also creates two scheduled demo trips (Lagos to Abuja, Lagos to Ibadan).
