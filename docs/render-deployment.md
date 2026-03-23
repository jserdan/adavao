# Render Deployment Guide

This project is deployed on Render.com.

## Services Overview

- **AdminSide (Laravel)**: Web dashboard for police/admin
- **UserSide Backend (Node/Express)**: API used by the mobile app
- **Database**: MySQL in local `docker-compose.yml` (production DB may be Render PostgreSQL/MySQL depending on your setup)
- **Cache**: Redis (optional)
- **SARIMA API**: Python service for forecasting (optional depending on environment)

> The repository includes a basic Render blueprint for the Laravel AdminSide in `render.yaml`.

## AdminSide on Render (Laravel)

### 1) Create a Web Service

- Environment: **PHP**
- Root: repository root

Render uses the commands defined in `render.yaml`:
- Build: `cd AdminSide/admin && composer install --no-dev --optimize-autoloader`
- Start: `bash scripts/render-migrate.sh && cd AdminSide/admin && heroku-php-apache2 public/`

The deploy migration script lives at `scripts/render-migrate.sh` and is run on every redeploy.
Add any future DB changes so they can be applied automatically during deployment (preferred: Laravel migrations executed by the script).

### No-Shell Backfill for Urgency Scores

If your Render plan does not include Shell access, urgency score backfill is now executed from the deploy script automatically.

Controls (set in Render environment variables for the AdminSide service):
- `URGENCY_BACKFILL_ON_DEPLOY=1` to run `php artisan reports:recalculate-urgency` during startup (default: `1`)
- `URGENCY_BACKFILL_FAIL_HARD=1` to make startup fail if backfill fails (default: `1`)
- `URGENCY_BACKFILL_RETRIES=2` to retry recalculation before failing (default: `2`)

Recommended for production:
- Keep `URGENCY_BACKFILL_ON_DEPLOY=1` until old records are corrected
- Then set `URGENCY_BACKFILL_ON_DEPLOY=0` to speed up future deploys

### 2) Configure Environment Variables

Set these in Render’s service settings:
- `APP_KEY` (generate locally with `php artisan key:generate --show`)
- `APP_ENV=production`
- `APP_DEBUG=false`
- Database variables (`DB_*`) for your production database
- Email provider keys (e.g., SendGrid) if enabled
- `NODE_BACKEND_URL` (URL of the deployed UserSide backend)

### 3) Database Migrations

The start command runs migrations automatically:
- `php artisan migrate --force`

If you prefer manual migrations, remove it from the start command and run migrations via Render shell.

## UserSide Backend on Render (Node)

The Node backend lives in `UserSide/backends/`.

Recommended setup:
- Create a **Web Service** (Node)
- Root directory: `UserSide/backends`
- Build command: `npm install`
- Start command: `node server.js`

Environment variables typically needed:
- `PORT` (Render sets this automatically)
- `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_DATABASE`
- Any email/env config used by the backend

## Local Deployment (Docker Compose)

For local dev/testing, the repo includes `docker-compose.yml` with:
- MySQL
- Redis
- AdminSide
- UserSide backend
- SARIMA API

Run:
- `docker compose up --build`

## Notes

- Keep AdminSide and UserSide backend URLs consistent in config.
- If you deploy SARIMA separately, update the AdminSide SARIMA API URL in its config/controller.
