<?php

/**
 * Se Stato = Bozza/Draft → Stato Contratto vuoto (null).
 *
 *   php tools/bonifica-quote-bozza-stato-null.php --dry-run
 *   php tools/bonifica-quote-bozza-stato-null.php
 *   php tools/bonifica-quote-bozza-stato-null.php Contratto_00154
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

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

$repo = $em->getRDBRepository('Quote');

if ($filter) {
    $collection = $repo
        ->where([
            'OR' => [
                ['id' => $filter],
                ['numberA*' => $filter],
                ['name*' => $filter],
            ],
        ])
        ->find();
} else {
    $collection = $repo
        ->where([
            'status' => ['Bozza', 'Draft'],
        ])
        ->find();
}

$updated = 0;
$skipped = 0;

foreach ($collection as $quote) {
    $status = (string) ($quote->get('status') ?? '');
    $stato = $quote->get('statoContratto');
    $statoStr = $stato === null ? '(null)' : (string) $stato;

    if ($status !== 'Bozza' && $status !== 'Draft') {
        echo 'SKIP not-bozza ' . $quote->getId() . ' ' . $quote->get('numberA')
            . ' status=' . $status . PHP_EOL;
        $skipped++;
        continue;
    }

    if ($stato === null || $stato === '') {
        echo 'SKIP already-empty ' . $quote->getId() . ' ' . $quote->get('numberA') . PHP_EOL;
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $quote->getId() . ' ' . $quote->get('numberA')
        . ' status=' . $status
        . ' statoContratto ' . $statoStr . ' → (vuoto)'
        . PHP_EOL;

    if (!$dryRun) {
        $quote->set('statoContratto', null);
        $em->saveEntity($quote, [
            'silent' => true,
            'skipHooks' => true,
            'skipAll' => true,
        ]);
    }

    $updated++;
}

echo PHP_EOL . "Aggiornati={$updated} skip={$skipped}" . ($dryRun ? ' (dry-run)' : '') . PHP_EOL;
