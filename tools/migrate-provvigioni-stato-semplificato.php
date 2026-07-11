<?php
/**
 * Migra stati provvigione legacy → modello semplificato e ricalcola date pagamento.
 *
 *   php tools/migrate-provvigioni-stato-semplificato.php
 *   php tools/migrate-provvigioni-stato-semplificato.php --dry-run
 */

declare(strict_types=1);

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\ProvvigioneStatusSync;
use Espo\ORM\EntityManager;

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

require_once $crmRoot . '/bootstrap.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

$map = [
    'Prevista' => 'Forecast',
    'Consolidata' => 'In pagamento',
    'InInvito' => 'In pagamento',
    'Fatturata' => 'Pagato',
    'Stornata' => 'Inesigibile',
];

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $app->getContainer()->get('injectableFactory');

/** @var ProvvigioneStatusSync $statusSync */
$statusSync = $injectableFactory->create(ProvvigioneStatusSync::class);

echo "=== Migrazione stati provvigione ===\n";

$provvigioni = $em->getRDBRepository('Provvigione')->find();
$updated = 0;

foreach ($provvigioni as $provvigione) {
    $current = (string) $provvigione->get('statoProvvigione');

    if (isset($map[$current])) {
        echo "Provvigione {$provvigione->getId()}: {$current} → {$map[$current]}\n";

        if (!$dryRun) {
            $provvigione->set('statoProvvigione', $map[$current]);
            $em->saveEntity($provvigione, ['skipHooks' => true, 'silent' => true]);
        }

        $updated++;
    }
}

$quotes = $em->getRDBRepository('Quote')->where(['opportunityId!=' => null])->find();
$synced = 0;

foreach ($quotes as $quote) {
    if (!$dryRun) {
        $statusSync->syncProvvigioniForQuote($quote);
    }

    $synced++;
}

echo "Stati migrati: {$updated}\n";
echo "Contratti sincronizzati: {$synced}\n";
echo $dryRun ? "DRY RUN — nessuna modifica salvata\n" : "=== Fatto ===\n";
