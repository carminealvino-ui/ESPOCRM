#!/usr/bin/env php
<?php
/**
 * Crea tabella regola_provvigionale se assente.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/create-regola-provvigionale-table.php
 *   php tools/create-regola-provvigionale-table.php --dry-run
 */

declare(strict_types=1);

$options = getopt('', ['crm-root::', 'dry-run']);

$crmRoot = rtrim($options['crm-root'] ?? getenv('CRM_ROOT') ?: getcwd(), '/');
$dryRun = array_key_exists('dry-run', $options);

$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "config-internal.php non trovato in {$crmRoot}/data/\n");
    exit(1);
}

$config = include $configInternal;

if (!is_array($config) || empty($config['database'])) {
    fwrite(STDERR, "Config database non valida in config-internal.php\n");
    exit(1);
}

$db = $config['database'];
$host = $db['host'] ?? 'localhost';
$port = isset($db['port']) ? (int) $db['port'] : 3306;
$dbname = $db['dbname'] ?? '';
$user = $db['user'] ?? '';
$pass = $db['password'] ?? '';
$charset = $db['charset'] ?? 'utf8mb4';

$sqlFile = $crmRoot . '/database/2026-07-03-regola-provvigionale-create-table.sql';

if (!is_file($sqlFile)) {
    fwrite(STDERR, "File SQL mancante: {$sqlFile}\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);

if ($sql === false) {
    fwrite(STDERR, "Impossibile leggere {$sqlFile}\n");
    exit(1);
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Connessione DB fallita: ' . $e->getMessage() . "\n");
    exit(1);
}

$tableExists = (bool) $pdo->query(
    "SHOW TABLES LIKE 'regola_provvigionale'"
)->fetchColumn();

if ($tableExists) {
    echo "[OK] Tabella regola_provvigionale già presente\n";
    exit(0);
}

echo "[INFO] Tabella regola_provvigionale assente — creazione in corso\n";

if ($dryRun) {
    echo "[DRY-RUN] Eseguirebbe SQL da {$sqlFile}\n";
    exit(0);
}

$statements = array_filter(
    array_map('trim', preg_split('/;\s*\n/', $sql)),
    static fn (string $s): bool => $s !== '' && !str_starts_with($s, '--')
);

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
    } catch (PDOException $e) {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Duplicate column name')) {
            echo "[SKIP] " . $msg . "\n";
            continue;
        }

        fwrite(STDERR, "[ERR] " . $msg . "\n");
        exit(1);
    }
}

$stillMissing = !(bool) $pdo->query(
    "SHOW TABLES LIKE 'regola_provvigionale'"
)->fetchColumn();

if ($stillMissing) {
    fwrite(STDERR, "[ERR] Tabella regola_provvigionale non creata\n");
    exit(1);
}

echo "[OK] Tabella regola_provvigionale creata\n";
echo "Poi: php clear_cache.php && php rebuild.php\n";
