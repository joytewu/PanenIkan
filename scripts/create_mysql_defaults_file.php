<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/app.php';

$databaseHost = app_environment_value('PANENIKAN_DB_HOST');
$databaseUser = app_environment_value('PANENIKAN_DB_USER');
$databasePassword = app_environment_value('PANENIKAN_DB_PASSWORD');
$databaseName = app_environment_value('PANENIKAN_DB_NAME');

$databaseHost = is_string($databaseHost) && $databaseHost !== ''
    ? $databaseHost
    : 'localhost';
$databaseUser = is_string($databaseUser) && $databaseUser !== ''
    ? $databaseUser
    : 'panenikan_app';
$databaseName = is_string($databaseName) && $databaseName !== ''
    ? $databaseName
    : 'panenikan';

if (
    !is_string($databasePassword) ||
    $databasePassword === '' ||
    preg_match('/\A[a-zA-Z0-9_]+\z/', $databaseName) !== 1
) {
    fwrite(STDERR, "PanenIkan database configuration is incomplete.\n");
    exit(1);
}

$optionFileValue = static function (string $value): string {
    return '"' . str_replace(
        ["\\", "\n", "\r", "\t", '"'],
        ["\\\\", "\\n", "\\r", "\\t", '\\"'],
        $value
    ) . '"';
};

if (($argv[1] ?? '') === '--database-name') {
    fwrite(STDOUT, $databaseName . PHP_EOL);
    exit(0);
}

if (($argv[1] ?? '') === '--backup-remote') {
    $remote = app_environment_value('PANENIKAN_BACKUP_REMOTE');
    fwrite(
        STDOUT,
        (is_string($remote) ? trim($remote) : '') . PHP_EOL
    );
    exit(0);
}

$defaultsFile = tempnam(sys_get_temp_dir(), 'panenikan-mysql-');

if (
    $defaultsFile === false ||
    !chmod($defaultsFile, 0600)
) {
    fwrite(STDERR, "Could not create secure temporary database credentials.\n");
    exit(1);
}

$contents = "[client]\n" .
    'host=' . $optionFileValue($databaseHost) . "\n" .
    'user=' . $optionFileValue($databaseUser) . "\n" .
    'password=' . $optionFileValue($databasePassword) . "\n" .
    'database=' . $optionFileValue($databaseName) . "\n";

if (file_put_contents($defaultsFile, $contents, LOCK_EX) === false) {
    unlink($defaultsFile);
    fwrite(STDERR, "Could not write secure temporary database credentials.\n");
    exit(1);
}

fwrite(STDOUT, $defaultsFile . PHP_EOL);
