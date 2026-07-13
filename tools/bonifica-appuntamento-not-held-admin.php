#!/usr/bin/env php
<?php
/**
 * Riassegna ad admin gli appuntamenti Not Held ancora sul consulente.
 *
 * Uso:
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoGoogleSync;

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;

if ($dryRun) {
    fwrite(STDOUT, "MODALITÀ dry-run (usa --apply per salvare)\n\n");
}

$application = new Application();
$application->setupSystemUser();

$container = $application->getContainer();
$entityManager = $container->get('entityManager');
$sync = $container->get('injectableFactory')->create(AppuntamentoGoogleSync::class);

$collection = $entityManager
    ->getRDBRepository('Appuntamento')
    ->where([
        'status' => 'Not Held',
    ])
    ->find();

$fixed = 0;
$skipped = 0;

foreach ($collection as $appointment) {
    if (!$sync->needsNotHeldAdminAssigneeFix($appointment)) {
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

    if ($sync->persistNotHeldAdminAssignees($appointment)) {
        $fixed++;
    }
}

fwrite(STDOUT, sprintf("\nRiassegnati: %d | Già admin: %d%s\n", $fixed, $skipped, $dryRun ? ' (dry-run)' : ''));
