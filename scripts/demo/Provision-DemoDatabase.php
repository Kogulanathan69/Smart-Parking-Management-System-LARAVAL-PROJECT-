<?php

declare(strict_types=1);

function stop(string $message): never
{
    fwrite(STDERR, "STOP: {$message}\n");
    exit(2);
}

$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$adminUser = getenv('DEMO_DB_ADMIN_USERNAME') ?: '';
$adminPassword = getenv('DEMO_DB_ADMIN_PASSWORD');
$approval = getenv('DEMO_PROVISION_APPROVAL') ?: '';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);

if (! preg_match('/\Aparkflow_demo_[A-Za-z0-9_]+\z/', $database)
    || in_array($database, ['smart_parking', 'smart_parking_restore_test_20261108'], true)) {
    stop('Protected or invalid demo database name.');
}
if (! preg_match('/\A[A-Za-z0-9_]{1,32}\z/', $username) || $password === '' || $adminUser === '' || $adminPassword === false) {
    stop('Required account configuration is missing or invalid.');
}
if (! hash_equals("CREATE {$database}", $approval)) {
    stop('Explicit database provisioning approval is missing.');
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $adminUser, $adminPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $quotedPassword = $pdo->quote($password);
    $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER '{$username}'@'localhost' IDENTIFIED BY {$quotedPassword}");
    $pdo->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'localhost'");
    $pdo->exec('FLUSH PRIVILEGES');
    echo "Created isolated demo database and narrowly scoped application account.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Provisioning failed: '.$e->getMessage()."\nNo automatic rollback or destructive cleanup was attempted.\n");
    exit(1);
} finally {
    $adminPassword = null;
    $password = null;
}
