<?php
// Salin menjadi config.php lalu isi. Jangan di-commit (sudah ada di .gitignore).
return [
    'db_host'  => 'localhost',
    'db_name'  => 'isi_nama_database',
    'db_user'  => 'isi_user_mysql',
    'db_pass'  => 'isi_password_mysql',
    'app_name' => 'LH Mart & Gudang - Yayasan Luqmanul Hakim',
    'timezone' => 'Asia/Jakarta',
    // Biarkan seperti ini bila config.php di server sudah berjalan. 'domain' kosong = semua dari satu alamat.
    'domain'    => 'yayasanluqmanulhakimmarelan.com',
    'subdomain' => ['pos' => 'poslh'],   // poslh.domain -> aplikasi ini
];
