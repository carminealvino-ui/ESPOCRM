<?php

/**
 * Aggiunge colonne base calcolo su provvigione se assenti.
 */

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();

$columns = [
    'base_calcolo' => "VARCHAR(100) DEFAULT NULL",
    'importo_base_calcolo' => 'DOUBLE DEFAULT NULL',
    'importo_base_calcolo_currency' => "VARCHAR(3) DEFAULT 'EUR'",
    'tasso_provvigioni' => 'DOUBLE DEFAULT NULL',
    'tipo' => "VARCHAR(100) DEFAULT 'Provvigione Base'",
];

foreach ($columns as $column => $definition) {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `provvigione` LIKE ?');
    $stmt->execute([$column]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "OK   colonna già presente: {$column}\n";
        continue;
    }

    $pdo->exec("ALTER TABLE `provvigione` ADD COLUMN `{$column}` {$definition}");
    echo "ADD  colonna: {$column}\n";
}

echo "Schema provvigione aggiornato.\n";
