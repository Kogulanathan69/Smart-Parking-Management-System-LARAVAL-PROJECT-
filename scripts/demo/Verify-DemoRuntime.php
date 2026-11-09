<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expected = getenv('DB_DATABASE') ?: '';
if (! preg_match('/\Aparkflow_demo_[A-Za-z0-9_]+\z/', $expected)
    || in_array($expected, ['smart_parking', 'smart_parking_restore_test_20261108'], true)) {
    fwrite(STDERR, "STOP: Refusing non-demo database configuration.\n");
    exit(2);
}

try {
    $connection = DB::connection();
    $actual = (string) ($connection->selectOne('SELECT DATABASE() AS name')->name ?? '');
    if (! hash_equals($expected, $actual)) {
        fwrite(STDERR, "STOP: Actual database identity does not match the configured demo database.\n");
        exit(3);
    }
    $tables = (int) ($connection->selectOne(
        'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ?',
        [$actual],
    )->total ?? 0);
    $requiredTables = ['users', 'vehicles', 'parking_sessions', 'qr_tickets', 'public_ticket_tokens', 'receipts'];
    $missingTables = array_values(array_filter($requiredTables, fn (string $table): bool => ! Schema::hasTable($table)));
    if ($tables > 0 && $missingTables !== []) {
        fwrite(STDERR, "STOP: Demo schema is partial or missing required Smart QR tables.\n");
        exit(5);
    }
    echo json_encode([
        'status' => 'ok',
        'driver' => $connection->getDriverName(),
        'database' => $actual,
        'tables' => $tables,
        'schema_ready' => $tables > 0 && $missingTables === [],
        'public_ticket_url_configured' => (bool) config('parking.qr.public_base_url'),
        'anpr_engine' => config('parking.drivers.anpr_engine'),
    ], JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'STOP: Demo database verification failed ('.$e::class.").\n");
    exit(4);
}
