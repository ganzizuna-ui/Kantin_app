CREATE DATABASE IF NOT EXISTS kantin CHARACTER SET utf8mb4;
USE kantin;

CREATE TABLE menu (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(80) NOT NULL,
  harga INT NOT NULL,
  stok INT NOT NULL DEFAULT 50
);

CREATE TABLE siswa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nis VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(60) NOT NULL,
  kelas VARCHAR(20),
  password_hash VARCHAR(255) NOT NULL,
  dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomor INT NOT NULL,
  tanggal DATE NOT NULL,
  nama_pemesan VARCHAR(60) NOT NULL,
  kelas VARCHAR(20),
  siswa_id INT NULL,
  total INT NOT NULL DEFAULT 0,
  status ENUM('menunggu','diproses','siap','selesai') NOT NULL DEFAULT 'menunggu',
  dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE detail_pesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pesanan_id INT NOT NULL,
  menu_id INT NOT NULL,
  jumlah INT NOT NULL,
  subtotal INT NOT NULL,
  FOREIGN KEY (pesanan_id) REFERENCES pesanan(id),
  FOREIGN KEY (menu_id) REFERENCES menu(id)
);

CREATE TABLE transaksi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
  total INT NOT NULL,
  bayar INT NOT NULL,
  kembali INT NOT NULL
);

CREATE TABLE detail_transaksi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT NOT NULL,
  menu_id INT NOT NULL,
  jumlah INT NOT NULL,
  subtotal INT NOT NULL,
  FOREIGN KEY (transaksi_id) REFERENCES transaksi(id),
  FOREIGN KEY (menu_id) REFERENCES menu(id)
);

INSERT INTO menu (nama, harga, stok) VALUES
('Nasi Goreng', 12000, 30),
('Mie Ayam', 10000, 30),
('Keripik Pisang', 3000, 50),
('Es Teh Manis', 8000, 50),
('Air Mineral', 3000, 60);

CREATE TABLE IF NOT EXISTS siswa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nis VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(60) NOT NULL,
  kelas VARCHAR(20),
  password_hash VARCHAR(255) NOT NULL,
  dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
