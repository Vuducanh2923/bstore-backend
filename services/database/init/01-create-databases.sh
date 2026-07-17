#!/usr/bin/env bash
set -Eeuo pipefail

: "${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD is required}"
: "${MYSQL_USER:?MYSQL_USER is required}"

if [[ ! "${MYSQL_USER}" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "MYSQL_USER may only contain letters, numbers and underscores" >&2
    exit 1
fi

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`bstore_auth_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`bstore_catalog_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`bstore_order_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`bstore_payment_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON \`bstore_auth_db\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`bstore_catalog_db\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`bstore_order_db\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`bstore_payment_db\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
