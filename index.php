<?php

require_once __DIR__ . '/config/app.php';
app_start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: auth/login.php');
exit;
