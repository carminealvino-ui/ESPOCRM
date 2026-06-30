<?php

/**
 * Conta appuntamenti Held + Pending senza Call e quanti avrebbero richiamo domani.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/audit-pending-call-candidates.php
 *   php tools/audit-pending-call-candidates.php --create   # crea Call mancanti
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;

$createMissing = in_array('--create', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

$timezone = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
$tomorrow = (new \DateTimeImmutable('tomorrow', $timezone))->format('Y-m-d');
$today = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');

echo '=== Audit Call Pending (appuntamenti Svolto + Pending) ===' . PHP_EOL;
echo 'Oggi (Rome): ' . $today . PHP_EOL;
echo 'Domani (Rome): ' . $tomorrow . PHP_EOL;
echo 'Regola richiamo: +2 giorni dalla data appuntamento, ore 09:00 (weekend → lunedì)' . PHP_EOL;
echo PHP_EOL;

$collection = $entityManager
    ->getRDBRepository('Appuntamento')
    ->select([
        'id',
        'name',
        'dateStart',
        'status',
        'sottostato',
        'parentType',
        'parentId',
        'parentName',
        'prospectId',
        'telefono',
        'assignedUserId',
    ])
    ->where([
        'status' => 'Held',
        'sottostato' => 'Pending',
    ])
    ->order('dateStart', 'DESC')
    ->find();

$stats = [
    'total_pending' => 0,
    'eligible' => 0,
    'not_eligible_date' => 0,
    'has_call' => 0,
    'has_planned_call' => 0,
    'has_only_completed_call' => 0,
    'missing_call' => 0,
    'missing_call_tomorrow' => 0,
    'missing_call_today' => 0,
    'missing_call_past' => 0,
    'missing_call_future' => 0,
    'missing_lead' => 0,
    'created' => 0,
];

/** @var list<array<string, mixed>> $tomorrowRows */
$tomorrowRows = [];
/** @var list<array<string, mixed>> $missingRows */
$missingRows = [];

foreach ($collection as $appuntamento) {
    $stats['total_pending']++;

    $dateStart = $appuntamento->get('dateStart');

    if (!PendingCallDateTime::isAppointmentEligible($dateStart)) {
        $stats['not_eligible_date']++;
        continue;
    }

    $stats['eligible']++;

    $appuntamentoId = (string) $appuntamento->getId();
    $existingCall = findExistingPendingCall($entityManager, $appuntamentoId);
    $callInstant = $creator->buildCallInstantFromAppointment($appuntamento);
    $callDateRome = $callInstant
        ? PendingCallDateTime::formatBusinessDateTime($callInstant, 'Y-m-d')
        : null;
    $callTimeRome = $callInstant
        ? PendingCallDateTime::formatBusinessDateTime($callInstant, 'd/m/Y H:i')
        : '-';
    $appointmentRome = $dateStart
        ? BusinessDateTime::formatBusiness($dateStart, 'd/m/Y H:i')
        : '-';

    $row = [
        'id' => $appuntamentoId,
        'name' => (string) $appuntamento->get('name'),
        'appointment' => $appointmentRome,
        'call_date' => $callTimeRome,
        'call_day' => $callDateRome,
        'call_id' => $existingCall?->getId(),
        'call_status' => $existingCall ? (string) $existingCall->get('status') : null,
        'lead_ok' => canResolveLead($entityManager, $appuntamento, $creator),
    ];

    if ($existingCall) {
        $stats['has_call']++;

        if ((string) $existingCall->get('status') === 'Planned') {
            $stats['has_planned_call']++;
        } else {
            $stats['has_only_completed_call']++;
        }

        if ((string) $existingCall->get('status') === 'Planned') {
            continue;
        }

        // Appuntamento ancora Pending ma Call già esitata → serve nuova Call
    }

    $stats['missing_call']++;

    if (!$row['lead_ok']) {
        $stats['missing_lead']++;
    }

    if ($callDateRome === $tomorrow) {
        $stats['missing_call_tomorrow']++;
        $tomorrowRows[] = $row;
    } elseif ($callDateRome === $today) {
        $stats['missing_call_today']++;
        $missingRows[] = $row;
    } elseif ($callDateRome !== null && $callDateRome < $today) {
        $stats['missing_call_past']++;
        $missingRows[] = $row;
    } else {
        $stats['missing_call_future']++;
    }

    if ($createMissing && $row['lead_ok']) {
        $full = $entityManager->getEntityById('Appuntamento', $appuntamentoId);
        $notBefore = new \DateTimeImmutable('today', $timezone);
        $leadId = $full ? $creator->ensureLeadId($full) : null;

        if ($full && $leadId) {
            $callId = $creator->createIfNeeded($full, $notBefore, $leadId);

            if ($callId) {
                $stats['created']++;
                echo 'CREATA Call ' . $callId . ' per appuntamento ' . $appuntamentoId . ' (' . $callTimeRome . ')' . PHP_EOL;
            } else {
                echo 'ERRORE creazione per appuntamento ' . $appuntamentoId . PHP_EOL;
            }
        } elseif ($full && !$leadId) {
            echo 'SKIP no-lead ' . $appuntamentoId . PHP_EOL;
        }
    }
}

