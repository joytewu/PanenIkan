
#!/bin/bash

# Konfigurasi database
DB_NAME="panenikan"
DB_USER="root"
DB_PASS=""

# Lokasi penyimpanan backup
BACKUP_DIR="$(dirname "$0")/../storage/backups"

# Membuat folder backup jika belum ada
mkdir -p "$BACKUP_DIR"

# Nama file backup berdasarkan tanggal dan waktu
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${DATE}.sql"

echo "Memulai backup database..."

# Proses backup
if [ -n "$DB_PASS" ]; then
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"
else
    mysqldump -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"
fi

# Memeriksa hasil backup
if [ $? -eq 0 ]; then
    echo "Backup berhasil!"
    echo "File tersimpan di: $BACKUP_FILE"
else
    echo "Backup gagal!"
    rm -f "$BACKUP_FILE"
    exit 1
fi
