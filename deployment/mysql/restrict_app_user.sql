-- Run only after a recent backup and after any scheduled backup job has a
-- separate read-only MySQL account. This leaves schema changes to an admin.
REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'panenikan_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON panenikan.*
TO 'panenikan_app'@'localhost';
