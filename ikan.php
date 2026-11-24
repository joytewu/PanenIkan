<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$error = '';
$userId = current_user_id();

// Memproses formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $action = request_text($_POST, 'action', 'Aksi', 32, true);
        $id = request_integer(
            $_POST,
            'id',
            'ID jenis ikan',
            0,
            2147483647,
            true
        );

        // Menghapus jenis ikan
        if ($action === 'hapus_ikan') {
            if ($id < 1) {
                throw new Exception('ID jenis ikan tidak valid.');
            }

            if (!fetch_by_id($conn, 'jenis_ikan', $id, $userId)) {
                throw new Exception('Jenis ikan tidak ditemukan atau akses ditolak.');
            }

            $stmt = $conn->prepare(
                'DELETE FROM jenis_ikan
                 WHERE id = ?
                 AND (owner_user_id = ? OR owner_user_id IS NULL)'
            );

            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();

            go('ikan.php', 'hapus');
        }

        // Menambah atau mengedit jenis ikan
        if ($action === 'simpan_ikan') {
            $nama = request_text(
                $_POST,
                'nama_ikan',
                'Nama ikan',
                100,
                true
            );

            // Mengedit jenis ikan
            if ($id > 0) {
                if (!fetch_by_id($conn, 'jenis_ikan', $id, $userId)) {
                    throw new Exception('Jenis ikan tidak ditemukan atau akses ditolak.');
                }

                $stmt = $conn->prepare(
                    'UPDATE jenis_ikan
                     SET nama_ikan = ?
                     WHERE id = ?
                     AND (owner_user_id = ? OR owner_user_id IS NULL)'
                );

                $stmt->bind_param('sii', $nama, $id, $userId);
                $stmt->execute();

                go('ikan.php', 'edit');
            }

            // Menambahkan jenis ikan
            $stmt = $conn->prepare(
                'INSERT INTO jenis_ikan (owner_user_id, nama_ikan)
                 VALUES (?, ?)'
            );

            $stmt->bind_param('is', $userId, $nama);
            $stmt->execute();

            go('ikan.php', 'tambah');
        }

        throw new Exception('Aksi tidak dikenal.');

    } catch (mysqli_sql_exception $ex) {
        $error = db_error_message($ex);

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

// Mengambil data untuk edit
$editId = optional_input_integer(
    $_GET['edit'] ?? null,
    1,
    2147483647
);

$edit = $editId > 0
    ? fetch_by_id($conn, 'jenis_ikan', $editId, $userId)
    : null;

// Mengambil daftar jenis ikan
$jenisRows = get_jenis_options($conn, $userId);

// Menampilkan halaman
render_header('Jenis Ikan', 'ikan', flash_message(), $error);
?>

<!-- Form Tambah dan Edit -->
<section class="panel">

    <div class="panel-head">
        <div>
            <h2>
                <?= $edit ? 'Edit Jenis Ikan' : 'Tambah Jenis Ikan' ?>
            </h2>

            <div class="muted">
                Contoh: Nila, Lele, Gurami.
            </div>
        </div>
    </div>

    <form class="fields" method="post">

        <?= csrf_field() ?>

        <input
            type="hidden"
            name="action"
            value="simpan_ikan"
        >

        <input
            type="hidden"
            name="id"
            value="<?= e($edit['id'] ?? '') ?>"
        >

        <label class="full">
            Nama Jenis Ikan

            <input
                type="text"
                name="nama_ikan"
                required
                maxlength="100"
                value="<?= e($edit['nama_ikan'] ?? '') ?>"
                placeholder="Masukkan nama ikan"
            >
        </label>

        <div class="full actions">

            <button class="btn" type="submit">
                <?= $edit ? 'Simpan Perubahan' : '+ Tambah Jenis Ikan' ?>
            </button>

            <?php if ($edit): ?>
                <a class="btn light" href="ikan.php">
                    Batal
                </a>
            <?php endif; ?>

        </div>

    </form>

</section>

<!-- Daftar Jenis Ikan -->
<section class="panel">

    <div class="panel-head">
        <div>
            <h2>Daftar Jenis Ikan</h2>

            <div class="muted">
                Jenis ikan yang tersedia untuk pencatatan panen.
            </div>
        </div>
    </div>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Ikan</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$jenisRows): ?>

                    <tr>
                        <td colspan="3" class="empty">
                            Belum ada jenis ikan.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($jenisRows as $i => $r): ?>

                        <tr>

                            <td><?= $i + 1 ?></td>

                            <td>
                                <span class="pill">
                                    <?= e($r['nama_ikan']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="actions">

                                    <a
                                        class="btn light small"
                                        href="ikan.php?edit=<?= (int) $r['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        class="inline-form"
                                        method="post"
                                        data-confirm="Hapus jenis ikan ini? Jenis yang sudah digunakan dalam panen tidak dapat dihapus."
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="hapus_ikan"
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
