<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/security_audit.php';

$items = security_audit_items();
$answers = [];
$notes = [];
$error = '';
$success = isset($_GET['saved']) && $_GET['saved'] === '1'
    ? 'Hasil pemeriksaan berhasil disimpan.'
    : '';
$storageUnavailable = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $transactionStarted = false;

    try {
        verify_csrf();

        $submittedAnswers = $_POST['answers'] ?? [];
        $submittedNotes = $_POST['notes'] ?? [];

        if (!is_array($submittedAnswers) || !is_array($submittedNotes)) {
            throw new InvalidArgumentException('Data pemeriksaan tidak valid.');
        }

        foreach ($items as $code => $item) {
            $itemAnswers = $submittedAnswers[$code] ?? [];
            $note = $submittedNotes[$code] ?? '';

            if (!is_array($itemAnswers) || !is_string($note)) {
                throw new InvalidArgumentException('Data pemeriksaan tidak valid.');
            }

            $yes = ($itemAnswers['yes'] ?? null) === '1';
            $no = ($itemAnswers['no'] ?? null) === '1';

            if (($itemAnswers['yes'] ?? null) !== null && !$yes) {
                throw new InvalidArgumentException('Pilihan jawaban tidak valid.');
            }

            if (($itemAnswers['no'] ?? null) !== null && !$no) {
                throw new InvalidArgumentException('Pilihan jawaban tidak valid.');
            }

            if ($yes && $no) {
                throw new InvalidArgumentException(
                    'Pilih satu jawaban saja untuk setiap pemeriksaan.'
                );
            }

            if (preg_match('//u', $note) !== 1) {
                throw new InvalidArgumentException('Catatan tidak valid.');
            }

            $note = request_text(
                ['note' => $note],
                'note',
                'Catatan',
                500
            );
            $answers[$code] = $yes ? 'yes' : ($no ? 'no' : null);
            $notes[$code] = $note;
        }

        $conn->begin_transaction();
        $transactionStarted = true;
        $stmt = $conn->prepare(
            'INSERT INTO security_audit_checks
                (item_code, answer, note, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                answer = VALUES(answer),
                note = VALUES(note),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP'
        );
        $userId = current_user_id();

        foreach ($items as $code => $item) {
            $answer = $answers[$code];
            $note = $notes[$code];
            $stmt->bind_param('sssi', $code, $answer, $note, $userId);
            $stmt->execute();
        }

        $conn->commit();
        header('Location: security_audit.php?saved=1');
        exit;
    } catch (mysqli_sql_exception $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }

        app_log(
            'Security audit results could not be saved.',
            'ERROR',
            'security.audit.save_failed'
        );
        $error = 'Hasil belum tersimpan. Periksa migrasi tabel pemeriksaan dan coba lagi.';
    } catch (InvalidArgumentException $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }

        $error = $e->getMessage();
    } catch (Exception $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }

        app_log(
            'Security audit request could not be processed.',
            'WARNING',
            'security.audit.request_rejected'
        );
        $error = 'Permintaan tidak valid atau hasil tidak dapat disimpan.';
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }

        app_log(
            'Security audit results could not be saved.',
            'ERROR',
            'security.audit.save_failed'
        );
        $error = 'Hasil belum tersimpan. Periksa migrasi tabel pemeriksaan dan coba lagi.';
    }
}

if (!$answers) {
    try {
        $result = $conn->query(
            'SELECT item_code, answer, note
             FROM security_audit_checks'
        );

        while ($row = $result->fetch_assoc()) {
            if (isset($items[$row['item_code']])) {
                $answers[$row['item_code']] = $row['answer'];
                $notes[$row['item_code']] = $row['note'];
            }
        }
    } catch (mysqli_sql_exception $e) {
        app_log(
            'Security audit results could not be loaded.',
            'ERROR',
            'security.audit.load_failed'
        );
        $storageUnavailable = true;
    }
}

$reviewedCount = 0;
$score = 0.0;

foreach ($items as $code => $item) {
    $answer = $answers[$code] ?? null;

    if ($answer === 'yes' || $answer === 'no') {
        $reviewedCount++;
    }

    if ($answer === 'yes') {
        $score += $item['weight'];
    }
}

$maximumScore = array_sum(array_column($items, 'weight'));
$scorePercent = $maximumScore > 0
    ? ($score / $maximumScore) * 100
    : 0;

render_header('Pemeriksaan Keamanan', 'security_audit', $success, $error);

?>

