<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensure_database_ready(): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    $schemaPath = dirname(__DIR__) . '/database/schema.sql';
    $schemaSql = file_get_contents($schemaPath);

    if ($schemaSql === false) {
        throw new RuntimeException('Database schema file missing.');
    }

    foreach (array_filter(array_map('trim', explode(";\n", $schemaSql))) as $statement) {
        if ($statement !== '') {
            db()->exec($statement);
        }
    }

    ensure_inventory_schema_extensions();
    $ready = true;
}

function ensure_inventory_schema_extensions(): void
{
    $columns = [
        's_no' => "ALTER TABLE inventory_items ADD COLUMN s_no VARCHAR(40) DEFAULT '' AFTER id",
        'item_description' => "ALTER TABLE inventory_items ADD COLUMN item_description VARCHAR(255) DEFAULT '' AFTER item_code",
        'item_long_description' => "ALTER TABLE inventory_items ADD COLUMN item_long_description TEXT DEFAULT NULL AFTER item_description",
        'quantity' => "ALTER TABLE inventory_items ADD COLUMN quantity VARCHAR(40) DEFAULT '' AFTER item_long_description",
        'unit' => "ALTER TABLE inventory_items ADD COLUMN unit VARCHAR(40) DEFAULT '' AFTER quantity",
        'value_text' => "ALTER TABLE inventory_items ADD COLUMN value_text VARCHAR(120) DEFAULT '' AFTER unit",
        'net_eff_value' => "ALTER TABLE inventory_items ADD COLUMN net_eff_value VARCHAR(120) DEFAULT '' AFTER value_text",
        'invt_ctrl_no' => "ALTER TABLE inventory_items ADD COLUMN invt_ctrl_no VARCHAR(120) DEFAULT '' AFTER net_eff_value",
        'department_name' => "ALTER TABLE inventory_items ADD COLUMN department_name VARCHAR(150) DEFAULT '' AFTER invt_ctrl_no",
        'issued_to' => "ALTER TABLE inventory_items ADD COLUMN issued_to VARCHAR(150) DEFAULT '' AFTER department_name",
        'issue_type' => "ALTER TABLE inventory_items ADD COLUMN issue_type VARCHAR(100) DEFAULT '' AFTER issued_to",
        'lab_code' => "ALTER TABLE inventory_items ADD COLUMN lab_code VARCHAR(150) DEFAULT '' AFTER issue_type",
        'gis_no' => "ALTER TABLE inventory_items ADD COLUMN gis_no VARCHAR(80) DEFAULT '' AFTER lab_code",
        'gis_date' => "ALTER TABLE inventory_items ADD COLUMN gis_date VARCHAR(80) DEFAULT '' AFTER gis_no",
        'nc_no' => "ALTER TABLE inventory_items ADD COLUMN nc_no VARCHAR(80) DEFAULT '' AFTER gis_date",
        'nc_date' => "ALTER TABLE inventory_items ADD COLUMN nc_date VARCHAR(80) DEFAULT '' AFTER nc_no",
        'source_name' => "ALTER TABLE inventory_items ADD COLUMN source_name VARCHAR(120) DEFAULT '' AFTER nc_date",
        'qr_view' => "ALTER TABLE inventory_items ADD COLUMN qr_view VARCHAR(120) DEFAULT '' AFTER source_name",
    ];

    foreach ($columns as $column => $statement) {
        if (!table_column_exists('inventory_items', $column)) {
            db()->exec($statement);
        }
    }

    remove_unique_index_for_column('inventory_items', 'item_code');

    if (!table_index_exists('inventory_items', 'uniq_inventory_invt_ctrl_no')) {
        db()->exec("ALTER TABLE inventory_items ADD UNIQUE KEY uniq_inventory_invt_ctrl_no (invt_ctrl_no)");
    }
}

function table_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name"
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function table_index_exists(string $table, string $indexName): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name"
    );
    $stmt->execute([
        'table_name' => $table,
        'index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function remove_unique_index_for_column(string $table, string $column): void
{
    $stmt = db()->prepare(
        "SELECT DISTINCT INDEX_NAME
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
           AND NON_UNIQUE = 0"
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $indexName) {
        if ($indexName !== 'PRIMARY') {
            db()->exec(sprintf('ALTER TABLE %s DROP INDEX %s', $table, $indexName));
        }
    }
}
