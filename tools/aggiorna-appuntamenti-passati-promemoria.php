<?php

/**
 * Aggiorna appuntamenti passati per ripristinare i promemoria:
 *  1) elenco Appuntamento Pianificato scaduti (popup esito)
 *  2) crea Call mancanti per Held + Pending
 *  3) riallinea promemoria popup sulle Call Pianificato
 *
 *   php tools/aggiorna-appuntamenti-passati-promemoria.php           # anteprima
 *   php tools/aggiorna-appuntamenti-passati-promemoria.php --apply   # applica
 *   php tools/aggiorna-appuntamenti-passati-promemoria.php --apply --limit=20
 */

declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;

$apply = in_array('--apply', $argv ?? [], true) || in_array('--create', $argv ?? [], true);
$limit = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

$app = new Application();
$app->setupSystemUser();

$em = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($em, $log);

$cutoff = PendingCallDateTime::popupEligibilityCutoff();
$nowRome = (new \DateTimeImmutable('now', new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)))
    ->format('Y-m-d H:i');

echo "=== Aggiorna appuntamenti passati / promemoria ===\n";
echo "Ora Rome: {$nowRome}\n";
echo "Cutoff popup esito (now-12h): {$cutoff}\n";
echo "Modalità: " . ($apply ? 'APPLICA' : 'ANTEPRIMA (aggiungi --apply)') . "\n";
if ($limit !== null) {
    echo "Limite: {$limit}\n";
}
echo "\n";

// -------------------------------------------------------------------------
// 1) Appuntamenti Pianificato scaduti → devono comparire nel popup esito
// -------------------------------------------------------------------------
echo "--- 1) Appuntamento Pianificato scaduti (popup esito) ---\n";

$plannedPast = $em->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Planned',
        'dateStart<=' => $cutoff,
    ])
    ->order('dateStart', 'DESC')
    ->limit(0, $limit ?? 200)
    ->find();

$plannedCount = 0;

foreach ($plannedPast as $appt) {
    $plannedCount++;
    $when = $appt->get('dateStart')
        ? BusinessDateTime::formatBusiness((string) $appt->get('dateStart'), 'd/m/Y H:i')
        : '?';
    echo "POPUP  {$appt->getId()} | {$when} | {$appt->get('name')}\n";
}

if ($plannedCount === 0) {
    echo "Nessun Appuntamento Pianificato oltre il cutoff (o già esitato).\n";
} else {
    echo "Totale candidati popup esito (max " . ($limit ?? 200) . "): {$plannedCount}\n";
    echo "Nota: non serve aggiornare lo stato — il provider popup li mostra se Pianificato + scaduto.\n";
}
echo "\n";

// -------------------------------------------------------------------------
// 2) Held + Pending senza Call Pianificato → crea Call
// -------------------------------------------------------------------------
echo "--- 2) Held + Pending → Call richiamo ---\n";

$pendingCollection = $em->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Held',
        'sottostato' => 'Pending',
    ])
    ->order('dateStart', 'DESC')
    ->find();

$stats = [
    'scanned' => 0,
    'skipped_ineligible' => 0,
    'skipped_has_call' => 0,
    'skipped_blocked' => 0,
    'to_create' => 0,
    'created' => 0,
    'failed' => 0,
];

