<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$error = '';

// Memproses formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        // Menghapus data panen
        if ($action === 'hapus_panen') {

            if ($id < 1) {
                throw new Exception('ID data panen tidak valid.');
            }

            $stmt = $conn->prepare(
                'DELETE FROM panen WHERE id = ?'
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();

            go('panen.php', 'hapus');
        }

        // Menambah atau mengedit data panen
        if ($action === 'simpan_panen') {

            $tanggal = trim($_POST['tanggal'] ?? '');

            $jenis = filter_input(
                INPUT_POST,
                'jenis_ikan_id',
                FILTER_VALIDATE_INT
            );

            $kolam = filter_input(
                INPUT_POST,
                'kolam_id',
                FILTER_VALIDATE_INT
            );

            $jumlah = filter_input(
                INPUT_POST,
                'jumlah_ekor',
                FILTER_VALIDATE_INT
            );

            $beratIn = trim($_POST['berat_kg'] ?? '');

            // Validasi tanggal
            $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

            if (!$date || $date->format('Y-m-d') !== $tanggal) {
                throw new Exception('Tanggal panen tidak valid.');
            }

            // Validasi jenis ikan dan kolam
            if (!$jenis || $jenis < 1 || !$kolam || $kolam < 1) {
                throw new Exception('Silakan pilih jenis ikan dan kolam.');
            }

            // Validasi jumlah ikan
            if (!$jumlah || $jumlah < 1) {
                throw new Exception('Jumlah ikan minimal 1 ekor.');
            }

            // Validasi berat panen
            if (
                $beratIn === '' ||
                !is_numeric($beratIn) ||
                !is_finite((float) $beratIn) ||
                (float) $beratIn <= 0
            ) {
                throw new Exception('Berat panen harus lebih dari 0 kg.');
            }

            $berat = (float) $beratIn;

            // Memastikan jenis ikan tersedia
            $checkJenis = $conn->prepare(
                'SELECT id FROM jenis_ikan WHERE id = ?'
            );

            $checkJenis->bind_param('i', $jenis);
            $checkJenis->execute();

            if (!$checkJenis->get_result()->fetch_assoc()) {
                throw new Exception('Jenis ikan tidak ditemukan.');
            }

            // Memastikan kolam tersedia
            $checkKolam = $conn->prepare(
                'SELECT id FROM kolam WHERE id = ?'
            );

            $checkKolam->bind_param('i', $kolam);
            $checkKolam->execute();

            if (!$checkKolam->get_result()->fetch_assoc()) {
                throw new Exception('Data kolam tidak ditemukan.');
            }

            // Mengedit data panen
            if ($id > 0) {

                $stmt = $conn->prepare(
                    'UPDATE panen
                     SET tanggal = ?,
                         jenis_ikan_id = ?,
                         kolam_id = ?,
                         jumlah_ekor = ?,
                         berat_kg = ?
                     WHERE id = ?'
                );

                $stmt->bind_param(
                    'siiidi',
                    $tanggal,
                    $jenis,
                    $kolam,
                    $jumlah,
                    $berat,
                    $id
                );

                $stmt->execute();

                go('panen.php', 'edit');
            }

            // Menambahkan data panen baru
            $stmt = $conn->prepare(
                'INSERT INTO panen
                 (tanggal, jenis_ikan_id, kolam_id, jumlah_ekor, berat_kg)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'siiid',
                $tanggal,
                $jenis,
                $kolam,
                $jumlah,
                $berat
            );

            $stmt->execute();

            go('panen.php', 'tambah');
        }

        throw new Exception('Aksi tidak dikenal.');

    } catch (mysqli_sql_exception $ex) {

        $error = db_error_message($ex);

    } catch (Throwable $ex) {

        $error = $ex->getMessage();

    }
}

// Mengambil data untuk proses edit
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

$edit = $editId > 0
    ? fetch_by_id($conn, 'panen', $editId)
    : null;

// Mengambil pilihan jenis ikan dan kolam
$jenisOptions = get_jenis_options($conn);
$kolamOptions = get_kolam_options($conn);

// Mengambil daftar hasil panen
$panenRows = $conn->query(
    'SELECT
        p.id,
        p.tanggal,
        j.nama_ikan,
        k.nama_kolam,
        p.jumlah_ekor,
        p.berat_kg
     FROM panen p
     JOIN jenis_ikan j ON p.jenis_ikan_id = j.id
     JOIN kolam k ON p.kolam_id = k.id
     ORDER BY p.tanggal DESC, p.id DESC'
)->fetch_all(MYSQLI_ASSOC);

