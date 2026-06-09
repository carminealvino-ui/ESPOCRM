#!/usr/bin/env php
<?php
/**
 * Bonifica Google Calendar ↔ Appuntamenti EspoCRM.
 *
 * Rimuove da Google gli eventi collegati ad appuntamenti:
 *   - eliminati (soft delete)
 *   - Non Svolto / annullati (status Not Held)
 *   - ghost "(APPUNTAMENTO SENZA PROSPECT)"
 *
 * Mantiene su Google: Planned, Held, Ingestibile del consulente con Google attivo.
 * Rimuove: admin, consulente diverso, syncConGoogle off, Not Held, eliminato, ghost.
 * Corregge Ingestibile assegnati per errore ad admin → consulente calendario.
 *
 * Uso (da root CRM, es. ~/public_html/crm/mec-group):
 *   php tools/bonifica-appuntamento-google-calendar.php --dry-run
 *   php tools/bonifica-appuntamento-google-calendar.php --apply
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --user-name="Alvino Carmine"
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --only-ingestibili
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --only-not-held
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --only-push
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --reconcile
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-ghosts
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --backfill-sync-flag
 *   php tools/bonifica-appuntamento-google-calendar.php --dry-run --only-purge-duplicates --from-date=2026-04-20 --to-date=2026-04-27
 *
 * @noinspection PhpUnhandledExceptionInspection
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (directory con bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\AppuntamentoGoogleSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();

$container = $app->getContainer();
$em = $container->get('entityManager');
$injectableFactory = $container->getByClass(InjectableFactory::class);
/** @var AppuntamentoGoogleSync $sync */
$sync = $injectableFactory->create(AppuntamentoGoogleSync::class);

$dryRun = !in_array('--apply', $argv, true);
$onlyIngestibili = in_array('--only-ingestibili', $argv, true);
$onlyNotHeld = in_array('--only-not-held', $argv, true);
$onlyPush = in_array('--only-push', $argv, true);
$reconcileOnly = in_array('--reconcile', $argv, true);
$onlyPurgeGhosts = in_array('--only-purge-ghosts', $argv, true);
$onlyPurgeDuplicates = in_array('--only-purge-duplicates', $argv, true);
$backfillSyncFlag = in_array('--backfill-sync-flag', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$pushSinceDays = 21;
$purgeFromDate = date('Y-m-d', strtotime('-30 days'));
$purgeToDate = date('Y-m-d', strtotime('+14 days'));

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--push-since-days=')) {
        $pushSinceDays = max(1, (int) substr($arg, 18));
    }
    if (str_starts_with($arg, '--from-date=')) {
        $purgeFromDate = substr($arg, 12);
    }
    if (str_starts_with($arg, '--to-date=')) {
        $purgeToDate = substr($arg, 10);
    }
}
$userIdArg = null;
$userNameArg = 'Alvino Carmine';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--user-id=')) {
        $userIdArg = substr($arg, 10);
    }
    if (str_starts_with($arg, '--user-name=')) {
        $userNameArg = substr($arg, 12);
    }
}

function resolveCalendarUser($em, ?string $userIdArg, string $userNameArg): ?Entity
{
    if ($userIdArg) {
        $user = $em->getEntityById('User', $userIdArg);

        return $user && $user->get('isActive') ? $user : null;
    }

    $parts = preg_split('/\s+/', trim($userNameArg), 2) ?: [];

    if (count($parts) === 2) {
        $user = $em->getRDBRepository('User')
            ->where([
                'isActive' => true,
                'firstName' => $parts[0],
                'lastName' => $parts[1],
            ])
            ->findOne();

        if ($user) {
            return $user;
        }

        $user = $em->getRDBRepository('User')
            ->where([
                'isActive' => true,
                'firstName' => $parts[1],
                'lastName' => $parts[0],
            ])
            ->findOne();

        if ($user) {
            return $user;
        }
    }

    return $em->getRDBRepository('User')
        ->where([
            'isActive' => true,
            ['name*' => '%' . $userNameArg . '%'],
        ])
        ->findOne();
}

$calendarUser = resolveCalendarUser($em, $userIdArg, $userNameArg);

