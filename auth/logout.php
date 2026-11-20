
<?php

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/logger.php';

// Mencatat aktivitas logout
if (!empty($_SESSION['user_id'])) {
    app_log('Pengguna melakukan logout.');
}

// Menghapus seluruh data session
$_SESSION = [];

// Menghapus cookie session
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );

}

// Mengakhiri session
session_destroy();

// Kembali ke halaman login
header('Location: ' . APP_URL . '/auth/login.php');
exit;