echo '--- Riepilogo ---' . PHP_EOL;
echo 'Appuntamenti Pending totali:        ' . $stats['total_pending'] . PHP_EOL;
echo '  di cui eleggibili (da 2026):      ' . $stats['eligible'] . PHP_EOL;
echo '  esclusi (app. prima del 2026):    ' . $stats['not_eligible_date'] . PHP_EOL;
echo 'Con Call collegata (qualsiasi stato): ' . $stats['has_call'] . PHP_EOL;
echo '  di cui ancora Pianificato:        ' . $stats['has_planned_call'] . PHP_EOL;
echo '  solo Svolto/Non svolto (da rifare): ' . $stats['has_only_completed_call'] . PHP_EOL;
echo 'Senza Call (da creare):             ' . $stats['missing_call'] . PHP_EOL;
echo '  → richiamo DOMANI (' . $tomorrow . '):  ' . $stats['missing_call_tomorrow'] . PHP_EOL;
echo '  → richiamo OGGI:                  ' . $stats['missing_call_today'] . PHP_EOL;
echo '  → richiamo già passato:           ' . $stats['missing_call_past'] . PHP_EOL;
echo '  → richiamo futuro (dopo domani):   ' . $stats['missing_call_future'] . PHP_EOL;
echo 'Senza Lead collegato:               ' . $stats['missing_lead'] . PHP_EOL;

if ($createMissing) {
    echo 'Call create ora:                    ' . $stats['created'] . PHP_EOL;
}

echo PHP_EOL;

if ($tomorrowRows !== []) {
    echo '--- Senza Call, richiamo previsto DOMANI ---' . PHP_EOL;

    foreach ($tomorrowRows as $row) {
        printRow($row);
    }

    echo PHP_EOL;
}

if ($missingRows !== [] && ($stats['missing_call_today'] > 0 || $stats['missing_call_past'] > 0)) {
    echo '--- Senza Call, richiamo OGGI o già passato ---' . PHP_EOL;

    foreach ($missingRows as $row) {
        printRow($row);
    }

    echo PHP_EOL;
}

if ($stats['missing_call_tomorrow'] > 0 && !$createMissing) {
    echo 'Per creare le Call mancanti (anche quelle di domani):' . PHP_EOL;
    echo '  php tools/audit-pending-call-candidates.php --create' . PHP_EOL;
}

function printRow(array $row): void
{
    $lead = $row['lead_ok'] ? 'OK' : 'MANCA LEAD';
    echo sprintf(
        '%s | app. %s | richiamo %s | %s | %s' . PHP_EOL,
        $row['id'],
        $row['appointment'],
        $row['call_date'],
        $row['name'] !== '' ? $row['name'] : $row['id'],
        $lead
    );
}

function findExistingPendingCall($entityManager, string $appuntamentoId): ?Entity
{
    $prefix = 'Auto-Pending-Appuntamento: ' . $appuntamentoId;

    $planned = $entityManager
        ->getRDBRepository('Call')
        ->where([
            'nota*' => $prefix,
            'status' => 'Planned',
        ])
        ->findOne();

    if ($planned) {
        return $planned;
    }

    return $entityManager
        ->getRDBRepository('Call')
        ->where(['nota*' => $prefix])
        ->findOne();
}

function canResolveLead($entityManager, Entity $appuntamento, AppuntamentoPendingCallCreator $creator): bool
{
    if ($appuntamento->get('parentType') === 'Lead' && $appuntamento->get('parentId')) {
        if ($entityManager->getEntityById('Lead', $appuntamento->get('parentId'))) {
            return true;
        }
    }

    return $creator->resolveProspect($appuntamento) !== null;
}
