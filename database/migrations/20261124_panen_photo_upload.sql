-- Run once after backing up the database.
ALTER TABLE panen
    ADD COLUMN foto_filename VARCHAR(64) NULL AFTER berat_kg;
