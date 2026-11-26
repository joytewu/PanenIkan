#!/usr/bin/env bash
set -euo pipefail
umask 077

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
MYSQL_CONFIG_SCRIPT="$APP_ROOT/scripts/create_mysql_defaults_file.php"
BACKUP_FILE="${1:-}"

if [[ -z "$BACKUP_FILE" || ! -f "$BACKUP_FILE" ]]; then
    echo "Usage: $0 /path/to/panenikan-backup.tar.gz" >&2
    exit 1
fi

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    echo "Run PanenIkan restores as root to use local MySQL socket authentication." >&2
    exit 1
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP CLI is required to restore PanenIkan backups." >&2
    exit 1
fi

if ! command -v mysql >/dev/null 2>&1; then
    echo "The MySQL client is required to restore PanenIkan backups." >&2
    exit 1
fi

if [[ -z "${PANENIKAN_RESTORE_UPLOAD_DIR:-}" ]]; then
    echo "Set PANENIKAN_RESTORE_UPLOAD_DIR to an empty upload destination." >&2
    exit 1
fi

RESTORE_UPLOAD_DIR="$PANENIKAN_RESTORE_UPLOAD_DIR"
if [[ -L "$RESTORE_UPLOAD_DIR" ]]; then
    echo "Restore upload destination must not be a symbolic link." >&2
    exit 1
fi

if [[ -e "$RESTORE_UPLOAD_DIR" && ! -d "$RESTORE_UPLOAD_DIR" ]]; then
    echo "Restore upload destination exists and is not a directory." >&2
    exit 1
fi

if [[ -d "$RESTORE_UPLOAD_DIR" ]] &&
    [[ -n "$(find "$RESTORE_UPLOAD_DIR" -mindepth 1 -print -quit)" ]]; then
    echo "Restore upload destination must be empty." >&2
    exit 1
fi

WORK_DIR=''
cleanup() {
    if [[ -n "${WORK_DIR:-}" && -d "$WORK_DIR" ]]; then
        rm -rf -- "$WORK_DIR"
    fi
}
trap cleanup EXIT

WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/panenikan-restore.XXXXXX")"
DB_NAME="$("$PHP_BIN" "$MYSQL_CONFIG_SCRIPT" --database-name)"
ARCHIVE_CONTENTS="$(tar -tzf "$BACKUP_FILE")"

while IFS= read -r entry; do
    case "$entry" in
        database.sql|uploads|uploads/|uploads/*) ;;
        *)
            echo "Backup contains an unexpected path; restore aborted." >&2
            exit 1
            ;;
    esac
    case "$entry" in
        /*|../*|*/../*|*/..|*\\*)
            echo "Backup contains an unsafe path; restore aborted." >&2
            exit 1
            ;;
    esac
done <<< "$ARCHIVE_CONTENTS"

if ! grep -Fxq 'database.sql' <<< "$ARCHIVE_CONTENTS"; then
    echo "Backup does not contain database.sql." >&2
    exit 1
fi

if ! grep -Eq '^uploads/?$' <<< "$ARCHIVE_CONTENTS"; then
    echo "Backup does not contain the uploads directory." >&2
    exit 1
fi

read -r -p "Target database [$DB_NAME]: " TARGET_DATABASE
TARGET_DATABASE="${TARGET_DATABASE:-$DB_NAME}"

if [[ ! "$TARGET_DATABASE" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo "Target database name is invalid." >&2
    exit 1
fi

printf 'This will replace data in database "%s". Type the database name to continue: ' "$TARGET_DATABASE"
read -r CONFIRM_DATABASE
if [[ "$CONFIRM_DATABASE" != "$TARGET_DATABASE" ]]; then
    echo "Restore cancelled."
    exit 1
fi

tar -xzf "$BACKUP_FILE" -C "$WORK_DIR" --no-same-owner
mysql --protocol=socket --user=root \
    --execute="CREATE DATABASE IF NOT EXISTS \`$TARGET_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql --protocol=socket --user=root "$TARGET_DATABASE" \
    < "$WORK_DIR/database.sql"

if [[ ! -d "$WORK_DIR/uploads" ]]; then
    echo "Backup does not contain uploads." >&2
    exit 1
fi

install -d -o www-data -g www-data -m 0750 "$RESTORE_UPLOAD_DIR"
cp -a "$WORK_DIR/uploads/." "$RESTORE_UPLOAD_DIR/"
chown -R www-data:www-data "$RESTORE_UPLOAD_DIR"

echo "Database and uploads restored for $TARGET_DATABASE."
