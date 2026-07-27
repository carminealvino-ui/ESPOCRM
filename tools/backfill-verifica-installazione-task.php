<?php
/**
 * Crea To-Do verifica installazione su contratti già con dataInstallazione.
 *
 *   php tools/backfill-verifica-installazione-task.php --dry-run
 *   php tools/backfill-verifica-installazione-task.php --apply --limit=50
 *   php tools/backfill-verifica-installazione-task.php --apply --include-past
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\QuoteInstallazioneVerificaTaskSync;
use Espo\Custom\Tools\DateTime\BusinessDateTime;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$apply = in_array('--apply', $argv ?? [], true);
$includePast = in_array('--include-past', $argv ?? [], true);
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

$today = (new \DateTimeImmutable('now', new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)))
    ->format('Y-m-d');

$where = [
    'dataInstallazione!=' => null,
    'status!=' => 'Installato',
    'statoContratto!=' => 'Chiuso',
];

if (!$includePast) {
    // Solo installazioni future (o oggi): evita flood di To-Do scaduti.
    $where['dataInstallazione>='] = $today;
}

$collection = $em->getRDBRepository('Quote')
    ->where($where)
    ->order('dataInstallazione', 'ASC')
    ->find();

$processed = 0;
$updated = 0;
$skipped = 0;

echo "=== Backfill To-Do verifica installazione ===\n";
echo ($dryRun && !$apply) ? "Modalità: DRY-RUN\n" : "Modalità: APPLY\n";
echo $includePast
    ? "Filtro date: TUTTE (include passate)\n\n"
    : "Filtro date: solo dataInstallazione >= {$today}\n\n";

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
