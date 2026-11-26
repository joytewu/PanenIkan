<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/harvest_photos.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

$id = optional_input_integer($_GET['id'] ?? null, 1, 2147483647);

if ($id < 1) {
    http_response_code(404);    Get-Location
    ssh -i "C:\Users\Acer\.ssh\panenikan_admin" -o IdentitiesOnly=yes -p 22 peachdevops@103.176.78.83
    exit;
}

$userId = current_user_id();
$stmt = $conn->prepare(
    'SELECT foto_filename
     FROM panen
     WHERE id = ?
     AND (owner_user_id = ? OR owner_user_id IS NULL)
     LIMIT 1'
);
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || !is_string($row['foto_filename'])) {
    http_response_code(404);
    exit;
}

$filename = $row['foto_filename'];
$path = harvest_photo_path($filename);
$mimeType = harvest_photo_mime($filename);
$fileSize = $path !== null ? filesize($path) : false;

if ($path === null || $mimeType === null || $fileSize === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header(
    'Content-Disposition: inline; filename="panen-' .
    $id . '.' . pathinfo($filename, PATHINFO_EXTENSION) . '"'
);
header('Cache-Control: private, no-store');

if ($method === 'GET') {
    readfile($path);
}

exit;