<section class="panel audit-summary">
    <div class="panel-head">
        <div>
            <h2>Checklist H, I &amp; J</h2>
            <div class="muted">
                Bobot H, I, dan J masing-masing 10 poin. Status awal tetap
                Belum Diperiksa sampai penilai memilih Yes atau No.
            </div>
        </div>
    </div>

    <?php if ($storageUnavailable): ?>
        <div class="error">
            Tabel hasil pemeriksaan belum tersedia. Terapkan migrasi
            <code>20261125_security_audit_checks.sql</code> sebelum menyimpan.
        </div>
    <?php endif; ?>

    <div class="audit-score-grid">
        <div class="stat">
            <div class="stat-top">Nilai berbobot</div>
            <div class="stat-number">
                <span id="audit-score"><?= number_format($score, 2) ?></span>
                <small>/ <?= number_format($maximumScore, 2) ?></small>
            </div>
            <div class="stat-note">
                <span id="audit-percent"><?= number_format($scorePercent, 1) ?></span>% dari total bobot
            </div>
        </div>
        <div class="stat">
            <div class="stat-top">Sudah diperiksa</div>
            <div class="stat-number">
                <span id="audit-reviewed"><?= $reviewedCount ?></span>
                <small>/ <?= count($items) ?></small>
            </div>
            <div class="stat-note">Jawaban Yes dan No yang dipilih</div>
        </div>
    </div>

    <div class="audit-legend">
        <span><b class="audit-dot yes"></b> Yes = bobot item</span>
        <span><b class="audit-dot no"></b> No = 0 poin</span>
        <span><b class="audit-dot pending"></b> Kosong = Belum Diperiksa</span>
    </div>
</section>

<form method="POST" action="security_audit.php" class="audit-form">
    <?= csrf_field() ?>

    <?php foreach (security_audit_sections() as $sectionCode => $section): ?>
        <section class="panel">
            <div class="panel-head audit-section-head">
                <div>
                    <h2><?= e($sectionCode . '. ' . $section['title']) ?></h2>
                    <div class="muted">
                        Bobot bagian: <?= number_format($section['weight'], 0) ?> poin
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Item Pemeriksaan</th>
                            <th>Bobot</th>
                            <th>Yes</th>
                            <th>No</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNumber = 1; ?>
                        <?php foreach ($section['items'] as $code => $item): ?>
                            <?php
                            $definition = $items[$code];
                            $answer = $answers[$code] ?? null;
                            $note = $notes[$code] ?? '';
                            ?>
                            <tr>
                                <td><?= $rowNumber++ ?></td>
                                <td>
                                    <strong><?= e($code . ' — ' . $item['title']) ?></strong>
                                    <div class="audit-detail"><?= e($item['detail']) ?></div>
                                    <div class="audit-pending" data-pending="<?= e($code) ?>">
                                        <?= $answer === null ? 'Belum Diperiksa' : '' ?>
                                    </div>
                                </td>
                                <td data-weight="<?= e(number_format($definition['weight'], 6, '.', '')) ?>">
                                    <?= number_format($definition['weight'], 2) ?> poin
                                </td>
                                <td>
                                    <label class="audit-choice" for="answer-<?= e($code) ?>-yes">
                                        <input
                                            id="answer-<?= e($code) ?>-yes"
                                            type="checkbox"
                                            name="answers[<?= e($code) ?>][yes]"
                                            value="1"
                                            data-audit-answer="<?= e($code) ?>"
                                            data-audit-value="yes"
                                            <?= $answer === 'yes' ? 'checked' : '' ?>
                                        >
                                        <span>Yes</span>
                                    </label>
                                </td>
                                <td>
                                    <label class="audit-choice" for="answer-<?= e($code) ?>-no">
                                        <input
                                            id="answer-<?= e($code) ?>-no"
                                            type="checkbox"
                                            name="answers[<?= e($code) ?>][no]"
                                            value="1"
                                            data-audit-answer="<?= e($code) ?>"
                                            data-audit-value="no"
                                            <?= $answer === 'no' ? 'checked' : '' ?>
                                        >
                                        <span>No</span>
                                    </label>
                                </td>
                                <td>
                                    <textarea
                                        name="notes[<?= e($code) ?>]"
                                        maxlength="500"
                                        rows="3"
                                        aria-label="Catatan <?= e($code) ?>"
                                        placeholder="Catatan atau bukti pemeriksaan (opsional)"
                                    ><?= e($note) ?></textarea>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="audit-actions">
        <span class="muted">Nilai dihitung ulang otomatis dan tersimpan setelah tombol ditekan.</span>
        <button type="submit" class="btn">Simpan Hasil Pemeriksaan</button>
    </div>
</form>

<?php render_footer(); ?>
