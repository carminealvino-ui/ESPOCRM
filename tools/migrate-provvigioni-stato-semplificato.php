<?php
/**
 * Migra stati provvigione legacy → modello semplificato.
 * Copia stato contratto da Opportunity → Quote, poi sincronizza provvigioni.
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

echo "=== Migrazione stati provvigione (driver: Contratto) ===\n";

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
$copied = 0;
$synced = 0;

foreach ($quotes as $quote) {
    $opportunity = $em->getEntityById('Opportunity', $quote->get('opportunityId'));

    if (!$opportunity) {
        continue;
    }

    $needsCopy = !$quote->get('statoContratto')
        || trim((string) $quote->get('statoContratto')) === '';

    if ($needsCopy) {
        echo "Quote {$quote->getId()}: copia stato da opportunità\n";

        if (!$dryRun) {
            $statusSync->copyContractFieldsFromOpportunity($quote, $opportunity);
            $em->saveEntity($quote, ['skipHooks' => true, 'silent' => true]);
        }

        $copied++;
    }

    if (!$dryRun) {
        $quote = $em->getEntityById('Quote', $quote->getId()) ?? $quote;
        $statusSync->syncProvvigioniForQuote($quote);
    }

    $synced++;
}

echo "Stati migrati: {$updated}\n";
echo "Contratti con stato copiato da opportunità: {$copied}\n";
echo "Contratti sincronizzati: {$synced}\n";
echo $dryRun ? "DRY RUN — nessuna modifica salvata\n" : "=== Fatto ===\n";
