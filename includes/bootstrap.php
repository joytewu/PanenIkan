<?php

// Memanggil konfigurasi aplikasi dan database
require_once __DIR__ . '/../config/app.php';
app_start_session();

require_once __DIR__ . '/../config/database.php';

// Memanggil sistem keamanan dan autentikasi
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/logger.php';

// Memastikan pengguna sudah login
require_login();

// Fungsi keamanan output
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Fungsi redirect
function go($file, $status = '') {
    $url = rtrim(APP_URL, '/') . '/' . ltrim($file, '/');

    if ($status !== '') {
        $url .= '?status=' . urlencode($status);
    }

    header('Location: ' . $url);
    exit;
}

// Pesan notifikasi
function flash_message() {
    $messages = [
        'tambah' => 'Data berhasil ditambahkan.',
        'edit'   => 'Data berhasil diperbarui.',
        'hapus'  => 'Data berhasil dihapus.',
    ];

    return $messages[$_GET['status'] ?? ''] ?? '';
}

// Menangani error database
function db_error_message(mysqli_sql_exception $ex) {
    app_log($ex->getMessage(), 'ERROR');

    if ((int) $ex->getCode() === 1451) {
        return 'Data tidak bisa dihapus karena masih digunakan oleh catatan panen.';
    }

    if ((int) $ex->getCode() === 1062) {
        return 'Data tersebut sudah ada.';
    }

    return 'Operasi database gagal. Periksa data dan coba kembali.';
}

// Memeriksa kolom database
function hasColumn($conn, $table, $column) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?"
    );

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return (int) $stmt->get_result()
        ->fetch_assoc()['total'] > 0;
}

// Mengambil data berdasarkan ID
function fetch_by_id($conn, $table, $id) {
    $allowed = ['panen', 'jenis_ikan', 'kolam'];

    $id = (int) $id;

    if (!in_array($table, $allowed, true) || $id < 1) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM `$table` WHERE id = ?"
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

// Mengambil pilihan jenis ikan
function get_jenis_options($conn) {
    return $conn->query(
        'SELECT id, nama_ikan
         FROM jenis_ikan
         ORDER BY nama_ikan'
    )->fetch_all(MYSQLI_ASSOC);
}

// Mengambil pilihan kolam
function get_kolam_options($conn) {
    return $conn->query(
        'SELECT id, nama_kolam
         FROM kolam
         ORDER BY nama_kolam'
    )->fetch_all(MYSQLI_ASSOC);
}
