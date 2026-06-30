<?php

/**
 * Crea Call mancanti per appuntamenti Held + Pending (backfill).
 *
 *   php tools/backfill-pending-calls.php           # anteprima
 *   php tools/backfill-pending-calls.php --create  # crea Call Pianificato
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;

$create = in_array('--create', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

$timezone = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
$notBefore = new \DateTimeImmutable('today', $timezone);
$today = $notBefore->format('Y-m-d');

echo '=== Backfill Call da appuntamenti Pending ===' . PHP_EOL;
echo 'Oggi (Rome): ' . $today . PHP_EOL;
echo 'Modalità: ' . ($create ? 'CREA Call mancanti' : 'SOLO ANTEPRIMA (aggiungi --create)') . PHP_EOL;
echo PHP_EOL;

$collection = $entityManager
    ->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Held',
        'sottostato' => 'Pending',
    ])
    ->order('dateStart', 'DESC')
    ->find();

$stats = [
    'total' => 0,
    'skipped_not_eligible' => 0,
    'skipped_has_planned' => 0,
    'skipped_no_lead' => 0,
    'to_create' => 0,
    'created' => 0,
    'failed' => 0,
];

foreach ($collection as $appuntamento) {
    $stats['total']++;
    $appuntamentoId = (string) $appuntamento->getId();
    $dateStart = $appuntamento->get('dateStart');

    if (!PendingCallDateTime::isAppointmentEligible($dateStart)) {
        $stats['skipped_not_eligible']++;
        continue;
    }

    $plannedCall = $entityManager
        ->getRDBRepository('Call')
        ->where([
            'nota*' => 'Auto-Pending-Appuntamento: ' . $appuntamentoId,
            'status' => 'Planned',
        ])
        ->findOne();

    if ($plannedCall) {
        $stats['skipped_has_planned']++;
        continue;
    }

    $leadId = $creator->resolveLeadId($appuntamento);

    if (!$leadId) {
        $stats['skipped_no_lead']++;
        echo 'SKIP no-lead ' . $appuntamentoId
            . ' | ' . (string) $appuntamento->get('name')
            . ' | parent=' . (string) $appuntamento->get('parentType')
            . '/' . (string) $appuntamento->get('parentId')
            . ' prospect=' . (string) $appuntamento->get('prospectId')
            . PHP_EOL;
        continue;
    }

    $full = $entityManager->getEntityById('Appuntamento', $appuntamentoId);

    if (!$full) {
        $stats['failed']++;
        continue;
    }

    $callInstant = $creator->buildCallInstantFromAppointment($full, $notBefore);
    $callWhen = $callInstant
        ? PendingCallDateTime::formatBusinessDateTime($callInstant, 'd/m/Y H:i')
        : '?';
    $appWhen = $dateStart
        ? BusinessDateTime::formatBusiness($dateStart, 'd/m/Y H:i')
        : '?';

    $stats['to_create']++;

    if (!$create) {
        echo 'CREEREBBE ' . $appuntamentoId
            . ' | app. ' . $appWhen
            . ' → richiamo ' . $callWhen
            . ' | ' . (string) $full->get('name')
            . PHP_EOL;
        continue;
    }

    $callId = $creator->createIfNeeded($full, $notBefore, $leadId);

    if ($callId) {
        $stats['created']++;
        echo 'CREATA ' . $callId
            . ' per app. ' . $appuntamentoId
            . ' | richiamo ' . $callWhen
            . PHP_EOL;
    } else {
        $stats['failed']++;
        echo 'ERRORE creazione per app. ' . $appuntamentoId . PHP_EOL;
    }
}

echo PHP_EOL . '--- Riepilogo ---' . PHP_EOL;
echo 'Appuntamenti Pending:          ' . $stats['total'] . PHP_EOL;
echo 'Esclusi (app. prima 2026):     ' . $stats['skipped_not_eligible'] . PHP_EOL;
echo 'Già con Call Pianificato:      ' . $stats['skipped_has_planned'] . PHP_EOL;
echo 'Senza Lead risolvibile:        ' . $stats['skipped_no_lead'] . PHP_EOL;
echo 'Da creare:                     ' . $stats['to_create'] . PHP_EOL;

if ($create) {
    echo 'Create:                        ' . $stats['created'] . PHP_EOL;
    echo 'Fallite:                       ' . $stats['failed'] . PHP_EOL;
} elseif ($stats['to_create'] > 0) {
    echo PHP_EOL . 'Per creare le Call:' . PHP_EOL;
    echo '  php tools/backfill-pending-calls.php --create' . PHP_EOL;
}
