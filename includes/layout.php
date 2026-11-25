<?php

require_once __DIR__ . '/../config/app.php';
app_deny_direct_access(__FILE__);

function render_header($title, $active, $success = '', $error = '') {

    $menu = [
        'dashboard' => ['Dashboard', '⌂'],
        'panen'     => ['Data Panen', '◉'],
        'ikan'      => ['Jenis Ikan', '♧'],
        'kolam'     => ['Data Kolam', '▤'],
        'laporan'   => ['Laporan Panen', '▥'],
        'security_audit' => ['Pemeriksaan Keamanan', '◇'],
    ];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | PanenIkan</title>
    <link
        rel="stylesheet"
        href="assets/panenikan.css?v=<?= (int) filemtime(__DIR__ . '/../assets/panenikan.css') ?>"
    >
    <script
        src="assets/panenikan.js?v=<?= (int) filemtime(__DIR__ . '/../assets/panenikan.js') ?>"
        defer
    ></script>
</head>
<body>

<div class="layout">

    <aside class="sidebar">

        <a class="brand" href="dashboard.php">
            <span class="logo">♧</span>
            <span>
                PanenIkan
                <small>Fish Harvest Monitor</small>
            </span>
        </a>

        <div class="nav-label">MENU UTAMA</div>

        <nav>
            <?php foreach ($menu as $key => [$label, $icon]): ?>

                <a
                    class="nav <?= $active === $key ? 'active' : '' ?>"
                    href="<?= e($key) ?>.php"
                >
                    <span class="ico"><?= e($icon) ?></span>
                    <?= e($label) ?>
                </a>

            <?php endforeach; ?>
        </nav>

        <div class="sidebar-bottom">
            <strong>PanenIkan System</strong><br>
            Sistem pemantauan hasil panen ikan berbasis web.
        </div>

    </aside>

    <main class="main">

        <header class="topbar">

            <div>
                <div class="eyebrow">
                    SISTEM INFORMASI / <?= e(strtoupper($title)) ?>
                </div>

                <h1><?= e($title) ?></h1>

                <div class="subtitle">
                    Kelola dan pantau hasil panen ikan dengan mudah.
                </div>
            </div>

            <div class="user">
                ● &nbsp;
                <?= e($_SESSION['nama'] ?? 'Admin PanenIkan') ?>

                <form
                    action="auth/logout.php"
                    method="POST"
                    class="inline-form"
                >
                    <?= csrf_field() ?>
                    <button type="submit" class="btn light small">
                        Logout
                    </button>
                </form>
            </div>

        </header>

        <?php if ($success): ?>
            <div class="notice"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

<?php
}

function render_footer() {
?>

        <div class="footer">
            PanenIkan · Sistem Informasi Pemantauan Hasil Panen Ikan
        </div>

    </main>
</div>

</body>
</html>

<?php
}
