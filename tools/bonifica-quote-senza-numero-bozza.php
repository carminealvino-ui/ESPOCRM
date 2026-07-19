<?php

/**
 * Contratti senza Numero Contratto (solo campo numeroContratto) → status Bozza.
 *
 *   php tools/bonifica-quote-senza-numero-bozza.php --dry-run
 *   php tools/bonifica-quote-senza-numero-bozza.php
 *   php tools/bonifica-quote-senza-numero-bozza.php Contratto_00154
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

$terminal = [
    'Installato',
    'Recesso',
    'Invalido',
    'Canceled',
    'Finanziamento Rifiutato',
];

$repo = $em->getRDBRepository('Quote');

if ($filter) {
    $collection = $repo
        ->where([
            'OR' => [
                ['id' => $filter],
                ['numberA*' => $filter],
                ['name*' => $filter],
                ['numeroContratto*' => $filter],
            ],
        ])
        ->find();
} else {
    $collection = $repo->find();
}

$updated = 0;
$skipped = 0;

foreach ($collection as $quote) {
    $numero = trim((string) ($quote->get('numeroContratto') ?? ''));

    if ($numero !== '') {
        echo 'SKIP has-numero ' . $quote->getId() . ' ' . $quote->get('numberA')
            . ' numeroContratto=' . $numero . PHP_EOL;
        $skipped++;
        continue;
    }

    $status = (string) $quote->get('status');

    if ($status === 'Bozza' || $status === 'Draft') {
        echo 'SKIP already-bozza ' . $quote->getId() . ' ' . $quote->get('numberA') . PHP_EOL;
        $skipped++;
        continue;
    }

    if (in_array($status, $terminal, true)) {
        echo 'SKIP terminal ' . $quote->getId() . ' status=' . $status . PHP_EOL;
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK ')
        . $quote->getId()
        . ' '
        . $quote->get('numberA')
        . ' status='
        . $status
        . ' number='
        . $quote->get('number')
        . ' → Bozza'
        . PHP_EOL;

    if (!$dryRun) {
        // Update SQL diretto: evita che altri hook/ACL blocchino il cambio.
        $update = $em->getQueryBuilder()
            ->update()
            ->in('Quote')
            ->set(['status' => 'Bozza'])
            ->where(['id' => $quote->getId()])
            ->build();

        $em->getQueryExecutor()->execute($update);
    }

    $updated++;
}

echo PHP_EOL;
echo ($dryRun ? 'DRY-RUN ' : '') . "Aggiornati: {$updated}\n";
echo "Saltati: {$skipped}\n";
