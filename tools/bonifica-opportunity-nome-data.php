<?php

/**
 * Ricostruisce nome Opportunità con data (niente più "- LOMMI...").
 *
 *   php tools/bonifica-opportunity-nome-data.php --dry-run
 *   php tools/bonifica-opportunity-nome-data.php
 *   php tools/bonifica-opportunity-nome-data.php --dry-run lommi
 *   php tools/bonifica-opportunity-nome-data.php lommi
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

if ($filter) {
    $collection = $em->getRDBRepository('Opportunity')
        ->where([
            'OR' => [
                ['id' => $filter],
                ['name*' => $filter],
                ['prospectName*' => $filter],
                ['accountName*' => $filter],
            ],
        ])
        ->find();
} else {
    // Ampia: tutte le opportunità; filtra in PHP con needsRebuild
    $collection = $em->getRDBRepository('Opportunity')->find();
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
