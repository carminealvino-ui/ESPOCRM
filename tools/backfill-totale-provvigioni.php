<?php
/**
 * Riallinea Quote.totaleProvvigioni = somma Provvigioni (importoConsolidato).
 * Usa UPDATE SQL diretto per non essere sovrascritto da formula legacy in produzione.
 *
 *   php tools/backfill-totale-provvigioni.php
 *   php tools/backfill-totale-provvigioni.php --dry-run
 *   php tools/backfill-totale-provvigioni.php --codice=Contratto_00152
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
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyCodice = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--codice=')) {
        $onlyCodice = substr($arg, 9);
    }
}

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->get('entityManager');

/** @var ProvvigioneManager $manager */
$factory = $container->get('injectableFactory');
$manager = $factory->create(ProvvigioneManager::class);

$where = [];

if ($onlyCodice) {
    $where['numberA'] = $onlyCodice;
}

$quotes = $em->getRDBRepository('Quote')
    ->select(['id', 'name', 'numberA', 'totaleProvvigioni'])
    ->where($where)
    ->find();

$total = $quotes->count();
$updated = 0;
$skipped = 0;
$errors = 0;
$examples = [];

echo ($dryRun ? "=== DRY RUN totaleProvvigioni ===\n" : "=== Backfill totaleProvvigioni ===\n");
echo "Contratti trovati: {$total}\n\n";

foreach ($quotes as $quote) {
    $old = $quote->get('totaleProvvigioni');
    $new = $manager->resolveTotaleProvvigioniForQuoteId($quote->getId());

    $oldRound = $old === null || $old === '' ? null : round((float) $old, 2);
    $newRound = $new === null ? null : round((float) $new, 2);

    $label = ($quote->get('numberA') ?: $quote->get('name') ?: $quote->getId())
        . ' : '
        . ($oldRound === null ? 'null' : number_format($oldRound, 2, '.', ''))
        . ' => '
        . ($newRound === null ? 'null' : number_format($newRound, 2, '.', ''));

    if ($oldRound === $newRound) {
        $skipped++;
        continue;
    }

    if (count($examples) < 10) {
        $examples[] = $label;
    }

    if ($dryRun) {
        echo '[DRY] ' . $label . "\n";
        $updated++;
        continue;
    }

    try {
        if (!$manager->updateQuoteTotaleProvvigioniInDatabase($quote->getId(), $newRound)) {
            $manager->refreshQuoteTotaleProvvigioni($quote);
        }

        $check = $em->getEntityById('Quote', $quote->getId());
        $saved = $check?->get('totaleProvvigioni');
        $savedRound = $saved === null || $saved === '' ? null : round((float) $saved, 2);

        if ($savedRound !== $newRound) {
            echo '[WARN] ' . $label . " — salvato {$savedRound}, atteso {$newRound}\n";
            $errors++;
            continue;
        }

        echo '[OK] ' . $label . "\n";
        $updated++;
    } catch (Throwable $e) {
        echo '[ERR] ' . $label . ' — ' . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nAggiornate: {$updated}, già ok: {$skipped}, errori: {$errors}\n";

if ($examples !== []) {
    echo "\nEsempi modificati:\n";
    foreach ($examples as $line) {
        echo "  - {$line}\n";
    }
}

exit($errors > 0 ? 1 : 0);
