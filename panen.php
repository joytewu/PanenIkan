<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/harvest_photos.php';
require_once __DIR__ . '/includes/layout.php';

$error = '';
$userId = current_user_id();
$stagedPhoto = null;

// Memproses formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = request_text($_POST, 'action', 'Aksi', 32, true);
        $id = request_integer(
            $_POST,
            'id',
            'ID data panen',
            0,
            2147483647,
            true
        );

        // Menghapus data panen
        if ($action === 'hapus_panen') {

            if ($id < 1) {
                throw new Exception('ID data panen tidak valid.');
            }

            $record = fetch_by_id($conn, 'panen', $id, $userId);

            if (!$record) {
                throw new Exception('Data panen tidak ditemukan atau akses ditolak.');
            }

            $stmt = $conn->prepare(
                'DELETE FROM panen
                 WHERE id = ?
                 AND (owner_user_id = ? OR owner_user_id IS NULL)'
            );

            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();

            $photoFilename = $record['foto_filename'] ?? null;

            if (
                is_string($photoFilename) &&
                $photoFilename !== '' &&
                !delete_harvest_photo($photoFilename)
            ) {
                app_log(
                    'A deleted harvest record left an unremovable photo file.',
                    'ERROR'
                );
            }

            go('panen.php', 'hapus');
        }

        // Menambah atau mengedit data panen
        if ($action === 'simpan_panen') {

            $tanggal = request_text(
                $_POST,
                'tanggal',
                'Tanggal panen',
                10,
                true
            );
            $jenis = request_integer(
                $_POST,
                'jenis_ikan_id',
                'Jenis ikan',
                1,
                2147483647
            );
            $kolam = request_integer(
                $_POST,
                'kolam_id',
                'Kolam',
                1,
                2147483647
            );
            $jumlah = request_integer(
                $_POST,
                'jumlah_ekor',
                'Jumlah ikan',
                1,
                2147483647
            );
            $beratIn = request_text(
                $_POST,
                'berat_kg',
                'Berat panen',
                11,
                true
            );

            // Validasi tanggal
            $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

            if (!$date || $date->format('Y-m-d') !== $tanggal) {
                throw new Exception('Tanggal panen tidak valid.');
            }

            // Validasi berat panen
            if (
                preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/', $beratIn) !== 1 ||
                (float) $beratIn <= 0
            ) {
                throw new InvalidArgumentException(
                    'Berat panen harus lebih dari 0 kg dan maksimal 8 digit sebelum koma serta 2 digit desimal.'
                );
            }

            $berat = (float) $beratIn;

            // Memastikan jenis ikan tersedia
            $checkJenis = $conn->prepare(
                'SELECT id
                 FROM jenis_ikan
                 WHERE id = ?
                 AND (owner_user_id = ? OR owner_user_id IS NULL)'
            );

            $checkJenis->bind_param('ii', $jenis, $userId);
            $checkJenis->execute();

            if (!$checkJenis->get_result()->fetch_assoc()) {
                throw new Exception('Jenis ikan tidak ditemukan.');
            }

            // Memastikan kolam tersedia
            $checkKolam = $conn->prepare(
                'SELECT id
                 FROM kolam
                 WHERE id = ?
                 AND (owner_user_id = ? OR owner_user_id IS NULL)'
            );

            $checkKolam->bind_param('ii', $kolam, $userId);
            $checkKolam->execute();

            if (!$checkKolam->get_result()->fetch_assoc()) {
                throw new Exception('Data kolam tidak ditemukan.');
            }

            $existingRecord = null;

            if ($id > 0) {
                $existingRecord = fetch_by_id($conn, 'panen', $id, $userId);

                if (!$existingRecord) {
                    throw new Exception('Data panen tidak ditemukan atau akses ditolak.');
                }
            }

            $stagedPhoto = store_harvest_photo($_FILES['foto'] ?? null);

            // Mengedit data panen
            if ($id > 0) {

                $oldPhotoFilename = $existingRecord['foto_filename'] ?? null;

                if ($stagedPhoto !== null) {
                    $stmt = $conn->prepare(
                        'UPDATE panen
                         SET tanggal = ?,
                             jenis_ikan_id = ?,
                             kolam_id = ?,
                             jumlah_ekor = ?,
                             berat_kg = ?,
                             foto_filename = ?
                         WHERE id = ?
                         AND (owner_user_id = ? OR owner_user_id IS NULL)'
                    );

                    $stmt->bind_param(
                        'siiidsii',
                        $tanggal,
                        $jenis,
                        $kolam,
                        $jumlah,
                        $berat,
                        $stagedPhoto,
                        $id,
                        $userId
                    );

                } else {

                    $stmt = $conn->prepare(
                        'UPDATE panen
                         SET tanggal = ?,
                             jenis_ikan_id = ?,
                             kolam_id = ?,
                             jumlah_ekor = ?,
                             berat_kg = ?
                         WHERE id = ?
                         AND (owner_user_id = ? OR owner_user_id IS NULL)'
                    );

                    $stmt->bind_param(
                        'siiidii',
                        $tanggal,
                        $jenis,
                        $kolam,
                        $jumlah,
                        $berat,
                        $id,
                        $userId
                    );

                }

                $stmt->execute();

                if ($stagedPhoto !== null) {
                    $replacementPhoto = $stagedPhoto;
                    $stagedPhoto = null;

                    if (
                        is_string($oldPhotoFilename) &&
                        $oldPhotoFilename !== '' &&
                        $oldPhotoFilename !== $replacementPhoto &&
                        !delete_harvest_photo($oldPhotoFilename)
                    ) {
                        app_log(
                            'A replaced harvest photo could not be removed.',
                            'ERROR'
                        );
                    }
                }

                go('panen.php', 'edit');
            }

            // Menambahkan data panen baru
            if ($stagedPhoto !== null) {
                $stmt = $conn->prepare(
                    'INSERT INTO panen
                     (owner_user_id, tanggal, jenis_ikan_id, kolam_id, jumlah_ekor, berat_kg, foto_filename)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->bind_param(
                    'isiiids',
                    $userId,
                    $tanggal,
                    $jenis,
                    $kolam,
                    $jumlah,
                    $berat,
                    $stagedPhoto
                );

            } else {

                $stmt = $conn->prepare(
                    'INSERT INTO panen
                     (owner_user_id, tanggal, jenis_ikan_id, kolam_id, jumlah_ekor, berat_kg)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );

                $stmt->bind_param(
                    'isiiid',
                    $userId,
                    $tanggal,
                    $jenis,
                    $kolam,
                    $jumlah,
                    $berat
                );

            }

            $stmt->execute();

            $stagedPhoto = null;

            go('panen.php', 'tambah');
        }

        throw new Exception('Aksi tidak dikenal.');

    } catch (mysqli_sql_exception $ex) {

        discard_staged_harvest_photo($stagedPhoto);
        $error = db_error_message($ex);

    } catch (Throwable $ex) {

        discard_staged_harvest_photo($stagedPhoto);
        $error = $ex->getMessage();

    }
}

