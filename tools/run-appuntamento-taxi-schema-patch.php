<?php

/**
 * Aggiunge colonna taxi su appuntamento se assente.
 *
 * Uso: php tools/run-appuntamento-taxi-schema-patch.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();

$column = 'taxi';
$definition = 'TINYINT(1) NOT NULL DEFAULT 0';

$stmt = $pdo->prepare('SHOW COLUMNS FROM `appuntamento` LIKE ?');
$stmt->execute([$column]);

if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "OK   colonna già presente: {$column}\n";
} else {
    $pdo->exec("ALTER TABLE `appuntamento` ADD COLUMN `{$column}` {$definition}");
    echo "ADD  colonna: {$column}\n";
}

echo "Schema appuntamento aggiornato.\n";
