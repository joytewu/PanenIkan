<?php
session_start();
require __DIR__ . '/../db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function go($file, $status = '') {
    $url = $file . '.php';
    if ($status !== '') {
        $url .= '?status=' . urlencode($status);
    }
    header('Location: ' . $url);
    exit;
}

function verify_csrf() {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        throw new Exception('Token keamanan tidak valid. Muat ulang halaman.');
    }
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function flash_message() {
    $messages = [
        'tambah' => 'Data berhasil ditambahkan.',
        'edit'   => 'Data berhasil diperbarui.',
        'hapus'  => 'Data berhasil dihapus.',
    ];
    return $messages[$_GET['status'] ?? ''] ?? '';
}

function db_error_message(mysqli_sql_exception $ex) {
    error_log('PanenIkan database error: ' . $ex->getMessage());
    if ((int)$ex->getCode() === 1451) {
        return 'Data tidak bisa dihapus karena masih digunakan oleh catatan panen.';
    }
    if ((int)$ex->getCode() === 1062) {
        return 'Data tersebut sudah ada.';
    }
    return 'Operasi database gagal. Periksa data dan coba kembali.';
}

function hasColumn($conn, $table, $column) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'] > 0;
}

function fetch_by_id($conn, $table, $id) {
    $allowed = ['panen', 'jenis_ikan', 'kolam'];
    if (!in_array($table, $allowed, true) || $id < 1) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_jenis_options($conn) {
    return $conn->query('SELECT id, nama_ikan FROM jenis_ikan ORDER BY nama_ikan')->fetch_all(MYSQLI_ASSOC);
}

function get_kolam_options($conn) {
    return $conn->query('SELECT id, nama_kolam FROM kolam ORDER BY nama_kolam')->fetch_all(MYSQLI_ASSOC);
}
