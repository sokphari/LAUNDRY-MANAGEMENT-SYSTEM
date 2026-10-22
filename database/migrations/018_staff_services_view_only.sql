DELETE rp
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id AND r.name = 'staff'
JOIN permissions p ON p.id = rp.permission_id AND p.permission_key = 'services.manage';
