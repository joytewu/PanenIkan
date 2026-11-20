
<?php

/**
 * ==========================================
 * KONFIGURASI DATABASE PANENIKAN
 * ==========================================
 */

// Konfigurasi koneksi database
$host     = 'localhost';
$username = 'root';
$password = '';
$database = 'panenikan';

// Pengaturan laporan error MySQL
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Proses koneksi database
try {

    $conn = new mysqli(
        $host,
        $username,
        $password,
        $database
    );

    // Mengatur karakter database
    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {

    // Mencatat kesalahan ke error log server
    error_log(
        'Database connection error: ' . $e->getMessage()
    );

    // Menampilkan pesan jika koneksi gagal
    die(
        'Koneksi database gagal. Periksa konfigurasi database PanenIkan.'
    );

}
