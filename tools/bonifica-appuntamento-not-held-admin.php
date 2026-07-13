#!/usr/bin/env php
<?php
/**
 * Riassegna ad admin gli appuntamenti Not Held ancora sul consulente.
 *
 * Uso:
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run --search=Panci
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoGoogleSync;

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;
$search = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--search=')) {
        $search = substr($arg, 9);
    }
}

if ($dryRun) {
    fwrite(STDOUT, "MODALITÀ dry-run (usa --apply per salvare)\n");
    fwrite(STDOUT, "Target: utente admin di sistema (userName admin), non ogni type=admin\n\n");
}

$application = new Application();
$application->setupSystemUser();

$container = $application->getContainer();
$entityManager = $container->get('entityManager');
$sync = $container->get('injectableFactory')->create(AppuntamentoGoogleSync::class);
$systemAdminId = $sync->resolvePrimarySystemAdminUserId();

fwrite(STDOUT, "Admin di sistema: {$systemAdminId}\n\n");

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

fwrite(STDOUT, sprintf(
    "\nRiassegnati: %d | Già su admin di sistema: %d%s\n",
    $fixed,
    $skipped,
    $dryRun ? ' (dry-run)' : ''
));