// Menampilkan header
render_header('Data Panen', 'panen', flash_message(), $error);

?>

<section class="panel">

    <div class="panel-head">
        <div>
            <h2>
                <?= $edit ? 'Edit Data Panen' : 'Tambah Data Panen' ?>
            </h2>

            <div class="muted">
                Isi rincian hasil panen di bawah ini.
            </div>
        </div>
    </div>

    <?php if (!$jenisOptions || !$kolamOptions): ?>
        <div class="error">
            Tambahkan setidaknya satu jenis ikan dan satu kolam
            terlebih dahulu sebelum mencatat panen.
        </div>
    <?php endif; ?>

    <form class="fields" method="post">

        <?= csrf_field() ?>

        <input type="hidden" name="action" value="simpan_panen">

        <input
            type="hidden"
            name="id"
            value="<?= e($edit['id'] ?? '') ?>"
        >

        <label>
            Tanggal Panen
            <input
                type="date"
                name="tanggal"
                required
                value="<?= e($edit['tanggal'] ?? date('Y-m-d')) ?>"
            >
        </label>

        <label>
            Jenis Ikan
            <select name="jenis_ikan_id" required>
                <option value="">Pilih jenis ikan</option>

                <?php foreach ($jenisOptions as $j): ?>
                    <option
                        value="<?= (int) $j['id'] ?>"
                        <?= (string) ($edit['jenis_ikan_id'] ?? '') === (string) $j['id'] ? 'selected' : '' ?>
                    >
                        <?= e($j['nama_ikan']) ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </label>

        <label>
            Kolam
            <select name="kolam_id" required>
                <option value="">Pilih kolam</option>

                <?php foreach ($kolamOptions as $k): ?>
                    <option
                        value="<?= (int) $k['id'] ?>"
                        <?= (string) ($edit['kolam_id'] ?? '') === (string) $k['id'] ? 'selected' : '' ?>
                    >
                        <?= e($k['nama_kolam']) ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </label>

        <label>
            Jumlah Ikan (ekor)
            <input
                type="number"
                name="jumlah_ekor"
                min="1"
                required
                value="<?= e($edit['jumlah_ekor'] ?? '') ?>"
                placeholder="Contoh: 200"
            >
        </label>

        <label>
            Berat Total (kg)
            <input
                type="number"
                name="berat_kg"
                min="0.01"
                step="0.01"
                required
                value="<?= e($edit['berat_kg'] ?? '') ?>"
                placeholder="Contoh: 50"
            >
        </label>

        <div class="full actions">

            <button
                class="btn"
                type="submit"
                <?= (!$jenisOptions || !$kolamOptions) ? 'disabled' : '' ?>
            >
                <?= $edit ? 'Simpan Perubahan' : '+ Simpan Data Panen' ?>
            </button>

            <?php if ($edit): ?>
                <a class="btn light" href="panen.php">
                    Batal Edit
                </a>
            <?php endif; ?>

        </div>

    </form>

</section>

<section class="panel">

    <div class="panel-head">
        <div>
            <h2>Daftar Hasil Panen</h2>

            <div class="muted">
                <?= count($panenRows) ?> catatan ditampilkan.
            </div>
        </div>
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
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$panenRows): ?>
                    <tr>
                        <td colspan="6" class="empty">
                            Belum ada data panen.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($panenRows as $r): ?>
                        <tr>

                            <td><?= e($r['tanggal']) ?></td>

                            <td><?= e($r['nama_ikan']) ?></td>

                            <td><?= e($r['nama_kolam']) ?></td>

                            <td>
                                <?= number_format((int) $r['jumlah_ekor']) ?>
                                ekor
                            </td>

                            <td>
                                <?= number_format((float) $r['berat_kg'], 2) ?>
                                kg
                            </td>

                            <td>
                                <div class="actions">

                                    <a
                                        class="btn light small"
                                        href="panen.php?edit=<?= (int) $r['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        class="inline-form"
                                        method="post"
                                        onsubmit="return confirm('Yakin ingin menghapus data panen ini?')"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="hapus_panen"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $r['id'] ?>"
                                        >

                                        <button class="btn red small" type="submit">
                                            Hapus
                                        </button>

                                    </form>

                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php render_footer(); ?>
