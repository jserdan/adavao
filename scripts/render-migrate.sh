#!/usr/bin/env bash
set -euo pipefail

# Runs DB migrations on deploy (Render)
# - Safe to re-run on every redeploy
# - Uses Laravel migrations (php artisan migrate --force)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ADMIN_DIR="$ROOT_DIR/AdminSide/admin"

if [[ ! -f "$ADMIN_DIR/artisan" ]]; then
  echo "❌ Could not find Laravel artisan at: $ADMIN_DIR/artisan" >&2
  exit 1
fi

echo "🚀 Running Laravel migrations (Render deploy)…"
cd "$ADMIN_DIR"

# If your DB connection is not ready yet, Render will restart the service and this will run again.
php artisan migrate --force

# Create storage symlink for public file access (announcements, uploads, etc.)
echo "🔗 Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || echo "   storage:link already exists or not needed"

echo "✅ Migrations complete."

# Run essential seeders (safe to re-run - uses insertOrIgnore)
echo "🌱 Running essential seeders..."
php artisan db:seed --class=RolesTableSeeder --force
php artisan db:seed --class=PoliceStationsSeeder --force 2>/dev/null || echo "   PoliceStationsSeeder skipped (may not be needed)"
php artisan db:seed --class=PatrolOfficerSeeder --force

echo "✅ Seeders complete."

# Optional no-shell backfill for urgency_score.
# Useful on Render free plans where service shell is unavailable.
#
# Controls:
# - URGENCY_BACKFILL_ON_DEPLOY=1 to enable (default: 1)
# - URGENCY_BACKFILL_FAIL_HARD=1 to fail startup when backfill fails (default: 0)
RUN_URGENCY_BACKFILL="${URGENCY_BACKFILL_ON_DEPLOY:-1}"
FAIL_HARD_ON_URGENCY_BACKFILL="${URGENCY_BACKFILL_FAIL_HARD:-0}"

if [[ "$RUN_URGENCY_BACKFILL" == "1" ]]; then
  echo "🚨 Recalculating report urgency scores..."
  if php artisan reports:recalculate-urgency; then
    echo "✅ Urgency score backfill complete."
  else
    if [[ "$FAIL_HARD_ON_URGENCY_BACKFILL" == "1" ]]; then
      echo "❌ Urgency score backfill failed (fail-hard enabled)." >&2
      exit 1
    fi
    echo "⚠️ Urgency score backfill failed. Continuing startup (fail-hard disabled)."
  fi
else
  echo "ℹ️ Skipping urgency score backfill (URGENCY_BACKFILL_ON_DEPLOY=$RUN_URGENCY_BACKFILL)."
fi