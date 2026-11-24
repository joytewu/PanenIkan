<?php

require_once __DIR__ . '/../config/app.php';
app_deny_direct_access(__FILE__);

function app_log($message, $level = 'INFO') {

    $logDirectory = __DIR__ . '/../storage/logs/';

    if (!is_dir($logDirectory)) {

        mkdir($logDirectory, 0750, true);

    }

    $logFile = $logDirectory . 'app-' . date('Y-m-d') . '.log';

    $userId = $_SESSION['user_id'] ?? 'guest';
    $userId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$userId);

    $logMessage = sprintf(
        "[%s] [%s] [User: %s] %s%s",
        date('Y-m-d H:i:s'),
        $level,
        $userId,
        $message,
        PHP_EOL
    );

    error_log($logMessage, 3, $logFile);

}
