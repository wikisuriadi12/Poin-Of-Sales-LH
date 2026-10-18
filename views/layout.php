<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kasir Koperasi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=DM+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="assets/pos.css?v=1">
</head>
<body>
<header class="top">
  <div class="brand"><span class="mark" aria-hidden="true">LH</span><div><strong>Kasir Koperasi</strong><small><?= esc(cfg('merchant_name', 'Koperasi')) ?></small></div></div>
  <nav aria-label="Menu utama">
    <?php foreach (['kasir' => 'Kasir', 'riwayat' => 'Riwayat', 'stok' => 'Stok', 'santri' => 'Santri'] as $key => $label): if (can($key)): ?>
      <a <?= $page === $key ? 'aria-current="page"' : '' ?> href="?page=<?= esc($key) ?>"><?= esc($label) ?></a>
    <?php endif; endforeach; ?>
  </nav>
  <form method="post" class="who">
    <?= token_field() ?>
    <span class="muted"><?= esc($_SESSION['username']) ?> · <?= esc($_SESSION['role']) ?></span>
    <button name="action" value="logout" class="ghost">Keluar</button>
  </form>
</header>
<main class="page-<?= esc($page) ?>">
<?php if (isset($_SESSION['flash'])): ?><div class="notice" role="status"><?= esc($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php
try {
    require __DIR__ . '/' . $page . '.php';
} catch (Throwable $e) {
    error_log($e->getMessage());
    echo '<p class="error">Data belum dapat ditampilkan. Periksa konfigurasi database dan impor tabel sistem.</p>';
}
?>
</main>
</body>
</html>
