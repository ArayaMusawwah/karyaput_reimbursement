-- Update the default admin password to Setup1PW!
UPDATE users 
SET password = '$2y$12$ZhXBblGDbZXlYpncojogTOl/w9ey40ewIky6vdC71rzsNph6qf3Za'
WHERE username = 'admin' AND email = 'admin@karyaputrabersama.com';

-- Verify the update
SELECT id, username, email, full_name, department, role 
FROM users 
WHERE username = 'admin';