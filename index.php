
<?php

session_start();

require_once __DIR__ . '/config/app.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/auth/login.php');
}

exit;
