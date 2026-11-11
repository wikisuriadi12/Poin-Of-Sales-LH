<?php
/** Petty Cash (kas kecil): satu kas, saldo tidak boleh minus. Positif = uang masuk, negatif = uang keluar. */
final class Petty
{
    public const KATEGORI = ['Belanja stok' => 'Belanja stok (barang dagang)', 'ATK' => 'ATK / alat tulis', 'Kebersihan' => 'Kebersihan', 'Operasional' => 'Operasional toko', 'Transport' => 'Transport / ongkir', 'Lainnya' => 'Lainnya'];

    public static function saldo(): int
    {
        $r = Db::one('SELECT saldo FROM petty_akun WHERE id = 1');
        if (!$r) { Db::exec("INSERT INTO petty_akun (id, nama, saldo) VALUES (1, 'Petty Cash', 0)"); return 0; }
        return (int) $r['saldo'];
    }

    /** Dalam transaksi: ubah saldo (kunci baris) dan catat satu baris kas. */
    public static function ubah(int $delta, string $tipe, string $kategori, string $ket, string $pemasok, ?int $userId, ?int $ref = null): int
    {
        if ($delta === 0) { throw new InvalidArgumentException('Jumlah tidak boleh nol.'); }
        self::saldo();
        $a = Db::one('SELECT saldo FROM petty_akun WHERE id = 1' . Db::forUpdate());
        $baru = (int) $a['saldo'] + $delta;
        if ($baru < 0) { throw new InvalidArgumentException('Saldo Petty Cash tidak cukup (sisa ' . rp($a['saldo']) . ', dibutuhkan ' . rp(-$delta) . '). Isi saldo dulu.'); }
        Db::exec('UPDATE petty_akun SET saldo = ? WHERE id = 1', [$baru]);
        $id = Db::insert('INSERT INTO petty_kas (tanggal, tipe, jumlah, saldo_setelah, kategori, keterangan, pemasok, batal, ref_id, user_id, created_at) VALUES (?,?,?,?,?,?,?,0,?,?,?)',
            [date('Y-m-d'), $tipe, $delta, $baru, mb_substr($kategori, 0, 40), mb_substr($ket, 0, 255), mb_substr($pemasok, 0, 100), $ref, $userId, date('Y-m-d H:i:s')]);
        Db::audit($userId, $tipe, 'petty_kas', $id, ($delta > 0 ? '+' : '') . $delta);
        return $id;
    }

    public static function isi(int $jumlah, string $ket, ?int $userId): int
    {
        if ($jumlah < 1 || $jumlah > 1000000000) { throw new InvalidArgumentException('Jumlah isi saldo tidak valid.'); }
        return Db::transaction(fn() => self::ubah($jumlah, 'isi', '', trim($ket) !== '' ? $ket : 'Isi saldo Petty Cash', '', $userId));
    }

    /** Belanja bukan stok (ATK, kebersihan, dll). Belanja stok dicatat lewat Barang Masuk. */
    public static function belanja(int $jumlah, string $kategori, string $ket, string $pemasok, ?int $userId): int
    {
        if ($jumlah < 1 || $jumlah > 1000000000) { throw new InvalidArgumentException('Jumlah belanja tidak valid.'); }
        if (trim($ket) === '') { throw new InvalidArgumentException('Isi keterangan belanja (apa yang dibeli).'); }
        if (!isset(self::KATEGORI[$kategori]) || $kategori === 'Belanja stok') { $kategori = 'Lainnya'; }
        return Db::transaction(fn() => self::ubah(-$jumlah, 'belanja', $kategori, $ket, $pemasok, $userId));
    }

    /** Koreksi saldo (selisih hitung fisik), + atau -. Wajib beralasan. */
    public static function koreksi(int $delta, string $alasan, ?int $userId): int
    {
        if ($delta === 0 || abs($delta) > 1000000000) { throw new InvalidArgumentException('Jumlah koreksi tidak valid.'); }
        if (trim($alasan) === '') { throw new InvalidArgumentException('Alasan koreksi wajib diisi.'); }
        return Db::transaction(fn() => self::ubah($delta, 'koreksi', '', 'Koreksi: ' . $alasan, '', $userId));
    }

    /** Batalkan isi saldo atau belanja non-stok (dicatat sebagai lawan transaksinya). Belanja stok tidak bisa dibatalkan di sini. */
    public static function batalkan(int $id, ?int $userId): void
    {
        Db::transaction(function () use ($id, $userId) {
            $r = Db::one('SELECT * FROM petty_kas WHERE id = ?' . Db::forUpdate(), [$id]);
            if (!$r || (int) $r['batal']) { throw new InvalidArgumentException('Catatan tidak ditemukan atau sudah dibatalkan.'); }
            if (!in_array($r['tipe'], ['isi', 'belanja'], true)) { throw new InvalidArgumentException('Hanya isi saldo dan belanja non-stok yang bisa dibatalkan. Belanja stok: koreksi lewat Stok.'); }
            self::ubah(-(int) $r['jumlah'], 'koreksi', (string) $r['kategori'], 'Batal #' . $id . ': ' . $r['keterangan'], (string) $r['pemasok'], $userId, $id);
            Db::exec('UPDATE petty_kas SET batal = 1 WHERE id = ?', [$id]);
        });
    }

    public static function daftar(string $dari, string $sampai, string $tipe = ''): array
    {
        $w = 'k.tanggal >= ? AND k.tanggal <= ?'; $p = [$dari, $sampai];
        if ($tipe !== '') { $w .= ' AND k.tipe = ?'; $p[] = $tipe; }
        return Db::all("SELECT k.*, u.nama AS petugas FROM petty_kas k LEFT JOIN users u ON u.id = k.user_id WHERE $w ORDER BY k.id DESC LIMIT 500", $p);
    }

    /** Ringkasan periode: uang masuk, belanja stok, belanja lain, koreksi, dan rincian per kategori. */
    public static function ringkasan(string $dari, string $sampai): array
    {
        $r = ['isi' => 0, 'belanja_stok' => 0, 'belanja' => 0, 'koreksi' => 0, 'kategori' => []];
        foreach (Db::all('SELECT tipe, kategori, SUM(jumlah) AS j FROM petty_kas WHERE tanggal >= ? AND tanggal <= ? GROUP BY tipe, kategori', [$dari, $sampai]) as $x) {
            $r[$x['tipe']] += (int) $x['j'];
            if ($x['tipe'] === 'belanja' || $x['tipe'] === 'belanja_stok') { $k = $x['kategori'] ?: 'Lainnya'; $r['kategori'][$k] = ($r['kategori'][$k] ?? 0) + (int) $x['j']; }
        }
        return $r;
    }
}
