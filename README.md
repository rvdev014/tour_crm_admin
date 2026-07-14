# Tour CRM Admin

Laravel 10 CRM/backend for a tour operator company. It powers an internal admin panel (Filament) for operators and a public REST API consumed by the [`tour_landing`](../tour_landing) customer-facing website.

## What It Does

1. **Internal CRM** — Filament admin panel at `/admin` where operators manage tours, hotels, transfers, restaurants, museums, drivers, and expenses, and generate Word/Excel export documents (invoices, vouchers, booking letters).
2. **Public API** — `/api` serves the customer-facing site with hotels, tours, banners, services, transfer requests, and user auth (Sanctum + Google OAuth).

## Tech Stack

- **Framework:** Laravel 10, PHP ^8.1
- **Admin panel:** Filament 3
- **Database:** PostgreSQL (despite `.env.example` defaulting to MySQL — see Environment below)
- **Auth:** Laravel Sanctum, Google OAuth
- **Exports:** phpoffice/phpword, phpoffice/phpspreadsheet, pxlrbt/filament-excel
- **Infra:** Docker Compose (nginx + PHP-FPM + Postgres + Mailhog)
- **Notifications:** Telegram (driver alerts), email (client transfer reminders)

## Getting Started

### Prerequisites
- Docker & Docker Compose
- PHP ^8.1, Composer (for local/non-Docker workflows)
- Node.js (for Vite asset builds)

### Setup (Docker)

```bash
cp .env.example .env
# set DB_CONNECTION=pgsql and other DB_* values to match docker-compose.yml
docker compose up -d
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
docker compose exec php php artisan db:seed
```

App: `http://localhost:${APP_PORT}` · Admin panel: `/admin` · Vite dev server: port `5173` · Mailhog UI: `${MAILHOG_PORT}`.

### Restore DB from backup

```bash
make restore-db
```

## Common Commands

```bash
# Tests
php artisan test
php artisan test tests/Feature/TourGroupNumberTest.php   # single file
php artisan test --filter=testMethodName                  # single test

# Assets
npm run dev      # Vite dev server
npm run build    # Production build

# Code style
./vendor/bin/pint          # Laravel Pint (PSR-12 fixer)
./vendor/bin/pint --test   # Lint without fixing

# Artisan
php artisan optimize:clear   # Clear all caches
php artisan queue:work       # Process queued jobs
```

A cron-driven scheduler (runs every minute) notifies drivers via Telegram and sends transfer reminder emails to clients.

## Architecture Overview

### Tour Types: TPS vs Corporate

Tours are either `TPS` or `Corporate` (`TourType` enum), and this distinction runs through the whole codebase:

- **TPS** tours track `pax_uz` / `pax_foreign` / `leader_pax` counts with a day-based expense structure (`TourDay` → `TourDayExpense`). `price_result × total_pax = total_price`; `income = total_price - expenses_total`.
- **Corporate** tours use `TourGroup` → `TourPassenger` passenger lists with separate expense calculations (`ExpenseService::getAllExpensesCorporate`).

Filament resources are split accordingly: `TourTpsResource` and `TourCorporateResource`.

### Expense Pipeline

`TourDayExpense` is the central expense record (`ExpenseType`: Hotel, Guide, Transport, Museum, Lunch, Dinner, Train, Flight, Show, Conference, Extra). Transport-type expenses are automatically mirrored into the `Transfer` model via `TourDayExpenseObserver`. `ExpenseService` recalculates and writes totals back to the `Tour` model. Expenses support multi-currency, with `_result`-suffixed columns storing converted values.

### Transfers & Notifications

Transfers are auto-created from Transport expenses, plus standalone ones from public API customer requests. `TourService::notifyDrivers()` sends Telegram alerts 60/30 minutes before a transfer (via the scheduler); `TransferService::notifyClientsForTransfer()` emails clients. `TransferObserver` snapshots prior values into `old_values` on update.

### Roles & Access

Filament panel access is gated by `User::canAccessPanel()`. Roles: `Admin(0)`, `Operator(1)`, `Accountant(2)`, `SeniorOperator(3)`, `User(10)`. `TourPolicy` uses `TourService::isVisible()` for edit/delete checks.

### Public API

Sanctum-authenticated endpoints, notably:
- `AuthController` — login, register, Google OAuth, profile, web-tour/contact requests
- `ManualController` — tour catalog, banners, services, countries/cities, transfer requests
- `HotelController` — hotel listing/detail/reviews/recommendations/booking requests

### Exports

`ExportController` generates `.docx`/`.xlsx` documents via dedicated services: `ExportService` (client invoice), `ExportClientService` (voucher), `ExportHotelService` (booking letter), `ExportMuseumService`, `ExportTransferService`. Templates live in `app/Services/Templates/`.

### Multilingual Fields

The `HasLocaleFields` trait exposes `getLocaleValue(string $attribute, Lang $lang)` for models with `_en` / `_ru` / `_uz` column suffixes.

## Environment

Key variables beyond Laravel defaults (see `.env.example`):

| Variable | Description |
|---|---|
| `DB_CONNECTION` | Must be `pgsql` — project runs on PostgreSQL |
| `APP_SCHEME` | Set to `https` to force HTTPS redirects (`AppServiceProvider`) |
| `TELEGRAM_BOT_TOKEN` | Used by `TelegramService` for driver notifications |

More context in [`CLAUDE.md`](./CLAUDE.md).

## Deployment

```bash
make deploy   # git pull, composer install --no-dev, migrate --force, optimize:clear
```

## License

MIT
