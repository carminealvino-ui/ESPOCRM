<?php
/**
 * Fix contratto: importoContratto = totale IVI, righe listino/codice/unitario (QuotePricingCalculator).
 *
 *   cd ~/public_html/crm/mec-group
 *   curl -fsSL ".../tools/fix-contratto-importo-minusplus-standalone.php" -o /tmp/fix-contratto.php
 *   php /tmp/fix-contratto.php --name="POLTRONI MARZIA" --importo=4500
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da: cd ~/public_html/crm/mec-group\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\QuotePricingCalculator;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$id = null;
$name = null;
$importo = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $id = substr($arg, 5);
    }
    if (str_starts_with($arg, '--name=')) {
        $name = substr($arg, 7);
    }
    if (str_starts_with($arg, '--importo=')) {
        $importo = (float) str_replace(',', '.', substr($arg, 10));
    }
}

if (!$id && !$name) {
    fwrite(STDERR, "Uso: php fix-contratto.php --name=\"POLTRONI\" --importo=4500\n");
    exit(1);
}

if ($id) {
    $quote = $em->getEntityById('Quote', $id);
} else {
    $quote = $em->getRDBRepository('Quote')
        ->where(['name*' => '%' . $name . '%'])
        ->order('modifiedAt', 'DESC')
        ->findOne();
}

if (!$quote) {
    fwrite(STDERR, "Contratto non trovato.\n");
    exit(1);
}

if ($importo === null || $importo <= 0) {
    $importo = (float) ($quote->get('importoContratto') ?? 0);

    if ($importo <= 0 && preg_match('/€\.?\s*([\d.,]+)/u', (string) $quote->get('name'), $m)) {
        $s = trim(str_replace([' ', '.'], ['', ''], $m[1]));
        $s = str_replace(',', '.', $s);
        $importo = (float) $s;
    }
}

if ($importo <= 0) {
    fwrite(STDERR, "Specificare --importo=4500\n");
    exit(1);
}

$quote->set([
    'importoContratto' => $importo,
    'isTaxInclusive' => true,
]);

$calc = new QuotePricingCalculator($em);
$calc->syncOnBeforeSave($quote);

$em->saveEntity($quote, ['skipHooks' => true, 'silent' => true]);

$fresh = $em->getEntityById('Quote', $quote->getId());
$first = null;
$itemList = $fresh->get('itemList');

if (is_array($itemList) && isset($itemList[0])) {
    $first = is_array($itemList[0]) ? $itemList[0] : (array) $itemList[0];
}

fwrite(STDOUT, "Contratto aggiornato:\n");
fwrite(STDOUT, json_encode([
    'id' => $fresh->getId(),
    'importoContratto' => $fresh->get('importoContratto'),
    'grandTotalAmount' => $fresh->get('grandTotalAmount'),
    'amount' => $fresh->get('amount'),
    'taxAmount' => $fresh->get('taxAmount'),
    'minusPlus' => $fresh->get('minusPlus'),
    'riga' => $first ? [
        'listPrice' => $first['listPrice'] ?? null,
        'prezzoCodice' => $first['prezzoCodice'] ?? null,
        'unitPrice' => $first['unitPrice'] ?? null,
        'amount' => $first['amount'] ?? null,
    ] : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
