#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Crea Call mancanti per Appuntamenti Held + sottostato Pending.
 *
 * Uso (sul server CRM):
 *   cd ~/public_html/crm/mec-group
 *   php tools/backfill-pending-calls.php --dry-run
 *   php tools/backfill-pending-calls.php
 *   php tools/backfill-pending-calls.php --limit=10
 */

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (es. ~/public_html/crm/mec-group).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$limit = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

$app = new Application();
$app->setupSystemUser();

$container = $app->getContainer();
$entityManager = $container->get('entityManager');
$log = $container->get('log');
$config = $container->get('config');

$creator = new AppuntamentoPendingCallCreator($entityManager, $log, $config);
$notBefore = new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));

$query = $entityManager
    ->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Held',
        'sottostato' => 'Pending',
        'dateStart>=' => PendingCallDateTime::MIN_APPOINTMENT_DATE,
    ])
    ->order('dateStart', 'DESC');

if ($limit !== null) {
    $query->limit(0, $limit);
}

$collection = $query->find();

$created = 0;
$skipped = 0;
$failed = 0;

foreach ($collection as $appuntamento) {
    $id = $appuntamento->getId();

    if ($dryRun) {
        $notaMarker = 'Auto-Pending-Appuntamento: ' . $id;
        $existing = $entityManager
            ->getRDBRepository('Call')
            ->where(['nota*' => $notaMarker])
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
        $callId = $creator->createIfNeeded($appuntamento, $notBefore);

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
