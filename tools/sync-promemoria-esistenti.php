<?php

/**
 * SOLO sync promemoria — NON crea nuove Call.
 *
 *  1) Riallinea Reminder popup sulle Call già Pianificato
 *  2) Crea/aggiorna Reminder popup sugli Appuntamento Pianificato scaduti
 *     (così compaiono i promemoria esito)
 *
 *   php tools/sync-promemoria-esistenti.php           # anteprima
 *   php tools/sync-promemoria-esistenti.php --apply   # applica
 */

declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\Modules\Crm\Entities\Reminder;
use Espo\ORM\Entity;

$apply = in_array('--apply', $argv ?? [], true);
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

echo "=== Sync promemoria esistenti (NESSUNA nuova Call) ===\n";
echo "Ora Rome: {$nowRome}\n";
echo "Cutoff popup Appuntamento (now-12h UTC): {$cutoff}\n";
echo "Modalità: " . ($apply ? 'APPLICA' : 'ANTEPRIMA (aggiungi --apply)') . "\n\n";

// -------------------------------------------------------------------------
// 1) Call Pianificato esistenti → sync Reminder
// -------------------------------------------------------------------------
echo "--- 1) Call Pianificato: sync Reminder popup ---\n";

$plannedCalls = $em->getRDBRepository('Call')
    ->where(['status' => 'Planned'])
    ->order('dateStart', 'DESC')
    ->limit(0, $limit ?? 500)
    ->find();

$callSyncOk = 0;
$callSyncSkip = 0;
$callSyncFail = 0;
$callDone = 0;

foreach ($plannedCalls as $call) {
    if ($limit !== null && $callDone >= $limit) {
        break;
    }

    $callDone++;
    $label = $call->getId() . ' | ' . $call->get('name');

    if (!$call->get('dateStart')) {
        $callSyncSkip++;
        echo "SKIP  {$label} | senza dateStart\n";
        continue;
    }

    if (!$apply) {
        $callSyncOk++;
        echo "SYNCEREBBE Call {$label}\n";
        continue;
    }

    try {
        // Forza creazione Reminder anche se non auto-pending:
        // syncPopupReminders standard può sopprimere duplicati; qui vogliamo solo
        // assicurare un Reminder popup per gli assegnatari sulle Call Pianificato.
        syncCallPopupReminderForce($em, $creator, $call);
        $callSyncOk++;
        echo "SYNC  Call {$label}\n";
    } catch (Throwable $e) {
        $callSyncFail++;
        echo "ERR   Call {$label} | {$e->getMessage()}\n";
        $log->error('sync-promemoria Call failed: ' . $e->getMessage(), ['exception' => $e]);
    }
}

echo "Call elaborate: {$callDone}, sync: {$callSyncOk}, skip: {$callSyncSkip}, err: {$callSyncFail}\n\n";

// -------------------------------------------------------------------------
// 2) Appuntamento Pianificato scaduti → Reminder popup
// -------------------------------------------------------------------------
echo "--- 2) Appuntamento Pianificato scaduti: Reminder popup ---\n";

$plannedAppt = $em->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Planned',
        'dateStart<=' => $cutoff,
    ])
    ->order('dateStart', 'DESC')
    ->limit(0, $limit ?? 300)
    ->find();

$apptOk = 0;
$apptSkip = 0;
$apptFail = 0;
$apptDone = 0;

foreach ($plannedAppt as $appt) {
    if ($limit !== null && $apptDone >= $limit) {
        break;
    }

    $apptDone++;
    $when = $appt->get('dateStart')
        ? BusinessDateTime::formatBusiness((string) $appt->get('dateStart'), 'd/m/Y H:i')
        : '?';
    $label = $appt->getId() . ' | ' . $when . ' | ' . $appt->get('name');

    $userIds = resolveAppuntamentoReminderUserIds($em, $appt);

    if ($userIds === []) {
        $apptSkip++;
        echo "SKIP  App. {$label} | nessun assegnatario\n";
        continue;
    }

    if (!$apply) {
        $apptOk++;
        echo "SYNCEREBBE App. {$label} | users=" . implode(',', $userIds) . "\n";
        continue;
    }

    try {
        syncAppuntamentoPopupReminders($em, $appt, $userIds);
        $apptOk++;
        echo "SYNC  App. {$label} | users=" . implode(',', $userIds) . "\n";
    } catch (Throwable $e) {
        $apptFail++;
        echo "ERR   App. {$label} | {$e->getMessage()}\n";
        $log->error('sync-promemoria Appuntamento failed: ' . $e->getMessage(), ['exception' => $e]);
    }
}

echo "Appuntamenti elaborati: {$apptDone}, sync: {$apptOk}, skip: {$apptSkip}, err: {$apptFail}\n\n";

