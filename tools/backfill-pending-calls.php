#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Crea Call mancanti per Appuntamenti Held + sottostato Pending.
 *
 * Uso (sul server CRM):
 *   cd ~/public_html/crm/mec-group
 *   php tools/backfill-pending-calls.php
 *   php tools/backfill-pending-calls.php --dry-run
 */

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$container = $app->getContainer();
$entityManager = $container->get('entityManager');
$log = $container->get('log');

$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

$collection = $entityManager
    ->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Held',
        'sottostato' => 'Pending',
    ])
    ->find();

$created = 0;
$skipped = 0;
$failed = 0;

foreach ($collection as $appuntamento) {
    $id = $appuntamento->getId();

    if ($dryRun) {
        $nota = 'Auto-Pending-Appuntamento: ' . $id;
        $existing = $entityManager
            ->getRDBRepository('Call')
            ->where(['nota' => $nota])
            ->findOne();

        if ($existing) {
            $skipped++;
            echo "SKIP (call esistente) {$id}\n";

            continue;
        }

        echo "DRY-RUN creerebbe Call per Appuntamento {$id}\n";
        $created++;

        continue;
    }

    try {
        $callId = $creator->createIfNeeded($appuntamento);

        if ($callId) {
            $created++;
            echo "OK Appuntamento {$id} -> Call {$callId}\n";
        } else {
            $skipped++;
            echo "SKIP Appuntamento {$id}\n";
        }
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL Appuntamento {$id}: {$e->getMessage()}\n";
    }
}

echo PHP_EOL;
echo "Creati: {$created}, saltati: {$skipped}, errori: {$failed}";
echo $dryRun ? ' (dry-run)' : '';
echo PHP_EOL;

exit($failed > 0 ? 1 : 0);
