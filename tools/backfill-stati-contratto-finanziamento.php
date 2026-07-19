<?php
/**
 * Normalizza i 3 stati Contratto + regole crociate (incluso Chiuso).
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
use Espo\Custom\Services\ContrattoStatiRules;
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
/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');
$rules = new ContrattoStatiRules();

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
    $before = [
        'status' => (string) ($quote->get('status') ?? ''),
        'statoContratto' => (string) ($quote->get('statoContratto') ?? ''),
        'statoFinanziamento' => (string) ($quote->get('statoFinanziamento') ?? ''),
        'finanziamento' => (bool) $quote->get('finanziamento'),
    ];

    // Pre-pass: se era Chiuso o Installato (lavorazione), allinea prima delle regole
    $stato = trim($before['statoContratto']);
    $status = trim($before['status']);

    if ($stato === 'Chiuso' || $status === 'Installato' || $stato === 'Installato') {
        if ($status !== 'Invalido' && $status !== 'Installato') {
            $quote->set('status', 'Installato');
        }
        if ($stato !== 'Recesso' && $stato !== 'Annullato' && $stato !== 'Sospeso') {
            $quote->set('statoContratto', 'Chiuso');
        }
    }

    if (in_array($stato, ['Appuntamento Fissato', 'Appuntamento fissato'], true)) {
        if (!in_array($status, ['Appuntamento fissato', 'Installato', 'Invalido'], true)) {
            $quote->set('status', 'Appuntamento fissato');
        }
    }

    $rules->apply($quote);

    $after = [
        'status' => (string) ($quote->get('status') ?? ''),
        'statoContratto' => (string) ($quote->get('statoContratto') ?? ''),
        'statoFinanziamento' => (string) ($quote->get('statoFinanziamento') ?? ''),
        'finanziamento' => (bool) $quote->get('finanziamento'),
    ];

    if ($before === $after) {
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'UPD ') . "{$label}\n";
    foreach (['status', 'statoContratto', 'statoFinanziamento', 'finanziamento'] as $k) {
        if ($before[$k] !== $after[$k]) {
            $b = $before[$k];
            $a = $after[$k];
            if (is_bool($before[$k])) {
                $b = $before[$k] ? 'true' : 'false';
                $a = $after[$k] ? 'true' : 'false';
            } else {
                $b = $b === '' ? '(vuoto)' : (string) $b;
                $a = $a === '' ? '(vuoto)' : (string) $a;
            }
            echo "  {$k}: {$b} → {$a}\n";
        }
    }

    if (!$dryRun) {
        $em->saveEntity($quote, ['silent' => true, 'skipHooks' => true]);
    }

    $updated++;
}

echo "Fatto. aggiornati={$updated} skip={$skipped} dryRun=" . ($dryRun ? 'yes' : 'no') . "\n";
