<?php
session_start();
require __DIR__ . '/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function go($page, $status = '') {
    $url = 'app.php?page=' . urlencode($page);
    if ($status !== '') {
        $url .= '&status=' . urlencode($status);
    }
    header('Location: ' . $url);
    exit;
}

function hasColumn($conn, $table, $column) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'] > 0;
}

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'panen', 'ikan', 'kolam', 'laporan'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$error = '';
$success = '';

$messages = [
    'tambah' => 'Data berhasil ditambahkan.',
    'edit' => 'Data berhasil diperbarui.',
    'hapus' => 'Data berhasil dihapus.'
];
$success = $messages[$_GET['status'] ?? ''] ?? '';

$hasLokasi = hasColumn($conn, 'kolam', 'lokasi');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )) {
            throw new Exception('Token keamanan tidak valid. Muat ulang halaman.');
        }

        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        if ($action === 'hapus_panen') {
            $stmt = $conn->prepare('DELETE FROM panen WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            go('panen', 'hapus');
        }

        if ($action === 'simpan_panen') {
            $tanggal = $_POST['tanggal'] ?? '';
            $jenis = filter_input(INPUT_POST, 'jenis_ikan_id', FILTER_VALIDATE_INT);
            $kolam = filter_input(INPUT_POST, 'kolam_id', FILTER_VALIDATE_INT);
            $jumlah = filter_input(INPUT_POST, 'jumlah_ekor', FILTER_VALIDATE_INT);
            $beratInput = $_POST['berat_kg'] ?? '';

            $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

            if (
                !$date || $date->format('Y-m-d') !== $tanggal ||
                !$jenis || !$kolam || !$jumlah || $jumlah < 1 ||
                !is_numeric($beratInput) || (float)$beratInput <= 0
            ) {
                throw new Exception('Periksa tanggal, jenis ikan, kolam, jumlah, dan berat.');
            }

            $berat = (float)$beratInput;

            if ($id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE panen
                     SET tanggal=?, jenis_ikan_id=?, kolam_id=?,
                         jumlah_ekor=?, berat_kg=?
                     WHERE id=?'
                );
                $stmt->bind_param(
                    'siiidi', $tanggal, $jenis, $kolam, $jumlah, $berat, $id
                );
                $stmt->execute();
                go('panen', 'edit');
            }

            $stmt = $conn->prepare(
                'INSERT INTO panen
                 (tanggal, jenis_ikan_id, kolam_id, jumlah_ekor, berat_kg)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'siiid', $tanggal, $jenis, $kolam, $jumlah, $berat
            );
            $stmt->execute();
            go('panen', 'tambah');
        }

        if ($action === 'simpan_ikan') {
            $nama = trim($_POST['nama_ikan'] ?? '');
            if ($nama === '') {
                throw new Exception('Nama ikan wajib diisi.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE jenis_ikan SET nama_ikan=? WHERE id=?'
                );
                $stmt->bind_param('si', $nama, $id);
                $stmt->execute();
                go('ikan', 'edit');
            }

            $stmt = $conn->prepare(
                'INSERT INTO jenis_ikan (nama_ikan) VALUES (?)'
            );
            $stmt->bind_param('s', $nama);
            $stmt->execute();
            go('ikan', 'tambah');
        }

        if ($action === 'hapus_ikan') {
            $stmt = $conn->prepare('DELETE FROM jenis_ikan WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            go('ikan', 'hapus');
        }

        if ($action === 'simpan_kolam') {
            $nama = trim($_POST['nama_kolam'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');

            if ($nama === '') {
                throw new Exception('Nama kolam wajib diisi.');
            }

            if ($hasLokasi) {
                if ($id > 0) {
                    $stmt = $conn->prepare(
                        'UPDATE kolam SET nama_kolam=?, lokasi=? WHERE id=?'
                    );
                    $stmt->bind_param('ssi', $nama, $lokasi, $id);
                    $stmt->execute();
                    go('kolam', 'edit');
                }

                $stmt = $conn->prepare(
                    'INSERT INTO kolam (nama_kolam, lokasi) VALUES (?, ?)'
                );
                $stmt->bind_param('ss', $nama, $lokasi);
            } else {
                if ($id > 0) {
                    $stmt = $conn->prepare(
                        'UPDATE kolam SET nama_kolam=? WHERE id=?'
                    );
                    $stmt->bind_param('si', $nama, $id);
                    $stmt->execute();
                    go('kolam', 'edit');
                }

                $stmt = $conn->prepare(
                    'INSERT INTO kolam (nama_kolam) VALUES (?)'
                );
                $stmt->bind_param('s', $nama);
            }

            $stmt->execute();
            go('kolam', 'tambah');
        }

        if ($action === 'hapus_kolam') {
            $stmt = $conn->prepare('DELETE FROM kolam WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            go('kolam', 'hapus');
        }

        throw new Exception('Aksi tidak dikenal.');
    } catch (mysqli_sql_exception $ex) {
        error_log('PanenIkan database error: ' . $ex->getMessage());
        if ((int)$ex->getCode() === 1451) {
            $error = 'Data tidak bisa dihapus karena masih digunakan oleh catatan panen.';
        } elseif ((int)$ex->getCode() === 1062) {
            $error = 'Data tersebut sudah ada.';
        } else {
            $error = 'Operasi database gagal. Periksa data dan coba kembali.';
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$edit = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    if ($page === 'panen') {
        $stmt = $conn->prepare('SELECT * FROM panen WHERE id=?');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
    } elseif ($page === 'ikan') {
        $stmt = $conn->prepare('SELECT * FROM jenis_ikan WHERE id=?');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
    } elseif ($page === 'kolam') {
        $stmt = $conn->prepare('SELECT * FROM kolam WHERE id=?');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
    }
}

$jenisOptions = $conn->query(
    'SELECT id, nama_ikan FROM jenis_ikan ORDER BY nama_ikan'
)->fetch_all(MYSQLI_ASSOC);

$kolamOptions = $conn->query(
    'SELECT id, nama_kolam FROM kolam ORDER BY nama_kolam'
)->fetch_all(MYSQLI_ASSOC);

$totalPanen = $conn->query(
    'SELECT COUNT(*) total FROM panen'
)->fetch_assoc()['total'];

$totalEkor = $conn->query(
    'SELECT COALESCE(SUM(jumlah_ekor),0) total FROM panen'
)->fetch_assoc()['total'];

$totalBerat = $conn->query(
    'SELECT COALESCE(SUM(berat_kg),0) total FROM panen'
)->fetch_assoc()['total'];

$totalKolam = $conn->query(
    'SELECT COUNT(*) total FROM kolam'
)->fetch_assoc()['total'];

$filterTanggal = $_GET['tanggal'] ?? '';
$filterJenis = filter_input(INPUT_GET, 'jenis', FILTER_VALIDATE_INT) ?: 0;
$filterKolam = filter_input(INPUT_GET, 'kolam', FILTER_VALIDATE_INT) ?: 0;

$sql = 'SELECT p.id, p.tanggal, j.nama_ikan, k.nama_kolam,
               p.jumlah_ekor, p.berat_kg
        FROM panen p
        JOIN jenis_ikan j ON p.jenis_ikan_id=j.id
        JOIN kolam k ON p.kolam_id=k.id
        WHERE 1=1';
$types = '';
$params = [];

if ($page === 'laporan' && $filterTanggal !== '') {
    $sql .= ' AND p.tanggal=?';
    $types .= 's';
    $params[] = $filterTanggal;
}
if ($page === 'laporan' && $filterJenis > 0) {
    $sql .= ' AND p.jenis_ikan_id=?';
    $types .= 'i';
    $params[] = $filterJenis;
}
if ($page === 'laporan' && $filterKolam > 0) {
    $sql .= ' AND p.kolam_id=?';
    $types .= 'i';
    $params[] = $filterKolam;
}
$sql .= ' ORDER BY p.tanggal DESC, p.id DESC';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$panenRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$jenisRows = $conn->query(
    'SELECT id, nama_ikan FROM jenis_ikan ORDER BY nama_ikan'
)->fetch_all(MYSQLI_ASSOC);

$kolamRows = $conn->query(
    'SELECT * FROM kolam ORDER BY nama_kolam'
)->fetch_all(MYSQLI_ASSOC);

$recentRows = array_slice($panenRows, 0, 6);

$jumlahLaporan = array_sum(array_map(
    fn($row) => (int)$row['jumlah_ekor'], $panenRows
));
$beratLaporan = array_sum(array_map(
    fn($row) => (float)$row['berat_kg'], $panenRows
));

$titles = [
    'dashboard' => 'Dashboard',
    'panen' => 'Data Panen',
    'ikan' => 'Jenis Ikan',
    'kolam' => 'Data Kolam',
    'laporan' => 'Laporan Panen'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titles[$page]) ?> | PanenIkan</title>
<style>
:root {
 --green:#087f70; --green-dark:#075e54; --mint:#e7f7f2;
 --ink:#172d2b; --muted:#71817e; --line:#e5eeeb;
 --bg:#f4f8f7; --white:#fff; --red:#d94444;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Inter,"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{text-decoration:none;color:inherit}
.layout{display:flex;min-height:100vh}
.sidebar{width:245px;background:#fff;border-right:1px solid var(--line);padding:27px 18px;flex-shrink:0}
.brand{display:flex;align-items:center;gap:12px;font-size:20px;font-weight:800;padding:0 10px 32px}
.logo{width:42px;height:42px;border-radius:14px;background:var(--green);color:white;display:grid;place-items:center;font-size:23px}
.brand small{display:block;font-size:11px;color:var(--muted);font-weight:500;margin-top:3px}
.nav-label{font-size:10px;color:#9aa8a5;font-weight:800;letter-spacing:1.5px;padding:10px 12px}
.nav{display:flex;gap:12px;align-items:center;padding:13px 14px;border-radius:11px;color:#61716e;font-size:14px;margin:4px 0}
.nav:hover,.nav.active{background:var(--mint);color:var(--green-dark);font-weight:700}
.nav .ico{width:20px;text-align:center;font-size:17px}
.sidebar-bottom{margin:38px 8px 0;padding:17px;background:#f2faf7;border-radius:14px;font-size:12px;color:var(--muted);line-height:1.7}
.sidebar-bottom strong{color:var(--green-dark)}
.main{min-width:0;flex:1;padding:28px 34px 40px}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:29px}
.eyebrow{font-size:12px;color:var(--muted);margin-bottom:7px}
h1{font-size:27px;margin:0;letter-spacing:-.6px}
.subtitle{color:var(--muted);font-size:13px;margin-top:8px}
.user{background:white;border:1px solid var(--line);padding:10px 14px;border-radius:13px;font-size:13px;white-space:nowrap}
.hero{background:linear-gradient(115deg,#075e54,#0c9882);color:white;padding:28px 30px;border-radius:20px;margin-bottom:23px;position:relative;overflow:hidden}
.hero:after{content:"";position:absolute;width:200px;height:200px;border-radius:50%;background:#ffffff12;right:-45px;top:-85px}
.hero h2{font-size:24px;margin:0 0 10px}
.hero p{font-size:14px;opacity:.85;max-width:550px;line-height:1.7;margin:0}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:20px 0 24px}
.stat,.panel{background:#fff;border:1px solid var(--line);border-radius:17px;padding:21px}
.stat-top{display:flex;justify-content:space-between;align-items:center;color:var(--muted);font-size:12px;gap:8px}
.stat-icon{background:var(--mint);color:var(--green);padding:9px;border-radius:11px;font-size:18px}
.stat-number{font-size:28px;font-weight:800;margin-top:16px;letter-spacing:-.7px}
.stat-note{font-size:12px;color:var(--muted);margin-top:6px}
.panel{margin-bottom:22px;min-width:0}
.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}
h2{font-size:17px;margin:0}
.muted{font-size:12px;color:var(--muted);margin-top:6px}
.btn{display:inline-flex;justify-content:center;align-items:center;gap:7px;background:var(--green);color:white;border:0;border-radius:9px;padding:11px 15px;font-size:13px;font-weight:700;cursor:pointer}
.btn:hover{background:var(--green-dark)}
.btn.light{background:var(--mint);color:var(--green-dark)}
.btn.red{background:#fff0f0;color:var(--red)}
.btn.small{padding:7px 10px;font-size:12px}
.grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px}
.shortcuts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.shortcut{padding:17px;border:1px solid var(--line);border-radius:13px;display:flex;align-items:center;gap:12px}
.shortcut:hover{border-color:#9ed9cc;background:#f9fdfb}
.shortcut .symbol{font-size:21px;background:var(--mint);border-radius:10px;padding:10px}
.shortcut strong{display:block;font-size:13px}
.shortcut small{display:block;color:var(--muted);font-size:11px;margin-top:5px}
form.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
label{display:block;font-size:12px;font-weight:700;color:#52635f}
input,select{width:100%;padding:12px 13px;border:1px solid #dce7e3;border-radius:9px;background:white;font-size:13px;margin-top:8px;color:var(--ink)}
input:focus,select:focus{outline:2px solid #b4e5da;border-color:var(--green)}
.full{grid-column:1/-1}
.actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.notice,.error{padding:13px 16px;border-radius:11px;font-size:13px;margin-bottom:18px}
.notice{background:#e4f8ec;color:#176b41}
.error{background:#fff0f0;color:#a12626}
.table-wrap{overflow-x:auto}
table{border-collapse:collapse;width:100%;min-width:650px}
th,td{text-align:left;padding:14px 12px;border-bottom:1px solid #edf2f0;font-size:12px}
th{background:#f7faf9;color:#71817e;font-size:10px;text-transform:uppercase;letter-spacing:.6px}
tbody tr:hover{background:#fbfdfc}
.pill{display:inline-block;background:var(--mint);color:var(--green-dark);padding:5px 9px;border-radius:20px;font-size:11px;font-weight:700}
.inline-form{display:inline}
.empty{text-align:center;color:var(--muted);padding:28px}
.filter{display:flex;align-items:end;gap:12px;flex-wrap:wrap}
.filter label{min-width:140px;flex:1}
.footer{text-align:center;color:#9aa8a5;font-size:11px;padding:20px}
@media(max-width:1050px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.main{padding:24px}}
@media(max-width:700px){.layout{display:block}.sidebar{width:100%;padding:15px}.brand{padding-bottom:15px}.nav-label,.sidebar-bottom{display:none}.sidebar nav{display:flex;flex-wrap:wrap;gap:4px}.nav{padding:9px 10px;margin:0;font-size:12px}.main{padding:18px 13px}.topbar{align-items:flex-start}.user{font-size:11px}h1{font-size:23px}.hero{padding:23px}.grid2{grid-template-columns:1fr}.stats{gap:10px}.stat{padding:15px}.stat-number{font-size:24px}.shortcuts{grid-template-columns:1fr}form.fields{grid-template-columns:1fr}.full{grid-column:auto}.panel{padding:15px}}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar">
    <a class="brand" href="app.php">
        <span class="logo">♧</span>
        <span>PanenIkan<small>Fish Harvest Monitor</small></span>
    </a>
    <div class="nav-label">MENU UTAMA</div>
    <nav>
        <a class="nav <?= $page==='dashboard'?'active':'' ?>" href="app.php"><span class="ico">⌂</span>Dashboard</a>
        <a class="nav <?= $page==='panen'?'active':'' ?>" href="app.php?page=panen"><span class="ico">◉</span>Data Panen</a>
        <a class="nav <?= $page==='ikan'?'active':'' ?>" href="app.php?page=ikan"><span class="ico">♧</span>Jenis Ikan</a>
        <a class="nav <?= $page==='kolam'?'active':'' ?>" href="app.php?page=kolam"><span class="ico">▤</span>Data Kolam</a>
        <a class="nav <?= $page==='laporan'?'active':'' ?>" href="app.php?page=laporan"><span class="ico">▥</span>Laporan Panen</a>
    </nav>
    <div class="sidebar-bottom">
        <strong>PanenIkan System</strong><br>
        Sistem pemantauan hasil panen ikan berbasis web.
    </div>
</aside>

<main class="main">
<header class="topbar">
    <div>
        <div class="eyebrow">SISTEM INFORMASI / <?= e(strtoupper($titles[$page])) ?></div>
        <h1><?= e($titles[$page]) ?></h1>
        <div class="subtitle">Kelola dan pantau hasil panen ikan dengan mudah.</div>
    </div>
    <div class="user">● &nbsp; Admin PanenIkan</div>
</header>

<?php if ($success): ?><div class="notice"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<?php if ($page === 'dashboard'): ?>
<section class="hero">
    <h2>Selamat datang di PanenIkan 👋</h2>
    <p>Pantau hasil panen, kelola jenis ikan dan kolam, serta lihat ringkasan produksi dari satu dashboard.</p>
</section>

<section class="stats">
    <div class="stat"><div class="stat-top">Total Ikan Dipanen <span class="stat-icon">🐟</span></div><div class="stat-number"><?= number_format((int)$totalEkor) ?></div><div class="stat-note">ekor tercatat di database</div></div>
    <div class="stat"><div class="stat-top">Total Berat Panen <span class="stat-icon">⚖</span></div><div class="stat-number"><?= number_format((float)$totalBerat,2) ?> <small style="font-size:13px">kg</small></div><div class="stat-note">berat keseluruhan</div></div>
    <div class="stat"><div class="stat-top">Jumlah Kolam <span class="stat-icon">▤</span></div><div class="stat-number"><?= number_format((int)$totalKolam) ?></div><div class="stat-note">kolam terdaftar</div></div>
    <div class="stat"><div class="stat-top">Catatan Panen <span class="stat-icon">▥</span></div><div class="stat-number"><?= number_format((int)$totalPanen) ?></div><div class="stat-note">transaksi panen tersimpan</div></div>
</section>

<div class="grid2">
<section class="panel">
    <div class="panel-head"><div><h2>Akses Cepat</h2><div class="muted">Pilih menu yang ingin dikelola.</div></div></div>
    <div class="shortcuts">
        <a class="shortcut" href="app.php?page=panen"><span class="symbol">🐟</span><span><strong>Data Panen</strong><small>Tambah dan kelola panen</small></span></a>
        <a class="shortcut" href="app.php?page=ikan"><span class="symbol">♧</span><span><strong>Jenis Ikan</strong><small>Kelola jenis ikan</small></span></a>
        <a class="shortcut" href="app.php?page=kolam"><span class="symbol">▤</span><span><strong>Data Kolam</strong><small>Kelola daftar kolam</small></span></a>
        <a class="shortcut" href="app.php?page=laporan"><span class="symbol">▥</span><span><strong>Laporan</strong><small>Filter data panen</small></span></a>
    </div>
</section>
<section class="panel">
    <div class="panel-head"><div><h2>Ringkasan Produksi</h2><div class="muted">Jumlah ikan menurut jenis.</div></div></div>
    <?php
    $summary = $conn->query(
        'SELECT j.nama_ikan, COALESCE(SUM(p.jumlah_ekor),0) ekor,
                COALESCE(SUM(p.berat_kg),0) berat
         FROM jenis_ikan j LEFT JOIN panen p ON p.jenis_ikan_id=j.id
         GROUP BY j.id,j.nama_ikan ORDER BY j.nama_ikan'
    )->fetch_all(MYSQLI_ASSOC);
    ?>
    <?php if (!$summary): ?><div class="empty">Belum ada jenis ikan.</div>
    <?php else: foreach ($summary as $s): ?>
    <div style="padding:12px 0;border-bottom:1px solid #edf2f0">
        <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px"><strong><?= e($s['nama_ikan']) ?></strong><span><?= number_format((int)$s['ekor']) ?> ekor</span></div>
        <div style="height:7px;background:#edf4f1;border-radius:8px;margin-top:10px;overflow:hidden"><div style="height:100%;background:#0c9882;border-radius:8px;width:<?= $totalEkor > 0 ? min(100,((int)$s['ekor']/(int)$totalEkor)*100) : 0 ?>%"></div></div>
        <div class="muted"><?= number_format((float)$s['berat'],2) ?> kg</div>
    </div>
    <?php endforeach; endif; ?>
</section>
</div>

<section class="panel">
    <div class="panel-head"><div><h2>Panen Terbaru</h2><div class="muted">Catatan panen terbaru dari database.</div></div><a class="btn light" href="app.php?page=panen">Lihat Semua →</a></div>
    <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Jenis Ikan</th><th>Kolam</th><th>Jumlah</th><th>Berat</th></tr></thead><tbody>
    <?php if (!$recentRows): ?><tr><td colspan="5" class="empty">Belum ada catatan panen.</td></tr>
    <?php else: foreach ($recentRows as $r): ?><tr><td><?= e($r['tanggal']) ?></td><td><span class="pill"><?= e($r['nama_ikan']) ?></span></td><td><?= e($r['nama_kolam']) ?></td><td><?= number_format((int)$r['jumlah_ekor']) ?> ekor</td><td><?= number_format((float)$r['berat_kg'],2) ?> kg</td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>

<?php elseif ($page === 'panen'): ?>
<section class="panel">
    <div class="panel-head"><div><h2><?= $edit ? 'Edit Data Panen' : 'Tambah Data Panen' ?></h2><div class="muted">Isi rincian hasil panen di bawah ini.</div></div></div>
    <?php if (!$jenisOptions || !$kolamOptions): ?>
        <div class="error">Tambahkan setidaknya satu jenis ikan dan satu kolam terlebih dahulu sebelum mencatat panen.</div>
    <?php endif; ?>
    <form class="fields" method="post">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="simpan_panen">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <label>Tanggal Panen<input type="date" name="tanggal" required value="<?= e($edit['tanggal'] ?? date('Y-m-d')) ?>"></label>
        <label>Jenis Ikan<select name="jenis_ikan_id" required><option value="">Pilih jenis ikan</option><?php foreach ($jenisOptions as $j): ?><option value="<?= e($j['id']) ?>" <?= (string)($edit['jenis_ikan_id'] ?? '')===(string)$j['id']?'selected':'' ?>><?= e($j['nama_ikan']) ?></option><?php endforeach; ?></select></label>
        <label>Kolam<select name="kolam_id" required><option value="">Pilih kolam</option><?php foreach ($kolamOptions as $k): ?><option value="<?= e($k['id']) ?>" <?= (string)($edit['kolam_id'] ?? '')===(string)$k['id']?'selected':'' ?>><?= e($k['nama_kolam']) ?></option><?php endforeach; ?></select></label>
        <label>Jumlah Ikan (ekor)<input type="number" name="jumlah_ekor" min="1" required value="<?= e($edit['jumlah_ekor'] ?? '') ?>" placeholder="Contoh: 200"></label>
        <label>Berat Total (kg)<input type="number" name="berat_kg" min="0.01" step="0.01" required value="<?= e($edit['berat_kg'] ?? '') ?>" placeholder="Contoh: 50"></label>
        <div class="full actions"><button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : '+ Simpan Data Panen' ?></button><?php if ($edit): ?><a class="btn light" href="app.php?page=panen">Batal Edit</a><?php endif; ?></div>
    </form>
</section>
<section class="panel">
    <div class="panel-head"><div><h2>Daftar Hasil Panen</h2><div class="muted"><?= count($panenRows) ?> catatan ditampilkan.</div></div></div>
    <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Jenis Ikan</th><th>Kolam</th><th>Jumlah</th><th>Berat</th><th>Aksi</th></tr></thead><tbody>
    <?php if (!$panenRows): ?><tr><td colspan="6" class="empty">Belum ada data panen.</td></tr>
    <?php else: foreach ($panenRows as $r): ?><tr><td><?= e($r['tanggal']) ?></td><td><?= e($r['nama_ikan']) ?></td><td><?= e($r['nama_kolam']) ?></td><td><?= number_format((int)$r['jumlah_ekor']) ?></td><td><?= number_format((float)$r['berat_kg'],2) ?> kg</td><td><div class="actions"><a class="btn light small" href="app.php?page=panen&edit=<?= e($r['id']) ?>">Edit</a><form class="inline-form" method="post" onsubmit="return confirm('Yakin ingin menghapus data panen ini?')"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="hapus_panen"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn red small" type="submit">Hapus</button></form></div></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>

<?php elseif ($page === 'ikan'): ?>
<section class="panel">
    <div class="panel-head"><div><h2><?= $edit ? 'Edit Jenis Ikan' : 'Tambah Jenis Ikan' ?></h2><div class="muted">Contoh: Nila, Lele, Gurami.</div></div></div>
    <form class="fields" method="post">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="simpan_ikan"><input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <label class="full">Nama Jenis Ikan<input name="nama_ikan" required maxlength="100" value="<?= e($edit['nama_ikan'] ?? '') ?>" placeholder="Masukkan nama ikan"></label>
        <div class="full actions"><button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : '+ Tambah Jenis Ikan' ?></button><?php if ($edit): ?><a class="btn light" href="app.php?page=ikan">Batal</a><?php endif; ?></div>
    </form>
</section>
<section class="panel">
    <div class="panel-head"><div><h2>Daftar Jenis Ikan</h2><div class="muted">Jenis ikan yang tersedia untuk pencatatan panen.</div></div></div>
    <div class="table-wrap"><table><thead><tr><th>No.</th><th>Nama Ikan</th><th>Aksi</th></tr></thead><tbody>
    <?php if (!$jenisRows): ?><tr><td colspan="3" class="empty">Belum ada jenis ikan.</td></tr>
    <?php else: foreach ($jenisRows as $i=>$r): ?><tr><td><?= $i+1 ?></td><td><span class="pill"><?= e($r['nama_ikan']) ?></span></td><td><div class="actions"><a class="btn light small" href="app.php?page=ikan&edit=<?= e($r['id']) ?>">Edit</a><form class="inline-form" method="post" onsubmit="return confirm('Hapus jenis ikan ini? Jenis yang sudah digunakan dalam panen tidak dapat dihapus.')"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="hapus_ikan"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn red small" type="submit">Hapus</button></form></div></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>

<?php elseif ($page === 'kolam'): ?>
<section class="panel">
    <div class="panel-head"><div><h2><?= $edit ? 'Edit Data Kolam' : 'Tambah Data Kolam' ?></h2><div class="muted">Kelola nama dan lokasi kolam.</div></div></div>
    <form class="fields" method="post">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="simpan_kolam"><input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <label>Nama Kolam<input name="nama_kolam" required maxlength="100" value="<?= e($edit['nama_kolam'] ?? '') ?>" placeholder="Contoh: Kolam A"></label>
        <?php if ($hasLokasi): ?><label>Lokasi Kolam<input name="lokasi" maxlength="255" value="<?= e($edit['lokasi'] ?? '') ?>" placeholder="Contoh: Area belakang"></label><?php endif; ?>
        <div class="full actions"><button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : '+ Tambah Kolam' ?></button><?php if ($edit): ?><a class="btn light" href="app.php?page=kolam">Batal</a><?php endif; ?></div>
    </form>
</section>
<section class="panel">
    <div class="panel-head"><div><h2>Daftar Kolam</h2><div class="muted">Kolam yang terdaftar di sistem.</div></div></div>
    <div class="table-wrap"><table><thead><tr><th>No.</th><th>Nama Kolam</th><?php if ($hasLokasi): ?><th>Lokasi</th><?php endif; ?><th>Aksi</th></tr></thead><tbody>
    <?php if (!$kolamRows): ?><tr><td colspan="<?= $hasLokasi?4:3 ?>" class="empty">Belum ada data kolam.</td></tr>
    <?php else: foreach ($kolamRows as $i=>$r): ?><tr><td><?= $i+1 ?></td><td><strong><?= e($r['nama_kolam']) ?></strong></td><?php if ($hasLokasi): ?><td><?= e($r['lokasi'] ?? '-') ?></td><?php endif; ?><td><div class="actions"><a class="btn light small" href="app.php?page=kolam&edit=<?= e($r['id']) ?>">Edit</a><form class="inline-form" method="post" onsubmit="return confirm('Hapus kolam ini? Kolam yang digunakan dalam data panen tidak dapat dihapus.')"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="hapus_kolam"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn red small" type="submit">Hapus</button></form></div></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>

<?php elseif ($page === 'laporan'): ?>
<section class="panel">
    <div class="panel-head"><div><h2>Filter Laporan</h2><div class="muted">Pilih kriteria untuk melihat data panen tertentu.</div></div></div>
    <form class="filter" method="get">
        <input type="hidden" name="page" value="laporan">
        <label>Tanggal Panen<input type="date" name="tanggal" value="<?= e($filterTanggal) ?>"></label>
        <label>Jenis Ikan<select name="jenis"><option value="0">Semua jenis</option><?php foreach ($jenisOptions as $j): ?><option value="<?= e($j['id']) ?>" <?= $filterJenis===(int)$j['id']?'selected':'' ?>><?= e($j['nama_ikan']) ?></option><?php endforeach; ?></select></label>
        <label>Kolam<select name="kolam"><option value="0">Semua kolam</option><?php foreach ($kolamOptions as $k): ?><option value="<?= e($k['id']) ?>" <?= $filterKolam===(int)$k['id']?'selected':'' ?>><?= e($k['nama_kolam']) ?></option><?php endforeach; ?></select></label>
        <button class="btn" type="submit">Tampilkan</button><a class="btn light" href="app.php?page=laporan">Reset</a>
    </form>
</section>
<section class="stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="stat"><div class="stat-top">Catatan Ditemukan <span class="stat-icon">▥</span></div><div class="stat-number"><?= count($panenRows) ?></div><div class="stat-note">baris laporan</div></div>
    <div class="stat"><div class="stat-top">Jumlah Ikan <span class="stat-icon">🐟</span></div><div class="stat-number"><?= number_format($jumlahLaporan) ?></div><div class="stat-note">ekor</div></div>
    <div class="stat"><div class="stat-top">Berat Total <span class="stat-icon">⚖</span></div><div class="stat-number"><?= number_format($beratLaporan,2) ?> <small style="font-size:13px">kg</small></div><div class="stat-note">berat panen terfilter</div></div>
</section>
<section class="panel">
    <div class="panel-head"><div><h2>Hasil Laporan Panen</h2><div class="muted">Gunakan tombol cetak untuk menyimpan laporan melalui browser.</div></div><button class="btn light" type="button" onclick="window.print()">Cetak Laporan</button></div>
    <div class="table-wrap"><table><thead><tr><th>No.</th><th>Tanggal</th><th>Jenis Ikan</th><th>Kolam</th><th>Jumlah (ekor)</th><th>Berat (kg)</th></tr></thead><tbody>
    <?php if (!$panenRows): ?><tr><td colspan="6" class="empty">Tidak ada data yang cocok dengan filter.</td></tr>
    <?php else: foreach ($panenRows as $i=>$r): ?><tr><td><?= $i+1 ?></td><td><?= e($r['tanggal']) ?></td><td><?= e($r['nama_ikan']) ?></td><td><?= e($r['nama_kolam']) ?></td><td><?= number_format((int)$r['jumlah_ekor']) ?></td><td><?= number_format((float)$r['berat_kg'],2) ?></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>
<?php endif; ?>

<div class="footer">PanenIkan · Sistem Informasi Pemantauan Hasil Panen Ikan</div>
</main>
</div>
</body>
</html>
