<?php
// Data contoh untuk uji tampilan dan pratinjau (BUKAN data asli). Gunakan setelah test_db().
declare(strict_types=1);

function seed_demo(): void
{
    Auth::createUser('wiki', 'Wiki Suryadi', 'admin', 'semua', 'rahasia12345', null);
    Auth::createUser('bendahara1', 'Siti Bendahara', 'bendahara', 'semua', 'rahasia12345', null);
    Auth::createUser('tu.ikhwan', 'Ustadz Ahmad (TU)', 'tu', 'ikhwan', 'rahasia12345', null);

    $data = [
        ['Muhammad Fatih', 'L', 'Ula', '3A', 'Abdullah Rahman', '0812-3456-7801', 0],
        ['Ahmad Zaki', 'L', 'Ula', '5B', 'Syamsul Arifin', '0813-2211-4502', 50000],
        ['Umar Hakim', 'L', 'Wustha', '2A', 'Hasan Basri', '0821-7788-9903', 0],
        ['Yusuf Maulana', 'L', 'Wustha', '3B', 'Ridwan Lubis', '0852-1100-2204', 0],
        ['Ibrahim Nasution', 'L', 'Ulya', '1A', 'Zulkifli Nasution', '0811-6070-1105', 100000],
        ['Salman Al-Farisi', 'L', 'TKQ', 'B1', 'Khairul Anwar', '0822-9090-3306', 0],
        ['Hamzah Siregar', 'L', 'Ulya', '2A', 'Parlindungan Siregar', '0813-7000-4407', 0],
        ['Aisyah Humaira', 'P', 'Ula', '4A', 'Ahmad Dalimunthe', '0812-5050-6608', 0],
        ['Khadijah Zahra', 'P', 'Ula', '2B', 'Marwan Harahap', '0821-4040-7709', 0],
        ['Fatimah Azzahra', 'P', 'Wustha', '1A', 'Irwan Tanjung', '0852-3030-8810', 75000],
        ['Maryam Salsabila', 'P', 'Wustha', '3A', 'Syahrul Pane', '0811-2020-9911', 0],
        ['Hafshah Nurul', 'P', 'Ulya', '3A', 'Budiman Sinaga', '0813-1010-1012', 0],
        ['Ummu Kultsum', 'P', 'TKQ', 'A2', 'Rahmat Hidayat', '0822-8080-1113', 0],
        ['Zainab Rahmah', 'P', 'Ulya', '1B', 'Samsuddin Lubis', '0812-9999-1214', 0],
    ];
    foreach ($data as [$n, $jk, $j, $k, $w, $hp, $pot]) {
        [$c] = Santri::validate(['nama' => $n, 'jenis_kelamin' => $jk, 'jenjang' => $j, 'kelas' => $k, 'nama_wali' => $w, 'hp_wali' => $hp,
            'hubungan_wali' => 'Ayah', 'tahun_masuk' => (string) (2022 + crc32($n) % 5), 'status' => 'aktif', 'potongan_spp' => (string) $pot]);
        Santri::create($c, 1);
    }
    foreach ([['Bilal Ramadhan', 'L', 'Ula', 'Fauzan Ramadhan'], ['Nabila Putri', 'P', 'TKQ', 'Dewi Lestari'], ['Rizky Pratama', 'L', 'Wustha', 'Agus Pratama']] as [$n, $jk, $j, $w]) {
        Santri::daftarOnline(['nama' => $n, 'jenis_kelamin' => $jk, 'jenjang' => $j, 'nama_wali' => $w, 'hp_wali' => '0812-7000-' . random_int(1000, 9999),
            'asal_sekolah' => 'SD Negeri 1 Marelan', 'tempat_lahir' => 'Medan', 'tanggal_lahir' => '2015-03-14', 'alamat' => 'Jl. Marelan Raya No. 12']);
    }


    Auth::createUser('kasir1', 'Ustadzah Rina (Kasir)', 'kasir', 'akhwat', 'rahasia12345', null);
    Auth::createUser('gudang1', 'Pak Hadi (Gudang)', 'gudang', 'semua', 'rahasia12345', null);
    $brg = [
        ['BK-001', 'Buku tulis 38 lembar', 'Alat tulis', 'pcs', 3000, 4500, 10], ['PN-001', 'Pulpen hitam', 'Alat tulis', 'pcs', 2000, 3000, 12],
        ['SB-001', 'Sabun mandi', 'Perlengkapan mandi', 'pcs', 3500, 5000, 8], ['SP-001', 'Sampo sachet', 'Perlengkapan mandi', 'pcs', 500, 1000, 20],
        ['MK-001', 'Mie instan', 'Makanan', 'pcs', 2800, 3500, 15], ['MK-002', 'Roti isi', 'Makanan', 'pcs', 3000, 4000, 10],
        ['MN-001', 'Air mineral 600ml', 'Minuman', 'btl', 2500, 3500, 12], ['MN-002', 'Teh gelas', 'Minuman', 'pcs', 1200, 2000, 15],
        ['SR-001', 'Sarung', 'Pakaian', 'pcs', 65000, 85000, 3], ['PK-001', 'Peci hitam', 'Pakaian', 'pcs', 18000, 25000, 4],
    ];
    foreach ($brg as $i => [$kode, $nm, $kt, $st, $hb, $hj, $mn]) {
        [$c] = Gudang::validateBarang(['kode' => $kode, 'nama' => $nm, 'kategori' => $kt, 'satuan' => $st, 'harga_beli' => (string) $hb, 'harga_jual' => (string) $hj, 'stok_min' => (string) $mn]);
        $bid = Gudang::simpanBarang($c, null, 1);
        foreach (['ikhwan', 'akhwat'] as $u) {
            $awal = $hj > 20000 ? 8 : 60 + ($i * 7) % 25;
            Gudang::masuk($bid, $u, $awal, $hb, 'Toko Barokah', 'stok awal', 1, null);
        }
    }
    foreach ([8, 9, 10] as $sidD) { Pos::topup($sidD, 50000 + $sidD * 5000, 'titipan wali', 1, null); }
    Pos::topup(1, 75000, 'titipan wali', 1, null);
    $bid = static fn(string $k): int => (int) Db::val('SELECT id FROM barang WHERE kode = ?', [$k]);
    for ($d = 6; $d >= 0; $d--) {
        foreach (['ikhwan', 'akhwat'] as $ui => $u) {
            for ($n = 0; $n < 2 + ($d + $ui) % 3; $n++) {
                $items = [$bid(['BK-001', 'MK-001', 'MN-001', 'PN-001', 'MN-002'][($d + $n) % 5]) => 1 + $n % 3, $bid('MK-002') => 1];
                $pid = Pos::jual($u, null, $items, 'tunai', 100000, 3, null);
                $tgl = date('Y-m-d', strtotime("-$d days"));
                Db::exec('UPDATE penjualan SET tanggal = ? WHERE id = ?', [$tgl, $pid]);
            }
        }
    }
    Pos::jual('akhwat', 8, [$bid('SB-001') => 1, $bid('MN-001') => 2], 'dompet', 0, 3, null);
    $x = Pos::jual('ikhwan', null, [$bid('PN-001') => 3], 'tunai', 10000, 3, null);
    Pos::batalkan($x, 'salah input barang', 3, null);
    Gudang::opname($bid('SB-001'), 'ikhwan', 4, 'opname', 1, null);
    Gudang::opname($bid('MK-002'), 'akhwat', 5, 'opname', 1, null);
    Gudang::opname($bid('PK-001'), 'akhwat', 0, 'opname', 1, null);
    Settings::set('alamat', 'Jl. Marelan Raya, Medan Marelan, Sumatera Utara');
    Settings::set('telepon', '0812-0000-1234');
    Settings::set('wa_admin', '0812-0000-1234');
    Settings::set('rekening', "BSI 7123456789 a.n. Yayasan Luqmanul Hakim\nBank Sumut 0123456789 a.n. Yayasan Luqmanul Hakim");
    Settings::set('biaya_pendaftaran', '250000');
}
