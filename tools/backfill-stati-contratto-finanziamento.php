<?php
/**
 * Allinea Stato Finanziamento:
 * - Recesso → Annullato
 * - Chiuso + finanziamento → Approvato
 * - Alias obsoleti: "In Attesa Documentazione" → "In attesa documentazione"
 *                 "In lavorazione" → "In valutazione"
 *
 *   php tools/backfill-stati-contratto-finanziamento.php --dry-run
 *   php tools/backfill-stati-contratto-finanziamento.php
 *   php tools/backfill-stati-contratto-finanziamento.php --codice=Contratto_00106
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyCodice = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--codice=')) {
        $onlyCodice = substr($arg, 9);
    }
}

$aliases = [
    'In Attesa Documentazione' => 'In attesa documentazione',
    'In lavorazione' => 'In valutazione',
];

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

$query = $em->getRDBRepository('Quote');

if ($onlyCodice) {
    $query->where([
        'OR' => [
            ['number' => $onlyCodice],
            ['numberA' => $onlyCodice],
            ['name' => $onlyCodice],
        ],
    ]);
}

$updated = 0;
$skipped = 0;

foreach ($query->find() as $quote) {
    $label = $quote->get('numberA') ?: $quote->get('name') ?: $quote->getId();
    $statoContratto = trim((string) ($quote->get('statoContratto') ?? ''));
    $statoFin = trim((string) ($quote->get('statoFinanziamento') ?? ''));
    $finanziamento = (bool) $quote->get('finanziamento');
    $from = $statoFin === '' ? '(vuoto)' : $statoFin;
    $patch = [];

    if (isset($aliases[$statoFin])) {
        $statoFin = $aliases[$statoFin];
        $patch['statoFinanziamento'] = $statoFin;
    }

    if ($statoContratto === 'Recesso' && $statoFin !== 'Annullato') {
        $statoFin = 'Annullato';
        $patch['statoFinanziamento'] = 'Annullato';
    }

    if ($statoContratto === 'Chiuso') {
        $hasFinancing = $finanziamento || $statoFin !== '';

        if ($hasFinancing) {
            if (!$finanziamento) {
                $patch['finanziamento'] = true;
            }
            if ($statoFin !== 'Approvato') {
                $statoFin = 'Approvato';
                $patch['statoFinanziamento'] = 'Approvato';
            }
        }
    }

    if (!$patch) {
        $skipped++;
        continue;
    }

    $to = $patch['statoFinanziamento'] ?? $statoFin;
    $extra = isset($patch['finanziamento']) ? ' +finanziamento=true' : '';
    echo ($dryRun ? 'DRY ' : 'UPD ')
        . "{$label} [{$statoContratto}]: {$from} → {$to}{$extra}\n";

    if (!$dryRun) {
        $quote->set($patch);
        $em->saveEntity($quote, ['silent' => true, 'skipHooks' => true]);
    }

    $updated++;
}

echo "Fatto. aggiornati={$updated} skip={$skipped} dryRun=" . ($dryRun ? 'yes' : 'no') . "\n";
