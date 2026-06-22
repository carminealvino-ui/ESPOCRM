#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Elimina Call auto-create collegate ad Appuntamenti con dateStart prima di una data soglia.
 *
 *   php tools/purge-pending-calls-before.php --dry-run
 *   php tools/purge-pending-calls-before.php
 *   php tools/purge-pending-calls-before.php --before=2026-01-01
 */

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (es. ~/public_html/crm/mec-group).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$before = PendingCallDateTime::MIN_APPOINTMENT_DATE;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--before=')) {
        $before = substr($arg, 9);
    }
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
    fwrite(STDERR, "Formato --before non valido (atteso YYYY-MM-DD): {$before}\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$timezone = new DateTimeZone('Europe/Rome');
$cutoff = new DateTimeImmutable($before . ' 00:00:00', $timezone);

$collection = $entityManager
    ->getRDBRepository('Call')
    ->where([
        'nota*' => 'Auto-Pending-Appuntamento:',
    ])
    ->find();

$deleted = 0;
$skipped = 0;
$failed = 0;

foreach ($collection as $call) {
    $callId = $call->getId();
    $nota = (string) $call->get('nota');

    if (!preg_match('/Auto-Pending-Appuntamento:\s*([a-f0-9]+)/i', $nota, $matches)) {
        $skipped++;
        echo "SKIP Call {$callId}: marker appuntamento non trovato in nota\n";
        continue;
    }

    $appuntamentoId = $matches[1];
    $appuntamento = $entityManager->getEntityById('Appuntamento', $appuntamentoId);

    if (!$appuntamento) {
        $skipped++;
        echo "SKIP Call {$callId}: Appuntamento {$appuntamentoId} inesistente\n";
        continue;
    }

    $dateStart = $appuntamento->get('dateStart');

    if (!$dateStart) {
        $skipped++;
        echo "SKIP Call {$callId}: Appuntamento {$appuntamentoId} senza dateStart\n";
        continue;
    }

    $appointment = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStart, $timezone)
        ?: new DateTimeImmutable($dateStart, $timezone);

    if ($appointment >= $cutoff) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        $deleted++;
        echo "DRY-RUN eliminerebbe Call {$callId} (Appuntamento {$appuntamentoId}, dateStart {$dateStart})\n";
        continue;
    }

    try {
        $entityManager->removeEntity($call, [
            'skipHooks' => true,
            'silent' => true,
        ]);
        $deleted++;
        echo "OK eliminata Call {$callId} (Appuntamento {$appuntamentoId}, dateStart {$dateStart})\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL Call {$callId}: {$e->getMessage()}\n";
    }
}

echo PHP_EOL;
echo "Eliminate: {$deleted}, saltate: {$skipped}, errori: {$failed}";
echo $dryRun ? ' (dry-run)' : '';
echo PHP_EOL;

exit($failed > 0 ? 1 : 0);