if (!$calendarUser) {
    fwrite(STDERR, "Consulente calendario non trovato. Usa --user-id=ID o --user-name=\"Nome Cognome\".\n");
    exit(1);
}

$calendarUserId = $calendarUser->getId();
$calendarUserLabel = trim($calendarUser->get('name') ?? $calendarUserId);

$externalAccount = $em->getEntityById('ExternalAccount', 'Google__' . $calendarUserId);
$googleOk = $externalAccount
    && $externalAccount->get('enabled')
    && ($externalAccount->get('calendarEnabled') || $externalAccount->get('googleCalendarEnabled'));

fwrite(STDOUT, "=== Bonifica Google Calendar Appuntamenti (v1.7.0) ===\n");
fwrite(STDOUT, 'Modalità: ' . ($dryRun ? 'DRY-RUN (nessuna modifica)' : 'APPLY') . "\n");
if ($onlyIngestibili) {
    fwrite(STDOUT, "Filtro: solo correzione Ingestibile (admin → consulente)\n");
}
if ($onlyNotHeld) {
    fwrite(STDOUT, "Filtro: solo rimozione Non Svolto / annullati (Not Held) da Google\n");
}
if ($onlyPush) {
    fwrite(STDOUT, "Filtro: solo push appuntamenti mancanti su Google (ultimi {$pushSinceDays} giorni e futuri)\n");
}
if ($reconcileOnly) {
    fwrite(STDOUT, "Filtro: solo riconciliazione Google (rimuove annullati fantasma su Google)\n");
}
if ($onlyPurgeGhosts) {
    fwrite(STDOUT, "Filtro: solo rimozione ghost APPUNTAMENTO SENZA PROSPECT duplicati\n");
}
if ($backfillSyncFlag) {
    fwrite(STDOUT, "Filtro: migrazione syncConGoogle=true (vecchio default false)\n");
}
if ($onlyPurgeDuplicates) {
    fwrite(STDOUT, "Filtro: rimuove duplicati Google (stesso codice + slot) {$purgeFromDate} → {$purgeToDate}\n");
}
fwrite(STDOUT, "Consulente calendario: {$calendarUserLabel} (id {$calendarUserId})\n");
fwrite(STDOUT, 'Google collegato: ' . ($googleOk ? 'sì' : 'NO — delete API potrebbe fallire') . "\n\n");

$stats = [
    'links_scanned' => 0,
    'google_removed' => 0,
    'google_failed' => 0,
    'google_skipped_keep' => 0,
    'orphan_links_cleaned' => 0,
    'ingestibile_fixed' => 0,
    'ingestibile_ok' => 0,
    'assigned_user_fixed' => 0,
    'google_pushed' => 0,
    'google_push_failed' => 0,
    'google_push_skipped' => 0,
    'google_reconcile_removed' => 0,
    'ghosts_purged' => 0,
    'sync_flag_backfilled' => 0,
    'google_duplicates_removed' => 0,
];

$purgeSince = date('Y-m-d', strtotime('-' . $pushSinceDays . ' days'));

if ($onlyPurgeGhosts || (!$onlyIngestibili && !$onlyPush && !$reconcileOnly && !$onlyPurgeDuplicates && !$backfillSyncFlag)) {
    fwrite(STDOUT, "[PURGE GHOSTS] Duplicati senza prospect dal {$purgeSince}\n");

    if ($dryRun) {
        $orphanPlanned = 0;

        foreach ($em->getRDBRepository('Appuntamento')
            ->where([
                'deleted' => false,
                'name*' => '%(APPUNTAMENTO SENZA PROSPECT)%',
                'dateStart>=' => $purgeSince . ' 00:00:00',
            ])
            ->find() as $ghost) {
            if (!$sync->isGhostAppointment($ghost)) {
                continue;
            }

            if ($ghost->get('status') === 'Planned') {
                $orphanPlanned++;
                $label = formatAppointmentLabel($ghost);
                fwrite(STDOUT, "  [ORPHAN PLANNED] {$label}\n");
            }
        }

        fwrite(STDOUT, "  ghost Planned orphan da rimuovere: {$orphanPlanned}\n\n");
        $stats['ghosts_purged'] = $orphanPlanned;
    } else {
        $stats['ghosts_purged'] = $sync->bonificaPurgeSlotGhosts($purgeSince);
        fwrite(STDOUT, '  ghost rimossi: ' . $stats['ghosts_purged'] . "\n\n");
    }
}

