<?php
declare(strict_types=1);

function str_post(string $key): string { $v = $_POST[$key] ?? null; return is_string($v) ? trim($v) : ''; }
function home_page(): string { foreach (['kasir', 'riwayat', 'stok', 'santri'] as $p) if (can($p)) return $p; return 'kasir'; }

/** Tangani semua form POST di index.php. Selalu berakhir dengan redirect. */
function handle_post(bool $loggedIn): never {
    if (!csrf_valid(str_post('csrf'))) { http_response_code(419); exit('Form kedaluwarsa. Muat ulang halaman.'); }
    $action = str_post('action');
    $target = 'kasir';
    try {
        if ($action === 'login') {
            $target = 'login';
            $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            attempt_login(str_post('username'), $pw);
            redirect_to(home_page());
        }
        if (!$loggedIn) { http_response_code(401); exit('Login diperlukan.'); }
        if ($action === 'logout') { $_SESSION = []; session_destroy(); redirect_to('kasir'); }

        $uid = (int)$_SESSION['user_id'];
        if ($action === 'cancel_order') {
            require_can('kasir'); $target = 'riwayat';
            cancel_order(str_post('nomor'));
        } elseif ($action === 'restock') {
            require_can('stok_tulis'); $target = 'stok';
            restock(to_int(str_post('product_id'), 1, PHP_INT_MAX, 'Barang'), to_int(str_post('qty'), 1, 1000000, 'Jumlah restok'), text_field(str_post('catatan'), 255, 'Catatan', false), $uid);
        } elseif ($action === 'save_product') {
            require_can('stok_tulis'); $target = 'stok';
            save_product($_POST, $uid);
        } elseif ($action === 'add_santri') {
            require_can('santri_tulis'); $target = 'santri';
            add_santri($_POST);
        } elseif ($action === 'topup') {
            require_can('santri_tulis'); $target = 'santri';
            topup_santri(to_int(str_post('santri_id'), 1, PHP_INT_MAX, 'Santri'), to_int(str_post('nominal'), 1, 100000000, 'Nominal top-up'), text_field(str_post('catatan'), 255, 'Catatan', false), $uid);
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }
        $_SESSION['flash'] = 'Berhasil disimpan.';
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['flash'] = $e->getCode() === '23000' || $e->getCode() === 23000 ? 'SKU atau nomor induk sudah digunakan.' : 'Penyimpanan gagal. Hubungi administrator.';
    } catch (RuntimeException $e) {
        $_SESSION['flash'] = $e->getMessage();
    }
    redirect_to($target);
}
