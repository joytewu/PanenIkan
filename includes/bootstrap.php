<?php

// Memanggil konfigurasi aplikasi dan database
require_once __DIR__ . '/../config/app.php';
app_deny_direct_access(__FILE__);
app_start_session();

// Memanggil sistem keamanan dan autentikasi
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/input_validation.php';
require_once __DIR__ . '/logger.php';

// Memastikan pengguna terautentikasi dan berhak sebelum membuka database
require_admin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

require_once __DIR__ . '/../config/database.php';

// Fungsi keamanan output
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
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

    $status = $_GET['status'] ?? '';

    return is_string($status)
        ? ($messages[$status] ?? '')
        : '';
}

function request_integer(
    array $source,
    string $key,
    string $label,
    int $minimum,
    int $maximum,
    bool $allowEmpty = false
): int {
    if (!array_key_exists($key, $source)) {
        if ($allowEmpty) {
            return 0;
        }

        throw new InvalidArgumentException($label . ' tidak valid.');
    }

    $value = $source[$key];

    if ($allowEmpty && $value === '') {
        return 0;
    }

    if (!is_string($value) && !is_int($value)) {
        throw new InvalidArgumentException($label . ' tidak valid.');
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT);

    if (
        $validated === false ||
        $validated < $minimum ||
        $validated > $maximum
    ) {
        throw new InvalidArgumentException($label . ' tidak valid.');
    }

    return $validated;
}

function optional_input_text($value, int $maxLength): string {
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);

    return mb_strlen($value, 'UTF-8') <= $maxLength
        ? $value
        : '';
}

function optional_input_integer(
    $value,
    int $minimum = 0,
    int $maximum = 2147483647
): int {
    if ((!is_string($value) && !is_int($value)) || $value === '') {
        return 0;
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT);

    return $validated !== false &&
        $validated >= $minimum &&
        $validated <= $maximum
        ? $validated
        : 0;
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
function fetch_by_id($conn, $table, $id, $userId) {
    $allowed = ['panen', 'jenis_ikan', 'kolam'];

    $id = (int) $id;
    $userId = (int) $userId;

    if (
        !in_array($table, $allowed, true) ||
        $id < 1 ||
        $userId < 1
    ) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT *
         FROM `$table`
         WHERE id = ?
         AND (owner_user_id = ? OR owner_user_id IS NULL)"
    );

    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

// Mengambil pilihan jenis ikan
function get_jenis_options($conn, $userId) {
    $stmt = $conn->prepare(
        'SELECT id, nama_ikan
         FROM jenis_ikan
         WHERE owner_user_id = ? OR owner_user_id IS NULL
         ORDER BY nama_ikan'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Mengambil pilihan kolam
function get_kolam_options($conn, $userId) {
    $stmt = $conn->prepare(
        'SELECT id, nama_kolam
         FROM kolam
         WHERE owner_user_id = ? OR owner_user_id IS NULL
         ORDER BY nama_kolam'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
