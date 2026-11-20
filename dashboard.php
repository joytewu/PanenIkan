
<?php
<<<<<<< HEAD

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

// Mengambil statistik dashboard
$totalPanen = $conn->query(
    'SELECT COUNT(*) AS total FROM panen'
)->fetch_assoc()['total'];

$totalEkor = $conn->query(
    'SELECT COALESCE(SUM(jumlah_ekor), 0) AS total FROM panen'
)->fetch_assoc()['total'];

$totalBerat = $conn->query(
    'SELECT COALESCE(SUM(berat_kg), 0) AS total FROM panen'
)->fetch_assoc()['total'];

$totalKolam = $conn->query(
    'SELECT COUNT(*) AS total FROM kolam'
)->fetch_assoc()['total'];

// Mengambil ringkasan produksi berdasarkan jenis ikan
$summary = $conn->query(
=======
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

/*
|--------------------------------------------------------------------------
| Memeriksa koneksi database
|--------------------------------------------------------------------------
*/
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Error: Koneksi database tidak tersedia. Periksa includes/bootstrap.php.');
}

/*
|--------------------------------------------------------------------------
| Menjalankan query dengan pemeriksaan error
|--------------------------------------------------------------------------
*/
function dashboardQuery($conn, $sql)
{
    $result = $conn->query($sql);

    if ($result === false) {
        error_log('Dashboard query error: ' . $conn->error);
        http_response_code(500);
        die('Terjadi kesalahan saat mengambil data dashboard. Periksa log error PHP.');
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Statistik dashboard
|--------------------------------------------------------------------------
*/
$result = dashboardQuery(
    $conn,
    'SELECT COUNT(*) AS total FROM panen'
);
$row = $result->fetch_assoc();
$totalPanen = $row['total'] ?? 0;

$result = dashboardQuery(
    $conn,
    'SELECT COALESCE(SUM(jumlah_ekor), 0) AS total FROM panen'
);
$row = $result->fetch_assoc();
$totalEkor = $row['total'] ?? 0;

$result = dashboardQuery(
    $conn,
    'SELECT COALESCE(SUM(berat_kg), 0) AS total FROM panen'
);
$row = $result->fetch_assoc();
$totalBerat = $row['total'] ?? 0;

$result = dashboardQuery(
    $conn,
    'SELECT COUNT(*) AS total FROM kolam'
);
$row = $result->fetch_assoc();
$totalKolam = $row['total'] ?? 0;

/*
|--------------------------------------------------------------------------
| Ringkasan produksi berdasarkan jenis ikan
|--------------------------------------------------------------------------
*/
$summary = dashboardQuery(
    $conn,
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
    'SELECT
        j.nama_ikan,
        COALESCE(SUM(p.jumlah_ekor), 0) AS ekor,
        COALESCE(SUM(p.berat_kg), 0) AS berat
     FROM jenis_ikan j
     LEFT JOIN panen p ON p.jenis_ikan_id = j.id
     GROUP BY j.id, j.nama_ikan
     ORDER BY j.nama_ikan'
)->fetch_all(MYSQLI_ASSOC);

<<<<<<< HEAD
// Mengambil enam catatan panen terbaru
$recentRows = $conn->query(
=======
/*
|--------------------------------------------------------------------------
| Data enam panen terbaru
|--------------------------------------------------------------------------
*/
$recentRows = dashboardQuery(
    $conn,
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
    'SELECT
        p.tanggal,
        j.nama_ikan,
        k.nama_kolam,
        p.jumlah_ekor,
        p.berat_kg
     FROM panen p
<<<<<<< HEAD
     JOIN jenis_ikan j ON p.jenis_ikan_id = j.id
     JOIN kolam k ON p.kolam_id = k.id
=======
     INNER JOIN jenis_ikan j ON p.jenis_ikan_id = j.id
     INNER JOIN kolam k ON p.kolam_id = k.id
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
     ORDER BY p.tanggal DESC, p.id DESC
     LIMIT 6'
)->fetch_all(MYSQLI_ASSOC);

<<<<<<< HEAD
// Menampilkan header
render_header('Dashboard', 'dashboard', flash_message());

?>

<section class="hero">
    <h2>Selamat datang di PanenIkan 👋</h2>
=======
/*
|--------------------------------------------------------------------------
| Header halaman
|--------------------------------------------------------------------------
*/
render_header('Dashboard', 'dashboard', flash_message());
?>

<section class="hero">
    <h2>halooo</h2>
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
    <p>
        Pantau hasil panen, kelola jenis ikan dan kolam,
        serta lihat ringkasan produksi dari satu dashboard.
    </p>
</section>

<section class="stats">

    <div class="stat">
        <div class="stat-top">
            Total Ikan Dipanen
            <span class="stat-icon">🐟</span>
        </div>
        <div class="stat-number">
            <?= number_format((int) $totalEkor) ?>
        </div>
        <div class="stat-note">ekor tercatat di database</div>
    </div>

    <div class="stat">
        <div class="stat-top">
            Total Berat Panen
            <span class="stat-icon">⚖</span>
        </div>
        <div class="stat-number">
            <?= number_format((float) $totalBerat, 2) ?>
            <small>kg</small>
        </div>
        <div class="stat-note">berat keseluruhan</div>
    </div>

    <div class="stat">
        <div class="stat-top">
            Jumlah Kolam
            <span class="stat-icon">▤</span>
        </div>
        <div class="stat-number">
            <?= number_format((int) $totalKolam) ?>
        </div>
        <div class="stat-note">kolam terdaftar</div>
    </div>

    <div class="stat">
        <div class="stat-top">
            Catatan Panen
            <span class="stat-icon">▥</span>
        </div>
        <div class="stat-number">
            <?= number_format((int) $totalPanen) ?>
        </div>
        <div class="stat-note">transaksi panen tersimpan</div>
    </div>

</section>

<div class="grid2">

<<<<<<< HEAD
    <!-- Akses Cepat -->
=======
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
    <section class="panel">

        <div class="panel-head">
            <div>
                <h2>Akses Cepat</h2>
                <div class="muted">Pilih menu yang ingin dikelola.</div>
            </div>
        </div>

        <div class="shortcuts">

            <a class="shortcut" href="panen.php">
                <span class="symbol">🐟</span>
                <span>
                    <strong>Data Panen</strong>
                    <small>Tambah dan kelola panen</small>
                </span>
            </a>

            <a class="shortcut" href="ikan.php">
                <span class="symbol">♧</span>
                <span>
                    <strong>Jenis Ikan</strong>
                    <small>Kelola jenis ikan</small>
                </span>
            </a>

            <a class="shortcut" href="kolam.php">
                <span class="symbol">▤</span>
                <span>
                    <strong>Data Kolam</strong>
                    <small>Kelola daftar kolam</small>
                </span>
            </a>

            <a class="shortcut" href="laporan.php">
                <span class="symbol">▥</span>
                <span>
                    <strong>Laporan</strong>
                    <small>Filter data panen</small>
                </span>
            </a>

        </div>

    </section>

<<<<<<< HEAD
    <!-- Ringkasan Produksi -->
=======
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
    <section class="panel">

        <div class="panel-head">
            <div>
                <h2>Ringkasan Produksi</h2>
                <div class="muted">Jumlah ikan menurut jenis.</div>
            </div>
        </div>

<<<<<<< HEAD
        <?php if (!$summary): ?>
=======
        <?php if (empty($summary)): ?>
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132

            <div class="empty">Belum ada jenis ikan.</div>

        <?php else: ?>

<<<<<<< HEAD
            <?php foreach ($summary as $s):

                $pct = $totalEkor > 0
                    ? min(100, ((int) $s['ekor'] / (int) $totalEkor) * 100)
                    : 0;

            ?>
=======
            <?php foreach ($summary as $s): ?>

                <?php
                $persentase = (float) $totalEkor > 0
                    ? min(
                        100,
                        ((int) $s['ekor'] / (int) $totalEkor) * 100
                    )
                    : 0;
                ?>
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132

                <div class="bar-row">

                    <div class="bar-top">
                        <strong><?= e($s['nama_ikan']) ?></strong>
                        <span>
                            <?= number_format((int) $s['ekor']) ?> ekor
                        </span>
                    </div>

                    <div class="bar-track">
                        <div
                            class="bar-fill"
<<<<<<< HEAD
                            style="width: <?= round($pct, 1) ?>%">
                        </div>
=======
                            style="width: <?= round($persentase, 1) ?>%;"
                        ></div>
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
                    </div>

                    <div class="muted">
                        <?= number_format((float) $s['berat'], 2) ?> kg
                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</div>

<<<<<<< HEAD
<!-- Panen Terbaru -->
<section class="panel">

    <div class="panel-head">

=======
<section class="panel">

    <div class="panel-head">
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
        <div>
            <h2>Panen Terbaru</h2>
            <div class="muted">
                Catatan panen terbaru dari database.
            </div>
        </div>

<<<<<<< HEAD
        <a class="btn light" href="panen.php">
            Lihat Semua →
        </a>

=======
        <a class="btn light" href="panen.php">Lihat Semua →</a>
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
    </div>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Ikan</th>
                    <th>Kolam</th>
                    <th>Jumlah</th>
                    <th>Berat</th>
                </tr>
            </thead>

            <tbody>

<<<<<<< HEAD
                <?php if (!$recentRows): ?>
=======
                <?php if (empty($recentRows)): ?>
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132

                    <tr>
                        <td colspan="5" class="empty">
                            Belum ada catatan panen.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentRows as $r): ?>

                        <tr>
                            <td><?= e($r['tanggal']) ?></td>

                            <td>
                                <span class="pill">
                                    <?= e($r['nama_ikan']) ?>
                                </span>
                            </td>

                            <td><?= e($r['nama_kolam']) ?></td>

                            <td>
                                <?= number_format((int) $r['jumlah_ekor']) ?>
                                ekor
                            </td>

                            <td>
                                <?= number_format((float) $r['berat_kg'], 2) ?>
                                kg
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php render_footer(); ?>
