<?php

require_once __DIR__ . '/app.php';
app_deny_direct_access(__FILE__);

$databasePassword = app_environment_value('PANENIKAN_DB_PASSWORD');

if (!is_string($databasePassword) || $databasePassword === '') {
    error_log(
        'PanenIkan database configuration is incomplete: PANENIKAN_DB_PASSWORD is unset.'
    );
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi database PanenIkan.');
}

$databaseHost = app_environment_value('PANENIKAN_DB_HOST');
$databaseUser = app_environment_value('PANENIKAN_DB_USER');
$databaseName = app_environment_value('PANENIKAN_DB_NAME');

$databaseHost = is_string($databaseHost) && $databaseHost !== ''
    ? $databaseHost
    : 'localhost';
$databaseUser = is_string($databaseUser) && $databaseUser !== ''
    ? $databaseUser
    : 'panenikan_app';
$databaseName = is_string($databaseName) && $databaseName !== ''
    ? $databaseName
    : 'panenikan';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $sslCa = app_environment_value('PANENIKAN_DB_SSL_CA');
    $sslEnabled = is_string($sslCa) && $sslCa !== '';
    $sslVerify = app_environment_value('PANENIKAN_DB_SSL_VERIFY') === '1';

    if ($sslEnabled) {
        $conn = mysqli_init();

        if ($conn === false) {
            throw new RuntimeException('Inisialisasi koneksi database gagal.');
        }

        $cipherSuites = '';
        $sslKey = app_environment_value('PANENIKAN_DB_SSL_KEY');
        $sslCert = app_environment_value('PANENIKAN_DB_SSL_CERT');
        $sslKey = is_string($sslKey) ? $sslKey : '';
        $sslCert = is_string($sslCert) ? $sslCert : '';
        $conn->ssl_set(
            $sslKey,
            $sslCert,
            $sslCa,
            null,
            $cipherSuites
        );
        $verifyFlag = defined('MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT')
            ? MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT
            : MYSQLI_CLIENT_SSL;
        $flags = $sslVerify ? $verifyFlag : MYSQLI_CLIENT_SSL;
        $conn->real_connect(
            $databaseHost,
            $databaseUser,
            $databasePassword,
            $databaseName,
            null,
            null,
            $flags
        );
    } else {
        $conn = new mysqli(
            $databaseHost,
            $databaseUser,
            $databasePassword,
            $databaseName
        );
    }

    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log(
        'PanenIkan database connection failed: ' . $e->getMessage()
    );
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi database PanenIkan.');
}
