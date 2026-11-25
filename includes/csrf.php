<?php

require_once __DIR__ . '/../config/app.php';
app_deny_direct_access(__FILE__);

function generate_csrf_token() {

    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );

    }

    return $_SESSION['csrf_token'];

}

function verify_csrf() {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {

        throw new Exception(
            'Token keamanan tidak valid. Muat ulang halaman.'
        );

    }

}

function csrf_field() {

    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';

}
