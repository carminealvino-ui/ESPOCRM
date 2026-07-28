<?php
/**
 * Bonifica: contratti Invalido/Annullato/Recesso → dataInstallazione = null
 * e annulla eventuali To-Do verifica installazione.
 *
 *   php tools/bonifica-invalido-data-installazione.php --dry-run
 *   php tools/bonifica-invalido-data-installazione.php --apply
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\QuoteInstallazioneVerificaTaskSync;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$apply = in_array('--apply', $argv ?? [], true);
$limit = 0;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

if (!$dryRun && !$apply) {
    $dryRun = true;
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
/** @var QuoteInstallazioneVerificaTaskSync $sync */
$sync = $app->getContainer()->get('injectableFactory')->create(QuoteInstallazioneVerificaTaskSync::class);

$collection = $em->getRDBRepository('Quote')
    ->where([
        'dataInstallazione!=' => null,
        'OR' => [
            ['status' => 'Invalido'],
            ['statoContratto' => ['Annullato', 'Recesso']],
        ],
    ])
    ->order('modifiedAt', 'DESC')
    ->find();

$processed = 0;
$updated = 0;

echo "=== Bonifica dataInstallazione su contratti Invalidi ===\n";
echo ($dryRun && !$apply) ? "Modalità: DRY-RUN\n\n" : "Modalità: APPLY\n\n";

foreach ($collection as $quoteLite) {
    if ($limit > 0 && $processed >= $limit) {
        break;
    }

    $processed++;
    $quote = $em->getEntityById('Quote', $quoteLite->getId());

    if (!$quote) {
        continue;
    }

    $label = ($quote->get('name') ?: $quote->getId())
        . ' | status=' . (string) $quote->get('status')
        . ' | statoContratto=' . (string) $quote->get('statoContratto')
        . ' | dataInstallazione=' . (string) $quote->get('dataInstallazione');

    if ($dryRun && !$apply) {
        echo "[DRY] {$label}\n";
        $updated++;
        continue;
    }

    try {
        $quote->set('dataInstallazione', null);
        $em->saveEntity($quote, [
            'silent' => true,
            'skipHooks' => false,
        ]);
        $sync->syncFromQuote($quote);
        echo "[OK] {$label}\n";
        $updated++;
    } catch (Throwable $e) {
        fwrite(STDERR, '[ERR] ' . $quote->getId() . ': ' . $e->getMessage() . "\n");
    }
}

echo "\nElaborati: {$processed}, aggiornati: {$updated}\n";

if ($dryRun && !$apply) {
    echo "Per applicare: php tools/bonifica-invalido-data-installazione.php --apply\n";
}
