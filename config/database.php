<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        'localhost',
        'panenikan_app',
        'PanenIkan2026!',
        'panenikan'
    );

    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    die('Koneksi database gagal. Periksa konfigurasi database PanenIkan.');
}
