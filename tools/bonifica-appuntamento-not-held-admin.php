#!/usr/bin/env php
<?php
/**
 * Riassegna ad admin TUTTI gli appuntamenti Non Svolto (annullati) ancora in agenda.
 *
 * Uso:
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run --force
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply --force --quiet
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply --force --search=YEBISOM
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoGoogleSync;

$apply = in_array('--apply', $argv, true);
$force = in_array('--force', $argv, true);
$quiet = in_array('--quiet', $argv, true);
$dryRun = !$apply;
$search = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--search=')) {
        $search = substr($arg, 9);
    }
}

if ($dryRun) {
    fwrite(STDOUT, "MODALITÀ dry-run (usa --apply per salvare)\n");
    fwrite(STDOUT, "Target: admin di sistema (userName admin)\n");

    if ($force) {
        fwrite(STDOUT, "Flag --force: tutti i Non Svolto ancora non su admin di sistema\n");
    }

    fwrite(STDOUT, "\n");
}

$application = new Application();
$application->setupSystemUser();

$container = $application->getContainer();
$entityManager = $container->get('entityManager');
$sync = $container->get('injectableFactory')->create(AppuntamentoGoogleSync::class);

fwrite(STDOUT, 'Admin di sistema: ' . $sync->describePrimarySystemAdmin() . "\n\n");

$where = ['status' => 'Not Held'];

if ($search !== null && $search !== '') {
    $where['name*'] = '%' . $search . '%';
}

$collection = $entityManager
    ->getRDBRepository('Appuntamento')
    ->where($where)
    ->find();

$total = count($collection);
$fixed = 0;
$skipped = 0;
$googleCleaned = 0;
$processed = 0;

fwrite(STDOUT, "Non Svolto da analizzare: {$total}\n\n");

foreach ($collection as $appointment) {
    $processed++;
    $needsFix = $force
        ? !$sync->isAssignedToPrimarySystemAdmin($appointment)
        : $sync->needsNotHeldAdminAssigneeFix($appointment);

    if (!$needsFix) {
        $skipped++;
        continue;
    }

    if (!$quiet) {
        fwrite(STDOUT, sprintf(
            "FIX %s | %s | %s → admin\n",
            $appointment->getId(),
            $appointment->get('name'),
            $sync->describeAssignee($appointment)
        ));
    } elseif ($fixed === 0 || ($fixed + 1) % 50 === 0) {
        fwrite(STDOUT, "  ... riassegnazione in corso ({$processed}/{$total})\n");
    }

    if ($dryRun) {
        $fixed++;
        continue;
    }

    $ok = $force
        ? $sync->forceNotHeldAdminAssignees($appointment)
        : $sync->persistNotHeldAdminAssignees($appointment);

    if (!$ok) {
        continue;
    }

    $fixed++;
    $fresh = $entityManager->getEntityById('Appuntamento', $appointment->getId());

    if ($fresh) {
        $sync->handleNotHeldStatus($fresh);
        $googleCleaned++;
    }
}

fwrite(STDOUT, sprintf(
    "\nTotale Non Svolto: %d\nRiassegnati ad admin: %d | Già ok: %d",
    $total,
    $fixed,
    $skipped
));

if (!$dryRun) {
    fwrite(STDOUT, " | Google/unlink tentati: {$googleCleaned}");
}

fwrite(STDOUT, $dryRun ? " (dry-run)\n" : "\n");

if ($dryRun && $fixed > 0) {
    fwrite(STDOUT, "\nApplica: php tools/bonifica-appuntamento-not-held-admin.php --apply --force --quiet\n");
    fwrite(STDOUT, "Poi pulisci Google: php tools/bonifica-appuntamento-google-calendar.php --apply --only-not-held\n");
}
