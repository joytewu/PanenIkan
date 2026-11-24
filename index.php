<?php

require_once __DIR__ . '/config/app.php';
app_start_session();
require_once __DIR__ . '/includes/auth_check.php';

if (has_role(APP_ROLE_ADMIN)) {
    header('Location: dashboard.php');
    exit;
}

header('Location: auth/login.php');
exit;
