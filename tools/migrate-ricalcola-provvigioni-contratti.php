<?php
/**
 * Ricalcola provvigioni consolidate e aggiorna stato per tutti i contratti.
 *
 *   php tools/migrate-ricalcola-provvigioni-contratti.php
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --dry-run
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --id=6a4e3247d258ac77f
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyId = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $onlyId = substr($arg, 5);
    }
}

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

/** @var ProvvigioneManager $provvigioneManager */
$provvigioneManager = $app->getContainer()->getByClass(ProvvigioneManager::class);

$where = [];

if ($onlyId) {
    $where['id'] = $onlyId;
}

$collection = $em
    ->getRDBRepository('Quote')
    ->where($where)
    ->order('createdAt', 'ASC')
    ->find();

$processed = 0;
$skipped = 0;
$errors = 0;
$statusUpdated = 0;
$provvigioniUpdated = 0;

echo $dryRun ? "=== DRY RUN ===\n" : "=== MIGRAZIONE PROVVIGIONI CONTRATTI ===\n";

foreach ($collection as $quote) {
    $label = ($quote->get('number') ?: $quote->get('numberA') ?: $quote->getId())
        . ' — ' . ($quote->get('name') ?? '');

    if (!$quote->get('opportunityId')) {
        echo "[SKIP] {$label} (senza opportunità)\n";
        $skipped++;
        continue;
    }

    $nextStatus = match ($quote->get('status')) {
        'Bozza' => 'In lavorazione',
        'Draft' => 'Presented',
        default => null,
    };

    $hasNumero = trim((string) ($quote->get('numeroContratto') ?? '')) !== ''
        || trim((string) ($quote->get('number') ?? '')) !== '';

    if ($dryRun) {
        echo "[DRY] {$label}\n";
        if ($hasNumero && $nextStatus !== null) {
            echo "      stato: {$quote->get('status')} → {$nextStatus}\n";
        }
        echo "      ricalcolo provvigioni\n";
        $processed++;
        continue;
    }

    try {
        if ($hasNumero && $nextStatus !== null) {
            $quote->set('status', $nextStatus);
            $em->saveEntity($quote, [
                'skipHooks' => true,
                'silent' => true,
                'skipFormula' => true,
            ]);
            $statusUpdated++;
        }

        $result = $provvigioneManager->recalculateAllForQuote($quote);

        echo "[OK] {$label} — provvigioni: {$result['created']} (purge {$result['purged']})\n";
        $processed++;
        $provvigioniUpdated += $result['created'];
    } catch (Throwable $e) {
        echo "[ERR] {$label} — {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\nContratti elaborati: {$processed}\n";
echo "Saltati: {$skipped}\n";
echo "Stati aggiornati: {$statusUpdated}\n";
echo "Provvigioni create/aggiornate: {$provvigioniUpdated}\n";
echo "Errori: {$errors}\n";

exit($errors > 0 ? 1 : 0);
