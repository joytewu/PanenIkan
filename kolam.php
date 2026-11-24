<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$error = '';
$userId = current_user_id();

// Memeriksa apakah kolom lokasi tersedia
$hasLokasi = hasColumn($conn, 'kolam', 'lokasi');

// Memproses formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = request_text($_POST, 'action', 'Aksi', 32, true);
        $id = request_integer(
            $_POST,
            'id',
            'ID kolam',
            0,
            2147483647,
            true
        );

        // Menghapus data kolam
        if ($action === 'hapus_kolam') {

            if ($id < 1) {
                throw new Exception('ID kolam tidak valid.');
            }

            if (!fetch_by_id($conn, 'kolam', $id, $userId)) {
                throw new Exception('Data kolam tidak ditemukan atau akses ditolak.');
            }

            $stmt = $conn->prepare(
                'DELETE FROM kolam
                 WHERE id = ?
                 AND (owner_user_id = ? OR owner_user_id IS NULL)'
            );

            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();

            go('kolam.php', 'hapus');
        }

        // Menambah atau mengedit data kolam
        if ($action === 'simpan_kolam') {

            $nama = request_text(
                $_POST,
                'nama_kolam',
                'Nama kolam',
                100,
                true
            );
            $lokasi = request_text(
                $_POST,
                'lokasi',
                'Lokasi kolam',
                255
            );

            // Mengedit data kolam
            if ($id > 0) {

                if (!fetch_by_id($conn, 'kolam', $id, $userId)) {
                    throw new Exception('Data kolam tidak ditemukan atau akses ditolak.');
                }

                if ($hasLokasi) {

                    $stmt = $conn->prepare(
                        'UPDATE kolam
                         SET nama_kolam = ?, lokasi = ?
                         WHERE id = ?
                         AND (owner_user_id = ? OR owner_user_id IS NULL)'
                    );

                    $stmt->bind_param('ssii', $nama, $lokasi, $id, $userId);

                } else {

                    $stmt = $conn->prepare(
                        'UPDATE kolam
                         SET nama_kolam = ?
                         WHERE id = ?
                         AND (owner_user_id = ? OR owner_user_id IS NULL)'
                    );

                    $stmt->bind_param('sii', $nama, $id, $userId);

                }

                $stmt->execute();

                go('kolam.php', 'edit');
            }

            // Menambahkan data kolam
            if ($hasLokasi) {

                $stmt = $conn->prepare(
                    'INSERT INTO kolam (owner_user_id, nama_kolam, lokasi)
                     VALUES (?, ?, ?)'
                );

                $stmt->bind_param('iss', $userId, $nama, $lokasi);

            } else {

                $stmt = $conn->prepare(
                    'INSERT INTO kolam (owner_user_id, nama_kolam)
                     VALUES (?, ?)'
                );

                $stmt->bind_param('is', $userId, $nama);

            }

            $stmt->execute();

            go('kolam.php', 'tambah');
        }

        throw new Exception('Aksi tidak dikenal.');

    } catch (mysqli_sql_exception $ex) {

        $error = db_error_message($ex);

    } catch (Throwable $ex) {

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
    ? fetch_by_id($conn, 'kolam', $editId, $userId)
    : null;

// Mengambil daftar kolam
$stmt = $conn->prepare(
    'SELECT *
     FROM kolam
     WHERE owner_user_id = ? OR owner_user_id IS NULL
     ORDER BY nama_kolam'
);

$stmt->bind_param('i', $userId);
$stmt->execute();

$kolamRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Menampilkan header
render_header('Data Kolam', 'kolam', flash_message(), $error);

?>

<!-- Form Tambah dan Edit Kolam -->
<section class="panel">

    <div class="panel-head">

        <div>
            <h2>
                <?= $edit ? 'Edit Data Kolam' : 'Tambah Data Kolam' ?>
            </h2>

            <div class="muted">
                Kelola nama dan lokasi kolam.
            </div>
        </div>

    </div>

    <form class="fields" method="post">

        <?= csrf_field() ?>

        <input
            type="hidden"
            name="action"
            value="simpan_kolam"
        >

        <input
            type="hidden"
            name="id"
            value="<?= e($edit['id'] ?? '') ?>"
        >

        <label>
            Nama Kolam

            <input
                type="text"
                name="nama_kolam"
                required
                maxlength="100"
                value="<?= e($edit['nama_kolam'] ?? '') ?>"
                placeholder="Contoh: Kolam A"
            >
        </label>

        <?php if ($hasLokasi): ?>

            <label>
                Lokasi Kolam

                <input
                    type="text"
                    name="lokasi"
                    maxlength="255"
                    value="<?= e($edit['lokasi'] ?? '') ?>"
                    placeholder="Contoh: Area belakang"
                >
            </label>

        <?php endif; ?>

        <div class="full actions">

            <button class="btn" type="submit">
                <?= $edit ? 'Simpan Perubahan' : '+ Tambah Kolam' ?>
            </button>

            <?php if ($edit): ?>

                <a class="btn light" href="kolam.php">
                    Batal
                </a>

            <?php endif; ?>

        </div>

    </form>

</section>

<!-- Daftar Kolam -->
<section class="panel">

    <div class="panel-head">

        <div>
            <h2>Daftar Kolam</h2>

            <div class="muted">
                Kolam yang terdaftar di sistem.
            </div>
        </div>

    </div>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Kolam</th>

                    <?php if ($hasLokasi): ?>
                        <th>Lokasi</th>
                    <?php endif; ?>

                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$kolamRows): ?>

                    <tr>
                        <td colspan="<?= $hasLokasi ? 4 : 3 ?>" class="empty">
                            Belum ada data kolam.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($kolamRows as $i => $r): ?>

                        <tr>

                            <td><?= $i + 1 ?></td>

                            <td>
                                <strong>
                                    <?= e($r['nama_kolam']) ?>
                                </strong>
                            </td>

                            <?php if ($hasLokasi): ?>

                                <td>
                                    <?= e($r['lokasi'] ?? '-') ?>
                                </td>

                            <?php endif; ?>

                            <td>

                                <div class="actions">

                                    <a
                                        class="btn light small"
                                        href="kolam.php?edit=<?= (int) $r['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        class="inline-form"
                                        method="post"
                                        data-confirm="Hapus kolam ini? Kolam yang digunakan dalam data panen tidak dapat dihapus."
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="hapus_kolam"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $r['id'] ?>"
                                        >

                                        <button
                                            class="btn red small"
                                            type="submit"
                                        >
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
