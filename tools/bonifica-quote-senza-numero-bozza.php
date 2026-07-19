<?php

/**
 * Contratti senza Numero Contratto → status Bozza.
 *
 *   php tools/bonifica-quote-senza-numero-bozza.php --dry-run
 *   php tools/bonifica-quote-senza-numero-bozza.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$dryRun = in_array('--dry-run', $argv ?? [], true);

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

$collection = $em->getRDBRepository('Quote')->find();

$updated = 0;
$skipped = 0;

foreach ($collection as $quote) {
    $numero = trim((string) ($quote->get('numeroContratto') ?: $quote->get('number') ?: ''));

    if ($numero !== '') {
        $skipped++;
        continue;
    }

    $status = (string) $quote->get('status');

    if ($status === 'Bozza' || $status === 'Draft') {
        $skipped++;
        continue;
    }

    if (in_array($status, $terminal, true)) {
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK ')
        . $quote->getId()
        . ' '
        . $quote->get('numberA')
        . ' status='
        . $status
        . ' → Bozza'
        . PHP_EOL;

    if (!$dryRun) {
        $quote->set('status', 'Bozza');
        $em->saveEntity($quote, [
            'silent' => true,
            'skipAcl' => true,
        ]);
    }

    $updated++;
}

echo PHP_EOL;
echo ($dryRun ? 'DRY-RUN ' : '') . "Aggiornati: {$updated}\n";
echo "Saltati: {$skipped}\n";
