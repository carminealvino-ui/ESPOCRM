#!/usr/bin/env php
<?php
/**
 * Verifica formule contratto IVA inclusa (COMBO: importo 4500, codice 4400 IVI).
 */
declare(strict_types=1);

$importoIvi = 4500.0;
$codiceIvi = 4400.0;
$aliquota = 10.0;
$imponibile = round($importoIvi / (1 + $aliquota / 100), 2);
$codiceNet = round($codiceIvi / (1 + $aliquota / 100), 2);
$minusPlus = round($imponibile - $codiceNet, 2);
$provvigione15 = round($imponibile * 0.15, 2);

$ok = abs($imponibile - 4090.91) < 0.02
    && abs($codiceNet - 4000.0) < 0.02
    && abs($minusPlus - 90.91) < 0.02
    && abs($provvigione15 - 613.64) < 0.02;

fwrite(STDOUT, "Imponibile {$imponibile} | codice net {$codiceNet} (IVI {$codiceIvi})\n");
fwrite(STDOUT, "minusPlus {$minusPlus} (≈90,91) | provvigione 15% {$provvigione15} → " . ($ok ? 'OK' : 'FAIL') . PHP_EOL);

exit($ok ? 0 : 1);
