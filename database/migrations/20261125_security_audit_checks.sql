CREATE TABLE security_audit_checks (
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
