<?php

/**
 * Ricostruisce nome Opportunità con data (niente più "- LOMMI...").
 *
 *   php tools/bonifica-opportunity-nome-data.php --dry-run
 *   php tools/bonifica-opportunity-nome-data.php
 *   php tools/bonifica-opportunity-nome-data.php --dry-run lommi
 *   php tools/bonifica-opportunity-nome-data.php 681d9c063557522f6
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\OpportunityNameBuilder;

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
$builder = new OpportunityNameBuilder($em);

// Carica tutte e filtra in PHP (name* Espo può essere case-sensitive).
$all = $em->getRDBRepository('Opportunity')->find();
$collection = [];

if ($filter) {
    $needle = mb_strtolower($filter);

    foreach ($all as $opportunity) {
        if ($opportunity->getId() === $filter) {
            $collection[] = $opportunity;
            continue;
        }

        $hay = mb_strtolower(implode(' ', [
            (string) $opportunity->get('name'),
            (string) $opportunity->get('prospectName'),
            (string) $opportunity->get('accountName'),
            (string) $opportunity->get('leadName'),
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

    if (!$builder->needsRebuild($opportunity)) {
        $skipped++;
        continue;
    }

    $result = $builder->rebuild($opportunity, $dryRun);

    if (!$result['changed']) {
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $opportunity->getId()
        . ' | ' . ($result['before'] ?? '')
        . ' → ' . ($result['name'] ?? '')
        . PHP_EOL;

    $updated++;
}

echo PHP_EOL
    . "Scansionate={$scanned} aggiornate={$updated} skip={$skipped}"
    . ($dryRun ? ' (dry-run)' : '')
    . PHP_EOL;
