#!/usr/bin/env php
<?php
/**
 * Verifica formule contratto IVA inclusa (2 righe: listino 5200 + 3950, totale 4500).
 */
declare(strict_types=1);

$importoIvi = 4500.0;
$aliquota = 10.0;
$list1 = 5200.0;
$list2 = 3950.0;
$weight = $list1 + $list2;
$line1Gross = round($importoIvi * ($list1 / $weight), 2);
$line2Gross = round($importoIvi - $line1Gross, 2);
$line1Net = round($line1Gross / (1 + $aliquota / 100), 2);
$line2Net = round($line2Gross / (1 + $aliquota / 100), 2);
$imponibile = round($importoIvi / (1 + $aliquota / 100), 2);
$codiceNet = round(4400.0 / (1 + $aliquota / 100), 2);
$minusPlus = round($imponibile - $codiceNet, 2);

$ok = abs($line1Gross + $line2Gross - $importoIvi) < 0.02
    && abs($line1Net + $line2Net - $imponibile) < 0.02
    && abs($codiceNet - 4000.0) < 0.02
    && abs($minusPlus - 90.91) < 0.02;

fwrite(STDOUT, "Riga1 IVI {$line1Gross} net {$line1Net}\n");
fwrite(STDOUT, "Riga2 IVI {$line2Gross} net {$line2Net}\n");
fwrite(STDOUT, "Imponibile {$imponibile} − codice net {$codiceNet} = {$minusPlus} (≈272,73) → " . ($ok ? 'OK' : 'FAIL') . PHP_EOL);

exit($ok ? 0 : 1);
