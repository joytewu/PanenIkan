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

if (APP_ENV === 'production') {
    foreach ([
        'display_errors' => '0',
        'display_startup_errors' => '0',
        'log_errors' => '1',
    ] as $setting => $value) {
        if (ini_set($setting, $value) === false) {
            error_log('PanenIkan could not apply production PHP setting: ' . $setting);
        }
    }
}

$configuredAppUrl = app_environment_value('PANENIKAN_URL');

if ($configuredAppUrl === false || trim($configuredAppUrl) === '') {
    $configuredAppUrl = APP_ENV === 'local'
        ? 'https://localhost'
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

    $directHttps = is_string($https) &&
        $https !== '' &&
        strtolower($https) !== 'off';

    if ($directHttps) {
        return true;
    }

    $trustProxy = app_environment_value('PANENIKAN_TRUST_PROXY');

    if ($trustProxy !== '1') {
        return false;
    }

    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

    if (is_string($forwardedProto)) {
        $firstProto = strtolower(trim(explode(',', $forwardedProto)[0]));

        if ($firstProto === 'https') {
            return true;
        }
    }

    $forwarded = $_SERVER['HTTP_FORWARDED'] ?? '';

    return is_string($forwarded) &&
        preg_match('/(?:^|[;,]\s*)proto\s*=\s*"?https"?(?:;|,|$)/i', $forwarded) === 1;
}

function app_enforce_https(): void {
    if (PHP_SAPI === 'cli') {
        return;
    }

    $configured = parse_url(APP_URL);

    if (!is_array($configured) || !isset($configured['scheme'], $configured['host'])) {
        throw new RuntimeException('PANENIKAN_URL tidak valid.');
    }

    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    $requestHostName = '';

    if (is_string($requestHost) && $requestHost !== '') {
        $hostOnly = parse_url('//' . $requestHost, PHP_URL_HOST);

        if (is_string($hostOnly)) {
            $requestHostName = strtolower(trim($hostOnly, '[]'));
        }
    }
    $configuredHost = strtolower($configured['host']);
    $needsCanonicalRedirect =
        APP_ENV === 'production' &&
        (
            !app_is_https_request() ||
            $requestHostName !== $configuredHost
        ) ||
        APP_ENV === 'local' &&
        (
            !app_is_https_request() ||
            (
                $requestHostName !== '' &&
                in_array($configuredHost, ['localhost', '127.0.0.1', '::1'], true) &&
                $requestHostName !== $configuredHost
            )
        );

    if (!$needsCanonicalRedirect) {
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

    if (!is_string($requestUri) || $requestUri === '' || $requestUri[0] !== '/') {
        $requestUri = '/';
    }

    if (preg_match('/[\r\n]/', $requestUri) === 1) {
        $requestUri = '/';
    }

    header('Location: ' . rtrim(APP_URL, '/') . $requestUri, true, 301);
    exit;
}

app_enforce_https();

function app_deny_direct_access($file) {

    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';

    if ($script !== '' && realpath($script) === realpath($file)) {
        app_render_http_error(404);
    }

}

function app_render_http_error(int $status): void {
    if (!in_array($status, [403, 404], true)) {
        throw new InvalidArgumentException('Kode status HTTP tidak didukung.');
    }

    http_response_code($status);
    $isForbidden = $status === 403;
    $title = $isForbidden ? 'Akses ditolak' : 'Halaman tidak ditemukan';
    $message = $isForbidden
        ? 'Anda tidak memiliki izin untuk membuka halaman ini.'
        : 'Halaman yang Anda cari tidak tersedia.';
    $link = rtrim(APP_URL, '/') . ($isForbidden ? '/auth/login.php' : '/');

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
        ' | PanenIkan</title><style>' .
        'body{margin:0;background:#f4f8f7;color:#172d2b;font:16px ' .
        'Segoe UI,Arial,sans-serif;display:grid;min-height:100vh;' .
        'place-items:center}.card{background:#fff;border:1px solid #e5eeeb;' .
        'border-radius:16px;padding:32px;max-width:480px;margin:20px}' .
        'h1{font-size:24px}p{color:#71817e;line-height:1.6}' .
        'a{display:inline-block;background:#087f70;color:#fff;padding:11px 16px;' .
        'border-radius:9px;text-decoration:none;font-weight:700}</style>' .
        '</head><body><main class="card"><h1>' .
        htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' .
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a href="' .
        htmlspecialchars($link, ENT_QUOTES, 'UTF-8') .
        '">Kembali</a></main></body></html>';
    exit;
}

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
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('X-Permitted-Cross-Domain-Policies: none');

if (APP_ENV === 'production' && app_is_https_request()) {
    header('Strict-Transport-Security: max-age=31536000');
}

app_deny_direct_access(__FILE__);

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
