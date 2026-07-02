<?php

/**
 * Diagnostica creazione Call per un singolo appuntamento Pending.
 *
 *   php tools/diagnose-pending-call-one.php <appuntamentoId>
 *   php tools/diagnose-pending-call-one.php <appuntamentoId> --create
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;

$appuntamentoId = null;
$create = false;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--create') {
        $create = true;
        continue;
    }

    if ($appuntamentoId === null && $arg !== '') {
        $appuntamentoId = $arg;
    }
}

if (!$appuntamentoId) {
    fwrite(STDERR, "Uso: php tools/diagnose-pending-call-one.php <appuntamentoId> [--create]\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

echo 'Creator: ' . AppuntamentoPendingCallCreator::CREATOR_VERSION . PHP_EOL;

$appuntamento = $entityManager->getEntityById('Appuntamento', $appuntamentoId);

if (!$appuntamento) {
    fwrite(STDERR, "Appuntamento non trovato: {$appuntamentoId}\n");
    exit(1);
}

echo 'Appuntamento: ' . $appuntamentoId . PHP_EOL;
echo 'status=' . (string) $appuntamento->get('status')
    . ' sottostato=' . (string) $appuntamento->get('sottostato') . PHP_EOL;
echo 'parent=' . (string) $appuntamento->get('parentType')
    . '/' . (string) $appuntamento->get('parentId') . PHP_EOL;
echo 'prospectId=' . (string) $appuntamento->get('prospectId') . PHP_EOL;
echo 'telefono=' . (string) $appuntamento->get('telefono') . PHP_EOL;

$prospect = $creator->resolveProspect($appuntamento);
echo 'resolveProspect: ' . ($prospect ? $prospect->getId() : 'null') . PHP_EOL;
echo 'resolveLeadId: ' . ($creator->resolveLeadId($appuntamento) ?: 'null') . PHP_EOL;

$block = $creator->diagnoseCreateBlockReason($appuntamento);
echo 'diagnose: ' . ($block ?? 'OK') . PHP_EOL;

if (!$create) {
    echo PHP_EOL . 'Per tentare la creazione: aggiungere --create' . PHP_EOL;
    exit($block ? 2 : 0);
}

try {
    $callId = $creator->createIfNeeded($appuntamento);
} catch (\Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(3);
}

if ($callId) {
    echo 'CREATA Call ' . $callId . PHP_EOL;
    exit(0);
}

echo 'FALLITA: ' . ($creator->getLastFailureReason() ?: 'motivo sconosciuto') . PHP_EOL;
exit(4);