// Mengambil data untuk proses edit
$editId = optional_input_integer(
    $_GET['edit'] ?? null,
    1,
    2147483647
);

$edit = $editId > 0
    ? fetch_by_id($conn, 'panen', $editId, $userId)
    : null;

// Mengambil pilihan jenis ikan dan kolam
$jenisOptions = get_jenis_options($conn, $userId);
$kolamOptions = get_kolam_options($conn, $userId);

// Mengambil daftar hasil panen
$stmt = $conn->prepare(
    'SELECT
        p.id,
        p.tanggal,
        j.nama_ikan,
        k.nama_kolam,
        p.jumlah_ekor,
        p.berat_kg,
        p.foto_filename
     FROM panen p
     JOIN jenis_ikan j
         ON p.jenis_ikan_id = j.id
         AND (j.owner_user_id = ? OR j.owner_user_id IS NULL)
     JOIN kolam k
         ON p.kolam_id = k.id
         AND (k.owner_user_id = ? OR k.owner_user_id IS NULL)
     WHERE p.owner_user_id = ? OR p.owner_user_id IS NULL
     ORDER BY p.tanggal DESC, p.id DESC'
);

$stmt->bind_param('iii', $userId, $userId, $userId);
$stmt->execute();

$panenRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

    <form class="fields" method="post" enctype="multipart/form-data">

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
                max="2147483647"
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
                max="99999999.99"
                step="0.01"
                required
                value="<?= e($edit['berat_kg'] ?? '') ?>"
                placeholder="Contoh: 50"
            >
        </label>

        <label class="full">
            Foto Bukti Panen (JPEG, PNG, WebP; maksimal 5 MB)
            <input
                type="file"
                name="foto"
                accept="image/jpeg,image/png,image/webp"
            >
        </label>

        <?php if (!empty($edit['foto_filename'])): ?>
            <div class="full">
                <span class="muted">Foto saat ini:</span>
                <img
                    class="harvest-photo preview"
                    src="panen_foto.php?id=<?= (int) $edit['id'] ?>"
                    alt="Foto panen <?= (int) $edit['id'] ?>"
                >
            </div>
        <?php endif; ?>

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
                    <th>Foto</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$panenRows): ?>
                    <tr>
                        <td colspan="7" class="empty">
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
                                <?php if (!empty($r['foto_filename'])): ?>
                                    <img
                                        class="harvest-photo"
                                        src="panen_foto.php?id=<?= (int) $r['id'] ?>"
                                        alt="Foto panen <?= (int) $r['id'] ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    -
                                <?php endif; ?>
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
                                        data-confirm="Yakin ingin menghapus data panen ini?"
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
