<?php

/**
 * Riallinea assignedUserId delle Call auto-pending dall'Appuntamento collegato.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/fix-call-assignment-from-appuntamento.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

$collection = $entityManager
    ->getRDBRepository('Call')
    ->where([
        'nota*' => 'Auto-Pending-Appuntamento:',
    ])
    ->find();

$updated = 0;
$skipped = 0;

foreach ($collection as $call) {
    $nota = (string) $call->get('nota');

    if (!preg_match('/Auto-Pending-Appuntamento:\s*([a-z0-9]{17})/i', $nota, $matches)) {
        $skipped++;

        continue;
    }

    $appuntamento = $entityManager->getEntityById('Appuntamento', $matches[1]);

    if (!$appuntamento) {
        $skipped++;

        continue;
    }

    $ownerUserId = $creator->resolveOwnerUserId($appuntamento);

    if (!$ownerUserId || (string) $call->get('assignedUserId') === $ownerUserId) {
        $skipped++;

        continue;
    }

    $ownerUserName = $entityManager->getEntityById('User', $ownerUserId)?->get('name');

    $call->set([
        'assignedUserId' => $ownerUserId,
        'assignedUserName' => $ownerUserName,
        'usersIds' => [$ownerUserId],
        'usersNames' => $ownerUserName ? [$ownerUserId => $ownerUserName] : (object) [],
    ]);

    $entityManager->saveEntity($call, ['skipAcl' => true, 'silent' => true]);
    $updated++;

    echo 'OK ' . $call->getId() . ' -> ' . $ownerUserName . PHP_EOL;
}

echo PHP_EOL . "Aggiornate: {$updated}, saltate: {$skipped}" . PHP_EOL;
