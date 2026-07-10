<?php
/**
 * Allinea statoProvvigione di tutte le provvigioni allo stato del contratto collegato.
 *
 *   php tools/backfill-provvigioni-stato-da-contratto.php
 *   php tools/backfill-provvigioni-stato-da-contratto.php --dry-run
 *   php tools/backfill-provvigioni-stato-da-contratto.php --verbose
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\ProvvigioneContractStatusSync;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$verbose = in_array('--verbose', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $app->getContainer()->get('injectableFactory');

/** @var ProvvigioneManager $manager */
$manager = $injectableFactory->create(ProvvigioneManager::class);

/** @var ProvvigioneContractStatusSync $statusSync */
$statusSync = $injectableFactory->create(ProvvigioneContractStatusSync::class);

$quotes = $em->getRDBRepository('Quote')->find();

$synced = 0;
$purged = 0;
$skipped = 0;

foreach ($quotes as $quote) {
    $codice = $quote->get('numberA') ?: $quote->get('number') ?: $quote->getId();
    $before = $em->getRDBRepository('Provvigione')->where(['contrattoId' => $quote->getId()])->count();

    if ($before === 0) {
        $skipped++;
        continue;
    }

    $target = $statusSync->shouldPurgeQuote($quote) ? 'PURGE' : ($statusSync->resolveStatoFromQuote($quote) ?? '—');
    $fields = implode(', ', $statusSync->describeQuoteStatuses($quote));

    if ($dryRun) {
        echo "[DRY] {$codice} → {$target} ({$fields}) provvigioni={$before}\n";
        $synced++;
        continue;
    }

    $manager->syncProvvigioniStatoForQuote($quote);

    $after = $em->getRDBRepository('Provvigione')->where(['contrattoId' => $quote->getId()])->count();

    if ($verbose || $after !== $before || $target !== '—') {
        $stati = [];

        foreach ($em->getRDBRepository('Provvigione')->where(['contrattoId' => $quote->getId()])->find() as $p) {
            $stati[] = ($p->get('tipo') ?: '?') . ':' . ($p->get('statoProvvigione') ?: '?');
        }

        echo "[OK] {$codice} → {$target} ({$fields}) | " . ($stati ? implode(', ', $stati) : 'nessuna') . "\n";
    }

    if ($after < $before) {
        $purged++;
    } else {
        $synced++;
    }
}

echo "\nSincronizzati: {$synced}, con purge: {$purged}, senza provvigioni: {$skipped}\n";
