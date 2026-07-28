<?php
/**
 * Crea To-Do verifica installazione solo su contratti in scadenza
 * (data installazione da oggi a +N giorni, non Invalidi).
 *
 *   php tools/backfill-verifica-installazione-task.php --dry-run
 *   php tools/backfill-verifica-installazione-task.php --apply
 *   php tools/backfill-verifica-installazione-task.php --apply --days=30
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\QuoteInstallazioneVerificaTaskSync;
use Espo\Custom\Tools\DateTime\BusinessDateTime;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$apply = in_array('--apply', $argv ?? [], true);
$limit = 0;
$days = 60;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }

    if (str_starts_with($arg, '--days=')) {
        $days = max(1, (int) substr($arg, 7));
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

$tz = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
$today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
$until = (new \DateTimeImmutable('now', $tz))->modify("+{$days} days")->format('Y-m-d');

$collection = $em->getRDBRepository('Quote')
    ->where([
        'dataInstallazione!=' => null,
        'dataInstallazione>=' => $today,
        'dataInstallazione<=' => $until,
        'status!=' => ['Installato', 'Invalido'],
        'statoContratto!=' => ['Chiuso', 'Annullato', 'Recesso'],
    ])
    ->order('dataInstallazione', 'ASC')
    ->find();

$processed = 0;
$updated = 0;
$skipped = 0;

echo "=== Backfill To-Do verifica installazione (in scadenza) ===\n";
echo ($dryRun && !$apply) ? "Modalità: DRY-RUN\n" : "Modalità: APPLY\n";
echo "Finestra: {$today} → {$until} ({$days} giorni)\n";
echo "Esclusi: Installato/Chiuso/Invalido/Annullato/Recesso\n\n";

foreach ($collection as $quoteLite) {
    if ($limit > 0 && $processed >= $limit) {
        break;
    }

    $processed++;
    $quote = $em->getEntityById('Quote', $quoteLite->getId());

    if (!$quote) {
        $skipped++;
        continue;
    }

    if ($quote->get('verificaInstallazioneTaskId')) {
        echo "[SKIP già collegato] " . ($quote->get('name') ?: $quote->getId()) . "\n";
        $skipped++;
        continue;
    }

    $label = ($quote->get('name') ?: $quote->getId())
        . ' | dataInstallazione=' . (string) $quote->get('dataInstallazione');

    if ($dryRun && !$apply) {
        echo "[DRY] {$label}\n";
        $updated++;
        continue;
    }

    try {
        $sync->syncFromQuote($quote);
        echo "[OK] {$label}\n";
        $updated++;
    } catch (Throwable $e) {
        fwrite(STDERR, '[ERR] ' . $quote->getId() . ': ' . $e->getMessage() . "\n");
    }
}

echo "\nElaborati: {$processed}, sync: {$updated}, saltati: {$skipped}\n";

if ($dryRun && !$apply) {
    echo "Per applicare: php tools/backfill-verifica-installazione-task.php --apply\n";
}
