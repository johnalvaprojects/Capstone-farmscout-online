-- Add super_admin role and set your account as Super Admin
-- Replace the username/email below before running

START TRANSACTION;

-- Optional: keep role catalog consistent (only if you use user_roles table)
INSERT INTO user_roles (role_name, description, created_at)
SELECT 'admin', 'DTI admin users with platform management access', NOW()
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE role_name = 'admin');

INSERT INTO user_roles (role_name, description, created_at)
SELECT 'super_admin', 'Highest-level administrator', NOW()
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE role_name = 'super_admin');

-- Set your account as Super Admin
UPDATE users
SET user_role = 'super_admin'
WHERE username = 'REPLACE_WITH_USERNAME'
   OR email = 'REPLACE_WITH_EMAIL';

COMMIT;
