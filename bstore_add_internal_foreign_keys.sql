-- BStore internal foreign-key maintenance script for MySQL 8+
-- Run manually in phpMyAdmin. No row is inserted, updated, or deleted.
-- A relation is added only after engine, column type, nullability and orphan checks pass.
-- Cross-service IDs are deliberately excluded; see the logical-reference list at the end.

USE `bstore_auth_db`;

DROP PROCEDURE IF EXISTS `sp_bstore_report_orphans`;
DROP PROCEDURE IF EXISTS `sp_bstore_report_type`;
DROP PROCEDURE IF EXISTS `sp_bstore_add_internal_fk`;

DELIMITER $$

-- Displays orphaned rows before every possible FK operation.
CREATE PROCEDURE `sp_bstore_report_orphans`(
    IN p_schema VARCHAR(64), IN p_child VARCHAR(64), IN p_column VARCHAR(64), IN p_parent VARCHAR(64)
)
BEGIN
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME IN (p_child, p_parent)) <> 2
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column) = 0 THEN
        SELECT CONCAT('SKIP orphan check: missing table or column for ', p_schema, '.', p_child, '.', p_column) AS warning;
    ELSE
        SET @sql = CONCAT(
            'SELECT child.* FROM `', p_schema, '`.`', p_child, '` child ',
            'LEFT JOIN `', p_schema, '`.`', p_parent, '` parent ON parent.`id` = child.`', p_column, '` ',
            'WHERE child.`', p_column, '` IS NOT NULL AND parent.`id` IS NULL'
        );
        PREPARE statement FROM @sql;
        EXECUTE statement;
        DEALLOCATE PREPARE statement;
    END IF;
END$$

-- Reports exact parent/child types and emits a review-only ALTER statement when they differ.
CREATE PROCEDURE `sp_bstore_report_type`(
    IN p_schema VARCHAR(64), IN p_child VARCHAR(64), IN p_column VARCHAR(64), IN p_parent VARCHAR(64), IN p_requires_nullable BOOLEAN
)
BEGIN
    DECLARE v_parent_type TEXT;
    DECLARE v_child_type TEXT;
    DECLARE v_nullable VARCHAR(3);

    SET v_parent_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_parent AND COLUMN_NAME = 'id');
    SET v_child_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column);
    SET v_nullable = (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column);

    SELECT p_schema AS table_schema, p_parent AS parent_table, 'id' AS parent_column, v_parent_type AS parent_type,
           p_child AS child_table, p_column AS child_column, v_child_type AS child_type, v_nullable AS child_nullable,
           CASE WHEN v_parent_type IS NULL OR v_child_type IS NULL THEN 'MISSING'
                WHEN LOWER(v_parent_type) <> LOWER(v_child_type) THEN 'TYPE_MISMATCH'
                WHEN p_requires_nullable AND v_nullable <> 'YES' THEN 'NULLABILITY_MISMATCH'
                ELSE 'OK' END AS status;

    IF v_parent_type IS NOT NULL AND v_child_type IS NOT NULL
       AND (LOWER(v_parent_type) <> LOWER(v_child_type) OR (p_requires_nullable AND v_nullable <> 'YES')) THEN
        SELECT CONCAT(
            '-- Review orphan report first, then run only if approved: ALTER TABLE `', p_schema, '`.`', p_child,
            '` MODIFY `', p_column, '` ', v_parent_type, IF(p_requires_nullable, ' NULL', IF(v_nullable = 'YES', ' NULL', ' NOT NULL')), ';'
        ) AS suggested_manual_type_fix;
    END IF;
END$$

