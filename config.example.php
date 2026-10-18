<?php
declare(strict_types=1);
// Salin menjadi config.php lalu isi. Jangan commit config.php.
return [
  'db_host' => 'localhost',
  'db_name' => 'ISI_NAMA_DATABASE',
  'db_user' => 'ISI_USER_DATABASE',
  'db_pass' => 'ISI_PASSWORD_DATABASE',

  'merchant_name' => 'Koperasi Luqmanul Hakim',

  // 'mock' = simulasi tanpa uang sungguhan (untuk uji coba).
  // 'bni'  = integrasi BNI; aktifkan hanya setelah kelas BniQris di src/qris.php dilengkapi.
  'qris_provider' => 'mock',
  'qris_expiry_minutes' => 5,

  // Rahasia untuk memverifikasi callback pembayaran. Isi dengan teks acak minimal 32 karakter.
  'webhook_secret' => 'ISI_RAHASIA_ACAK_PANJANG',

  // Kredensial BNI: diisi setelah mendaftar sebagai merchant. Jangan dibagikan.
  'bni_merchant_id' => '',
  'bni_api_key' => '',
  'bni_api_secret' => '',
  'bni_base_url' => '',
];
