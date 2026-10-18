<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

/** Baca konfigurasi dari config.php; variabel lingkungan POS_* menimpa (dipakai untuk pengujian/CI). */
function cfg(string $key, mixed $default = null): mixed {
    static $c;
    if ($c === null) {
        $c = [];
        $file = dirname(__DIR__) . '/config.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) $c = $loaded;
        }
        foreach (['db_dsn', 'db_host', 'db_name', 'db_user', 'db_pass', 'qris_provider', 'qris_expiry_minutes', 'webhook_secret', 'merchant_name'] as $k) {
            $v = getenv('POS_' . strtoupper($k));
            if ($v !== false && $v !== '') $c[$k] = $v;
        }
    }
    return $c[$key] ?? $default;
}

function esc(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rupiah(int|string $v): string { return 'Rp ' . number_format((int)$v, 0, ',', '.'); }
function now(): string { return date('Y-m-d H:i:s'); }
function query(string $key, string $default = ''): string { return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : $default; }
function post(string $key): mixed { return $_POST[$key] ?? null; }

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $dsn = cfg('db_dsn');
        if (!is_string($dsn) || $dsn === '') {
            foreach (['db_host', 'db_name', 'db_user', 'db_pass'] as $k) {
                if (!is_string(cfg($k))) throw new RuntimeException('Database belum dikonfigurasi. Hubungi administrator.');
            }
            $dsn = 'mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4';
        }
        $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function is_mysql(): bool { return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'; }
/** Penguncian baris pada transaksi (MySQL). SQLite mengunci seluruh database sehingga tidak perlu. */
function lock_clause(): string { return is_mysql() ? ' FOR UPDATE' : ''; }
function sql(string $statement, array $params = []): PDOStatement { $q = db()->prepare($statement); $q->execute($params); return $q; }

/** Bilangan bulat ketat: int atau string digit. Rupiah selalu bilangan bulat. */
function to_int(mixed $v, int $min, int $max, string $label): int {
    if (is_string($v) && preg_match('/^(0|[1-9][0-9]{0,15})$/D', $v)) $v = (int)$v;
    if (!is_int($v) || $v < $min || $v > $max) throw new RuntimeException($label . ' tidak valid.');
    return $v;
}
function text_field(mixed $v, int $max, string $label, bool $required = true): string {
    $s = is_string($v) ? trim($v) : '';
    if (preg_match('//u', $s) !== 1 || ($required && $s === '') || preg_match_all('/./us', $s) > $max) {
        throw new RuntimeException($label . ' kosong atau terlalu panjang.');
    }
    return $s;
}

function valid_date(string $v): bool { $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v); return $d && $d->format('Y-m-d') === $v; }
function redirect_to(string $page): never { header('Location: ?page=' . rawurlencode($page)); exit; }

/* ---------- Sesi, CSRF, hak akses ---------- */
function start_session(): void {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
    session_start();
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function token_field(): string { return '<input type="hidden" name="csrf" value="' . esc(csrf()) . '">'; }
function csrf_valid(string $given): bool { return hash_equals(csrf(), $given); }

const ROLE_PERMS = [
    'kasir'        => ['admin', 'kasir'],
    'riwayat'      => ['admin', 'kasir', 'pimpinan'],
    'stok'         => ['admin', 'kasir', 'gudang', 'pimpinan'],
    'stok_tulis'   => ['admin', 'gudang'],
    'santri'       => ['admin', 'kasir', 'pimpinan'],
    'santri_tulis' => ['admin', 'kasir'],
];
function can(string $perm): bool { return in_array($_SESSION['role'] ?? '', ROLE_PERMS[$perm] ?? [], true); }
function require_can(string $perm): void { if (!can($perm)) { http_response_code(403); exit('Akses ditolak.'); } }

/** Muat pengguna dari sesi; batalkan sesi bila akun hilang atau password sudah diganti. */
function load_user(): bool {
    if (!isset($_SESSION['user_id'])) return false;
    $u = sql('SELECT id,username,role,password_hash FROM users WHERE id=?', [$_SESSION['user_id']])->fetch();
    $ver = $u ? hash('sha256', (string)$u['password_hash']) : '';
    if (!$u || !hash_equals((string)($_SESSION['auth_version'] ?? ''), $ver)) { $_SESSION = []; return false; }
    $_SESSION['role'] = $u['role'];
    $_SESSION['username'] = $u['username'];
    return true;
}
function attempt_login(string $username, string $password): void {
    if (($_SESSION['login_wait'] ?? 0) > time()) throw new RuntimeException('Terlalu banyak percobaan. Tunggu satu menit.');
    $u = sql('SELECT id,username,password_hash,role FROM users WHERE username=?', [$username])->fetch();
    if (!$u || !password_verify($password, (string)$u['password_hash'])) {
        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= 5) { $_SESSION['login_wait'] = time() + 60; $_SESSION['login_fails'] = 0; }
        throw new RuntimeException('Username atau password tidak cocok.');
    }
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int)$u['id'], 'username' => $u['username'], 'role' => $u['role'], 'auth_version' => hash('sha256', (string)$u['password_hash'])];
}

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
