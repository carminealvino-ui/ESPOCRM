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
 * Mantiene su Google: Planned, Held, Ingestibile.
 * Corregge Ingestibile assegnati per errore ad admin → consulente calendario.
 *
 * Uso (da root CRM, es. ~/public_html/crm/mec-group):
 *   php tools/bonifica-appuntamento-google-calendar.php --dry-run
 *   php tools/bonifica-appuntamento-google-calendar.php --apply
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --user-name="Alvino Carmine"
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --only-ingestibili
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --only-not-held
 *   php tools/bonifica-appuntamento-google-calendar.php --apply --only-push
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
$pushSinceDays = 14;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--push-since-days=')) {
        $pushSinceDays = max(1, (int) substr($arg, 18));
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

fwrite(STDOUT, "=== Bonifica Google Calendar Appuntamenti ===\n");
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
];

if (!$onlyIngestibili && !$onlyPush) {
$shouldRemoveAppointment = static function (AppuntamentoGoogleSync $sync, Entity $appointment) use ($onlyNotHeld): bool {
    if ($onlyNotHeld) {
        return !$appointment->get('deleted') && $appointment->get('status') === 'Not Held';
    }

    return !$sync->shouldStayOnGoogleCalendar($appointment);
};

// --- Fase 1: tutti i link GoogleCalendarEvent → Appuntamento ---
$linkRows = $em->getRDBRepository('GoogleCalendarEvent')
    ->where([
        'entityType' => 'Appuntamento',
        ['googleCalendarEventId!=' => ''],
        ['googleCalendarEventId!=' => 'FAIL'],
        ['googleCalendarEventId!=' => null],
    ])
    ->order('createdAt', 'ASC')
    ->find();

foreach ($linkRows as $link) {
    $stats['links_scanned']++;
    $entityId = $link->get('entityId');
    $googleEventId = $link->get('googleCalendarEventId');

    $appointment = $em->getRDBRepository('Appuntamento')
        ->where(['id' => $entityId])
        ->withDeleted()
        ->findOne();

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
        fwrite(STDOUT, "[KEEP] {$label}\n");

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
$staleWhere = $onlyNotHeld
    ? ['status' => 'Not Held', 'deleted' => false]
    : [
        'OR' => [
            ['deleted' => true],
            ['status' => 'Not Held'],
        ],
    ];

$staleAppointments = $em->getRDBRepository('Appuntamento')
    ->where($staleWhere)
    ->withDeleted()
    ->find();

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

// --- Fase 5: push su Google appuntamenti senza link (es. sabato mancante) ---
if ($onlyPush || (!$onlyIngestibili && !$onlyNotHeld)) {
    $pushSince = date('Y-m-d 00:00:00', strtotime('-' . $pushSinceDays . ' days'));
    $toPush = $em->getRDBRepository('Appuntamento')
        ->where([
            'deleted' => false,
            'status' => ['Planned', 'Held', 'Ingestibile'],
            'dateStart>=' => $pushSince,
        ])
        ->order('dateStart', 'ASC')
        ->find();

    foreach ($toPush as $appointment) {
        if ($sync->needsAssignedUserIdFix($appointment)) {
            $label = formatAppointmentLabel($appointment);
            fwrite(STDOUT, "[FIX ASSIGNED USER] {$label}\n");

            if (!$dryRun && $sync->bonificaFixAssignedUserId($appointment)) {
                $stats['assigned_user_fixed']++;
            } elseif ($dryRun) {
                $stats['assigned_user_fixed']++;
            }
        }

        $googleData = $em->getRepository('GoogleCalendar')->getEventEntityGoogleData(
            'Appuntamento',
            $appointment->getId()
        );

        if (is_array($googleData) && !empty($googleData['googleCalendarEventId'])) {
            $stats['google_push_skipped']++;

            continue;
        }

        if (!$sync->shouldStayOnGoogleCalendar($appointment)) {
            $stats['google_push_skipped']++;

            continue;
        }

        $label = formatAppointmentLabel($appointment);
        fwrite(STDOUT, "[PUSH GOOGLE] {$label}\n");

        if ($dryRun) {
            $stats['google_pushed']++;

            continue;
        }

        $result = $sync->bonificaPushMissing($appointment, $calendarUserId);

        if ($result === 'pushed') {
            $stats['google_pushed']++;
        } elseif ($result === 'failed') {
            $stats['google_push_failed']++;
            fwrite(STDOUT, "  → ERRORE push Google\n");
        } else {
            $stats['google_push_skipped']++;
        }
    }
}

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

function formatAppointmentLabel(Entity $appointment): string
{
    $id = $appointment->getId();
    $name = $appointment->get('name') ?? '';
    $status = $appointment->get('status') ?? '';
    $start = $appointment->get('dateStart') ?? '';
    $deleted = $appointment->get('deleted') ? ' [DELETED]' : '';

    return "{$id} | {$start} | {$status}{$deleted} | {$name}";
}
