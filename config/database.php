<?php

$host = 'localhost';
$username = 'USERNAME_DATABASE_VPS';
$password = 'PASSWORD_DATABASE_VPS';
$database = 'panenikan';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    die('Koneksi database gagal. Periksa konfigurasi database PanenIkan.');
}