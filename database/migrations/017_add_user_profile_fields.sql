ALTER TABLE users
    ADD COLUMN gender VARCHAR(32) NULL AFTER full_name,
    ADD COLUMN profile TEXT NULL AFTER gender;
