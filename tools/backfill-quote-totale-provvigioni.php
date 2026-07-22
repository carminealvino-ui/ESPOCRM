<?php
/**
 * Ricalcola totaleProvvigioni = somma importoConsolidato (fallback importo).
 *
 *   php tools/backfill-quote-totale-provvigioni.php --dry-run
 *   php tools/backfill-quote-totale-provvigioni.php
 *   php tools/backfill-quote-totale-provvigioni.php --codice=Contratto_00153
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
$factory = $container->get('injectableFactory');
/** @var ProvvigioneManager $manager */
$manager = $factory->create(ProvvigioneManager::class);

$where = [];

if ($onlyCodice) {
    $where['numberA'] = $onlyCodice;
}

$quotes = $em->getRDBRepository('Quote')
    ->select(['id', 'name', 'numberA', 'totaleProvvigioni'])
    ->where($where)
    ->find();

$updated = 0;
$unchanged = 0;
$errors = 0;

echo ($dryRun ? "=== DRY RUN totaleProvvigioni ===\n" : "=== Backfill totaleProvvigioni ===\n");
echo 'Contratti: ' . $quotes->count() . "\n\n";

foreach ($quotes as $quoteLite) {
    $label = (string) ($quoteLite->get('numberA') ?: $quoteLite->get('name') ?: $quoteLite->getId());

    try {
        $expected = $manager->resolveTotaleProvvigioniForQuoteId($quoteLite->getId());
        $current = $quoteLite->get('totaleProvvigioni');

        $expectedNorm = $expected === null ? null : round((float) $expected, 2);
        $currentNorm = $current === null || $current === '' ? null : round((float) $current, 2);

        if ($expectedNorm === $currentNorm) {
            $unchanged++;
            continue;
        }

        $line = sprintf(
            '%s: %s → %s',
            $label,
            $currentNorm === null ? 'null' : number_format($currentNorm, 2, '.', ''),
            $expectedNorm === null ? 'null' : number_format($expectedNorm, 2, '.', '')
        );

        if ($dryRun) {
            echo '[DRY] ' . $line . "\n";
            $updated++;
            continue;
        }

        $quote = $em->getEntityById('Quote', $quoteLite->getId());

        if (!$quote) {
            throw new RuntimeException('Quote non trovata');
        }

        $manager->refreshQuoteTotaleProvvigioni($quote);

        $check = $em->getEntityById('Quote', $quote->getId());
        $saved = $check?->get('totaleProvvigioni');
        $savedNorm = $saved === null || $saved === '' ? null : round((float) $saved, 2);

        if ($savedNorm !== $expectedNorm) {
            echo '[WARN] ' . $line . " — salvato {$savedNorm}\n";
            $errors++;
            continue;
        }

        echo '[OK] ' . $line . "\n";
        $updated++;
    } catch (Throwable $e) {
        echo '[ERR] ' . $label . ' — ' . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nAggiornati: {$updated}, già ok: {$unchanged}, errori: {$errors}\n";

exit($errors > 0 ? 1 : 0);
