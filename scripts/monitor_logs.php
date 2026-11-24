<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/logger.php';

function monitor_emit(int $priority, string $message): void {
    if (
        function_exists('openlog') &&
        function_exists('syslog') &&
        function_exists('closelog')
    ) {
        openlog('panenikan-monitor', LOG_PID, LOG_USER);
        syslog($priority, $message);
        closelog();
        return;
    }

    error_log('[PanenIkan monitor] ' . $message);
}

$monitorWarningPriority = defined('LOG_WARNING') ? LOG_WARNING : 4;
$monitorErrorPriority = defined('LOG_ERR') ? LOG_ERR : 3;

$logFile = app_log_directory() .
    DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';

if (!is_file($logFile)) {
    exit(0);
}

$handle = fopen($logFile, 'rb');

if ($handle === false) {
    monitor_emit($monitorErrorPriority, 'Private application log could not be opened.');
    exit(1);
}

$fileSize = filesize($logFile);
$maxReadBytes = 5242880;

if ($fileSize === false) {
    fclose($handle);
    monitor_emit($monitorErrorPriority, 'Private application log size could not be read.');
    exit(1);
}

if ($fileSize > $maxReadBytes) {
    fseek($handle, $fileSize - $maxReadBytes);
    fgets($handle);
}

$now = time();
$windowSeconds = 300;
$failedLoginsByIp = [];
$deniedRequestsByIp = [];
$summary = [
    'failed_login_events' => 0,
    'failed_login_sources_over_threshold' => 0,
    'authorization_denials' => 0,
    'denied_sources_over_threshold' => 0,
    'admin_activity_events' => 0,
];

while (($line = fgets($handle)) !== false) {
    $record = json_decode($line, true);

    if (!is_array($record)) {
        continue;
    }

    $loggedAt = isset($record['timestamp']) && is_string($record['timestamp'])
        ? strtotime($record['timestamp'])
        : false;

    if (
        $loggedAt === false ||
        $loggedAt > $now + 60 ||
        $now - $loggedAt > $windowSeconds
    ) {
        continue;
    }

    $event = $record['event'] ?? '';
    $remoteIp = $record['remote_ip'] ?? '';

    if (!is_string($event)) {
        continue;
    }

    if (str_starts_with($event, 'admin.')) {
        $summary['admin_activity_events']++;
    }

    if ($event === 'auth.login.failed') {
        $summary['failed_login_events']++;

        if (is_string($remoteIp) && filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            $failedLoginsByIp[$remoteIp] =
                ($failedLoginsByIp[$remoteIp] ?? 0) + 1;
        }
    }

    if ($event === 'authz.denied') {
        $summary['authorization_denials']++;

        if (is_string($remoteIp) && filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            $deniedRequestsByIp[$remoteIp] =
                ($deniedRequestsByIp[$remoteIp] ?? 0) + 1;
        }
    }
}

fclose($handle);

$summary['failed_login_sources_over_threshold'] = count(
    array_filter($failedLoginsByIp, static function ($count) {
        return $count >= 5;
    })
);
$summary['denied_sources_over_threshold'] = count(
    array_filter($deniedRequestsByIp, static function ($count) {
        return $count >= 10;
    })
);

$alerts = [];

if ($summary['failed_login_sources_over_threshold'] > 0) {
    $alerts[] = 'Repeated failed login attempts were detected.';
}

if ($summary['denied_sources_over_threshold'] > 0) {
    $alerts[] = 'Repeated authorization denials were detected.';
}

if ($alerts === []) {
    exit(0);
}

foreach ($alerts as $alert) {
    monitor_emit($monitorWarningPriority, $alert);
}

if (app_environment_value('PANENIKAN_AI_ENABLED') !== '1') {
    exit(0);
}

$model = app_environment_value('PANENIKAN_AI_MODEL');

if ($model === false || $model === '') {
    $model = 'qwen2.5:0.5b';
}

if (preg_match('/\A[a-zA-Z0-9._:-]{1,100}\z/', $model) !== 1) {
    monitor_emit($monitorErrorPriority, 'Configured local AI model name is invalid.');
    exit(1);
}

$prompt = "Analyze only these aggregate security-event counts from a five-minute window. " .
    "No usernames, IP addresses, passwords, tokens, or raw logs are included. " .
    "Return JSON with keys suspicious (boolean), risk (low|medium|high), and reason (short string). " .
    "Do not recommend automatic blocking or executing commands. Counts: " .
    json_encode($summary, JSON_UNESCAPED_SLASHES);
$requestBody = json_encode([
    'model' => $model,
    'prompt' => $prompt,
    'stream' => false,
    'format' => 'json',
    'options' => ['num_predict' => 128],
]);

if (!is_string($requestBody)) {
    monitor_emit($monitorErrorPriority, 'Local AI request could not be encoded.');
    exit(1);
}

$requestContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $requestBody,
        'timeout' => 20,
        'ignore_errors' => true,
    ],
]);
$response = @file_get_contents(
    'http://127.0.0.1:11434/api/generate',
    false,
    $requestContext
);

if (!is_string($response)) {
    monitor_emit($monitorErrorPriority, 'Local AI service did not return a response.');
    exit(1);
}

$responseData = json_decode($response, true);
$analysis = is_array($responseData) &&
    is_string($responseData['response'] ?? null)
        ? json_decode($responseData['response'], true)
        : null;

if (
    !is_array($analysis) ||
    !is_bool($analysis['suspicious'] ?? null) ||
    !in_array($analysis['risk'] ?? null, ['low', 'medium', 'high'], true) ||
    !is_string($analysis['reason'] ?? null)
) {
    monitor_emit($monitorErrorPriority, 'Local AI response did not match the expected schema.');
    exit(1);
}

if ($analysis['suspicious'] || $analysis['risk'] === 'high') {
    $reason = preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', $analysis['reason']);
    $reason = is_string($reason) ? substr($reason, 0, 240) : '';
    monitor_emit(
        $monitorWarningPriority,
        'Local AI flagged activity as ' . $analysis['risk'] . ': ' . $reason
    );
}