if ($onlyPurgeGhosts) {
    goto summary;
}

if ($onlyPurgeDuplicates) {
    fwrite(STDOUT, "[PURGE DUPLICATES] Google {$purgeFromDate} → {$purgeToDate}\n");

    $dupResult = $sync->bonificaPurgeGoogleDuplicateEvents(
        $calendarUserId,
        $purgeFromDate,
        $purgeToDate,
        !$dryRun
    );

    fwrite(STDOUT, '  eventi appuntamento su Google: ' . $dupResult['scanned'] . "\n");
    fwrite(STDOUT, '  gruppi duplicati: ' . $dupResult['duplicate_groups'] . "\n");
    fwrite(STDOUT, '  eventi da rimuovere: ' . $dupResult['candidates'] . "\n");

    foreach ($dupResult['details'] as $detail) {
        fwrite(STDOUT, '  [DUP] ' . $detail['slot'] . "\n");
        fwrite(STDOUT, '    KEEP: ' . $detail['keep'] . "\n");

        foreach ($detail['remove'] as $removeSummary) {
            fwrite(STDOUT, '    DEL:  ' . $removeSummary . "\n");
        }
    }

    $stats['google_duplicates_removed'] = $dryRun
        ? $dupResult['candidates']
        : $dupResult['removed'];
    fwrite(STDOUT, ($dryRun ? '  (dry-run) ' : '') . 'rimossi: ' . $stats['google_duplicates_removed'] . "\n\n");

    goto summary;
}

if ($backfillSyncFlag) {
    fwrite(STDOUT, "[BACKFILL SYNC FLAG] syncConGoogle=true per appuntamenti sincronizzabili dal {$purgeSince}\n");

    if ($dryRun) {
        $wouldUpdate = 0;

        foreach ($em->getRDBRepository('Appuntamento')
            ->where([
                'deleted' => false,
                'syncConGoogle' => false,
                'status' => ['Planned', 'Held', 'Ingestibile'],
                'dateStart>=' => $purgeSince . ' 00:00:00',
            ])
            ->find() as $appointment) {
            if ($sync->isGhostAppointment($appointment)) {
                continue;
            }

            $wouldUpdate++;
            fwrite(STDOUT, '  [FLAG ON] ' . formatAppointmentLabel($appointment) . "\n");
        }

        $stats['sync_flag_backfilled'] = $wouldUpdate;
        fwrite(STDOUT, "  flag da attivare: {$wouldUpdate}\n\n");
    } else {
        $stats['sync_flag_backfilled'] = $sync->bonificaBackfillSyncConGoogleFlag($purgeSince);
        fwrite(STDOUT, '  flag attivati: ' . $stats['sync_flag_backfilled'] . "\n\n");
    }

    goto summary;
}

$runReconcile = $reconcileOnly || (!$onlyIngestibili && !$onlyPush && !$onlyPurgeDuplicates);
$runPush = $onlyPush || (!$onlyIngestibili && !$onlyNotHeld && !$onlyPurgeDuplicates);
$runCleanup = !$onlyIngestibili && !$onlyPush && !$onlyPurgeDuplicates;

if ($runReconcile) {
    $reconcileFrom = date('Y-m-d', strtotime('-14 days'));
    $reconcileTo = date('Y-m-d', strtotime('+21 days'));
    fwrite(STDOUT, "[RECONCILE] Scansione Google {$reconcileFrom} → {$reconcileTo}\n");

    $reconcileResult = $sync->bonificaReconcileGoogleRange(
        $calendarUserId,
        $reconcileFrom,
        $reconcileTo,
        !$dryRun
    );
    $stats['google_reconcile_removed'] = $reconcileResult['removed'];
    fwrite(STDOUT, '  eventi appuntamento su Google: ' . $reconcileResult['scanned'] . "\n");
    fwrite(STDOUT, '  candidati rimozione (Not Held / admin / consulente errato in Espo): ' . $reconcileResult['candidates'] . "\n");
    fwrite(STDOUT, '  rimossi da Google: ' . $reconcileResult['removed'] . "\n\n");
}

