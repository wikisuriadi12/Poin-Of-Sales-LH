<?php
// Salin menjadi config.php lalu isi. Jangan di-commit (sudah ada di .gitignore).
return [
    'db_host'  => 'localhost',
    'db_name'  => 'isi_nama_database',
    'db_user'  => 'isi_user_mysql',
    'db_pass'  => 'isi_password_mysql',
    'app_name' => 'LH Mart & Gudang - Yayasan Luqmanul Hakim',
    'timezone' => 'Asia/Jakarta',
    // Kunci enkripsi Server Key QRIS (acak, min. 32 karakter, jangan diubah setelah dipakai).
    // Buat: php -r "echo bin2hex(random_bytes(32));"  Setelah menambah/mengganti, isi ulang Server Key di menu Akun.
    'qris_key' => '',
    // Biarkan seperti ini bila config.php di server sudah berjalan. 'domain' kosong = semua dari satu alamat.
    'domain'    => 'yayasanluqmanulhakimmarelan.com',
    'subdomain' => ['pos' => 'poslh'],   // poslh.domain -> aplikasi ini
];
