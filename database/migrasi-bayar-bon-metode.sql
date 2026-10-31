-- Cara bayar bon pelanggan: cash (tunai) atau transfer. Pembayaran lama dianggap tunai.
-- Bila kolom sudah ada, galat 'Duplicate column' aman diabaikan.
ALTER TABLE piutang_bayar ADD COLUMN metode ENUM('tunai','transfer') NOT NULL DEFAULT 'tunai' AFTER tanggal;
