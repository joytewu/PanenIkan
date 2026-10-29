<?php
$map = ['dashboard', 'panen', 'ikan', 'kolam', 'laporan'];
$page = $_GET['page'] ?? 'dashboard';
if (!in_array($page, $map, true)) {
    $page = 'dashboard';
}
header('Location: ' . $page . '.php');
exit;
