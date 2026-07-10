<?php
/**
 * Allinea statoProvvigione di tutte le provvigioni allo stato del contratto collegato.
 *
 *   php tools/backfill-provvigioni-stato-da-contratto.php
 *   php tools/backfill-provvigioni-stato-da-contratto.php --dry-run
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
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $app->getContainer()->get('injectableFactory');

/** @var ProvvigioneManager $manager */
$manager = $injectableFactory->create(ProvvigioneManager::class);

$quotes = $em->getRDBRepository('Quote')->find();

$synced = 0;
$purged = 0;

foreach ($quotes as $quote) {
    $status = $quote->get('status') ?: $quote->get('statoContratto') ?: '—';
    $before = $em->getRDBRepository('Provvigione')->where(['contrattoId' => $quote->getId()])->count();

    if ($dryRun) {
        echo "[DRY] {$quote->get('numberA')} status={$status} provvigioni={$before}\n";
        $synced++;
        continue;
    }

    $manager->syncProvvigioniStatoForQuote($quote);

    $after = $em->getRDBRepository('Provvigione')->where(['contrattoId' => $quote->getId()])->count();

    if ($after < $before) {
        $purged++;
    } else {
        $synced++;
    }
}

echo "\nSincronizzati: {$synced}, con purge: {$purged}\n";
