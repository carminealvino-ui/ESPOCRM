#!/usr/bin/env php
<?php
/**
 * Bonifica massiva: riassegna ad admin di sistema gli appuntamenti annullati (Not Held).
 *
 * Uso:
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply
 *   php tools/bonifica-appuntamento-not-held-admin.php --apply --quiet
 *   php tools/bonifica-appuntamento-not-held-admin.php --dry-run --search=Panci
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoGoogleSync;
use Espo\ORM\EntityManager;

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
    fwrite(STDOUT, "MODALITÀ dry-run (usa --apply per salvare)\n");
    fwrite(STDOUT, "Target: admin di sistema (userName admin), non ogni type=admin\n\n");
} elseif ($quiet) {
    fwrite(STDOUT, "MODALITÀ apply (quiet)\n\n");
}

$application = new Application();
$application->setupSystemUser();

$container = $application->getContainer();
/** @var EntityManager $entityManager */
$entityManager = $container->get('entityManager');
$sync = $container->get('injectableFactory')->create(AppuntamentoGoogleSync::class);

fwrite(STDOUT, 'Admin di sistema: ' . $sync->describePrimarySystemAdmin() . "\n\n");

$collection = findCancelledAppointments($entityManager, $search);
$total = count($collection);

fwrite(STDOUT, "Appuntamenti annullati da analizzare: {$total}\n\n");

$fixed = 0;
$skipped = 0;
$failed = 0;
$processed = 0;

foreach ($collection as $appointment) {
    $processed++;

    if (!$sync->needsNotHeldAdminAssigneeFix($appointment)) {
        $skipped++;

        if (!$quiet && $dryRun && $processed <= 5) {
            fwrite(STDOUT, "OK  {$appointment->getId()} | già su admin\n");
        }

        continue;
    }

    if (!$quiet) {
        fwrite(STDOUT, sprintf(
            "FIX %s | %s | %s → admin\n",
            $appointment->getId(),
            $appointment->get('name'),
            $sync->describeAssignee($appointment)
        ));
    } elseif ($fixed === 0 || $fixed % 25 === 0) {
        fwrite(STDOUT, "  ... in corso ({$processed}/{$total})\n");
    }

    if ($dryRun) {
        $fixed++;
        continue;
    }

    if ($sync->persistNotHeldAdminAssignees($appointment)) {
        $fixed++;
    } else {
        $failed++;
    }
}

fwrite(STDOUT, sprintf(
    "\nTotale analizzati: %d\nRiassegnati: %d | Già su admin di sistema: %d",
    $total,
    $fixed,
    $skipped
));

if ($failed > 0) {
    fwrite(STDOUT, " | Errori: {$failed}");
}

fwrite(STDOUT, $dryRun ? " (dry-run)\n" : "\n");

if ($dryRun && $fixed > 0) {
    fwrite(STDOUT, "\nPer applicare su tutti: php tools/bonifica-appuntamento-not-held-admin.php --apply --quiet\n");
}

/**
 * @return list<\Espo\ORM\Entity>
 */
function findCancelledAppointments(EntityManager $entityManager, ?string $search): array
{
    $where = [
        'OR' => [
            ['status' => 'Not Held'],
            ['sottostato' => 'Annullato'],
            ['esito' => 'Annullato dal Potenziale'],
            ['esito' => 'Annullato dal Consulente'],
            ['esito' => 'Annullato Azienda'],
            ['esito' => 'Annullato Call Center'],
        ],
    ];

    if ($search !== null && $search !== '') {
        $where['name*'] = '%' . $search . '%';
    }

    $result = [];

    foreach (
        $entityManager
            ->getRDBRepository('Appuntamento')
            ->where($where)
            ->find() as $appointment
    ) {
        $result[] = $appointment;
    }

    return $result;
}
