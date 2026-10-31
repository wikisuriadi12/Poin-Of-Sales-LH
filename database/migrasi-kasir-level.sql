-- Kasir Level 2 (kasir2): jual dan laporan penjualan saja.
ALTER TABLE users MODIFY role ENUM('admin','tu','bendahara','pimpinan','kasir','kasir2','gudang','ustadz','pembina') NOT NULL;
