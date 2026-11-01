<?php
declare(strict_types=1);

/**
 * Top up e-money (koperasi sebagai agen). Kasir mengirim saldo lewat aplikasi agen, lalu mencatatnya di sini.
 * Setiap top up berhasil = satu baris penjualan (jenis 'topup') + rincian di emoney_trx + potongan saldo deposit agen.
 */
final class Emoney
{
    public const JENIS = ['dompet' => 'Dompet digital', 'kartu' => 'Kartu e-money / e-Toll'];
    /** Cara bayar top up (kunci penjualan.metode). Bon tidak boleh karena uang deposit langsung keluar. */
    public const METODE = ['tunai' => 'Cash', 'qris' => 'QRIS / Transfer'];

    public static function sumber(bool $hanyaAktif = false): array
    {
        return Db::all('SELECT * FROM emoney_sumber' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY nama');
    }

    public static function produk(bool $hanyaAktif = false): array
    {
        return Db::all('SELECT p.*, s.nama AS sumber_nama, s.saldo AS sumber_saldo FROM emoney_produk p JOIN emoney_sumber s ON s.id = p.sumber_id'
            . ($hanyaAktif ? ' WHERE p.aktif = 1 AND s.aktif = 1' : '') . ' ORDER BY p.nama');
    }

    public static function findProduk(int $id): ?array
    {
        return Db::one('SELECT * FROM emoney_produk WHERE id = ?', [$id]);
    }

    public static function simpanSumber(string $nama, int $saldoMin, ?int $id, ?int $userId): int
    {
        $nama = mb_substr(trim($nama), 0, 80);
        if ($nama === '') { throw new InvalidArgumentException('Nama saldo deposit wajib diisi.'); }
        if ($id) {
            Db::exec('UPDATE emoney_sumber SET nama = ?, saldo_min = ? WHERE id = ?', [$nama, max(0, $saldoMin), $id]);
        } else {
            $id = Db::insert('INSERT INTO emoney_sumber (nama, saldo, saldo_min, aktif, created_at) VALUES (?,0,?,1,?)', [$nama, max(0, $saldoMin), date('Y-m-d H:i:s')]);
        }
        Db::audit($userId, 'simpan', 'emoney_sumber', $id, $nama);
        return $id;
    }

    /** @param array<string,mixed> $in */
    public static function simpanProduk(array $in, ?int $id, ?int $userId): int
    {
        $num = static fn(string $k): int => (int) preg_replace('/\D/', '', (string) ($in[$k] ?? '0'));
        $nama = mb_substr(trim((string) ($in['nama'] ?? '')), 0, 60);
        $jenis = (string) ($in['jenis'] ?? 'dompet');
        $sumberId = $num('sumber_id');
        $min = $num('nominal_min'); $maks = $num('nominal_maks');
        if ($nama === '') { throw new InvalidArgumentException('Nama produk wajib diisi.'); }
        if (!isset(self::JENIS[$jenis])) { throw new InvalidArgumentException('Jenis produk tidak valid.'); }
        if (!Db::val('SELECT id FROM emoney_sumber WHERE id = ?', [$sumberId])) { throw new InvalidArgumentException('Pilih saldo deposit yang dipakai produk ini.'); }
        if ($min < 1 || $maks < $min) { throw new InvalidArgumentException('Nominal minimal/maksimal tidak valid.'); }
        $v = [$nama, $jenis, $sumberId, $num('biaya_admin'), $num('biaya_modal'), $min, $maks, !empty($in['aktif']) ? 1 : 0];
        if ($id) {
            Db::exec('UPDATE emoney_produk SET nama=?, jenis=?, sumber_id=?, biaya_admin=?, biaya_modal=?, nominal_min=?, nominal_maks=?, aktif=? WHERE id=?', array_merge($v, [$id]));
        } else {
            $id = Db::insert('INSERT INTO emoney_produk (nama, jenis, sumber_id, biaya_admin, biaya_modal, nominal_min, nominal_maks, aktif, created_at) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($v, [date('Y-m-d H:i:s')]));
        }
        Db::audit($userId, 'simpan', 'emoney_produk', $id, $nama);
        return $id;
    }

    /** Dalam transaksi: ubah saldo deposit dan catat mutasi. Saldo tidak boleh minus. */
    private static function ubahSaldo(int $sumberId, int $delta, string $tipe, ?int $ref, string $catatan, ?int $userId): int
    {
        $s = Db::one('SELECT id, nama, saldo FROM emoney_sumber WHERE id = ?' . Db::forUpdate(), [$sumberId]);
        if (!$s) { throw new InvalidArgumentException('Saldo deposit tidak ditemukan.'); }
        $baru = (int) $s['saldo'] + $delta;
        if ($baru < 0) { throw new InvalidArgumentException('Saldo deposit ' . $s['nama'] . ' tidak cukup (sisa ' . rp($s['saldo']) . '). Isi deposit dulu.'); }
        Db::exec('UPDATE emoney_sumber SET saldo = ? WHERE id = ?', [$baru, $sumberId]);
        Db::exec('INSERT INTO emoney_mutasi (sumber_id, tipe, jumlah, saldo_setelah, ref_id, catatan, user_id, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$sumberId, $tipe, $delta, $baru, $ref, mb_substr($catatan, 0, 255), $userId, date('Y-m-d H:i:s')]);
        return $baru;
    }

    /** Isi deposit (jumlah > 0) atau koreksi (+/-) saldo agen. */
    public static function ubahDeposit(int $sumberId, int $jumlah, string $tipe, string $catatan, ?int $userId): int
    {
        if (!in_array($tipe, ['isi', 'koreksi'], true)) { throw new InvalidArgumentException('Jenis perubahan saldo tidak valid.'); }
        if ($jumlah === 0 || ($tipe === 'isi' && $jumlah < 0)) { throw new InvalidArgumentException('Nominal tidak valid.'); }
        if ($tipe === 'koreksi' && trim($catatan) === '') { throw new InvalidArgumentException('Alasan koreksi wajib diisi.'); }
        return Db::transaction(function () use ($sumberId, $jumlah, $tipe, $catatan, $userId) {
            $baru = self::ubahSaldo($sumberId, $jumlah, $tipe, null, $catatan !== '' ? $catatan : 'Isi deposit', $userId);
            Db::audit($userId, $tipe . '_deposit', 'emoney_sumber', $sumberId, ($jumlah > 0 ? '+' : '') . $jumlah);
            return $baru;
        });
    }

    /** Harga yang dibayar pelanggan dan modal yang terpotong dari deposit. @return array{bayar:int,modal:int} */
    public static function hitung(array $produk, int $nominal): array
    {
        return ['bayar' => $nominal + (int) $produk['biaya_admin'], 'modal' => $nominal + (int) $produk['biaya_modal']];
    }

    /** Catat top up berhasil. @return int id penjualan (untuk struk) */
    public static function topup(int $produkId, string $noTujuan, string $nama, int $nominal, string $metode, string $ref, ?int $userId): int
    {
        $pr = self::findProduk($produkId);
        if (!$pr || !(int) $pr['aktif']) { throw new InvalidArgumentException('Pilih produk e-money.'); }
        $noTujuan = preg_replace('/[^0-9A-Za-z]/', '', $noTujuan);
        if (strlen($noTujuan) < 6 || strlen($noTujuan) > 40) { throw new InvalidArgumentException('Nomor tujuan (no. HP / no. kartu) tidak valid.'); }
        if ($nominal < (int) $pr['nominal_min'] || $nominal > (int) $pr['nominal_maks']) {
            throw new InvalidArgumentException('Nominal ' . $pr['nama'] . ' antara ' . rp($pr['nominal_min']) . ' dan ' . rp($pr['nominal_maks']) . '.');
        }
        if (!isset(self::METODE[$metode])) { throw new InvalidArgumentException('Cara bayar top up: cash atau QRIS/transfer.'); }
        $nama = mb_substr(trim($nama), 0, 80);
        $ref = mb_substr(trim($ref), 0, 80);
        $h = self::hitung($pr, $nominal);
        return Db::transaction(function () use ($pr, $noTujuan, $nama, $nominal, $metode, $ref, $userId, $h) {
            $pelanggan = mb_substr('Top up ' . $pr['nama'] . ': ' . $noTujuan . ($nama !== '' ? ' (' . $nama . ')' : ''), 0, 80);
            $now = date('Y-m-d H:i:s');
            $id = Db::insert('INSERT INTO penjualan (no_struk, tanggal, unit, santri_id, total, metode, dibayar, dibatalkan, user_id, created_at, pelanggan, jenis) VALUES (?,?,?,?,?,?,?,0,?,?,?,?)',
                ['', date('Y-m-d'), Pos::TOKO, null, $h['bayar'], $metode, $h['bayar'], $userId, $now, $pelanggan, 'topup']);
            $no = 'PJ-' . date('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            Db::exec('UPDATE penjualan SET no_struk = ? WHERE id = ?', [$no, $id]);
            Db::exec('INSERT INTO emoney_trx (penjualan_id, produk_id, sumber_id, no_tujuan, nama_pelanggan, nominal, biaya_admin, modal, ref_penyedia, user_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$id, $pr['id'], $pr['sumber_id'], $noTujuan, $nama, $nominal, (int) $pr['biaya_admin'], $h['modal'], $ref, $userId, $now]);
            self::ubahSaldo((int) $pr['sumber_id'], -$h['modal'], 'pakai', $id, $no . ' ' . $pr['nama'] . ' ' . $noTujuan, $userId);
            Db::audit($userId, 'topup', 'penjualan', $id, "$no {$pr['nama']} $noTujuan $nominal");
            return $id;
        });
    }

    /** Rincian top up untuk struk / laporan. */
    public static function trx(int $penjualanId): ?array
    {
        return Db::one('SELECT t.*, p.nama AS produk_nama FROM emoney_trx t JOIN emoney_produk p ON p.id = t.produk_id WHERE t.penjualan_id = ?', [$penjualanId]);
    }

    /** Dipanggil dari Pos::batalkan di dalam transaksi: modal dikembalikan ke deposit. */
    public static function batalkanPenjualan(int $penjualanId, ?int $userId): void
    {
        $t = Db::one('SELECT * FROM emoney_trx WHERE penjualan_id = ?', [$penjualanId]);
        if ($t) { self::ubahSaldo((int) $t['sumber_id'], (int) $t['modal'], 'kembali', $penjualanId, 'Batal top up ' . $t['no_tujuan'], $userId); }
    }

    public static function mutasi(int $limit = 50): array
    {
        return Db::all('SELECT m.*, s.nama AS sumber_nama, u.nama AS petugas FROM emoney_mutasi m JOIN emoney_sumber s ON s.id = m.sumber_id LEFT JOIN users u ON u.id = m.user_id ORDER BY m.id DESC LIMIT ' . (int) $limit);
    }

    /** Laporan top up (transaksi sah) pada rentang; $userId = hanya milik kasir itu. */
    public static function laporan(string $dari, string $sampai, ?int $userId = null): array
    {
        $a = [$dari, $sampai]; $w = '';
        if ($userId) { $w = ' AND j.user_id = ?'; $a[] = $userId; }
        return Db::all("SELECT t.*, p.nama AS produk_nama, j.no_struk, j.tanggal, j.metode, j.total, u.nama AS kasir FROM emoney_trx t JOIN penjualan j ON j.id = t.penjualan_id JOIN emoney_produk p ON p.id = t.produk_id LEFT JOIN users u ON u.id = j.user_id WHERE j.dibatalkan = 0 AND j.tanggal >= ? AND j.tanggal <= ?$w ORDER BY j.id DESC LIMIT 2000", $a);
    }

    /** Top up terakhir milik kasir hari ini (untuk halaman top up). */
    public static function hariIni(?int $userId, int $limit = 10): array
    {
        $a = [date('Y-m-d')]; $w = '';
        if ($userId) { $w = ' AND j.user_id = ?'; $a[] = $userId; }
        return Db::all("SELECT t.*, p.nama AS produk_nama, j.no_struk, j.total, j.metode, j.dibatalkan, j.created_at AS waktu FROM emoney_trx t JOIN penjualan j ON j.id = t.penjualan_id JOIN emoney_produk p ON p.id = t.produk_id WHERE j.tanggal = ?$w ORDER BY j.id DESC LIMIT " . (int) $limit, $a);
    }
}
