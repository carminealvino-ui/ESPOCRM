<?php
/**
 * Riallinea Quote.totaleProvvigioni = somma Provvigioni (importoConsolidato).
 *
 *   php tools/backfill-totale-provvigioni.php
 *   php tools/backfill-totale-provvigioni.php --dry-run
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\ProvvigioneManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');
/** @var ProvvigioneManager $manager */
$manager = $container->getByClass(ProvvigioneManager::class);

$quotes = $em->getRDBRepository('Quote')->select(['id', 'name', 'totaleProvvigioni'])->find();

$updated = 0;
$skipped = 0;
$examples = [];

foreach ($quotes as $quote) {
    $old = $quote->get('totaleProvvigioni');
    $new = $manager->resolveTotaleProvvigioniForQuoteId($quote->getId());

    $oldRound = $old === null || $old === '' ? null : round((float) $old, 2);
    $newRound = $new === null ? null : round((float) $new, 2);

    if ($oldRound === $newRound) {
        $skipped++;
        continue;
    }

    $label = ($quote->get('name') ?: $quote->getId())
        . ' : '
        . ($oldRound === null ? 'null' : (string) $oldRound)
        . ' => '
        . ($newRound === null ? 'null' : (string) $newRound);

    if (count($examples) < 8) {
        $examples[] = $label;
    }

    if ($dryRun) {
        echo '[DRY] ' . $label . "\n";
        $updated++;
        continue;
    }

    $manager->refreshQuoteTotaleProvvigioni($quote);
    echo '[OK] ' . $label . "\n";
    $updated++;
}

echo "\nAggiornate: {$updated}, già ok: {$skipped}\n";

if ($examples !== []) {
    echo "\nEsempi:\n";
    foreach ($examples as $line) {
        echo "  - {$line}\n";
    }
}
