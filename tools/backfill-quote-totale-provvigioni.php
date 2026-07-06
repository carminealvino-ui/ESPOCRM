<?php

/**
 * Ricalcola totaleProvvigioni su tutti i contratti dalla somma importoConsolidato.
 *
 * Uso: php tools/backfill-quote-totale-provvigioni.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv ?? [], true);

require dirname(__DIR__) . '/bootstrap.php';

use Espo\Custom\Services\ProvvigioneManager;

$app = new Espo\Core\Application();
$app->setupSystemUser();

$container = $app->getContainer();
$entityManager = $container->get('entityManager');
/** @var ProvvigioneManager $manager */
$manager = $container->get('injectableFactory')->create(ProvvigioneManager::class);

$quotes = $entityManager
    ->getRDBRepository('Quote')
    ->find();

$updated = 0;
$unchanged = 0;

foreach ($quotes as $quote) {
    $expected = $manager->resolveTotaleProvvigioniForQuoteId($quote->getId());
    $current = $quote->get('totaleProvvigioni');

    $expectedNorm = $expected === null ? null : round((float) $expected, 2);
    $currentNorm = $current === null || $current === '' ? null : round((float) $current, 2);

    if ($expectedNorm === $currentNorm) {
        $unchanged++;
        continue;
    }

    echo sprintf(
        "%s: %s → %s\n",
        $quote->get('name') ?: $quote->getId(),
        $currentNorm === null ? 'null' : number_format($currentNorm, 2, '.', ''),
        $expectedNorm === null ? 'null' : number_format($expectedNorm, 2, '.', '')
    );

    if (!$dryRun) {
        $manager->refreshQuoteTotaleProvvigioni($quote);
    }

    $updated++;
}

echo sprintf(
    "\n%s: %d aggiornati, %d già corretti\n",
    $dryRun ? 'DRY-RUN' : 'FATTO',
    $updated,
    $unchanged
);
