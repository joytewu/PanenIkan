<?php

require_once __DIR__ . '/../config/app.php';
app_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/logger.php';

$error = '';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            throw new Exception('Username dan password wajib diisi.');
        }

        $stmt = $conn->prepare(
            'SELECT id, username, password, nama
             FROM users
             WHERE username = ?
             LIMIT 1'
        );

        $stmt->bind_param('s', $username);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {

            app_log('Percobaan login gagal: ' . $username, 'WARNING');

            throw new Exception('Username atau password salah.');

        }

        // Mengamankan session setelah login berhasil
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Session gagal diperbarui setelah login.');
        }

        $_SESSION = [];

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['last_activity'] = time();

        // Membuat token CSRF baru setelah login
        unset($_SESSION['csrf_token']);

        app_log('Pengguna berhasil login.');

        header('Location: ' . APP_URL . '/dashboard.php');
        exit;

    } catch (mysqli_sql_exception $e) {

        app_log($e->getMessage(), 'ERROR');
        $error = 'Terjadi kesalahan database.';

    } catch (Throwable $e) {

        $error = $e->getMessage();

    }

}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | PanenIkan</title>
<link rel="stylesheet" href="../assets/panenikan.css">
</head>

<body>

<div class="login-container">

    <div class="panel">

        <h2>Login PanenIkan</h2>

        <p>Silakan masuk untuk mengelola data panen ikan.</p>

        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="fields">

            <?= csrf_field() ?>

            <label>
                Username
                <input
                    type="text"
                    name="username"
                    required
                    autocomplete="username"
                >
            </label>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >
            </label>

            <button type="submit" class="btn">
                Login
            </button>

        </form>

    </div>

</div>

</body>
</html>
