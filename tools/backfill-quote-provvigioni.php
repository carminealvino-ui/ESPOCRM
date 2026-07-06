<?php

/**
 * Ricalcola provvigioni su tutti i contratti con opportunità collegata.
 *
 * Uso: php tools/backfill-quote-provvigioni.php [--dry-run]
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
    ->where([
        'opportunityId!=' => null,
    ])
    ->find();

$ok = 0;
$skip = 0;
$errors = 0;

foreach ($quotes as $quote) {
    if (!$quote->get('opportunityId')) {
        $skip++;
        continue;
    }

    $label = $quote->get('name') ?: $quote->getId();

    if ($dryRun) {
        echo "DRY-RUN {$label}\n";
        $ok++;
        continue;
    }

    try {
        $result = $manager->recalculateAllForQuote($quote);
        $quote = $entityManager->getEntityById('Quote', $quote->getId());
        $totale = $quote ? $quote->get('totaleProvvigioni') : null;

        echo sprintf(
            "OK %s — provvigioni: %d, totale: %s\n",
            $label,
            (int) ($result['created'] ?? 0),
            $totale === null ? 'null' : number_format((float) $totale, 2, '.', '')
        );
        $ok++;
    } catch (Throwable $e) {
        echo "ERR {$label}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo sprintf(
    "\n%s: %d elaborati, %d saltati, %d errori\n",
    $dryRun ? 'DRY-RUN' : 'FATTO',
    $ok,
    $skip,
    $errors
);

exit($errors > 0 ? 1 : 0);
