UPDATE services
SET unit = 'piece'
WHERE unit = 'item';

ALTER TABLE services
    CHANGE COLUMN unit billing_unit ENUM('kg', 'piece') NOT NULL DEFAULT 'piece',
    ADD UNIQUE KEY services_name_billing_unit_unique (name, billing_unit);
