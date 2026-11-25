<?php

require_once __DIR__ . '/../config/app.php';
app_deny_direct_access(__FILE__);

function security_audit_sections(): array {
    return [
        'H' => [
            'title' => 'Configuration & Deployment',
            'weight' => 10,
            'items' => [
                'H1' => [
                    'title' => 'Error detail tidak ditampilkan kepada pengguna',
                    'detail' => 'Konfigurasi production mematikan display_errors; periksa juga respons error aktual.',
                ],
                'H2' => [
                    'title' => 'Debug mode dimatikan di production',
                    'detail' => 'Nilai PANENIKAN_ENV production dan konfigurasi runtime belum dapat dibuktikan dari source saja.',
                ],
                'H3' => [
                    'title' => 'File konfigurasi dan .env terlindungi',
                    'detail' => 'Aturan Apache menolak akses ke dotfiles dan folder config; verifikasi AllowOverride di server.',
                ],
                'H4' => [
                    'title' => 'Permission folder menggunakan pengaturan aman',
                    'detail' => 'Folder private sebaiknya 0750, konfigurasi 0640, dan file sensitif 0640 atau lebih ketat.',
                ],
                'H5' => [
                    'title' => 'Directory listing dinonaktifkan',
                    'detail' => 'Root .htaccess memuat Options -Indexes; pastikan aturan tersebut aktif di Apache.',
                ],
                'H6' => [
                    'title' => 'Fungsi eksekusi perintah PHP dibatasi',
                        'detail' => 'Source aplikasi tidak memakai fungsi proses shell; terapkan php-production.ini dan verifikasi konfigurasi runtime.',
                ],
            ],
        ],
        'I' => [
            'title' => 'Security',
            'weight' => 10,
            'items' => [
                'I1' => [
                    'title' => 'Penanganan HTTP 403 dan 404',
                    'detail' => 'Aplikasi dan Apache menyediakan halaman generik; uji kode status untuk route terlarang dan tidak ditemukan.',
                ],
                'I2' => [
                    'title' => 'Port SSH 22 telah diganti',
                    'detail' => 'Perlu memeriksa sshd_config dan port yang benar-benar listen di server.',
                ],
                'I3' => [
                    'title' => 'Firewall UFW/iptables aktif',
                    'detail' => 'Periksa status firewall dan aturan yang mengizinkan hanya port layanan yang dibutuhkan.',
                ],
                'I4' => [
                    'title' => 'Akun root database dinonaktifkan atau dibatasi',
                    'detail' => 'Periksa akun MySQL yang tersedia dan kebijakan autentikasi root.',
                ],
                'I5' => [
                    'title' => 'Hak akses user database dibatasi',
                    'detail' => 'Periksa SHOW GRANTS untuk akun aplikasi; hindari hak global atau administratif.',
                ],
                'I6' => [
                    'title' => 'Fail2Ban aktif',
                    'detail' => 'Periksa status service, jail SSH/Apache, dan log ban di server.',
                ],
                'I7' => [
                    'title' => 'HTTP Security Headers memperoleh nilai A+',
                    'detail' => 'Header dasar diterapkan di source; nilai A+ hanya dapat dipastikan dengan pemindaian domain production.',
                ],
                'I8' => [
                    'title' => 'SSH menggunakan autentikasi key-based',
                    'detail' => 'Periksa konfigurasi sshd dan uji login key sebelum menonaktifkan password.',
                ],
            ],
        ],
        'J' => [
            'title' => 'Backup & Recovery',
            'weight' => 10,
            'items' => [
                'J1' => [
                    'title' => 'Backup database dan data penting berjalan rutin',
                    'detail' => 'Script dan template cron tersedia; pastikan jadwal aktif serta mencakup database dan uploads.',
                ],
                'J2' => [
                    'title' => 'Pemulihan backup telah diuji',
                    'detail' => 'Pulihkan ke database uji terisolasi dan verifikasi tabel, jumlah data, serta berkas uploads.',
                ],
                'J3' => [
                    'title' => 'Salinan backup disimpan terpisah dari server utama',
                    'detail' => 'Konfigurasikan target rsync jarak jauh berbasis SSH dan pastikan hasil salinan dapat dibaca.',
                ],
            ],
        ],
    ];
}

function security_audit_items(): array {
    $items = [];

    foreach (security_audit_sections() as $sectionCode => $section) {
        $count = count($section['items']);
        $weight = $section['weight'] / $count;

        foreach ($section['items'] as $code => $item) {
            $items[$code] = $item + [
                'section' => $sectionCode,
                'section_title' => $section['title'],
                'weight' => $weight,
            ];
        }
    }

    return $items;
}
