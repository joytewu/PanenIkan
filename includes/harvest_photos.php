<?php

require_once __DIR__ . '/../config/app.php';
app_deny_direct_access(__FILE__);

const HARVEST_PHOTO_MAX_BYTES = 5242880;

function harvest_photo_directory(): string {
    return dirname(__DIR__, 2) .
        DIRECTORY_SEPARATOR . 'panenikan-private' .
        DIRECTORY_SEPARATOR . 'uploads' .
        DIRECTORY_SEPARATOR . 'panen';
}

function harvest_photo_types(): array {
    return [
        'image/jpeg' => [
            'extension' => 'jpg',
            'image_type' => IMAGETYPE_JPEG,
        ],
        'image/png' => [
            'extension' => 'png',
            'image_type' => IMAGETYPE_PNG,
        ],
        'image/webp' => [
            'extension' => 'webp',
            'image_type' => IMAGETYPE_WEBP,
        ],
    ];
}

function store_harvest_photo($upload): ?string {
    if ($upload === null) {
        return null;
    }

    if (!is_array($upload) || !isset($upload['error'], $upload['tmp_name'])) {
        throw new InvalidArgumentException('Data foto tidak valid.');
    }

    if (!is_int($upload['error']) || !is_string($upload['tmp_name'])) {
        throw new InvalidArgumentException('Data foto tidak valid.');
    }

    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        if ($upload['error'] === UPLOAD_ERR_INI_SIZE) {
            throw new InvalidArgumentException(
                'Ukuran foto melebihi batas upload yang dikonfigurasi di server.'
            );
        }

        if ($upload['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('Ukuran foto melebihi batas 5 MB.');
        }

        throw new RuntimeException('Upload foto gagal. Silakan coba kembali.');
    }

    $temporaryPath = $upload['tmp_name'];

    if (!is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('Sumber foto tidak valid.');
    }

    $fileSize = filesize($temporaryPath);

    if (
        $fileSize === false ||
        $fileSize < 1 ||
        $fileSize > HARVEST_PHOTO_MAX_BYTES
    ) {
        throw new InvalidArgumentException('Ukuran foto harus antara 1 byte dan 5 MB.');
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException('Validasi tipe foto tidak tersedia di server.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $allowedTypes = harvest_photo_types();

    if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
        throw new InvalidArgumentException(
            'Format foto harus JPEG, PNG, atau WebP.'
        );
    }

    $imageInfo = @getimagesize($temporaryPath);

    if (
        $imageInfo === false ||
        $imageInfo[2] !== $allowedTypes[$mimeType]['image_type'] ||
        $imageInfo[0] < 1 ||
        $imageInfo[1] < 1 ||
        $imageInfo[0] > 10000 ||
        $imageInfo[1] > 10000 ||
        $imageInfo[0] * $imageInfo[1] > 40000000
    ) {
        throw new InvalidArgumentException('Isi file bukan gambar yang valid.');
    }

    $directory = harvest_photo_directory();

    if (
        !is_dir($directory) &&
        !mkdir($directory, 0750, true) &&
        !is_dir($directory)
    ) {
        throw new RuntimeException('Folder penyimpanan foto belum disiapkan.');
    }

    if (!is_writable($directory)) {
        throw new RuntimeException('Folder penyimpanan foto tidak dapat ditulis.');
    }

    $filename = bin2hex(random_bytes(16)) .
        '.' . $allowedTypes[$mimeType]['extension'];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Foto gagal disimpan.');
    }

    if (DIRECTORY_SEPARATOR === '/' && !chmod($destination, 0640)) {
        unlink($destination);
        throw new RuntimeException('Izin file foto gagal diamankan.');
    }

    return $filename;
}

function harvest_photo_path($filename): ?string {
    if (
        !is_string($filename) ||
        preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename) !== 1
    ) {
        return null;
    }

    $directory = realpath(harvest_photo_directory());

    if ($directory === false) {
        return null;
    }

    $path = realpath($directory . DIRECTORY_SEPARATOR . $filename);

    if (
        $path === false ||
        dirname($path) !== $directory ||
        !is_file($path)
    ) {
        return null;
    }

    return $path;
}

function harvest_photo_mime($filename): ?string {
    if (!is_string($filename)) {
        return null;
    }

    $types = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    return $types[$extension] ?? null;
}

function delete_harvest_photo($filename): bool {
    if (
        !is_string($filename) ||
        preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename) !== 1
    ) {
        return false;
    }

    $path = harvest_photo_path($filename);

    if ($path === null) {
        return !file_exists(
            harvest_photo_directory() . DIRECTORY_SEPARATOR . $filename
        );
    }

    return unlink($path);
}

function discard_staged_harvest_photo(?string $filename): void {
    if ($filename !== null && !delete_harvest_photo($filename)) {
        app_log(
            'A staged harvest photo could not be removed after a failed operation.',
            'ERROR'
        );
    }
}
