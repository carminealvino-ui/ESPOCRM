<?php

/**
 * Ricostruisce name Appuntamento (calendario / lista).
 *
 *   php tools/bonifica-appuntamento-nome.php --dry-run
 *   php tools/bonifica-appuntamento-nome.php --dry-run panfili
 *   php tools/bonifica-appuntamento-nome.php panfili
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoNameBuilder;

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
$builder = new AppuntamentoNameBuilder($em);

$all = $em->getRDBRepository('Appuntamento')->find();
$scanned = 0;
$updated = 0;
$skipped = 0;

foreach ($all as $appuntamento) {
    $scanned++;

    if ($filter) {
        $needle = mb_strtolower($filter);

        if ($appuntamento->getId() !== $filter) {
            $hay = mb_strtolower(implode(' ', [
                (string) $appuntamento->get('name'),
                (string) $appuntamento->get('prospectName'),
                (string) $appuntamento->get('parentName'),
                (string) $appuntamento->get('description'),
            ]));

            if (!str_contains($hay, $needle)) {
                continue;
            }
        }
    }

    if (!$builder->needsRebuild($appuntamento)) {
        $skipped++;
        continue;
    }

    $result = $builder->rebuild($appuntamento, $dryRun);

    if (!$result['changed']) {
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $appuntamento->getId()
        . ' | ' . ($result['before'] ?: '∅')
        . ' → ' . ($result['name'] ?? '')
        . PHP_EOL;

    $updated++;
}

echo PHP_EOL
    . "Scansionati={$scanned} aggiornati={$updated} skip={$skipped}"
    . ($dryRun ? ' (dry-run)' : '')
    . PHP_EOL;
