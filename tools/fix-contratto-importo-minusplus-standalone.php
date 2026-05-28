<?php
/**
 * Fix contratto POLTRONI (o altro): importoContratto, totali, minusPlus.
 * Non richiede git pull — solo bootstrap EspoCRM.
 *
 *   cd ~/public_html/crm/mec-group
 *   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigioni-manuali-fase-a-9999/tools/fix-contratto-importo-minusplus-standalone.php" -o /tmp/fix-contratto.php
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
        $importo = parseAmount($m[1]);
    }
}

if ($importo <= 0) {
    fwrite(STDERR, "Specificare --importo=4500\n");
    exit(1);
}

$aliquota = 10.0;

if ($quote->get('taxId')) {
    $tax = $em->getEntityById('Tax', $quote->get('taxId'));

    if ($tax) {
        $rate = (float) ($tax->get('rate') ?? $tax->get('taxRate') ?? 0);

        if ($rate > 0) {
            $aliquota = $rate < 1 ? $rate * 100 : $rate;
        }
    }
}

$ivaField = $quote->get('aliquotaIVA');

if ($ivaField !== null && $ivaField !== '' && (float) $ivaField > 0) {
    $aliquota = (float) $ivaField;
}

$taxInclusive = (bool) $quote->get('isTaxInclusive');

if ($taxInclusive) {
    $net = round($importo / (1 + $aliquota / 100), 2);
    $gross = round($importo, 2);
    $taxAmt = round($gross - $net, 2);
} else {
    $net = round($importo, 2);
    $taxAmt = round($net * $aliquota / 100, 2);
    $gross = round($net + $taxAmt, 2);
}

$codiceNet = resolveCodiceNetto($em, $quote, $aliquota);
$codiceIvi = $codiceNet > 0 ? round($codiceNet * (1 + $aliquota / 100), 2) : 0.0;
$minusPlus = $codiceNet > 0 ? round($net - $codiceNet, 2) : null;

$itemList = $quote->get('itemList');

if (is_array($itemList)) {
    foreach ($itemList as $index => $item) {
        $qty = 1.0;

        if (is_array($item)) {
            $qty = (float) ($item['quantity'] ?? 1);
        } elseif (is_object($item)) {
            $qty = (float) ($item->quantity ?? 1);
        }

        if ($qty <= 0) {
            $qty = 1.0;
        }

        $lineNet = round($net / $qty, 2);
        $lineTax = round($taxAmt / $qty, 2);

        if (is_array($item)) {
            $item['amount'] = $lineNet;
            $item['taxAmount'] = $lineTax;
            $item['listPrice'] = $lineNet;
            $item['unitPrice'] = $lineNet;

            if ($codiceNet > 0) {
                $item['prezzoCodice'] = $codiceNet;
            }

            $itemList[$index] = $item;
        } elseif (is_object($item)) {
            $item->amount = $lineNet;
            $item->taxAmount = $lineTax;
            $item->listPrice = $lineNet;
            $item->unitPrice = $lineNet;

            if ($codiceNet > 0) {
                $item->prezzoCodice = $codiceNet;
            }
        }
    }

    $quote->set('itemList', $itemList);
}

$patch = [
    'importoContratto' => $gross,
    'amount' => $net,
    'taxAmount' => $taxAmt,
    'grandTotalAmount' => $gross,
    'aliquotaIVA' => $aliquota,
    'taxRate' => round($aliquota / 100, 4),
];

if ($codiceNet > 0) {
    $patch['totalPrezzoCodice'] = $codiceNet;
    $patch['prezzoCodiceIvaEsclusa'] = $codiceNet;
    $patch['prezzoCodiceIvaInclusa'] = $codiceIvi;
}

if ($listinoNet > 0) {
    $patch['prezzoListinoIvaEsclusa'] = $listinoNet;
}

if ($listinoIvi > 0) {
    $patch['prezzoListinoIVAInclusa'] = $listinoIvi;
}

$quote->set($patch);

if ($minusPlus !== null) {
    $quote->set('minusPlus', $minusPlus);
}

$em->saveEntity($quote, ['skipHooks' => true, 'silent' => true]);

$fresh = $em->getEntityById('Quote', $quote->getId());

fwrite(STDOUT, "Contratto aggiornato:\n");
fwrite(STDOUT, json_encode([
    'id' => $fresh->getId(),
    'importoContratto' => $fresh->get('importoContratto'),
    'amount' => $fresh->get('amount'),
    'taxAmount' => $fresh->get('taxAmount'),
    'grandTotalAmount' => $fresh->get('grandTotalAmount'),
    'minusPlus' => $fresh->get('minusPlus'),
    'prezzoCodiceIvaEsclusa' => $fresh->get('prezzoCodiceIvaEsclusa'),
    'prezzoCodiceIvaInclusa' => $fresh->get('prezzoCodiceIvaInclusa'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

function parseAmount(string $raw): float
{
    $s = trim(str_replace([' ', '.'], ['', ''], $raw));
    $s = str_replace(',', '.', $s);

    return (float) $s;
}

/**
 * @return array{0: float, 1: float, 2: float, 3: float} codiceNet, codiceIvi, listinoNet, listinoIvi
 */
function resolvePrezziDaRiga($em, $quote, float $aliquota): array
{
    $codiceNet = 0.0;
    $codiceIvi = 0.0;
    $listinoNet = 0.0;
    $listinoIvi = 0.0;
    $itemList = $quote->get('itemList');

    if (!is_array($itemList)) {
        return [0.0, 0.0, 0.0, 0.0];
    }

    foreach ($itemList as $item) {
        $productId = is_array($item) ? ($item['productId'] ?? null) : ($item->productId ?? null);
        $lineCodice = is_array($item) ? ($item['prezzoCodice'] ?? null) : ($item->prezzoCodice ?? null);

        if ($productId) {
            $product = $em->getEntityById('Product', $productId);

            if ($product) {
                $listinoNet = (float) ($product->get('listPrice') ?? 0);
                $listinoIvi = (float) ($product->get('prezzoListinoIvaInclusa') ?? 0);

                if ($listinoIvi <= 0 && $listinoNet > 0) {
                    $listinoIvi = round($listinoNet * (1 + $aliquota / 100), 2);
                }

                $codiceNet = (float) ($product->get('prezzoCodice') ?? 0);
                $codiceIvi = (float) ($product->get('prezzoCodiceIvaInclusa') ?? 0);

                if ($codiceIvi <= 0 && $codiceNet > 0) {
                    $codiceIvi = round($codiceNet * (1 + $aliquota / 100), 2);
                }
            }
        }

        if ($lineCodice !== null && $lineCodice !== '' && $codiceIvi <= 0) {
            $line = (float) $lineCodice;

            if ($line >= 4000 && $line <= 5500) {
                $codiceIvi = round($line, 2);
                $codiceNet = round($line / (1 + $aliquota / 100), 2);
            } else {
                $codiceNet = round($line, 2);
                $codiceIvi = round($line * (1 + $aliquota / 100), 2);
            }
        }
    }

    return [
        round($codiceNet, 2),
        round($codiceIvi, 2),
        round($listinoNet, 2),
        round($listinoIvi, 2),
    ];
}