-- Converts MyISAM only when required, then rebuilds a same-database FK with the requested name and actions.
-- If data or metadata is invalid, it returns a warning and does not add the FK.
CREATE PROCEDURE `sp_bstore_add_internal_fk`(
    IN p_schema VARCHAR(64), IN p_child VARCHAR(64), IN p_column VARCHAR(64), IN p_parent VARCHAR(64),
    IN p_constraint VARCHAR(64), IN p_on_delete VARCHAR(16), IN p_requires_nullable BOOLEAN
)
BEGIN
    DECLARE v_child_engine VARCHAR(64);
    DECLARE v_parent_engine VARCHAR(64);
    DECLARE v_child_type TEXT;
    DECLARE v_parent_type TEXT;
    DECLARE v_nullable VARCHAR(3);
    DECLARE v_orphans BIGINT DEFAULT 0;
    DECLARE v_existing_fk VARCHAR(64);
    DECLARE v_constraint_exists INT DEFAULT 0;

    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME IN (p_child, p_parent)) <> 2
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column) = 0
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_parent AND COLUMN_NAME = 'id') = 0 THEN
        SELECT CONCAT('SKIP ', p_constraint, ': missing required table or column.') AS warning;
    ELSE
        SET v_child_engine = (SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child);
        SET v_parent_engine = (SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_parent);

        IF UPPER(v_child_engine) = 'MYISAM' THEN
            SET @sql = CONCAT('ALTER TABLE `', p_schema, '`.`', p_child, '` ENGINE = InnoDB');
            PREPARE statement FROM @sql; EXECUTE statement; DEALLOCATE PREPARE statement;
            SET v_child_engine = 'InnoDB';
        END IF;
        IF UPPER(v_parent_engine) = 'MYISAM' THEN
            SET @sql = CONCAT('ALTER TABLE `', p_schema, '`.`', p_parent, '` ENGINE = InnoDB');
            PREPARE statement FROM @sql; EXECUTE statement; DEALLOCATE PREPARE statement;
            SET v_parent_engine = 'InnoDB';
        END IF;

        SET v_child_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column);
        SET v_parent_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_parent AND COLUMN_NAME = 'id');
        SET v_nullable = (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column);

        SET @sql = CONCAT(
            'SELECT COUNT(*) INTO @bstore_orphan_count FROM `', p_schema, '`.`', p_child, '` child ',
            'LEFT JOIN `', p_schema, '`.`', p_parent, '` parent ON parent.`id` = child.`', p_column, '` ',
            'WHERE child.`', p_column, '` IS NOT NULL AND parent.`id` IS NULL'
        );
        PREPARE statement FROM @sql; EXECUTE statement; DEALLOCATE PREPARE statement;
        SET v_orphans = @bstore_orphan_count;

        IF UPPER(v_child_engine) <> 'INNODB' OR UPPER(v_parent_engine) <> 'INNODB' THEN
            SELECT CONCAT('SKIP ', p_constraint, ': both tables must be InnoDB; found ', v_child_engine, ' / ', v_parent_engine, '.') AS warning;
        ELSEIF LOWER(v_child_type) <> LOWER(v_parent_type) THEN
            SELECT CONCAT('SKIP ', p_constraint, ': type mismatch: ', p_child, '.', p_column, ' is ', v_child_type,
                          ', but ', p_parent, '.id is ', v_parent_type, '. Review the type report before retrying.') AS warning;
        ELSEIF p_requires_nullable AND v_nullable <> 'YES' THEN
            SELECT CONCAT('SKIP ', p_constraint, ': ', p_child, '.', p_column, ' must be nullable for ON DELETE SET NULL.') AS warning;
        ELSEIF v_orphans > 0 THEN
            SELECT CONCAT('SKIP ', p_constraint, ': ', v_orphans, ' orphaned row(s) found. Resolve manually; no data was changed.') AS warning;
        ELSE
            -- Remove only existing FKs for this exact child-column -> parent-table relation.
            SET v_existing_fk = (
                SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column
                  AND REFERENCED_TABLE_SCHEMA = p_schema AND REFERENCED_TABLE_NAME = p_parent
                LIMIT 1
            );
            WHILE v_existing_fk IS NOT NULL DO
                SET @sql = CONCAT('ALTER TABLE `', p_schema, '`.`', p_child, '` DROP FOREIGN KEY `', v_existing_fk, '`');
                PREPARE statement FROM @sql; EXECUTE statement; DEALLOCATE PREPARE statement;
                SET v_existing_fk = (
                    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = p_schema AND TABLE_NAME = p_child AND COLUMN_NAME = p_column
                      AND REFERENCED_TABLE_SCHEMA = p_schema AND REFERENCED_TABLE_NAME = p_parent
                    LIMIT 1
                );
            END WHILE;

            SET v_constraint_exists = (
                SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = p_schema AND CONSTRAINT_NAME = p_constraint
            );
            IF v_constraint_exists > 0 THEN
                SELECT CONCAT('SKIP ', p_constraint, ': that constraint name is already used by another relation.') AS warning;
            ELSE
                SET @sql = CONCAT(
                    'ALTER TABLE `', p_schema, '`.`', p_child, '` ADD CONSTRAINT `', p_constraint,
                    '` FOREIGN KEY (`', p_column, '`) REFERENCES `', p_parent, '` (`id`) ON UPDATE CASCADE ON DELETE ', p_on_delete
                );
                PREPARE statement FROM @sql; EXECUTE statement; DEALLOCATE PREPARE statement;
                SELECT CONCAT('ADDED ', p_schema, '.', p_constraint) AS result;
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;

