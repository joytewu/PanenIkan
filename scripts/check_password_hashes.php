<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$stmt = $conn->prepare('SELECT password FROM users');
$stmt->execute();
$result = $stmt->get_result();
$counts = [
    'bcrypt' => 0,
    'argon2i' => 0,
    'argon2id' => 0,
    'other' => 0,
];

while ($row = $result->fetch_assoc()) {
    $passwordHash = is_string($row['password'] ?? null)
        ? $row['password']
        : '';
    $hashInfo = password_get_info($passwordHash);
    $algorithm = $hashInfo['algoName'] ?? 'unknown';

    if (isset($counts[$algorithm])) {
        $counts[$algorithm]++;
    } else {
        $counts['other']++;
    }
}

printf(
    "bcrypt=%d argon2i=%d argon2id=%d other_or_plaintext=%d\n",
    $counts['bcrypt'],
    $counts['argon2i'],
    $counts['argon2id'],
    $counts['other']
);

exit($counts['other'] === 0 ? 0 : 2);
