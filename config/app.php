<?php

define('APP_NAME', 'PanenIkan');
define('APP_VERSION', '1.0.0');
define('APP_ROLE_ADMIN', 'admin');

function app_environment_value(string $name) {
    $value = getenv($name);

    if ($value !== false) {
        return $value;
    }

    if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) {
        return $_SERVER[$name];
    }

    static $externalConfig = null;

    if ($externalConfig === null) {
        $configFile = dirname(__DIR__, 2) .
            DIRECTORY_SEPARATOR . 'panenikan-private' .
            DIRECTORY_SEPARATOR . 'config.php';

        if (is_file($configFile)) {
            $loadedConfig = require $configFile;

            if (!is_array($loadedConfig)) {
                throw new RuntimeException(
                    'Konfigurasi lokal PanenIkan harus mengembalikan array.'
                );
            }

            $externalConfig = $loadedConfig;
        } else {
            $externalConfig = [];
        }
    }

    return isset($externalConfig[$name]) &&
        is_string($externalConfig[$name])
            ? $externalConfig[$name]
            : false;
}

$appEnvironment = app_environment_value('PANENIKAN_ENV');

if ($appEnvironment === false || $appEnvironment === '') {
    $appEnvironment = 'production';
}

if (!in_array($appEnvironment, ['local', 'production'], true)) {
    throw new RuntimeException('PANENIKAN_ENV harus local atau production.');
}

define('APP_ENV', $appEnvironment);

$configuredAppUrl = app_environment_value('PANENIKAN_URL');

if ($configuredAppUrl === false || trim($configuredAppUrl) === '') {
    $configuredAppUrl = APP_ENV === 'local'
        ? 'http://localhost'
        : 'https://www.devps.online';
}

$appUrlParts = parse_url($configuredAppUrl);

if (
    !is_array($appUrlParts) ||
    !isset($appUrlParts['scheme'], $appUrlParts['host']) ||
    !in_array(strtolower($appUrlParts['scheme']), ['http', 'https'], true) ||
    (APP_ENV === 'production' && strtolower($appUrlParts['scheme']) !== 'https')
) {
    throw new RuntimeException('PANENIKAN_URL tidak valid untuk lingkungan aplikasi.');
}

define('APP_URL', rtrim($configuredAppUrl, '/'));

define('APP_TIMEZONE', 'Asia/Makassar');

function app_is_https_request(): bool {
    $https = $_SERVER['HTTPS'] ?? '';

    return is_string($https) &&
        $https !== '' &&
        strtolower($https) !== 'off';
}

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

if (APP_ENV === 'production' && app_is_https_request()) {
    header('Strict-Transport-Security: max-age=31536000');
}

date_default_timezone_set(APP_TIMEZONE);

function app_start_session() {

    static $started = false;

    if ($started) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        $secureCookie = APP_ENV !== 'local' || app_is_https_request();

        $settings = [
            'session.use_trans_sid' => '0',
            'session.use_only_cookies' => '1',
            'session.use_strict_mode' => '1',
            'session.cookie_lifetime' => '0',
            'session.cookie_secure' => $secureCookie ? '1' : '0',
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
            'secure' => $secureCookie,
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
    $secureCookie = APP_ENV !== 'local' || app_is_https_request();

    if (!setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ])) {
        throw new RuntimeException('Cookie session gagal dihapus.');
    }
}
