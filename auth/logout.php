<?php

require_once __DIR__ . '/../config/app.php';
app_start_session();

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

try {
    verify_csrf();
} catch (Exception $e) {
    http_response_code(403);
    exit('Token keamanan tidak valid. Muat ulang halaman.');
}

// Mencatat aktivitas logout
if (!empty($_SESSION['user_id'])) {
    app_log('Pengguna melakukan logout.');
}

// Menghapus seluruh data session
$_SESSION = [];

if (!session_destroy()) {
    throw new RuntimeException('Session gagal dihapus saat logout.');
}

app_expire_session_cookie();

// Kembali ke halaman login
header('Location: ' . APP_URL . '/auth/login.php');
exit;