if ($reconcileOnly) {
    goto summary;
}

// --- Fase 5 (priorità): push su Google prima della pulizia link ---
if ($runPush) {
    fwrite(STDOUT, "=== Fase 5: push appuntamenti su Google ===\n");
    $pushSince = date('Y-m-d 00:00:00', strtotime('-' . $pushSinceDays . ' days'));

    try {
        $toPush = $em->getRDBRepository('Appuntamento')
            ->where([
                'deleted' => false,
                'status' => ['Planned', 'Held', 'Ingestibile'],
                'dateStart>=' => $pushSince,
            ])
            ->order('dateStart', 'ASC')
            ->find();
    } catch (\Throwable $e) {
        fwrite(STDERR, 'ERRORE Fase 5 (query): ' . $e->getMessage() . "\n");
        $toPush = [];
    }

    $pushList = is_iterable($toPush) ? iterator_to_array($toPush) : [];
    fwrite(STDOUT, '  appuntamenti da verificare: ' . count($pushList) . "\n");

    foreach ($pushList as $appointment) {
        if ($sync->needsAssignedUserIdFix($appointment)) {
            $label = formatAppointmentLabel($appointment);
            fwrite(STDOUT, "[FIX ASSIGNED USER] {$label}\n");

            if (!$dryRun && $sync->bonificaFixAssignedUserId($appointment)) {
                $stats['assigned_user_fixed']++;
            } elseif ($dryRun) {
                $stats['assigned_user_fixed']++;
            }
        }

        $skipReason = $sync->describePushSkipReason($appointment, $calendarUserId);
        $syncUserId = $sync->resolveSyncableConsultantUserId($appointment);
        $hasStaleLink = $syncUserId === $calendarUserId
            && $sync->hasGoogleLink($appointment->getId())
            && !$sync->isGoogleEventAlive($appointment, $syncUserId);
        $needsPush = $skipReason === null || $hasStaleLink;

        if (!$needsPush) {
            $stats['google_push_skipped']++;

            if ($verbose && $skipReason !== null) {
                fwrite(STDOUT, '[SKIP] ' . formatAppointmentLabel($appointment) . " — {$skipReason}\n");
            }

            continue;
        }

        $label = formatAppointmentLabel($appointment);
        $action = $hasStaleLink ? '[REPAIR+PUSH]' : '[PUSH GOOGLE]';
        fwrite(STDOUT, "{$action} {$label}\n");

        if ($dryRun) {
            $stats['google_pushed']++;

            continue;
        }

        try {
            $result = $sync->bonificaPushMissing($appointment, $calendarUserId);
        } catch (\Throwable $e) {
            $stats['google_push_failed']++;
            fwrite(STDOUT, '  → ERRORE: ' . $e->getMessage() . "\n");

            continue;
        }

        if ($result === 'pushed') {
            $stats['google_pushed']++;
        } elseif ($result === 'failed') {
            $stats['google_push_failed']++;
            fwrite(STDOUT, "  → ERRORE push Google (controlla log Espo)\n");
        } else {
            $stats['google_push_skipped']++;

            if ($verbose) {
                fwrite(STDOUT, "  → saltato dopo tentativo\n");
            }
        }
    }

    fwrite(STDOUT, "\n");
}

if ($onlyPush) {
    goto summary;
}

