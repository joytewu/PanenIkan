#!/usr/bin/env bash
set -euo pipefail
umask 077

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
BACKUP_DIR="${PANENIKAN_BACKUP_DIR:-/var/backups/panenikan}"
UPLOAD_DIR="${PANENIKAN_UPLOAD_DIR:-$(dirname "$APP_ROOT")/panenikan-private/uploads/panen}"
MYSQL_CONFIG_SCRIPT="$APP_ROOT/scripts/create_mysql_defaults_file.php"

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    echo "Run PanenIkan backups as root to use local MySQL socket authentication." >&2
    exit 1
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP CLI is required for PanenIkan backups." >&2
    exit 1
fi

if ! command -v mysqldump >/dev/null 2>&1; then
    echo "mysqldump is required for PanenIkan backups." >&2
    exit 1
fi

if [[ -L "$BACKUP_DIR" ]]; then
    echo "Backup destination must not be a symbolic link." >&2
    exit 1
fi

install -d -o root -g root -m 0700 "$BACKUP_DIR"
chown root:root "$BACKUP_DIR"
chmod 0700 "$BACKUP_DIR"

cleanup() {
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
    --protocol=socket \
    --user=root \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --no-tablespaces \
    "$DB_NAME" > "$WORK_DIR/database.sql"

if [[ ! -s "$WORK_DIR/database.sql" ]]; then
    echo "Database dump is empty; backup aborted." >&2
    exit 1
fi

cp -a "$UPLOAD_DIR/." "$WORK_DIR/uploads/"
tar -czf "$TMP_ARCHIVE" -C "$WORK_DIR" database.sql uploads
if ! gzip -t "$TMP_ARCHIVE"; then
    echo "Backup archive failed gzip integrity validation." >&2
    exit 1
fi

ARCHIVE_CONTENTS="$(tar -tzf "$TMP_ARCHIVE")"
if ! grep -Fxq 'database.sql' <<< "$ARCHIVE_CONTENTS" ||
    ! grep -Eq '^uploads/?$' <<< "$ARCHIVE_CONTENTS"; then
    echo "Backup archive is missing the database dump or uploads directory." >&2
    exit 1
fi

chmod 0600 "$TMP_ARCHIVE"
mv -- "$TMP_ARCHIVE" "$ARCHIVE"

if [[ -n "$REMOTE_TARGET" ]]; then
    if ! command -v rsync >/dev/null 2>&1; then
        echo "Remote backup is configured but rsync is not installed." >&2
        exit 1
    fi

    rsync -a --checksum --chmod=Du=rwx,Dgo=,Fu=rw,Fgo= \
        "$ARCHIVE" "${REMOTE_TARGET%/}/"

    if ! VERIFY_OUTPUT="$(rsync -a --checksum --dry-run \
        --itemize-changes --out-format='%i %n' \
        "$ARCHIVE" "${REMOTE_TARGET%/}/")"; then
        echo "Remote backup verification command failed." >&2
        exit 1
    fi

    if [[ -n "$VERIFY_OUTPUT" ]]; then
        echo "Remote backup verification found a difference:" >&2
        printf '%s\n' "$VERIFY_OUTPUT" >&2
        exit 1
    fi

    echo "Local and remote backup completed: $ARCHIVE"
else
    echo "Local backup completed: $ARCHIVE"
    echo "Offsite copy is not configured; set PANENIKAN_BACKUP_REMOTE in the private config."
fi

find "$BACKUP_DIR" -maxdepth 1 -type f \
    -name 'panenikan-*.tar.gz' -mtime +30 -delete
