<?php

require_once __DIR__ . '/../config/app.php';
app_deny_direct_access(__FILE__);

function app_log_directory(): string {
    return dirname(__DIR__, 2) .
        DIRECTORY_SEPARATOR . 'panenikan-private' .
        DIRECTORY_SEPARATOR . 'logs';
}

function app_log(
    string $message,
    string $level = 'INFO',
    string $event = 'application.event',
    array $context = []
): bool {
    $level = strtoupper($level);

    if (!in_array($level, ['INFO', 'WARNING', 'ERROR', 'ALERT'], true)) {
        throw new InvalidArgumentException('Level log tidak valid.');
    }

    if (preg_match('/\A[a-z0-9._-]{1,80}\z/', $event) !== 1) {
        throw new InvalidArgumentException('Jenis event log tidak valid.');
    }

    $logDirectory = app_log_directory();

    if (
        !is_dir($logDirectory) &&
        !mkdir($logDirectory, 0750, true) &&
        !is_dir($logDirectory)
    ) {
        error_log('PanenIkan private log directory could not be created.');
        return false;
    }

    if (
        DIRECTORY_SEPARATOR === '/' &&
        !chmod($logDirectory, 0750)
    ) {
        error_log('PanenIkan private log directory permissions could not be set.');
        return false;
    }

    $userId = filter_var(
        $_SESSION['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!is_string($remoteIp) || filter_var($remoteIp, FILTER_VALIDATE_IP) === false) {
        $remoteIp = null;
    }

    $record = [
        'timestamp' => gmdate(DATE_ATOM),
        'level' => $level,
        'event' => $event,
        'user_id' => $userId !== false ? $userId : null,
        'remote_ip' => $remoteIp,
        'message' => $message,
        'context' => $context,
    ];
    $encoded = json_encode(
        $record,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if (!is_string($encoded)) {
        error_log('PanenIkan application log entry could not be encoded.');
        return false;
    }

    $logFile = $logDirectory .
        DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
    $bytesWritten = file_put_contents(
        $logFile,
        $encoded . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    if ($bytesWritten === false) {
        error_log('PanenIkan application log entry could not be written.');
        return false;
    }

    if (
        DIRECTORY_SEPARATOR === '/' &&
        !chmod($logFile, 0640)
    ) {
        error_log('PanenIkan application log permissions could not be set.');
        return false;
    }

    return true;
}
