<?php

/**
 * Riallinea Call auto-pending: assegnatario dall'Appuntamento e Data Riscontro.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/fix-call-assignment-from-appuntamento.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\DateTime\BusinessDateTime;

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
    $changed = false;

    if (preg_match('/Auto-Pending-Appuntamento:\s*([a-z0-9]{17})/i', $nota, $matches)) {
        $appuntamento = $entityManager->getEntityById('Appuntamento', $matches[1]);

        if ($appuntamento) {
            $ownerUserId = $creator->resolveOwnerUserId($appuntamento);

            if ($ownerUserId && (string) $call->get('assignedUserId') !== $ownerUserId) {
                $ownerUserName = $entityManager->getEntityById('User', $ownerUserId)?->get('name');

                $call->set([
                    'assignedUserId' => $ownerUserId,
                    'assignedUserName' => $ownerUserName,
                    'usersIds' => [$ownerUserId],
                    'usersNames' => $ownerUserName ? [$ownerUserId => $ownerUserName] : (object) [],
                ]);
                $changed = true;
            }
        }
    }

    $status = (string) $call->get('status');

    if ($status === 'Planned' && $call->get('data')) {
        $call->set('data', null);
        $changed = true;
    }

    if (in_array($status, ['Held', 'Not Held'], true) && !$call->get('data')) {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)))
            ->format('Y-m-d');
        $call->set('data', $today);
        $changed = true;
    }

    if (!$changed) {
        $skipped++;

        continue;
    }

    $entityManager->saveEntity($call, ['skipAcl' => true, 'silent' => true]);
    $updated++;

    echo 'OK ' . $call->getId() . ' (' . $status . ')' . PHP_EOL;
}

echo PHP_EOL . "Aggiornate: {$updated}, saltate: {$skipped}" . PHP_EOL;
