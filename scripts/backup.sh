#!/usr/bin/env bash
set -euo pipefail
umask 077

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
BACKUP_DIR="${PANENIKAN_BACKUP_DIR:-/var/backups/panenikan}"
UPLOAD_DIR="${PANENIKAN_UPLOAD_DIR:-$(dirname "$APP_ROOT")/panenikan-private/uploads/panen}"
MYSQL_CONFIG_SCRIPT="$APP_ROOT/scripts/create_mysql_defaults_file.php"

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP CLI is required for PanenIkan backups." >&2
    exit 1
fi

install -d -m 0700 "$BACKUP_DIR"
chmod 0700 "$BACKUP_DIR"

MYSQL_DEFAULTS="$("$PHP_BIN" "$MYSQL_CONFIG_SCRIPT")"
cleanup() {
    if [[ -n "${MYSQL_DEFAULTS:-}" && -f "$MYSQL_DEFAULTS" ]]; then
        rm -f -- "$MYSQL_DEFAULTS"
    fi
    if [[ -n "${WORK_DIR:-}" && -d "$WORK_DIR" ]]; then
        rm -rf -- "$WORK_DIR"
    fi
}
trap cleanup EXIT

DB_NAME="$("$PHP_BIN" "$MYSQL_CONFIG_SCRIPT" --database-name)"
REMOTE_TARGET="$("$PHP_BIN" "$MYSQL_CONFIG_SCRIPT" --backup-remote)"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
WORK_DIR="$(mktemp -d "$BACKUP_DIR/.panenikan-backup.XXXXXX")"
ARCHIVE="$BACKUP_DIR/panenikan-$STAMP.tar.gz"
TMP_ARCHIVE="$WORK_DIR/panenikan-$STAMP.tar.gz"

if [[ ! -d "$UPLOAD_DIR" ]]; then
    echo "PanenIkan upload directory is missing: $UPLOAD_DIR" >&2
    exit 1
fi

if [[ -n "$(find "$UPLOAD_DIR" -type l -print -quit)" ]]; then
    echo "PanenIkan upload directory contains a symbolic link; backup aborted." >&2
    exit 1
fi

mkdir "$WORK_DIR/uploads"
echo "Creating database and upload backup..."
mysqldump \
    --defaults-extra-file="$MYSQL_DEFAULTS" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --no-tablespaces \
    "$DB_NAME" > "$WORK_DIR/database.sql"
cp -a "$UPLOAD_DIR/." "$WORK_DIR/uploads/"
tar -czf "$TMP_ARCHIVE" -C "$WORK_DIR" database.sql uploads
chmod 0600 "$TMP_ARCHIVE"
mv -- "$TMP_ARCHIVE" "$ARCHIVE"

if [[ -n "$REMOTE_TARGET" ]]; then
    if ! command -v rsync >/dev/null 2>&1; then
        echo "Remote backup is configured but rsync is not installed." >&2
        exit 1
    fi
    rsync -a --chmod=Du=rwx,Dgo=,Fu=rw,Fgo= "$ARCHIVE" "${REMOTE_TARGET%/}/"
    echo "Local and remote backup completed: $ARCHIVE"
else
    echo "Local backup completed: $ARCHIVE"
    echo "Offsite copy is not configured; set PANENIKAN_BACKUP_REMOTE in the private config."
fi

find "$BACKUP_DIR" -maxdepth 1 -type f \
    -name 'panenikan-*.tar.gz' -mtime +30 -delete
