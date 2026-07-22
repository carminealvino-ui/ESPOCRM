<?php

/**
 * Colonne regola_provvigionale richieste da scalette legacy Ariel.
 *
 * Uso: php tools/run-regola-provvigionale-schema-patch.php
 */

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();

$tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'regola_provvigionale'")->fetchColumn();

if (!$tableExists) {
    fwrite(STDERR, "Tabella regola_provvigionale assente. Eseguire create-regola-provvigionale-table.php\n");
    exit(1);
}

$columns = [
    'regime_provvigione' => "VARCHAR(50) DEFAULT NULL",
    'gruppo_provvigione' => "VARCHAR(100) DEFAULT NULL",
    'tipo_calcolo' => "VARCHAR(80) DEFAULT NULL",
    'tipo_provvigione_record' => "VARCHAR(80) DEFAULT NULL",
    'percentuale' => 'DOUBLE DEFAULT NULL',
    'percentuale_addizionale' => 'DOUBLE DEFAULT NULL',
    'coefficiente' => 'DOUBLE DEFAULT NULL',
    'margine_min' => 'DOUBLE DEFAULT NULL',
    'margine_max' => 'DOUBLE DEFAULT NULL',
    'inflow_min' => 'DOUBLE DEFAULT NULL',
    'inflow_max' => 'DOUBLE DEFAULT NULL',
    'pod_min' => 'INT DEFAULT NULL',
    'pod_max' => 'INT DEFAULT NULL',
    'giorni_liquidazione' => 'INT DEFAULT NULL',
    'attiva' => 'TINYINT(1) DEFAULT 1',
    'priorita' => 'INT DEFAULT 100',
];

foreach ($columns as $column => $definition) {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `regola_provvigionale` LIKE ?');
    $stmt->execute([$column]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "OK   colonna presente: {$column}\n";
        continue;
    }

    try {
        $pdo->exec("ALTER TABLE `regola_provvigionale` ADD COLUMN `{$column}` {$definition}");
        echo "ADD  colonna: {$column}\n";
    } catch (Throwable $e) {
        echo "WARN colonna {$column}: {$e->getMessage()}\n";
    }
}

echo "Schema regola_provvigionale verificato.\n";
