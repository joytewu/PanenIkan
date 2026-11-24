<?php

// Memeriksa apakah pengguna sudah login
function require_login() {

    app_start_session();

    if (!is_logged_in()) {

        header('Location: ' . rtrim(APP_URL, '/') . '/auth/login.php');
        exit;

    }

}

// Memeriksa status login pengguna
function is_logged_in() {

    return !empty($_SESSION['user_id']);

}
