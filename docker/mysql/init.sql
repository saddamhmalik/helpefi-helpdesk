CREATE DATABASE IF NOT EXISTS helpdesk_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT CREATE ON *.* TO 'helpdesk'@'%';
GRANT ALL PRIVILEGES ON helpdesk_central.* TO 'helpdesk'@'%';
GRANT ALL PRIVILEGES ON `helpdesk_tenant_%`.* TO 'helpdesk'@'%';
FLUSH PRIVILEGES;
