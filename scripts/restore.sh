
#!/bin/bash

# Konfigurasi database
DB_NAME="panenikan"
DB_USER="root"
DB_PASS=""

# Memeriksa argumen file backup
if [ -z "$1" ]; then
    echo "Cara penggunaan:"
    echo "./restore.sh nama_file_backup.sql"
    exit 1
fi

BACKUP_FILE="$1"

# Memeriksa keberadaan file
if [ ! -f "$BACKUP_FILE" ]; then
    echo "File backup tidak ditemukan!"
    exit 1
fi

echo "PERINGATAN: Database $DB_NAME akan dipulihkan."
read -p "Lanjutkan? (y/n): " CONFIRM

if [ "$CONFIRM" != "y" ]; then
    echo "Restore dibatalkan."
    exit 0
fi

# Proses restore
if [ -n "$DB_PASS" ]; then
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"
else
    mysql -u "$DB_USER" "$DB_NAME" < "$BACKUP_FILE"
fi

if [ $? -eq 0 ]; then
    echo "Restore database berhasil!"
else
    echo "Restore database gagal!"
    exit 1
fi
