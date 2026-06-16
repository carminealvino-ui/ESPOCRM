#!/usr/bin/env php
<?php
/**
 * Ricalcola e salva totaleProvvigioni su tutti i contratti (Quote).
 * Necessario se il report griglia mostra 0 nonostante le provvigioni siano presenti.
 *
 *   php tools/backfill-quote-totale-provvigioni.php --dry-run
 *   php tools/backfill-quote-totale-provvigioni.php --verbose
 *   php tools/backfill-quote-totale-provvigioni.php --quote-id=ID_CONTRATTO
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (es. ~/public_html/crm/mec-group).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Custom\Services\QuoteProvvigioniSync;

$app = new Application();
$app->setupSystemUser();

$container = $app->getContainer();
$em = $container->get('entityManager');
/** @var Config $config */
$config = $container->get('config');

$sync = new QuoteProvvigioniSync($em, $config);

$dryRun = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$quoteFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--quote-id=')) {
        $quoteFilter = substr($arg, 11);
    }
}

$where = [];

if ($quoteFilter) {
    $where['id'] = $quoteFilter;
}

$collection = $em->getRDBRepository('Quote')->where($where)->find();

$updated = 0;
$skipped = 0;

foreach ($collection as $quote) {
    $quoteId = $quote->getId();
    $sum = $sync->sumTotaleProvvigioni($quoteId);
    $stored = (float) ($quote->get('totaleProvvigioni') ?? 0);

    if (abs($stored - $sum) < 0.001 && $quote->get('totaleProvvigioniCurrency')) {
        $skipped++;

        if ($verbose) {
            fwrite(STDOUT, "SKIP {$quoteId} totale={$stored}\n");
        }

        continue;
    }

    if ($verbose || $dryRun) {
        fwrite(STDOUT, ($dryRun ? '[dry-run] ' : '') . "Quote {$quoteId}: {$stored} -> {$sum}\n");
    }

    if ($dryRun) {
        $updated++;
        continue;
    }

    $sync->syncTotaleProvvigioniOnQuote($quoteId);
    $updated++;
}

fwrite(STDOUT, "Contratti aggiornati: {$updated}, già ok: {$skipped}\n");

exit(0);
