#!/usr/bin/env php
<?php
/**
 * Verifica aritmetica COMBO 9+9 (GDL: imponibile netto − prezzo codice netto).
 */
$importoVendutoIvi = 4500.0;
$aliquota = 10.0;
$imponibileNet = round($importoVendutoIvi / (1 + $aliquota / 100), 2);
$prezzoCodiceNet = 4000.0;
$minusPlus = round($imponibileNet - $prezzoCodiceNet, 2);
$ok = abs($minusPlus - 90.91) < 0.02;

fwrite(STDOUT, "imponibile {$imponibileNet} − codice {$prezzoCodiceNet} = {$minusPlus} (≈91) → " . ($ok ? 'OK' : 'FAIL') . PHP_EOL);

exit($ok ? 0 : 1);
