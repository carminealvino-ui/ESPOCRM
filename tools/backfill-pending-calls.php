<?php

/**
 * Crea Call mancanti per appuntamenti Held + Pending (backfill).
 *
 *   php tools/backfill-pending-calls.php           # anteprima
 *   php tools/backfill-pending-calls.php --create  # crea Call Pianificato
 *   php tools/backfill-pending-calls.php --create --limit=10
 */

declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;

$create = in_array('--create', $argv ?? [], true);
$limit = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

$timezone = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
$today = (new \DateTimeImmutable('today', $timezone))->format('Y-m-d');

echo '=== Backfill Call da appuntamenti Pending ===' . PHP_EOL;
echo 'Creator: ' . AppuntamentoPendingCallCreator::CREATOR_VERSION . PHP_EOL;
echo 'Oggi (Rome): ' . $today . PHP_EOL;
echo 'Modalità: ' . ($create ? 'CREA Call mancanti' : 'SOLO ANTEPRIMA (aggiungi --create)') . PHP_EOL;

if ($limit !== null) {
    echo 'Limite: ' . $limit . ' Call' . PHP_EOL;
}

$heldTotal = $entityManager
    ->getRDBRepository('Appuntamento')
    ->where(['status' => 'Held'])
    ->count();

echo 'Appuntamenti Held (tutti i sottostati): ' . $heldTotal . PHP_EOL;
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

/** @var array<string, int> $failureReasons */
$failureReasons = [];
/** @var list<string> $failureSamples */
$failureSamples = [];

if (AppuntamentoPendingCallCreator::CREATOR_VERSION !== '2026-07-01d') {
    echo 'ATTENZIONE: versione creator '
        . AppuntamentoPendingCallCreator::CREATOR_VERSION
        . ' — eseguire deploy-pending-call-popup-fix.sh (atteso 2026-07-01d)'
        . PHP_EOL;
}

foreach ($collection as $appuntamento) {
    if ($limit !== null && $stats['to_create'] >= $limit && !$create) {
        break;
    }

    if ($limit !== null && $stats['created'] >= $limit && $create) {
        break;
    }

    $stats['total']++;

    try {
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

        $full = $entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$full) {
            $stats['failed']++;
            $reason = 'appuntamento non ricaricabile';
            $failureReasons[$reason] = ($failureReasons[$reason] ?? 0) + 1;
            if (count($failureSamples) < 5) {
                $failureSamples[] = $appuntamentoId . ' | ' . $reason;
            }
            echo 'ERRORE ' . $appuntamentoId . ' | ' . $reason . PHP_EOL;
            continue;
        }

        $callInstant = $creator->buildEffectiveCallInstant($full);
        $callWhen = $callInstant
            ? PendingCallDateTime::formatBusinessDateTime($callInstant, 'd/m/Y H:i')
            : '?';
        $appWhen = $dateStart
            ? BusinessDateTime::formatBusiness($dateStart, 'd/m/Y H:i')
            : '?';

        if (!$create) {
            $reason = $creator->diagnoseCreateBlockReason($full);

            if ($reason !== null) {
                $stats['skipped_no_lead']++;
                echo 'SKIP ' . $appuntamentoId . ' | ' . $reason . PHP_EOL;
                continue;
            }

            $stats['to_create']++;
            echo 'CREEREBBE ' . $appuntamentoId
                . ' | app. ' . $appWhen
                . ' → richiamo ' . $callWhen
                . ' | ' . (string) $full->get('name')
                . PHP_EOL;
            continue;
        }

        $stats['to_create']++;
        $callId = $creator->createIfNeeded($full);

        if ($callId) {
            $stats['created']++;
            echo 'CREATA ' . $callId
                . ' per app. ' . $appuntamentoId
                . ' | richiamo ' . $callWhen
                . PHP_EOL;
        } else {
            $stats['failed']++;
            $reason = $creator->getLastFailureReason()
                ?: $creator->diagnoseCreateBlockReason($full)
                ?: 'motivo sconosciuto';
            $failureReasons[$reason] = ($failureReasons[$reason] ?? 0) + 1;
            if (count($failureSamples) < 5) {
                $failureSamples[] = $appuntamentoId . ' | ' . $reason;
            }
            echo 'ERRORE ' . $appuntamentoId . ' | ' . $reason . PHP_EOL;
        }
    } catch (\Throwable $e) {
        $stats['failed']++;
        $reason = 'EXCEPTION: ' . $e->getMessage();
        $failureReasons[$reason] = ($failureReasons[$reason] ?? 0) + 1;
        if (count($failureSamples) < 5) {
            $failureSamples[] = ($appuntamentoId ?? '?') . ' | ' . $reason;
        }
        echo 'EXCEPTION ' . ($appuntamentoId ?? '?') . ': ' . $e->getMessage() . PHP_EOL;
        $log->error('Backfill pending call failed: {message}', [
            'message' => $e->getMessage(),
            'exception' => $e,
        ]);
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

    if ($failureReasons !== []) {
        echo PHP_EOL . '--- Motivi fallimento (aggregati) ---' . PHP_EOL;
        arsort($failureReasons);

        foreach ($failureReasons as $reason => $count) {
            echo $count . 'x  ' . $reason . PHP_EOL;
        }
    }

    if ($failureSamples !== []) {
        echo PHP_EOL . '--- Esempi (max 5) ---' . PHP_EOL;

        foreach ($failureSamples as $sample) {
            echo $sample . PHP_EOL;
        }
    }
}

if ($stats['total'] === 0) {
    echo PHP_EOL . 'ATTENZIONE: nessun appuntamento con status=Held e sottostato=Pending.' . PHP_EOL;
    echo 'Le Call automatiche partono solo da appuntamenti esitati così.' . PHP_EOL;
} elseif ($stats['to_create'] > 0 && !$create) {
    echo PHP_EOL . 'Per creare le Call:' . PHP_EOL;
    echo '  php tools/backfill-pending-calls.php --create' . PHP_EOL;
}
