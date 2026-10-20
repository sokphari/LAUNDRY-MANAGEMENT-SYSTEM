CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    description VARCHAR(255) NULL,
    is_system BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY roles_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_key VARCHAR(100) NOT NULL,
    module VARCHAR(60) NOT NULL,
    label VARCHAR(120) NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY permissions_key_unique (permission_key), KEY permissions_module_index (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT role_permissions_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT role_permissions_permission_fk FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permissions (
    user_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    effect ENUM('allow', 'deny') NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_id),
    CONSTRAINT user_permissions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT user_permissions_permission_fk FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, description) VALUES ('admin', 'Full system access'), ('staff', 'Standard laundry operations');
INSERT INTO permissions (permission_key, module, label) VALUES
('dashboard.view','Dashboard','View dashboard'),
('customers.view','Customers','View customers'),('customers.manage','Customers','Manage customers'),
('services.view','Services','View services'),('services.manage','Services','Manage services'),
('orders.view','Orders','View orders'),('orders.manage','Orders','Manage orders'),
('payments.view','Payments','View payments'),('payments.record','Payments','Record payments'),('payments.refund','Payments','Refund and correct payments'),
('audit_logs.view','Audit Logs','View audit logs'),('reports.view','Reports','View and export reports'),
('users.manage','Users','Manage user accounts'),('roles.manage','Roles & Permissions','Manage role permissions'),('users.permissions','User Permissions','Manage user overrides');
INSERT INTO role_permissions (role_id, permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='admin';
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN ('dashboard.view','customers.view','customers.manage','services.view','services.manage','orders.view','orders.manage','payments.view','payments.record') WHERE r.name='staff';
