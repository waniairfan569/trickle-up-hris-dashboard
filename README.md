# TrickleUp Hub

A Human Resources Information System (HRIS) admin dashboard built with **Laravel 13** (PHP 8.3). It is a server-rendered monolith with two interfaces over a single domain model:

- **Web admin UI** — server-rendered **Blade** templates styled with **Tailwind** and made interactive with **Alpine.js** (both via CDN; there is no Node/Vite build step).
- **REST API** — token-based (`/api/v1/*`) using **Laravel Sanctum**, intended for an external SPA / mobile client.
- **Realtime** — broadcasting via **Laravel Reverb** / **Pusher** (notifications, time-off and review events).

## Modules

Employees · Time Off (requests, policies, balances) · Attendance (clock in/out, breaks, corrections, geofencing) · Performance reviews · Onboarding workflows · Shifts & scheduling · Office locations · Holiday calendars · Departments & company entities · Dynamic profile templates · Roles & permissions (RBAC) · Activity / audit logging · Email invitations.

## Tech Stack

| Concern        | Choice                                |
| -------------- | ------------------------------------- |
| Framework      | Laravel 13 (PHP ^8.3)                  |
| Auth (web)     | Session + CSRF                        |
| Auth (API)     | Laravel Sanctum tokens                |
| Realtime       | Laravel Reverb + Pusher               |
| Database       | MySQL                                 |
| UI             | Blade + Tailwind (CDN) + Alpine.js    |
| Import/Export  | maatwebsite/excel                     |

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+ (database name defaults to `workable_admin`)

## Local Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Create your environment file
cp .env.example .env

# 3. Generate the app key
php artisan key:generate

# 4. Configure your database in .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD),
#    then create the database and run migrations + seeders
php artisan migrate --seed

# 5. Serve the app
php artisan serve
```

The app is then available at `http://127.0.0.1:8000`.

### Realtime (optional)

To enable websocket broadcasting locally, configure the Reverb / Pusher keys in `.env` and run:

```bash
php artisan reverb:start
php artisan queue:listen
```

## Project Structure

```
app/
  Http/Controllers/        # Web controllers (Blade pages)
  Http/Controllers/Api/    # REST API controllers (/api/v1)
  Http/Middleware/         # RBAC: CheckRole, CheckPermission, EnsureSuperAdmin, ...
  Models/                  # ~40 Eloquent models (User is the employee + auth entity)
  Services/                # Domain services (Attendance, TimeOffBalance, Onboarding, ...)
  Events/                  # Broadcast events (notifications, reviews, time-off)
  Policies/ Traits/
routes/
  web.php                  # Session-authenticated Blade routes
  api.php                  # Sanctum token API (/api/v1)
  channels.php             # Broadcast channel authorization
resources/views/           # Blade templates (one folder per module)
database/
  migrations/  seeders/  factories/
```

## Authorization

Access is governed by roles (`super_admin`, `hr_admin`, `manager`, employee) and granular permissions
(`manage_users`, `hr_records`, `approve_timeoff`, `reports`, ...), enforced by route middleware.
A user's effective data scope (`all` / `department` / `team` / `self`) is derived from their role and
reporting line — see `App\Models\User::getAccessScope()`.

## Notes

- `.env`, `/vendor`, and the local `database/database.sqlite` are git-ignored and not part of this repository.
- The live database is MySQL; the seeders provide demo roles, permissions, and sample data.
