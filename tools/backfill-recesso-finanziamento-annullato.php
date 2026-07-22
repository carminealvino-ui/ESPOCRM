<?php
/**
 * Per i contratti in Recesso, forza Stato Finanziamento = Annullato.
 *
 *   php tools/backfill-recesso-finanziamento-annullato.php --dry-run
 *   php tools/backfill-recesso-finanziamento-annullato.php
 *   php tools/backfill-recesso-finanziamento-annullato.php --codice=Contratto_00142
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

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

$query = $em->getRDBRepository('Quote')
    ->where(['statoContratto' => 'Recesso']);

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
    $current = trim((string) ($quote->get('statoFinanziamento') ?? ''));
    $label = $quote->get('numberA') ?: $quote->get('name') ?: $quote->getId();

    if ($current === 'Annullato') {
        $skipped++;
        echo "SKIP {$label} già Annullato\n";
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'UPD ') . "{$label}: '{$current}' → Annullato\n";

    if (!$dryRun) {
        $quote->set('statoFinanziamento', 'Annullato');
        $em->saveEntity($quote, ['silent' => true, 'skipHooks' => true]);
    }

    $updated++;
}

echo "Fatto. aggiornati={$updated} skip={$skipped} dryRun=" . ($dryRun ? 'yes' : 'no') . "\n";
