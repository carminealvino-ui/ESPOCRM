#!/usr/bin/env php
<?php
/**
 * B2C: Product.prezzoCodice è netto → in riga IVA inclusa = netto × 1.1
 * Es. 4090.91 → 4500.00 (non lasciare 4090.91 in riga).
 */
$aliquota = 10.0;
$codiceNet = 4090.91;
$codiceIviExpected = 4500.00;
$codiceIvi = round($codiceNet * (1 + $aliquota / 100), 2);
$ok = abs($codiceIvi - $codiceIviExpected) < 0.02;

fwrite(STDOUT, "net {$codiceNet} → IVI {$codiceIvi} (atteso {$codiceIviExpected}) → " . ($ok ? 'OK' : 'FAIL') . PHP_EOL);

// Se si lascia il netto in riga e si riscorpora: doppio errore 3719
$wrongEscl = round($codiceNet / (1 + $aliquota / 100), 2);
fwrite(STDOUT, "bug doppio scorporo: {$codiceNet}/1.1 = {$wrongEscl} (da evitare)\n");

exit($ok ? 0 : 1);
