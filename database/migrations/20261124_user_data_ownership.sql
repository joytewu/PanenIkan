-- Run once after backing up the database.
-- Existing rows remain shared because owner_user_id is NULL.
-- The application assigns the authenticated user's ID to new rows.

ALTER TABLE jenis_ikan
    ADD COLUMN owner_user_id INT NULL AFTER id,
    DROP INDEX nama_ikan,
    ADD UNIQUE KEY uq_jenis_ikan_owner_name (owner_user_id, nama_ikan),
    ADD CONSTRAINT fk_jenis_ikan_owner
        FOREIGN KEY (owner_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

ALTER TABLE kolam
    ADD COLUMN owner_user_id INT NULL AFTER id,
    DROP INDEX nama_kolam,
    ADD UNIQUE KEY uq_kolam_owner_name (owner_user_id, nama_kolam),
    ADD CONSTRAINT fk_kolam_owner
        FOREIGN KEY (owner_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

ALTER TABLE panen
    ADD COLUMN owner_user_id INT NULL AFTER id,
    ADD KEY idx_panen_owner_date (owner_user_id, tanggal, id),
    ADD CONSTRAINT fk_panen_owner
        FOREIGN KEY (owner_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;
