<?php
/** @var string $title @var string $content @var array $cfg @var ?array $user @var array $flash @var string $lembaga @var bool|string $public */
$cur = (string) ($_GET['p'] ?? 'pos');
$asset = static fn(string $f): string => 'assets/' . $f . '?v=' . (@filemtime(__DIR__ . '/../assets/' . $f) ?: 8);
$appName = $cfg['app_name'] ?? 'SPP & Data Santri';
$showApp = $user && $public === false;
$sis = $showApp ? Auth::sistem() : null;
$logo = '<span class="logo">' . icon('book', 20) . '</span>';
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f766e">
<title><?= e($title) ?> - <?= e($appName) ?></title>
<script>try{var t=localStorage.getItem('theme');if(!t&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="stylesheet" href="<?= e($asset('app.css')) ?>">
<?php if ($user): ?><link rel="stylesheet" href="<?= e($asset('topnav.css')) ?>"><?php endif; ?>
</head>
<body class="<?= $showApp ? 'is-app' : 'is-pub' ?><?= $sis ? ' sys-' . e($sis) : '' ?>">
<?php if ($flash): ?>
<div id="toasts" role="status" aria-live="polite">
  <?php foreach ($flash as [$t, $m]): ?>
    <div class="toast <?= e($t) ?>"><?= icon($t === 'ok' ? 'check' : 'alert', 18) ?><div><?= e($m) ?></div><button class="x" data-close aria-label="Tutup"><?= icon('x', 16) ?></button></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($showApp): ?>
<?= topnav($cur) ?>
<main class="content"><?= $content ?></main>
<?php elseif ($public === 'bare'): ?>
<?= $content ?>
<?php else: ?>
<header class="pub-top">
  <a class="brand" href="?p=login"><?= $logo ?><div><b><?= e($lembaga) ?></b><small>LH Mart &amp; Gudang</small></div></a>
  <nav>
    <a href="?p=<?= $user ? 'pos' : 'login' ?>"><?= $user ? 'Kasir' : 'Masuk petugas' ?></a>
    <button class="ibtn" data-theme-toggle aria-label="Mode gelap/terang"><?= icon('moon', 18) ?></button>
  </nav>
</header>
<main class="pub"><?= $content ?></main>
<?php endif; ?>
<script src="<?= e($asset('app.js')) ?>" defer></script>
</body>
</html>
