#!/usr/bin/env php
<?php
/**
 * Riassegna ad admin gli appuntamenti Not Held ancora sul consulente.
 *
 * Uso:
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply --force
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply --search=MACESEANU
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoGoogleSync;

$apply = in_array('--apply', $argv, true);
$force = in_array('--force', $argv, true);
$dryRun = !$apply;
$search = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--search=')) {
        $search = substr($arg, 9);
    }
}

if ($dryRun) {
    fwrite(STDOUT, "MODALITÀ dry-run (usa --apply per salvare)\n");
    fwrite(STDOUT, "Target: admin di sistema (userName admin), non ogni type=admin\n");

    if ($force) {
        fwrite(STDOUT, "Flag --force: riassegna TUTTI i Not Held\n");
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

$fixed = 0;
$skipped = 0;

foreach ($collection as $appointment) {
    $needsFix = $force || $sync->needsNotHeldAdminAssigneeFix($appointment);

    if (!$needsFix) {
        $skipped++;
        continue;
    }

    fwrite(STDOUT, sprintf(
        "FIX %s | %s | %s → admin\n",
        $appointment->getId(),
        $appointment->get('name'),
        $sync->describeAssignee($appointment)
    ));

    if ($dryRun) {
        $fixed++;
        continue;
    }

    $ok = $force
        ? $sync->forceNotHeldAdminAssignees($appointment)
        : $sync->persistNotHeldAdminAssignees($appointment);

    if ($ok) {
        $fixed++;
        $fresh = $entityManager->getEntityById('Appuntamento', $appointment->getId());

        if ($fresh) {
            $sync->handleNotHeldStatus($fresh);
        }
    }
}

fwrite(STDOUT, sprintf(
    "\nRiassegnati: %d | Già su admin di sistema: %d%s\n",
    $fixed,
    $skipped,
    $dryRun ? ' (dry-run)' : ''
));

if ($dryRun && $fixed === 0 && !$force) {
    fwrite(STDOUT, "Prova: php tools/bonifica-appuntamento-not-held-admin.php --dry-run --force\n");
}
