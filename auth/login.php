<?php

require_once __DIR__ . '/../config/app.php';
app_start_session();

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/input_validation.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/logger.php';

$error = '';
$loginUsername = null;

if (has_role(APP_ROLE_ADMIN)) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $username = request_text(
            $_POST,
            'username',
            'Username',
            50,
            true
        );
        $loginUsername = $username;
        $password = $_POST['password'] ?? null;

        if (
            !is_string($password) ||
            $password === '' ||
            strlen($password) > 4096
        ) {
            throw new InvalidArgumentException('Password tidak valid.');
        }

        $passwordAlgorithm = defined('PASSWORD_ARGON2ID')
            ? PASSWORD_ARGON2ID
            : PASSWORD_BCRYPT;

        if ($passwordAlgorithm === PASSWORD_BCRYPT && strlen($password) > 72) {
            throw new InvalidArgumentException('Password tidak valid.');
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

        $storedPasswordHash = is_array($user) &&
            is_string($user['password'] ?? null)
                ? $user['password']
                : '';
        $passwordHashInfo = password_get_info($storedPasswordHash);
        $passwordHashAlgorithm = $passwordHashInfo['algoName'] ?? 'unknown';
        $isSupportedPasswordHash = in_array(
            $passwordHashAlgorithm,
            ['bcrypt', 'argon2i', 'argon2id'],
            true
        );

        if (
            !$user ||
            !$isSupportedPasswordHash ||
            !password_verify($password, $storedPasswordHash)
        ) {
            throw new Exception('Username atau password salah.');
        }

        if (password_needs_rehash($storedPasswordHash, $passwordAlgorithm)) {
            $updatedPasswordHash = password_hash(
                $password,
                $passwordAlgorithm
            );

            if (!is_string($updatedPasswordHash)) {
                throw new RuntimeException('Password gagal diperbarui dengan aman.');
            }

            $updatePassword = $conn->prepare(
                'UPDATE users
                 SET password = ?
                 WHERE id = ?'
            );
            $updatePassword->bind_param(
                'si',
                $updatedPasswordHash,
                $user['id']
            );
            $updatePassword->execute();
        }

        // Mengamankan session setelah login berhasil
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Session gagal diperbarui setelah login.');
        }

        $_SESSION = [];

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['role'] = APP_ROLE_ADMIN;
        $_SESSION['last_activity'] = time();

        // Membuat token CSRF baru setelah login
        unset($_SESSION['csrf_token']);

        app_log(
            'Administrator berhasil login.',
            'INFO',
            'auth.login.success'
        );

        header('Location: ' . APP_URL . '/dashboard.php');
        exit;

    } catch (mysqli_sql_exception $e) {

        app_log(
            'Permintaan login gagal karena gangguan database.',
            'ERROR',
            'auth.login.error'
        );
        $error = 'Terjadi kesalahan database.';

    } catch (Exception $e) {

        app_log(
            'Permintaan login ditolak.',
            'WARNING',
            'auth.login.failed',
            ['username' => $loginUsername]
        );
        $error = 'Username atau password salah, atau permintaan tidak valid.';

    } catch (Throwable $e) {

        app_log(
            'Permintaan login gagal karena kesalahan internal.',
            'ERROR',
            'auth.login.error',
            ['username' => $loginUsername]
        );
        $error = 'Login tidak dapat diproses. Silakan coba kembali.';

    }

}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | PanenIkan</title>
<link
    rel="stylesheet"
    href="../assets/panenikan.css?v=<?= (int) filemtime(__DIR__ . '/../assets/panenikan.css') ?>"
>
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
                    maxlength="50"
                    autocomplete="username"
                >
            </label>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    required
                    maxlength="4096"
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
