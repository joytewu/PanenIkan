<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$userId = current_user_id();

// Mengambil pilihan jenis ikan dan kolam
$jenisOptions = get_jenis_options($conn, $userId);
$kolamOptions = get_kolam_options($conn, $userId);

// Mengambil filter tanggal
$filterTanggal = trim($_GET['tanggal'] ?? '');

$dateObj = DateTime::createFromFormat('!Y-m-d', $filterTanggal);

if (
    !$dateObj ||
    $dateObj->format('Y-m-d') !== $filterTanggal
) {
    $filterTanggal = '';
}

// Mengambil filter jenis ikan dan kolam
$filterJenis = filter_input(
    INPUT_GET,
    'jenis',
    FILTER_VALIDATE_INT
) ?: 0;

$filterKolam = filter_input(
    INPUT_GET,
    'kolam',
    FILTER_VALIDATE_INT
) ?: 0;

// Menyiapkan query laporan
$sql = '
    SELECT
        p.id,
        p.tanggal,
        j.nama_ikan,
        k.nama_kolam,
        p.jumlah_ekor,
        p.berat_kg
    FROM panen p
    JOIN jenis_ikan j
       ON p.jenis_ikan_id = j.id
       AND (j.owner_user_id = ? OR j.owner_user_id IS NULL)
    JOIN kolam k
       ON p.kolam_id = k.id
       AND (k.owner_user_id = ? OR k.owner_user_id IS NULL)
    WHERE (p.owner_user_id = ? OR p.owner_user_id IS NULL)
';

$types = 'iii';
$params = [$userId, $userId, $userId];

// Filter berdasarkan tanggal
if ($filterTanggal !== '') {

    $sql .= ' AND p.tanggal = ?';

    $types .= 's';
    $params[] = $filterTanggal;
}

// Filter berdasarkan jenis ikan
if ($filterJenis > 0) {

    $sql .= ' AND p.jenis_ikan_id = ?';

    $types .= 'i';
    $params[] = $filterJenis;
}

// Filter berdasarkan kolam
if ($filterKolam > 0) {

    $sql .= ' AND p.kolam_id = ?';

    $types .= 'i';
    $params[] = $filterKolam;
}

// Urutan data laporan
$sql .= ' ORDER BY p.tanggal DESC, p.id DESC';

// Menjalankan query
$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Menghitung ringkasan laporan
$jumlahLaporan = 0;
$beratLaporan = 0;

foreach ($rows as $r) {

    $jumlahLaporan += (int) $r['jumlah_ekor'];
    $beratLaporan += (float) $r['berat_kg'];

}

// Menampilkan header
render_header('Laporan Panen', 'laporan');

?>

<!-- Filter Laporan -->
<section class="panel">

    <div class="panel-head">

        <div>
            <h2>Filter Laporan</h2>

            <div class="muted">
                Pilih kriteria untuk melihat data panen tertentu.
            </div>
        </div>

    </div>

    <form class="filter" method="get">

        <label>
            Tanggal Panen

            <input
                type="date"
                name="tanggal"
                value="<?= e($filterTanggal) ?>"
            >
        </label>

        <label>
            Jenis Ikan

            <select name="jenis">

                <option value="0">
                    Semua jenis
                </option>

                <?php foreach ($jenisOptions as $j): ?>

                    <option
                        value="<?= (int) $j['id'] ?>"
                        <?= $filterJenis === (int) $j['id'] ? 'selected' : '' ?>
                    >
                        <?= e($j['nama_ikan']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </label>

        <label>
            Kolam

            <select name="kolam">

                <option value="0">
                    Semua kolam
                </option>

                <?php foreach ($kolamOptions as $k): ?>

                    <option
                        value="<?= (int) $k['id'] ?>"
                        <?= $filterKolam === (int) $k['id'] ? 'selected' : '' ?>
                    >
                        <?= e($k['nama_kolam']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </label>

        <button class="btn" type="submit">
            Tampilkan
        </button>

        <a class="btn light" href="laporan.php">
            Reset
        </a>

    </form>

</section>

<!-- Ringkasan Laporan -->
<section class="stats three">

    <div class="stat">

        <div class="stat-top">
            Catatan Ditemukan
            <span class="stat-icon">▥</span>
        </div>

        <div class="stat-number">
            <?= count($rows) ?>
        </div>

        <div class="stat-note">
            baris laporan
        </div>

    </div>

    <div class="stat">

        <div class="stat-top">
            Jumlah Ikan
            <span class="stat-icon">🐟</span>
        </div>

        <div class="stat-number">
            <?= number_format($jumlahLaporan) ?>
        </div>

        <div class="stat-note">
            ekor
        </div>

    </div>

    <div class="stat">

        <div class="stat-top">
            Berat Total
            <span class="stat-icon">⚖</span>
        </div>

        <div class="stat-number">
            <?= number_format($beratLaporan, 2) ?>
            <small>kg</small>
        </div>

        <div class="stat-note">
            berat panen terfilter
        </div>

    </div>

</section>

<!-- Tabel Hasil Laporan -->
<section class="panel">

    <div class="panel-head">

        <div>
            <h2>Hasil Laporan Panen</h2>

            <div class="muted">
                Gunakan tombol cetak untuk menyimpan laporan melalui browser.
            </div>
        </div>

        <button
            class="btn light"
            type="button"
            onclick="window.print()"
        >
            Cetak Laporan
        </button>

    </div>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>No.</th>
                    <th>Tanggal</th>
                    <th>Jenis Ikan</th>
                    <th>Kolam</th>
                    <th>Jumlah (ekor)</th>
                    <th>Berat (kg)</th>
                </tr>

            </thead>

            <tbody>

                <?php if (!$rows): ?>

                    <tr>
                        <td colspan="6" class="empty">
                            Tidak ada data yang cocok dengan filter.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($rows as $i => $r): ?>

                        <tr>

                            <td>
                                <?= $i + 1 ?>
                            </td>

                            <td>
                                <?= e($r['tanggal']) ?>
                            </td>

                            <td>
                                <?= e($r['nama_ikan']) ?>
                            </td>

                            <td>
                                <?= e($r['nama_kolam']) ?>
                            </td>

                            <td>
                                <?= number_format((int) $r['jumlah_ekor']) ?>
                            </td>

                            <td>
                                <?= number_format((float) $r['berat_kg'], 2) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php render_footer(); ?>