-- 1. ENGINE REPORT: run once before any changes. MyISAM is converted only by a valid FK call below.
SELECT TABLE_SCHEMA, TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA IN ('bstore_auth_db', 'bstore_catalog_db', 'bstore_order_db', 'bstore_payment_db')
ORDER BY TABLE_SCHEMA, TABLE_NAME;

-- 2. EXISTING FK REPORT: compare current constraints/actions before the calls below.
SELECT k.TABLE_SCHEMA, k.TABLE_NAME AS child_table, k.COLUMN_NAME AS child_column,
       k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME AS parent_table,
       r.UPDATE_RULE, r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.TABLE_SCHEMA IN ('bstore_auth_db', 'bstore_catalog_db', 'bstore_order_db', 'bstore_payment_db')
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY k.TABLE_SCHEMA, k.TABLE_NAME, k.CONSTRAINT_NAME;

-- 3-6. AUTH SERVICE: orphan/type reports, MyISAM->InnoDB if necessary, then FK normalization.
CALL sp_bstore_report_orphans('bstore_auth_db', 'users', 'role_id', 'roles');
CALL sp_bstore_report_type('bstore_auth_db', 'users', 'role_id', 'roles', TRUE);
CALL sp_bstore_add_internal_fk('bstore_auth_db', 'users', 'role_id', 'roles', 'fk_auth_users_role', 'SET NULL', TRUE);
CALL sp_bstore_report_orphans('bstore_auth_db', 'user_addresses', 'user_id', 'users');
CALL sp_bstore_report_type('bstore_auth_db', 'user_addresses', 'user_id', 'users', FALSE);
CALL sp_bstore_add_internal_fk('bstore_auth_db', 'user_addresses', 'user_id', 'users', 'fk_auth_user_addresses_user', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_auth_db', 'auth_sessions', 'user_id', 'users');
CALL sp_bstore_report_type('bstore_auth_db', 'auth_sessions', 'user_id', 'users', FALSE);
CALL sp_bstore_add_internal_fk('bstore_auth_db', 'auth_sessions', 'user_id', 'users', 'fk_auth_sessions_user', 'CASCADE', FALSE);

-- CATALOG SERVICE
CALL sp_bstore_report_orphans('bstore_catalog_db', 'attributes', 'group_id', 'attribute_groups');
CALL sp_bstore_report_type('bstore_catalog_db', 'attributes', 'group_id', 'attribute_groups', TRUE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'attributes', 'group_id', 'attribute_groups', 'fk_catalog_attributes_group', 'SET NULL', TRUE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'category_attributes', 'category_id', 'categories');
CALL sp_bstore_report_type('bstore_catalog_db', 'category_attributes', 'category_id', 'categories', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'category_attributes', 'category_id', 'categories', 'fk_catalog_category_attributes_category', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'category_attributes', 'attribute_id', 'attributes');
CALL sp_bstore_report_type('bstore_catalog_db', 'category_attributes', 'attribute_id', 'attributes', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'category_attributes', 'attribute_id', 'attributes', 'fk_catalog_category_attributes_attribute', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'product_attributes', 'product_id', 'products');
CALL sp_bstore_report_type('bstore_catalog_db', 'product_attributes', 'product_id', 'products', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'product_attributes', 'product_id', 'products', 'fk_catalog_product_attributes_product', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'product_attributes', 'attribute_id', 'attributes');
CALL sp_bstore_report_type('bstore_catalog_db', 'product_attributes', 'attribute_id', 'attributes', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'product_attributes', 'attribute_id', 'attributes', 'fk_catalog_product_attributes_attribute', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'products', 'category_id', 'categories');
CALL sp_bstore_report_type('bstore_catalog_db', 'products', 'category_id', 'categories', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'products', 'category_id', 'categories', 'fk_catalog_products_category', 'RESTRICT', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'products', 'brand_id', 'brands');
CALL sp_bstore_report_type('bstore_catalog_db', 'products', 'brand_id', 'brands', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'products', 'brand_id', 'brands', 'fk_catalog_products_brand', 'RESTRICT', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'products', 'warranty_policy_id', 'warranty_policies');
CALL sp_bstore_report_type('bstore_catalog_db', 'products', 'warranty_policy_id', 'warranty_policies', TRUE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'products', 'warranty_policy_id', 'warranty_policies', 'fk_catalog_products_warranty_policy', 'SET NULL', TRUE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'product_variants', 'product_id', 'products');
CALL sp_bstore_report_type('bstore_catalog_db', 'product_variants', 'product_id', 'products', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'product_variants', 'product_id', 'products', 'fk_catalog_product_variants_product', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'product_images', 'product_id', 'products');
CALL sp_bstore_report_type('bstore_catalog_db', 'product_images', 'product_id', 'products', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'product_images', 'product_id', 'products', 'fk_catalog_product_images_product', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'product_images', 'product_variant_id', 'product_variants');
CALL sp_bstore_report_type('bstore_catalog_db', 'product_images', 'product_variant_id', 'product_variants', TRUE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'product_images', 'product_variant_id', 'product_variants', 'fk_catalog_product_images_variant', 'SET NULL', TRUE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'inventories', 'product_variant_id', 'product_variants');
CALL sp_bstore_report_type('bstore_catalog_db', 'inventories', 'product_variant_id', 'product_variants', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'inventories', 'product_variant_id', 'product_variants', 'fk_catalog_inventories_variant', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'inventory_transactions', 'product_variant_id', 'product_variants');
CALL sp_bstore_report_type('bstore_catalog_db', 'inventory_transactions', 'product_variant_id', 'product_variants', FALSE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'inventory_transactions', 'product_variant_id', 'product_variants', 'fk_catalog_inventory_transactions_variant', 'RESTRICT', FALSE);
CALL sp_bstore_report_orphans('bstore_catalog_db', 'banners', 'product_image_id', 'product_images');
CALL sp_bstore_report_type('bstore_catalog_db', 'banners', 'product_image_id', 'product_images', TRUE);
CALL sp_bstore_add_internal_fk('bstore_catalog_db', 'banners', 'product_image_id', 'product_images', 'fk_catalog_banners_product_image', 'SET NULL', TRUE);

-- ORDER SERVICE (warranty_requests.order_item_id exists in the current Laravel migration).
CALL sp_bstore_report_orphans('bstore_order_db', 'cart_items', 'cart_id', 'carts');
CALL sp_bstore_report_type('bstore_order_db', 'cart_items', 'cart_id', 'carts', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'cart_items', 'cart_id', 'carts', 'fk_order_cart_items_cart', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'order_items', 'order_id', 'orders');
CALL sp_bstore_report_type('bstore_order_db', 'order_items', 'order_id', 'orders', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'order_items', 'order_id', 'orders', 'fk_order_order_items_order', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'order_discounts', 'order_id', 'orders');
CALL sp_bstore_report_type('bstore_order_db', 'order_discounts', 'order_id', 'orders', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'order_discounts', 'order_id', 'orders', 'fk_order_order_discounts_order', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'order_discounts', 'discount_id', 'discounts');
CALL sp_bstore_report_type('bstore_order_db', 'order_discounts', 'discount_id', 'discounts', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'order_discounts', 'discount_id', 'discounts', 'fk_order_order_discounts_discount', 'RESTRICT', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'order_histories', 'order_id', 'orders');
CALL sp_bstore_report_type('bstore_order_db', 'order_histories', 'order_id', 'orders', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'order_histories', 'order_id', 'orders', 'fk_order_order_histories_order', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'notifications', 'order_id', 'orders');
CALL sp_bstore_report_type('bstore_order_db', 'notifications', 'order_id', 'orders', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'notifications', 'order_id', 'orders', 'fk_order_notifications_order', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'complaints', 'order_id', 'orders');
CALL sp_bstore_report_type('bstore_order_db', 'complaints', 'order_id', 'orders', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'complaints', 'order_id', 'orders', 'fk_order_complaints_order', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'refund_requests', 'order_id', 'orders');
CALL sp_bstore_report_type('bstore_order_db', 'refund_requests', 'order_id', 'orders', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'refund_requests', 'order_id', 'orders', 'fk_order_refund_requests_order', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'warranty_requests', 'order_id', 'orders');
CALL sp_bstore_report_type('bstore_order_db', 'warranty_requests', 'order_id', 'orders', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'warranty_requests', 'order_id', 'orders', 'fk_order_warranty_requests_order', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_order_db', 'warranty_requests', 'order_item_id', 'order_items');
CALL sp_bstore_report_type('bstore_order_db', 'warranty_requests', 'order_item_id', 'order_items', FALSE);
CALL sp_bstore_add_internal_fk('bstore_order_db', 'warranty_requests', 'order_item_id', 'order_items', 'fk_order_warranty_requests_order_item', 'RESTRICT', FALSE);

-- PAYMENT SERVICE
CALL sp_bstore_report_orphans('bstore_payment_db', 'payment_transactions', 'payment_id', 'payments');
CALL sp_bstore_report_type('bstore_payment_db', 'payment_transactions', 'payment_id', 'payments', FALSE);
CALL sp_bstore_add_internal_fk('bstore_payment_db', 'payment_transactions', 'payment_id', 'payments', 'fk_payment_transactions_payment', 'CASCADE', FALSE);
CALL sp_bstore_report_orphans('bstore_payment_db', 'invoices', 'payment_id', 'payments');
CALL sp_bstore_report_type('bstore_payment_db', 'invoices', 'payment_id', 'payments', FALSE);
CALL sp_bstore_add_internal_fk('bstore_payment_db', 'invoices', 'payment_id', 'payments', 'fk_payment_invoices_payment', 'CASCADE', FALSE);

-- 7. FK REPORT AFTER EXECUTION
SELECT k.TABLE_SCHEMA, k.TABLE_NAME AS child_table, k.COLUMN_NAME AS child_column,
       k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME AS parent_table,
       r.UPDATE_RULE, r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.TABLE_SCHEMA IN ('bstore_auth_db', 'bstore_catalog_db', 'bstore_order_db', 'bstore_payment_db')
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY k.TABLE_SCHEMA, k.TABLE_NAME, k.CONSTRAINT_NAME;

-- 8. ROLLBACK: run only if you want to remove the constraints introduced/normalized by this file.
-- ALTER TABLE `bstore_auth_db`.`users` DROP FOREIGN KEY `fk_auth_users_role`;
-- ALTER TABLE `bstore_auth_db`.`user_addresses` DROP FOREIGN KEY `fk_auth_user_addresses_user`;
-- ALTER TABLE `bstore_auth_db`.`auth_sessions` DROP FOREIGN KEY `fk_auth_sessions_user`;
-- ALTER TABLE `bstore_catalog_db`.`attributes` DROP FOREIGN KEY `fk_catalog_attributes_group`;
-- ALTER TABLE `bstore_catalog_db`.`category_attributes` DROP FOREIGN KEY `fk_catalog_category_attributes_category`, DROP FOREIGN KEY `fk_catalog_category_attributes_attribute`;
-- ALTER TABLE `bstore_catalog_db`.`product_attributes` DROP FOREIGN KEY `fk_catalog_product_attributes_product`, DROP FOREIGN KEY `fk_catalog_product_attributes_attribute`;
-- ALTER TABLE `bstore_catalog_db`.`products` DROP FOREIGN KEY `fk_catalog_products_category`, DROP FOREIGN KEY `fk_catalog_products_brand`, DROP FOREIGN KEY `fk_catalog_products_warranty_policy`;
-- ALTER TABLE `bstore_catalog_db`.`product_variants` DROP FOREIGN KEY `fk_catalog_product_variants_product`;
-- ALTER TABLE `bstore_catalog_db`.`product_images` DROP FOREIGN KEY `fk_catalog_product_images_product`, DROP FOREIGN KEY `fk_catalog_product_images_variant`;
-- ALTER TABLE `bstore_catalog_db`.`inventories` DROP FOREIGN KEY `fk_catalog_inventories_variant`;
-- ALTER TABLE `bstore_catalog_db`.`inventory_transactions` DROP FOREIGN KEY `fk_catalog_inventory_transactions_variant`;
-- ALTER TABLE `bstore_catalog_db`.`banners` DROP FOREIGN KEY `fk_catalog_banners_product_image`;
-- ALTER TABLE `bstore_order_db`.`cart_items` DROP FOREIGN KEY `fk_order_cart_items_cart`;
-- ALTER TABLE `bstore_order_db`.`order_items` DROP FOREIGN KEY `fk_order_order_items_order`;
-- ALTER TABLE `bstore_order_db`.`order_discounts` DROP FOREIGN KEY `fk_order_order_discounts_order`, DROP FOREIGN KEY `fk_order_order_discounts_discount`;
-- ALTER TABLE `bstore_order_db`.`order_histories` DROP FOREIGN KEY `fk_order_order_histories_order`;
-- ALTER TABLE `bstore_order_db`.`notifications` DROP FOREIGN KEY `fk_order_notifications_order`;
-- ALTER TABLE `bstore_order_db`.`complaints` DROP FOREIGN KEY `fk_order_complaints_order`;
-- ALTER TABLE `bstore_order_db`.`refund_requests` DROP FOREIGN KEY `fk_order_refund_requests_order`;
-- ALTER TABLE `bstore_order_db`.`warranty_requests` DROP FOREIGN KEY `fk_order_warranty_requests_order`, DROP FOREIGN KEY `fk_order_warranty_requests_order_item`;
-- ALTER TABLE `bstore_payment_db`.`payment_transactions` DROP FOREIGN KEY `fk_payment_transactions_payment`;
-- ALTER TABLE `bstore_payment_db`.`invoices` DROP FOREIGN KEY `fk_payment_invoices_payment`;

-- 9. Cross-service logical references only (no physical FK):
-- Auth: users.email <-> email_verifications.email.
-- Order: carts.user_id, orders.user_id, orders.assigned_staff_id, order_histories.staff_id,
--        complaints.customer_id, complaints.staff_id, notifications.user_id, cart_items.product_variant_id,
--        order_items.product_id/product_variant_id, warranty_requests.customer_id/product_id/product_variant_id,
--        refund_requests.customer_id.
-- Payment: payments.order_id, invoices.order_id.

-- 10. Remove helper routines after reviewing the result sets. This does not affect any table or data.
DROP PROCEDURE IF EXISTS `sp_bstore_report_orphans`;
DROP PROCEDURE IF EXISTS `sp_bstore_report_type`;
DROP PROCEDURE IF EXISTS `sp_bstore_add_internal_fk`;