if ($runCleanup) {
fwrite(STDOUT, "=== Fase 1-3: pulizia link e Not Held ===\n");

try {
$shouldRemoveAppointment = static function (AppuntamentoGoogleSync $sync, Entity $appointment) use ($onlyNotHeld, $calendarUserId): bool {
    if ($onlyNotHeld) {
        return !$appointment->get('deleted') && $appointment->get('status') === 'Not Held';
    }

    return !$sync->shouldStayOnConsultantGoogleCalendar($appointment, $calendarUserId);
};

// --- Fase 1: tutti i link GoogleCalendarEvent → Appuntamento ---
try {
    $linkRows = $em->getRDBRepository('GoogleCalendarEvent')
        ->where([
            'entityType' => 'Appuntamento',
            ['googleCalendarEventId!=' => ''],
            ['googleCalendarEventId!=' => 'FAIL'],
            ['googleCalendarEventId!=' => null],
        ])
        ->order('id', 'ASC')
        ->find();
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERRORE Fase 1 (query link): ' . $e->getMessage() . "\n");
    $linkRows = [];
}

foreach ($linkRows as $link) {
    $stats['links_scanned']++;
    $entityId = $link->get('entityId');
    $googleEventId = $link->get('googleCalendarEventId');

    $appointment = findAppointmentIncludingDeleted($em, $entityId);

    if (!$appointment) {
        fwrite(STDOUT, "[ORPHAN LINK] entityId={$entityId} googleEvent={$googleEventId} — appuntamento assente\n");

        if (!$dryRun) {
            $em->getRepository('GoogleCalendar')->resetEventRelation('Appuntamento', $entityId);
            $stats['orphan_links_cleaned']++;
        }

        continue;
    }

    $label = formatAppointmentLabel($appointment);

    if (!$shouldRemoveAppointment($sync, $appointment)) {
        $stats['google_skipped_keep']++;

        if ($verbose) {
            fwrite(STDOUT, "[KEEP] {$label}\n");
        }

        continue;
    }

    fwrite(STDOUT, "[REMOVE] {$label} (google {$googleEventId})\n");

    if ($dryRun) {
        $stats['google_removed']++;

        continue;
    }

    $result = $sync->bonificaForceRemoveGoogleLink($appointment, $calendarUserId);

    if ($result === 'removed' || $result === 'no_link') {
        $stats['google_removed']++;
    } else {
        $stats['google_failed']++;
        fwrite(STDOUT, "  → ERRORE rimozione Google\n");
    }
}

// --- Fase 2: Not Held / deleted con link residuo (senza riga in scan se già puliti) ---
$staleAppointments = findAppointmentsIncludingDeleted($em, $onlyNotHeld);

foreach ($staleAppointments as $appointment) {
    $googleData = $em->getRepository('GoogleCalendar')->getEventEntityGoogleData(
        'Appuntamento',
        $appointment->getId()
    );

    if ($googleData === false || empty($googleData['googleCalendarEventId'])) {
        continue;
    }

    $label = formatAppointmentLabel($appointment);
    fwrite(STDOUT, "[REMOVE STALE] {$label}\n");

    if ($dryRun) {
        $stats['google_removed']++;

        continue;
    }

    $result = $sync->bonificaForceRemoveGoogleLink($appointment, $calendarUserId);

    if ($result === 'removed' || $result === 'no_link') {
        $stats['google_removed']++;
    } else {
        $stats['google_failed']++;
    }
}

// --- Fase 3: ghost con nome APPUNTAMENTO SENZA PROSPECT ---
if (!$onlyNotHeld) {
$ghosts = $em->getRDBRepository('Appuntamento')
    ->where([
        'name*' => '%(APPUNTAMENTO SENZA PROSPECT)%',
        'deleted' => false,
    ])
    ->find();

foreach ($ghosts as $appointment) {
    if (!$sync->isGhostAppointment($appointment)) {
        continue;
    }

    $googleData = $em->getRepository('GoogleCalendar')->getEventEntityGoogleData(
        'Appuntamento',
        $appointment->getId()
    );

    if ($googleData === false || empty($googleData['googleCalendarEventId'])) {
        continue;
    }

    $label = formatAppointmentLabel($appointment);
    fwrite(STDOUT, "[REMOVE GHOST] {$label}\n");

    if ($dryRun) {
        $stats['google_removed']++;

        continue;
    }

    $result = $sync->bonificaForceRemoveGoogleLink($appointment, $calendarUserId);

    if ($result === 'removed' || $result === 'no_link') {
        $stats['google_removed']++;
    } else {
        $stats['google_failed']++;
    }
}

}

} catch (\Throwable $e) {
    fwrite(STDERR, 'ERRORE Fase 1-3: ' . $e->getMessage() . "\n");
}

}

// --- Fase 4: Ingestibile assegnati ad admin → consulente (Carmine Alvino) ---
if (!$onlyPush && ($onlyIngestibili || (!$onlyIngestibili && !$onlyNotHeld))) {
$ingestibili = $em->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Ingestibile',
        'deleted' => false,
    ])
    ->find();

