CREATE DATABASE IF NOT EXISTS `bstore_auth_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `bstore_catalog_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `bstore_order_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `bstore_payment_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'bstore_user'@'%'
    IDENTIFIED BY 'bstore_password';

GRANT ALL PRIVILEGES ON `bstore_auth_db`.* TO 'bstore_user'@'%';
GRANT ALL PRIVILEGES ON `bstore_catalog_db`.* TO 'bstore_user'@'%';
GRANT ALL PRIVILEGES ON `bstore_order_db`.* TO 'bstore_user'@'%';
GRANT ALL PRIVILEGES ON `bstore_payment_db`.* TO 'bstore_user'@'%';

FLUSH PRIVILEGES;
