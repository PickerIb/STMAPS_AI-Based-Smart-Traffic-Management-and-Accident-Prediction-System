-- Upgrade script only. Fresh installs should use database/smart_traffic_db.sql instead.
-- Adds driver license ID and traffic police service ID columns.

ALTER TABLE users
    ADD COLUMN license_id VARCHAR(50) NULL DEFAULT NULL AFTER user_type,
    ADD COLUMN police_id VARCHAR(50) NULL DEFAULT NULL AFTER license_id,
    ADD UNIQUE KEY uk_users_license_id (license_id),
    ADD UNIQUE KEY uk_users_police_id (police_id);
