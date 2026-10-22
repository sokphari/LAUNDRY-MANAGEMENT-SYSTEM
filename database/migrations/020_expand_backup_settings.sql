ALTER TABLE backup_settings
    ADD COLUMN database_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER automatic_enabled,
    ADD COLUMN uploaded_files_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER database_enabled,
    ADD COLUMN offsite_enabled BOOLEAN NOT NULL DEFAULT FALSE AFTER uploaded_files_enabled,
    ADD COLUMN notifications_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER offsite_enabled,
    ADD COLUMN auto_cleanup_enabled BOOLEAN NOT NULL DEFAULT FALSE AFTER notifications_enabled,
    ADD COLUMN frequency ENUM('daily', '6hours', 'weekly') NOT NULL DEFAULT 'daily' AFTER auto_cleanup_enabled,
    ADD COLUMN retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER frequency;
