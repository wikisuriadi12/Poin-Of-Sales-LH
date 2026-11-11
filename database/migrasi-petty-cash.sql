-- Petty Cash (kas kecil) untuk belanja. Saldo dipotong saat belanja, termasuk belanja barang yang masuk stok.
-- Aman dijalankan ulang ('already exists' diabaikan).
CREATE TABLE IF NOT EXISTS petty_akun (
  id TINYINT UNSIGNED PRIMARY KEY,
  nama VARCHAR(60) NOT NULL,
  saldo BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO petty_akun (id, nama, saldo) VALUES (1, 'Petty Cash', 0);
CREATE TABLE IF NOT EXISTS petty_kas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  tipe ENUM('isi','belanja','belanja_stok','koreksi') NOT NULL,
  jumlah BIGINT NOT NULL,
  saldo_setelah BIGINT NOT NULL,
  kategori VARCHAR(40) NOT NULL DEFAULT '',
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  pemasok VARCHAR(100) NOT NULL DEFAULT '',
  batal TINYINT(1) NOT NULL DEFAULT 0,
  ref_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_petty_tgl (tanggal),
  KEY idx_petty_tipe (tipe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
