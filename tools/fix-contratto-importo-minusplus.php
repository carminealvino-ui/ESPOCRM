#!/usr/bin/env php
<?php
/**
 * Corregge un contratto esistente: importoContratto + totali + minusPlus.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/fix-contratto-importo-minusplus.php --id=ID_CONTRATTO
 *   php tools/fix-contratto-importo-minusplus.php --name="POLTRONI MARZIA"
 *   php tools/fix-contratto-importo-minusplus.php --name="POLTRONI" --importo=4500
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
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
        $importo = (float) substr($arg, 10);
    }
}

$repo = $em->getRDBRepository('Quote');

if ($id) {
    $quote = $em->getEntityById('Quote', $id);
} elseif ($name) {
    $quote = $repo->where(['name*' => '%' . $name . '%'])->order('modifiedAt', 'DESC')->findOne();
} else {
    fwrite(STDERR, "Specificare --id= o --name=\n");
    exit(1);
}

if (!$quote) {
    fwrite(STDERR, "Contratto non trovato.\n");
    exit(1);
}

if ($importo !== null && $importo > 0) {
    $quote->set('importoContratto', $importo);
}

$calc = new QuotePricingCalculator($em);
$calc->syncOnBeforeSave($quote);

$em->saveEntity($quote, ['contractPricingSync' => true]);

$fresh = $em->getEntityById('Quote', $quote->getId());

fwrite(STDOUT, json_encode([
    'id' => $fresh->getId(),
    'name' => $fresh->get('name'),
    'importoContratto' => $fresh->get('importoContratto'),
    'amount' => $fresh->get('amount'),
    'taxAmount' => $fresh->get('taxAmount'),
    'grandTotalAmount' => $fresh->get('grandTotalAmount'),
    'minusPlus' => $fresh->get('minusPlus'),
    'prezzoCodiceIvaEsclusa' => $fresh->get('prezzoCodiceIvaEsclusa'),
    'prezzoCodiceIvaInclusa' => $fresh->get('prezzoCodiceIvaInclusa'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