echo "=== Fine (nessuna Call creata) ===\n";

if (!$apply) {
    echo "Per applicare:\n  php tools/sync-promemoria-esistenti.php --apply\n";
}

/**
 * @return list<string>
 */
function resolveAppuntamentoReminderUserIds($em, Entity $appt): array
{
    $ids = [];

    $assignedUserId = (string) ($appt->get('assignedUserId') ?? '');

    if ($assignedUserId !== '') {
        $ids[] = $assignedUserId;
    }

    $multi = $appt->get('assignedUsersIds');

    if (is_array($multi)) {
        foreach ($multi as $id) {
            $id = (string) $id;

            if ($id !== '') {
                $ids[] = $id;
            }
        }
    }

    // entity_user fallback
    try {
        $stmt = $em->getPDO()->prepare(
            "SELECT user_id FROM entity_user
             WHERE entity_type = 'Appuntamento' AND entity_id = ? AND deleted = 0"
        );
        $stmt->execute([$appt->getId()]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['user_id'])) {
                $ids[] = (string) $row['user_id'];
            }
        }
    } catch (Throwable) {
        // ignore
    }

    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
}

/**
 * @param list<string> $userIds
 */
function syncAppuntamentoPopupReminders($em, Entity $appt, array $userIds): void
{
    $appuntamentoId = (string) $appt->getId();
    $dateStart = (string) $appt->get('dateStart');

    if ($appuntamentoId === '' || $dateStart === '') {
        return;
    }

    // remindAt = dateStart (già scaduto per Pianificato oltre cutoff)
    $remindAt = $dateStart;

    $existing = $em->getRDBRepository('Reminder')
        ->where([
            'entityType' => 'Appuntamento',
            'entityId' => $appuntamentoId,
            'type' => Reminder::TYPE_POPUP,
        ])
        ->find();

    /** @var array<string, Entity> $byUser */
    $byUser = [];

    foreach ($existing as $reminder) {
        $byUser[(string) $reminder->get('userId')] = $reminder;
    }

    foreach ($userIds as $userId) {
        if (isset($byUser[$userId])) {
            $reminder = $byUser[$userId];
            $reminder->set([
                'remindAt' => $remindAt,
                'startAt' => $dateStart,
                'seconds' => 0,
            ]);
            $em->saveEntity($reminder, ['skipAcl' => true, 'silent' => true, 'skipHooks' => true]);
            unset($byUser[$userId]);
            continue;
        }

        $em->createEntity('Reminder', [
            'entityType' => 'Appuntamento',
            'entityId' => $appuntamentoId,
            'type' => Reminder::TYPE_POPUP,
            'userId' => $userId,
            'seconds' => 0,
            'remindAt' => $remindAt,
            'startAt' => $dateStart,
        ], [
            'skipAcl' => true,
            'silent' => true,
            'skipHooks' => true,
        ]);
    }

    // Rimuovi reminder di utenti non più assegnati
    foreach ($byUser as $reminder) {
        $em->removeEntity($reminder, ['skipAcl' => true]);
    }
}

function syncCallPopupReminderForce($em, AppuntamentoPendingCallCreator $creator, Entity $call): void
{
    // Preferisci sync standard se la Call è candidata popup
    if ($creator->shouldShowAutoPendingCallInPopup($call)) {
        $creator->syncPopupReminders($call);

        return;
    }

    // Altrimenti crea comunque un Reminder per assignedUser (Call Pianificato generiche)
    $callId = (string) $call->getId();
    $dateStart = (string) $call->get('dateStart');
    $userId = (string) ($call->get('assignedUserId') ?? '');

    if ($callId === '' || $dateStart === '' || $userId === '') {
        return;
    }

    $existing = $em->getRDBRepository('Reminder')
        ->where([
            'entityType' => 'Call',
            'entityId' => $callId,
            'type' => Reminder::TYPE_POPUP,
            'userId' => $userId,
        ])
        ->findOne();

    if ($existing) {
        $existing->set([
            'remindAt' => $dateStart,
            'startAt' => $dateStart,
            'seconds' => 0,
        ]);
        $em->saveEntity($existing, ['skipAcl' => true, 'silent' => true, 'skipHooks' => true]);

        return;
    }

    $em->createEntity('Reminder', [
        'entityType' => 'Call',
        'entityId' => $callId,
        'type' => Reminder::TYPE_POPUP,
        'userId' => $userId,
        'seconds' => 0,
        'remindAt' => $dateStart,
        'startAt' => $dateStart,
    ], [
        'skipAcl' => true,
        'silent' => true,
        'skipHooks' => true,
    ]);
}
