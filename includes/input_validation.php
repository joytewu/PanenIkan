<?php

function request_text(
    array $source,
    string $key,
    string $label,
    int $maxLength,
    bool $required = false
): string {
    if (!array_key_exists($key, $source)) {
        if ($required) {
            throw new InvalidArgumentException($label . ' wajib diisi.');
        }

        return '';
    }

    if (!is_string($source[$key])) {
        throw new InvalidArgumentException($label . ' tidak valid.');
    }

    $value = trim($source[$key]);

    if ($required && $value === '') {
        throw new InvalidArgumentException($label . ' wajib diisi.');
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        throw new InvalidArgumentException($label . ' berisi karakter yang tidak valid.');
    }

    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException(
            $label . ' maksimal ' . $maxLength . ' karakter.'
        );
    }

    return $value;
}
