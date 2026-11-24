<?php

define('APP_NAME', 'PanenIkan');
define('APP_VERSION', '1.0.0');
define('APP_ROLE_ADMIN', 'admin');

define('APP_ENV', 'production');

define('APP_URL', 'https://www.devps.online');

define('APP_TIMEZONE', 'Asia/Makassar');

function app_deny_direct_access($file) {

    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';

    if ($script !== '' && realpath($script) === realpath($file)) {
        http_response_code(404);
        exit;
    }

}

app_deny_direct_access(__FILE__);

header(
    "Content-Security-Policy: default-src 'self'; base-uri 'self'; " .
    "object-src 'none'; frame-ancestors 'none'; form-action 'self'; " .
    "script-src 'self'; style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; font-src 'self'"
);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

date_default_timezone_set(APP_TIMEZONE);

function app_start_session() {

    static $started = false;

    if ($started) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {

        $settings = [
            'session.use_trans_sid' => '0',
            'session.use_only_cookies' => '1',
            'session.use_strict_mode' => '1',
            'session.cookie_lifetime' => '0',
            'session.cookie_secure' => '1',
            'session.cookie_httponly' => '1',
            'session.cookie_samesite' => 'Lax',
            'session.gc_maxlifetime' => '1800',
        ];

        foreach ($settings as $setting => $value) {

            if (ini_set($setting, $value) === false) {
                throw new RuntimeException(
                    'Tidak dapat menerapkan konfigurasi session: ' . $setting
                );
            }

        }

        $cookie = session_get_cookie_params();

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new RuntimeException('Session tidak dapat dimulai.');
        }

    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Status session tidak valid.');
    }

    $lastActivity = $_SESSION['last_activity'] ?? null;

    if (!empty($_SESSION['user_id'])) {

        if (
            !is_int($lastActivity) ||
            $lastActivity > time() ||
            time() - $lastActivity >= 1800
        ) {

            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                app_expire_session_cookie();
            }

            if (!session_destroy()) {
                throw new RuntimeException('Session yang kedaluwarsa gagal dihapus.');
            }

            session_id('');

            if (!session_start()) {
                throw new RuntimeException('Session baru tidak dapat dimulai.');
            }

        } else {
            $_SESSION['last_activity'] = time();
        }

    }

    $started = true;
}

function app_expire_session_cookie() {

    if (!ini_get('session.use_cookies')) {
        return;
    }

    $cookie = session_get_cookie_params();

    if (!setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ])) {
        throw new RuntimeException('Cookie session gagal dihapus.');
    }
}
