<?php

/**
 * Bonifica Opportunità con Appuntamento orfano (ID grezzo in scheda).
 *
 *   php tools/bonifica-opportunity-appuntamento-orfano.php --dry-run
 *   php tools/bonifica-opportunity-appuntamento-orfano.php --dry-run berana
 *   php tools/bonifica-opportunity-appuntamento-orfano.php berana
 *   php tools/bonifica-opportunity-appuntamento-orfano.php 67ebb599b5324ca2c
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\OpportunityAppuntamentoOrphanRepair;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$filter = null;

foreach ($argv ?? [] as $i => $arg) {
    if ($i === 0 || str_starts_with((string) $arg, '--')) {
        continue;
    }

    $filter = trim((string) $arg);
    break;
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
$repair = new OpportunityAppuntamentoOrphanRepair($em);

$all = $em->getRDBRepository('Opportunity')->find();
$collection = [];

if ($filter) {
    $needle = mb_strtolower($filter);

    foreach ($all as $opportunity) {
        if ($opportunity->getId() === $filter) {
            $collection[] = $opportunity;
            continue;
        }

        $appuntamentoId = (string) $opportunity->get('appuntamentoId');

        if ($appuntamentoId !== '' && $appuntamentoId === $filter) {
            $collection[] = $opportunity;
            continue;
        }

        $hay = mb_strtolower(implode(' ', [
            (string) $opportunity->get('name'),
            (string) $opportunity->get('leadName'),
            (string) $opportunity->get('prospectName'),
            (string) $opportunity->get('appuntamentoName'),
            $appuntamentoId,
        ]));

        if (str_contains($hay, $needle)) {
            $collection[] = $opportunity;
        }
    }
} else {
    foreach ($all as $opportunity) {
        $collection[] = $opportunity;
    }
}

$scanned = 0;
$updated = 0;
$skipped = 0;

foreach ($collection as $opportunity) {
    $scanned++;

    $diag = $repair->diagnose($opportunity);

    if (!$diag['needs']) {
        $skipped++;
        continue;
    }

    $result = $repair->repair($opportunity, $dryRun);

    if (!$result['changed']) {
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $opportunity->getId()
        . ' | ' . ($result['reason'] ?? '')
        . ' | ' . ($result['beforeId'] ?? '∅')
        . ' / ' . ($result['beforeName'] ?? '∅')
        . ' → ' . ($result['afterId'] ?? '∅')
        . ' / ' . ($result['afterName'] ?? '∅')
        . ' || ' . $opportunity->get('name')
        . PHP_EOL;

    $updated++;
}

echo PHP_EOL
    . "Scansionate={$scanned} aggiornate={$updated} skip={$skipped}"
    . ($dryRun ? ' (dry-run)' : '')
    . PHP_EOL;