foreach ($pendingCollection as $appuntamento) {
    if ($limit !== null && ($stats['to_create'] + $stats['created']) >= $limit) {
        break;
    }

    $stats['scanned']++;
    $appuntamentoId = (string) $appuntamento->getId();
    $dateStart = $appuntamento->get('dateStart');

    if (!PendingCallDateTime::isAppointmentEligible($dateStart)) {
        $stats['skipped_ineligible']++;
        continue;
    }

    $existing = $em->getRDBRepository('Call')
        ->where([
            'nota*' => 'Auto-Pending-Appuntamento: ' . $appuntamentoId,
            'status' => 'Planned',
        ])
        ->findOne();

    if ($existing) {
        $stats['skipped_has_call']++;
        continue;
    }

    $full = $em->getEntityById('Appuntamento', $appuntamentoId);

    if (!$full) {
        $stats['failed']++;
        continue;
    }

    $block = $creator->diagnoseCreateBlockReason($full);

    if ($block !== null) {
        $stats['skipped_blocked']++;
        echo "SKIP   {$appuntamentoId} | {$block}\n";
        continue;
    }

    $callInstant = $creator->buildEffectiveCallInstant($full);
    $callWhen = $callInstant
        ? PendingCallDateTime::formatBusinessDateTime($callInstant, 'd/m/Y H:i')
        : '?';
    $appWhen = $dateStart
        ? BusinessDateTime::formatBusiness((string) $dateStart, 'd/m/Y H:i')
        : '?';

    if (!$apply) {
        $stats['to_create']++;
        echo "CREEREBBE {$appuntamentoId} | app. {$appWhen} → call {$callWhen} | {$full->get('name')}\n";
        continue;
    }

    $stats['to_create']++;
    $callId = $creator->createIfNeeded($full);

    if ($callId) {
        $stats['created']++;
        echo "CREATA {$callId} per {$appuntamentoId} | richiamo {$callWhen}\n";
    } else {
        $stats['failed']++;
        $reason = $creator->getLastFailureReason()
            ?: $creator->diagnoseCreateBlockReason($full)
            ?: 'sconosciuto';
        echo "ERRORE {$appuntamentoId} | {$reason}\n";
    }
}

echo "Scansionati Pending: {$stats['scanned']}\n";
echo "Già con Call: {$stats['skipped_has_call']}\n";
echo "Non eleggibili / bloccati: " . ($stats['skipped_ineligible'] + $stats['skipped_blocked']) . "\n";
echo ($apply ? "Call create: {$stats['created']}" : "Da creare: {$stats['to_create']}") . "\n";
echo "Falliti: {$stats['failed']}\n\n";

// -------------------------------------------------------------------------
// 3) Riallinea promemoria popup sulle Call auto-pending Pianificato
// -------------------------------------------------------------------------
echo "--- 3) Sync promemoria popup sulle Call Pianificato ---\n";

$callQueries = [
    ['nota*' => 'Auto-Pending-Appuntamento:'],
    ['nota*' => 'Auto-Richiamo-Appuntamento:'],
];

/** @var array<string, Entity> $callsById */
$callsById = [];

foreach ($callQueries as $where) {
    foreach ($em->getRDBRepository('Call')->where($where)->find() as $call) {
        if ((string) $call->get('status') !== 'Planned') {
            continue;
        }

        $callsById[(string) $call->getId()] = $call;
    }
}

$remindersOk = 0;
$remindersFail = 0;
$remindersDone = 0;

foreach ($callsById as $call) {
    if ($limit !== null && $remindersDone >= $limit) {
        break;
    }

    $remindersDone++;
    $label = $call->getId() . ' | ' . $call->get('name');

    if (!$apply) {
        echo "SYNCEREBBE {$label}\n";
        $remindersOk++;
        continue;
    }

    try {
        $creator->syncPopupReminders($call);
        $remindersOk++;
        echo "SYNC   {$label}\n";
    } catch (Throwable $e) {
        $remindersFail++;
        echo "ERRORE {$label} | {$e->getMessage()}\n";
    }
}

echo "Call Pianificato auto-pending: " . count($callsById) . "\n";
echo ($apply ? "Promemoria sincronizzati: {$remindersOk}" : "Da sincronizzare: {$remindersOk}") . "\n";
echo "Errori sync: {$remindersFail}\n\n";

echo "=== Fine ===\n";

if (!$apply) {
    echo "Per applicare:\n";
    echo "  php tools/aggiorna-appuntamenti-passati-promemoria.php --apply\n";
}
