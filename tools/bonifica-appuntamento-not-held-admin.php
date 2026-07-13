#!/usr/bin/env php
<?php
/**
 * Bonifica massiva appuntamenti annullati:
 * - riassegna ad admin di sistema (entity_user + assigned_user_id)
 * - rimuove evento da Google Calendar del consulente
 *
 * Uso:
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply --quiet
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run --search=Panci
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoGoogleSync;

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;
$quiet = in_array('--quiet', $argv, true);
$search = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--search=')) {
        $search = substr($arg, 9);
    }
}

if ($dryRun) {
    fwrite(STDOUT, "MODALITÀ dry-run (usa --apply per salvare)\n\n");
} elseif ($quiet) {
    fwrite(STDOUT, "MODALITÀ apply\n\n");
}

$application = new Application();
$application->setupSystemUser();

$container = $application->getContainer();
$entityManager = $container->get('entityManager');
$sync = $container->get('injectableFactory')->create(AppuntamentoGoogleSync::class);

fwrite(STDOUT, 'Admin di sistema: ' . $sync->describePrimarySystemAdmin() . "\n\n");

$totalCancelled = $sync->countCancelledAppointments($search);
$assignIds = $sync->listCancelledAppointmentIdsNeedingAdminFix($search);
$googleIds = $sync->listCancelledAppointmentIdsWithGoogleLink($search);
$allIds = array_values(array_unique(array_merge($assignIds, $googleIds)));

fwrite(STDOUT, "Annullati totali: {$totalCancelled}\n");
fwrite(STDOUT, 'Da riassegnare (DB): ' . count($assignIds) . "\n");
fwrite(STDOUT, 'Con link Google da pulire: ' . count($googleIds) . "\n");
fwrite(STDOUT, 'Da elaborare: ' . count($allIds) . "\n\n");

if ($allIds === []) {
    fwrite(STDOUT, "Nessun record da aggiornare.\n");
    exit(0);
}

$assigned = 0;
$google = 0;
$skipped = 0;
$failed = 0;
$processed = 0;

foreach ($allIds as $entityId) {
    $processed++;
    $appointment = $entityManager->getEntityById('Appuntamento', $entityId);

    if (!$appointment) {
        $failed++;
        continue;
    }

    $needsAssign = in_array($entityId, $assignIds, true);
    $needsGoogle = in_array($entityId, $googleIds, true);
    $label = sprintf(
        '%s | %s | %s',
        $entityId,
        $appointment->get('name'),
        $sync->describeAssignee($appointment)
    );

    if (!$quiet) {
        fwrite(STDOUT, sprintf(
            "FIX %s | assegnazione=%s google=%s\n",
            $label,
            $needsAssign ? 'SI' : 'no',
            $needsGoogle ? 'SI' : 'no'
        ));
    } elseif ($processed % 25 === 0) {
        fwrite(STDOUT, "  ... {$processed}/" . count($allIds) . "\n");
    }

    if ($dryRun) {
        if ($needsAssign) {
            $assigned++;
        }
        if ($needsGoogle) {
            $google++;
        }
        continue;
    }

    $result = $sync->forceCancelledAdminAssignee($entityId, true);

    if ($result === 'assigned') {
        $assigned++;
        if ($needsGoogle) {
            $google++;
        }
    } elseif ($result === 'google') {
        $google++;
    } elseif ($result === 'skipped') {
        $skipped++;
    } else {
        $failed++;
    }
}

fwrite(STDOUT, sprintf(
    "\nElaborati: %d\nRiassegnati DB: %d | Google puliti: %d | Saltati: %d",
    count($allIds),
    $assigned,
    $google,
    $skipped
));

if ($failed > 0) {
    fwrite(STDOUT, " | Errori: {$failed}";
}

fwrite(STDOUT, $dryRun ? " (dry-run)\n" : "\n");

if ($dryRun) {
    fwrite(STDOUT, "\nApplica: php tools/bonifica-appuntamento-not-held-admin.php --apply --quiet\n");
}
