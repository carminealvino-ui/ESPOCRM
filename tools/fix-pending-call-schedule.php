#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Corregge ora (09:30 Europe/Rome) e assegnatario sulle Call Pending auto-create.
 *
 *   php tools/fix-pending-call-schedule.php --dry-run
 *   php tools/fix-pending-call-schedule.php
 */

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (es. ~/public_html/crm/mec-group).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

require_once $root . '/custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);

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
$failed = 0;

foreach ($collection as $call) {
    $callId = $call->getId();
    $nota = (string) $call->get('nota');

    if (!preg_match('/Auto-Pending-Appuntamento:\s*([a-f0-9]+)/i', $nota, $matches)) {
        $skipped++;
        continue;
    }

    $appuntamento = $entityManager->getEntityById('Appuntamento', $matches[1]);

    if (!$appuntamento) {
        $skipped++;
        echo "SKIP Call {$callId}: appuntamento {$matches[1]} inesistente\n";
        continue;
    }

    $expectedDateStart = PendingCallDateTime::fromAppointmentDateStart(
        $appuntamento->get('dateStart')
    );

    if (!$expectedDateStart) {
        $skipped++;
        continue;
    }

    $expectedUserId = $appuntamento->get('assignedUserId') ?: $appuntamento->get('createdById');
    $parentName = $call->get('parentName');
    $telefono = $call->get('telefono');
    $presentation = $creator->buildCallPresentationFields($expectedDateStart, $parentName, $telefono);

    $currentDateStart = (string) $call->get('dateStart');
    $currentUserId = (string) $call->get('assignedUserId');

    $needsUpdate = $currentDateStart !== $expectedDateStart
        || (string) $expectedUserId !== $currentUserId
        || $call->get('name') !== $presentation['name'];

    if (!$needsUpdate) {
        $skipped++;
        continue;
    }

    $localLabel = PendingCallDateTime::formatLocalDateTime($expectedDateStart);

    if ($dryRun) {
        $updated++;
        echo "DRY-RUN Call {$callId}: dateStart {$currentDateStart} -> {$expectedDateStart} ({$localLabel}), assignedUser {$currentUserId} -> {$expectedUserId}\n";
        continue;
    }

    try {
        $call->set(array_merge($presentation, [
            'dateStart' => $expectedDateStart,
            'assignedUserId' => $expectedUserId,
        ]));

        $entityManager->saveEntity($call, [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);

        $updated++;
        echo "OK Call {$callId}: {$localLabel}, utente {$expectedUserId}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL Call {$callId}: {$e->getMessage()}\n";
    }
}

echo PHP_EOL;
echo "Aggiornate: {$updated}, già ok: {$skipped}, errori: {$failed}";
echo $dryRun ? ' (dry-run)' : '';
echo PHP_EOL;

exit($failed > 0 ? 1 : 0);
