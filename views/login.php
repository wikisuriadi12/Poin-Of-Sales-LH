<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk · Kasir Koperasi</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/pos.css?v=1">
</head>
<body class="login">
<main>
  <form method="post" class="card narrow">
    <span class="mark" aria-hidden="true">LH</span>
    <h1>Kasir Koperasi</h1>
    <p class="muted"><?= esc(cfg('merchant_name', 'Koperasi')) ?></p>
    <?php if (isset($_SESSION['flash'])): ?><div class="notice" role="alert"><?= esc($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
    <?= token_field() ?>
    <label>Username<input name="username" autocomplete="username" required autofocus></label>
    <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
    <button class="primary" name="action" value="login">Masuk</button>
  </form>
</main>
</body>
</html>
