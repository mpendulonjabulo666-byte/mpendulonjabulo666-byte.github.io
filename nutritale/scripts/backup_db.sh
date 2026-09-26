#!/bin/sh
# Daily database backup for NutriTale — see DEPLOYMENT.md's "Backups"
# section for the crontab line that runs this and the restore command.
#
# Reads DB_HOST/DB_NAME/DB_USER/DB_PASS straight out of config/config.php
# via `php -r`, rather than duplicating those credentials a second time in
# this script — one source of truth, and nothing here needs editing when
# they change on the server.
#
# Usage: backup_db.sh <backup-directory>
# The backup directory must be OUTSIDE the web root (e.g. a sibling of
# nutritale/, not inside it) so a dump can never be downloaded by URL —
# .htaccess blocks nutritale/config and a few others, but does not (and
# should not have to) guess where you point this script.

set -eu

BACKUP_DIR="${1:?Usage: backup_db.sh <backup-directory>}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"
KEEP_DAYS=14

mkdir -p "$BACKUP_DIR"

eval "$(php -r '
require "'"$APP_DIR"'/config/config.php";
echo "DB_HOST=" . escapeshellarg(DB_HOST) . "\n";
echo "DB_PORT=" . escapeshellarg(DB_PORT) . "\n";
echo "DB_NAME=" . escapeshellarg(DB_NAME) . "\n";
echo "DB_USER=" . escapeshellarg(DB_USER) . "\n";
echo "DB_PASS=" . escapeshellarg(DB_PASS) . "\n";
')"

TIMESTAMP="$(date +%Y-%m-%d_%H%M%S)"
OUT_FILE="$BACKUP_DIR/nutritale_${TIMESTAMP}.sql.gz"

mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" \
    --user="$DB_USER" --password="$DB_PASS" \
    --single-transaction --quick --routines \
    "$DB_NAME" | gzip > "$OUT_FILE"

# Rotation: delete backups older than KEEP_DAYS so this directory doesn't
# grow forever. Only touches files this script itself created (the
# nutritale_*.sql.gz name pattern), never anything else that might live
# in the same backup directory.
find "$BACKUP_DIR" -maxdepth 1 -name 'nutritale_*.sql.gz' -mtime "+${KEEP_DAYS}" -delete

echo "Backed up to $OUT_FILE"
