-- ============================================================
-- Skema basis data replika simulasi IFJUDUL
-- Dijalankan HANYA di node1. Galera akan mereplikasi ke node2 & node3.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ifjudul_simulasi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ifjudul_simulasi;

CREATE TABLE IF NOT EXISTS judul_ta (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nim             VARCHAR(15)  NOT NULL,
  tahun           SMALLINT     NOT NULL,
  semester        VARCHAR(10)  NOT NULL,
  nama_mahasiswa  VARCHAR(100) NOT NULL,
  judul_skripsi   TEXT         NOT NULL,
  nama_pembimbing VARCHAR(100) NOT NULL,
  dibuat_pada     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nim (nim),
  INDEX idx_tahun (tahun),
  INDEX idx_nama (nama_mahasiswa)
) ENGINE=InnoDB;

CREATE USER IF NOT EXISTS 'ifjudul'@'localhost'
  IDENTIFIED BY 'GANTI_DENGAN_SANDI';

GRANT SELECT, INSERT, UPDATE, DELETE
  ON ifjudul_simulasi.*
  TO 'ifjudul'@'localhost';

GRANT USAGE ON *.* TO 'ifjudul'@'localhost';

FLUSH PRIVILEGES;
