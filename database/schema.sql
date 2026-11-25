
CREATE DATABASE IF NOT EXISTS panenikan
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE panenikan;

-- Tabel pengguna
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel jenis ikan
CREATE TABLE IF NOT EXISTS jenis_ikan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NULL,
    nama_ikan VARCHAR(100) NOT NULL,
    CONSTRAINT fk_jenis_ikan_owner
        FOREIGN KEY (owner_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    UNIQUE KEY uq_jenis_ikan_owner_name (owner_user_id, nama_ikan)
);

-- Tabel kolam
CREATE TABLE IF NOT EXISTS kolam (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NULL,
    nama_kolam VARCHAR(100) NOT NULL,
    lokasi VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_kolam_owner
        FOREIGN KEY (owner_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    UNIQUE KEY uq_kolam_owner_name (owner_user_id, nama_kolam)
);

-- Tabel panen
CREATE TABLE IF NOT EXISTS panen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NULL,
    tanggal DATE NOT NULL,
    jenis_ikan_id INT NOT NULL,
    kolam_id INT NOT NULL,
    jumlah_ekor INT NOT NULL,
    berat_kg DECIMAL(10,2) NOT NULL,
    foto_filename VARCHAR(64) NULL,

    CONSTRAINT fk_panen_owner
        FOREIGN KEY (owner_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_panen_jenis
        FOREIGN KEY (jenis_ikan_id)
        REFERENCES jenis_ikan(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_panen_kolam
        FOREIGN KEY (kolam_id)
        REFERENCES kolam(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS security_audit_checks (
    item_code VARCHAR(8) NOT NULL PRIMARY KEY,
    answer ENUM('yes', 'no') NULL DEFAULT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    updated_by INT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_security_audit_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
