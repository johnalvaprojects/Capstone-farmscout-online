-- DTI Admin: catalog rows in user_roles (optional reference table).
-- Safe to run multiple times (idempotent).
-- Run against your FarmScout database (e.g. farmscout_online).

START TRANSACTION;

INSERT INTO user_roles (role_name, description, created_at)
SELECT 'admin', 'DTI Admin — Department of Trade and Industry; platform oversight and approvals', NOW()
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE role_name = 'admin');

INSERT INTO user_roles (role_name, description, created_at)
SELECT 'super_admin', 'Super Admin — full platform control, including creating and managing DTI Admin accounts', NOW()
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE role_name = 'super_admin');

COMMIT;
