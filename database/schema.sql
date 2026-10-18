CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','kasir','gudang','pimpinan') NOT NULL DEFAULT 'kasir',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sku VARCHAR(60) NOT NULL UNIQUE,
 nama VARCHAR(180) NOT NULL,
 kategori VARCHAR(100) NOT NULL DEFAULT 'Lainnya',
 harga BIGINT UNSIGNED NOT NULL,
 stok INT NOT NULL DEFAULT 0,
 stok_min INT NOT NULL DEFAULT 0,
 pondok ENUM('semua','ikhwan','akhwat') NOT NULL DEFAULT 'semua',
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS santri (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nomor_induk VARCHAR(50) NOT NULL UNIQUE,
 nama VARCHAR(180) NOT NULL,
 pondok ENUM('ikhwan','akhwat') NOT NULL,
 saldo BIGINT NOT NULL DEFAULT 0,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nomor VARCHAR(40) NOT NULL UNIQUE,
 pondok ENUM('ikhwan','akhwat') NOT NULL,
 status ENUM('menunggu','lunas','batal','kedaluwarsa') NOT NULL,
 metode ENUM('tunai','qris','saldo') NOT NULL,
 subtotal BIGINT NOT NULL,
 diskon BIGINT NOT NULL DEFAULT 0,
 total BIGINT NOT NULL,
 santri_id BIGINT UNSIGNED NULL,
 uang_diterima BIGINT NULL,
 kembalian BIGINT NULL,
 catatan VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 paid_at DATETIME NULL,
 CONSTRAINT fk_orders_user FOREIGN KEY (created_by) REFERENCES users(id),
 CONSTRAINT fk_orders_santri FOREIGN KEY (santri_id) REFERENCES santri(id),
 INDEX idx_orders_created (created_at),
 INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 nama VARCHAR(180) NOT NULL,
 harga BIGINT NOT NULL,
 qty INT NOT NULL,
 subtotal BIGINT NOT NULL,
 CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id),
 CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id),
 INDEX idx_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 perubahan INT NOT NULL,
 alasan ENUM('penjualan','restok','retur','koreksi') NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 catatan VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_moves_product FOREIGN KEY (product_id) REFERENCES products(id),
 INDEX idx_moves_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS santri_ledger (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 santri_id BIGINT UNSIGNED NOT NULL,
 perubahan BIGINT NOT NULL,
 jenis ENUM('topup','belanja','koreksi') NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 catatan VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_ledger_santri FOREIGN KEY (santri_id) REFERENCES santri(id),
 INDEX idx_ledger_santri (santri_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(20) NOT NULL,
 provider_ref VARCHAR(100) NULL UNIQUE,
 qr_string TEXT NULL,
 status ENUM('menunggu','lunas','gagal','kedaluwarsa') NOT NULL,
 amount BIGINT NOT NULL,
 expires_at DATETIME NULL,
 paid_at DATETIME NULL,
 raw_payload TEXT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id),
 INDEX idx_payments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
