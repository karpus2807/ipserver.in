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

    seed_default_admin();
    $ready = true;
}

function seed_default_admin(): void
{
    $count = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    if ($count > 0) {
        return;
    }

    $password = password_hash(getenv('ADMIN_PASSWORD') ?: 'Admin@123', PASSWORD_DEFAULT);
    db()->prepare(
        'INSERT INTO users (name, email, password_hash, department, year_level, enrollment_no, phone, role)
         VALUES (:name, :email, :password_hash, :department, :year_level, :enrollment_no, :phone, :role)'
    )->execute([
        'name' => getenv('ADMIN_NAME') ?: 'Lab Administrator',
        'email' => strtolower(getenv('ADMIN_EMAIL') ?: 'admin@ipserver.in'),
        'password_hash' => $password,
        'department' => 'Administration',
        'year_level' => 'Staff',
        'enrollment_no' => 'ADMIN-001',
        'phone' => '',
        'role' => 'admin',
    ]);
}