foreach ($ingestibili as $appointment) {
    if (!$sync->needsIngestibileConsultantFix($appointment, $calendarUserId)) {
        $stats['ingestibile_ok']++;

        continue;
    }

    $label = formatAppointmentLabel($appointment);
    $current = $sync->describeAssignee($appointment);
    fwrite(STDOUT, "[FIX INGESTIBILE] {$label}\n");
    fwrite(STDOUT, "  consulente attuale: {$current} → {$calendarUserLabel}\n");

    if ($dryRun) {
        $stats['ingestibile_fixed']++;

        continue;
    }

    if ($sync->bonificaFixIngestibileConsultant($appointment, $calendarUserId)) {
        $stats['ingestibile_fixed']++;
    }
}

}

summary:
fwrite(STDOUT, "\n--- Riepilogo ---\n");
foreach ($stats as $key => $value) {
    fwrite(STDOUT, sprintf("%-22s %d\n", $key . ':', $value));
}

if ($dryRun) {
    fwrite(STDOUT, "\nNessuna modifica. Ripeti con --apply per eseguire.\n");
} else {
    fwrite(STDOUT, "\nBonifica completata. Controlla Google Calendar e fai Ctrl+F5 in Espo.\n");
}

fwrite(STDOUT, "\nNota: esegui un solo comando per riga (usa --user-id=67c93e694705fde80 se serve).\n");

function findAppointmentIncludingDeleted(EntityManager $em, string $entityId): ?Entity
{
    $appointment = $em->getEntityById('Appuntamento', $entityId);

    if ($appointment) {
        return $appointment;
    }

    $pdo = $em->getPDO();

    if (!$pdo instanceof \PDO) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, status, date_start AS dateStart, date_end AS dateEnd,
                assigned_user_id AS assignedUserId, sync_con_google AS syncConGoogle, deleted
         FROM appuntamento WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$entityId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $appointment = $em->getNewEntity('Appuntamento');

    foreach ($row as $field => $value) {
        if ($field === 'syncConGoogle') {
            $appointment->set($field, (bool) $value);

            continue;
        }

        if ($field === 'deleted') {
            $appointment->set($field, (bool) $value);

            continue;
        }

        $appointment->set($field, $value);
    }

    return $appointment;
}

/**
 * @return iterable<Entity>
 */
function findAppointmentsIncludingDeleted(EntityManager $em, bool $onlyNotHeld): iterable
{
    if ($onlyNotHeld) {
        return $em->getRDBRepository('Appuntamento')
            ->where(['status' => 'Not Held', 'deleted' => false])
            ->find();
    }

    $pdo = $em->getPDO();

    if (!$pdo instanceof \PDO) {
        return $em->getRDBRepository('Appuntamento')
            ->where(['status' => 'Not Held', 'deleted' => false])
            ->find();
    }

    $stmt = $pdo->query(
        "SELECT DISTINCT a.id
         FROM appuntamento a
         INNER JOIN google_calendar_event g
            ON g.entity_id = a.id AND g.entity_type = 'Appuntamento'
         WHERE (a.deleted = 1 OR a.status = 'Not Held')
           AND g.google_calendar_event_id IS NOT NULL
           AND g.google_calendar_event_id != ''
           AND g.google_calendar_event_id != 'FAIL'"
    );

    if ($stmt === false) {
        return [];
    }

    $results = [];

    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $appointment = findAppointmentIncludingDeleted($em, (string) $row['id']);

        if ($appointment) {
            $results[] = $appointment;
        }
    }

    foreach ($em->getRDBRepository('Appuntamento')
        ->where(['status' => 'Not Held', 'deleted' => false])
        ->find() as $appointment) {
        $results[$appointment->getId()] = $appointment;
    }

    return array_values($results);
}

function formatAppointmentLabel(Entity $appointment): string
{
    $id = $appointment->getId();
    $name = $appointment->get('name') ?? '';
    $status = $appointment->get('status') ?? '';
    $start = $appointment->get('dateStart') ?? '';
    $deleted = $appointment->get('deleted') ? ' [DELETED]' : '';

    return "{$id} | {$start} | {$status}{$deleted} | {$name}";
}
