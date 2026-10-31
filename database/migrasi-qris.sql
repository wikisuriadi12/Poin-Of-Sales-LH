-- Migrasi QRIS kasir: izinkan metode pembayaran qris pada penjualan.
-- Aman dijalankan ulang. Dijalankan otomatis oleh tools/install.php atau tempel di phpMyAdmin.
-- 'hutang' ikut disebut: file ini jalan sesudah migrasi-piutang.sql (urut abjad) dan tidak boleh menghapusnya.
ALTER TABLE penjualan MODIFY metode ENUM('tunai','dompet','qris','hutang') NOT NULL;
