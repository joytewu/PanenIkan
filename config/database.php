<?php

$conn = new mysqli(
    'localhost',
    'panenikan_app',
    'PanenIkan2026!',
    'panenikan'
);

if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');