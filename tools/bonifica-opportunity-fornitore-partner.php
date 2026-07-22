<?php

/**
 * Bonifica Fornitore/Partner su Opportunità (ID grezzo tipo 690f48d… → GFB).
 *
 *   php tools/bonifica-opportunity-fornitore-partner.php --dry-run
 *   php tools/bonifica-opportunity-fornitore-partner.php --dry-run gfb
 *   php tools/bonifica-opportunity-fornitore-partner.php gfb
 *   php tools/bonifica-opportunity-fornitore-partner.php 690f48d25850e9719
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\OpportunityFornitorePartnerRepair;

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
$repair = new OpportunityFornitorePartnerRepair($em);

$all = $em->getRDBRepository('Opportunity')->find();
$collection = [];

if ($filter) {
    $needle = mb_strtolower($filter);

    foreach ($all as $opportunity) {
        if ($opportunity->getId() === $filter) {
            $collection[] = $opportunity;
            continue;
        }

        $partnerId = (string) $opportunity->get('fornitorePartnerId');

        if ($partnerId !== '' && $partnerId === $filter) {
            $collection[] = $opportunity;
            continue;
        }

        $hay = mb_strtolower(implode(' ', [
            (string) $opportunity->get('name'),
            (string) $opportunity->get('azienda'),
            (string) $opportunity->get('productBrandName'),
            (string) $opportunity->get('fornitorePartnerName'),
            $partnerId,
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
