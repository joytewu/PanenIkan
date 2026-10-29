<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        if ($action === 'hapus_ikan') {
            $stmt = $conn->prepare('DELETE FROM jenis_ikan WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            go('ikan', 'hapus');
        }

        if ($action === 'simpan_ikan') {
            $nama = trim($_POST['nama_ikan'] ?? '');
            if ($nama === '') {
                throw new Exception('Nama ikan wajib diisi.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE jenis_ikan SET nama_ikan=? WHERE id=?');
                $stmt->bind_param('si', $nama, $id);
                $stmt->execute();
                go('ikan', 'edit');
            }

            $stmt = $conn->prepare('INSERT INTO jenis_ikan (nama_ikan) VALUES (?)');
            $stmt->bind_param('s', $nama);
            $stmt->execute();
            go('ikan', 'tambah');
        }

        throw new Exception('Aksi tidak dikenal.');
    } catch (mysqli_sql_exception $ex) {
        $error = db_error_message($ex);
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$edit = $editId ? fetch_by_id($conn, 'jenis_ikan', $editId) : null;
$jenisRows = get_jenis_options($conn);

render_header('Jenis Ikan', 'ikan', flash_message(), $error);
?>
<section class="panel">
    <div class="panel-head"><div><h2><?= $edit ? 'Edit Jenis Ikan' : 'Tambah Jenis Ikan' ?></h2><div class="muted">Contoh: Nila, Lele, Gurami.</div></div></div>
    <form class="fields" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="simpan_ikan">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <label class="full">Nama Jenis Ikan<input name="nama_ikan" required maxlength="100" value="<?= e($edit['nama_ikan'] ?? '') ?>" placeholder="Masukkan nama ikan"></label>
        <div class="full actions">
            <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : '+ Tambah Jenis Ikan' ?></button>
            <?php if ($edit): ?><a class="btn light" href="ikan.php">Batal</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><div><h2>Daftar Jenis Ikan</h2><div class="muted">Jenis ikan yang tersedia untuk pencatatan panen.</div></div></div>
    <div class="table-wrap"><table>
        <thead><tr><th>No.</th><th>Nama Ikan</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (!$jenisRows): ?>
            <tr><td colspan="3" class="empty">Belum ada jenis ikan.</td></tr>
        <?php else: foreach ($jenisRows as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><span class="pill"><?= e($r['nama_ikan']) ?></span></td>
                <td><div class="actions">
                    <a class="btn light small" href="ikan.php?edit=<?= e($r['id']) ?>">Edit</a>
                    <form class="inline-form" method="post" onsubmit="return confirm('Hapus jenis ikan ini? Jenis yang sudah digunakan dalam panen tidak dapat dihapus.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="hapus_ikan">
                        <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                        <button class="btn red small" type="submit">Hapus</button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</section>
<?php render_footer(); ?>
