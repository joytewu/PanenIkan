
<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$error = '';

// Memeriksa apakah kolom lokasi tersedia
$hasLokasi = hasColumn($conn, 'kolam', 'lokasi');

// Memproses formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        // Menghapus data kolam
        if ($action === 'hapus_kolam') {

            if ($id < 1) {
                throw new Exception('ID kolam tidak valid.');
            }

            $stmt = $conn->prepare(
                'DELETE FROM kolam WHERE id = ?'
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();

            go('kolam.php', 'hapus');
        }

        // Menambah atau mengedit data kolam
        if ($action === 'simpan_kolam') {

            $nama = trim($_POST['nama_kolam'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');

            if ($nama === '') {
                throw new Exception('Nama kolam wajib diisi.');
            }

            if (mb_strlen($nama) > 100) {
                throw new Exception('Nama kolam maksimal 100 karakter.');
            }

            if (mb_strlen($lokasi) > 255) {
                throw new Exception('Lokasi kolam maksimal 255 karakter.');
            }

            // Mengedit data kolam
            if ($id > 0) {

                if ($hasLokasi) {

                    $stmt = $conn->prepare(
                        'UPDATE kolam
                         SET nama_kolam = ?, lokasi = ?
                         WHERE id = ?'
                    );

                    $stmt->bind_param('ssi', $nama, $lokasi, $id);

                } else {

                    $stmt = $conn->prepare(
                        'UPDATE kolam
                         SET nama_kolam = ?
                         WHERE id = ?'
                    );

                    $stmt->bind_param('si', $nama, $id);

                }

                $stmt->execute();

                go('kolam.php', 'edit');
            }

            // Menambahkan data kolam
            if ($hasLokasi) {

                $stmt = $conn->prepare(
                    'INSERT INTO kolam (nama_kolam, lokasi)
                     VALUES (?, ?)'
                );

                $stmt->bind_param('ss', $nama, $lokasi);

            } else {

                $stmt = $conn->prepare(
                    'INSERT INTO kolam (nama_kolam)
                     VALUES (?)'
                );

                $stmt->bind_param('s', $nama);

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
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

$edit = $editId > 0
    ? fetch_by_id($conn, 'kolam', $editId)
    : null;

// Mengambil daftar kolam
$kolamRows = $conn->query(
    'SELECT * FROM kolam ORDER BY nama_kolam'
)->fetch_all(MYSQLI_ASSOC);

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
                                        onsubmit="return confirm('Hapus kolam ini? Kolam yang digunakan dalam data panen tidak dapat dihapus.')"
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
