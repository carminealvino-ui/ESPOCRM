<?php
/**
 * Rigenera provvigioni consolidate + totaleProvvigioni per tutti i contratti con opportunità.
 *
 *   php tools/backfill-ricalcola-provvigioni.php
 *   php tools/backfill-ricalcola-provvigioni.php --dry-run
 *   php tools/backfill-ricalcola-provvigioni.php --codice=Contratto_00152
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
    ->select(['id', 'name', 'numberA', 'opportunityId', 'totaleProvvigioni'])
    ->where($where)
    ->find();

$total = $quotes->count();
$processed = 0;
$skipped = 0;
$errors = 0;

echo $dryRun
    ? "=== DRY RUN ricalcolo provvigioni ===\n"
    : "=== Ricalcolo provvigioni ===\n";
echo "Contratti trovati: {$total}\n\n";

foreach ($quotes as $quoteLite) {
    $label = (string) ($quoteLite->get('numberA') ?: $quoteLite->get('name') ?: $quoteLite->getId());

    if (!$quoteLite->get('opportunityId')) {
        echo "[SKIP] {$label} — nessuna opportunità collegata\n";
        $skipped++;
        continue;
    }

    $oldTotale = $quoteLite->get('totaleProvvigioni');
    $oldCount = $em->getRDBRepository('Provvigione')
        ->where(['contrattoId' => $quoteLite->getId()])
        ->count();

    if ($dryRun) {
        echo "[DRY] {$label} — provvigioni attuali: {$oldCount}, totale: "
            . ($oldTotale === null || $oldTotale === '' ? 'null' : number_format((float) $oldTotale, 2, '.', ''))
            . "\n";
        $processed++;
        continue;
    }

    try {
        $quote = $em->getEntityById('Quote', $quoteLite->getId());

        if (!$quote) {
            throw new RuntimeException('Quote non trovata');
        }

        $result = $manager->recalculateAllForQuote($quote);
        $quote = $em->getEntityById('Quote', $quote->getId());
        $newTotale = $quote?->get('totaleProvvigioni');
        $newCount = $em->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteLite->getId()])
            ->count();

        echo "[OK] {$label} — provvigioni: {$oldCount} -> {$newCount}, totale: "
            . ($oldTotale === null || $oldTotale === '' ? 'null' : number_format((float) $oldTotale, 2, '.', ''))
            . ' -> '
            . ($newTotale === null || $newTotale === '' ? 'null' : number_format((float) $newTotale, 2, '.', ''))
            . ($result['purged'] ? " (rimosse {$result['purged']})" : '')
            . "\n";
        $processed++;
    } catch (Throwable $e) {
        echo "[ERR] {$label} — {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\nElaborati: {$processed}, saltati: {$skipped}, errori: {$errors}\n";

exit($errors > 0 ? 1 : 0);
