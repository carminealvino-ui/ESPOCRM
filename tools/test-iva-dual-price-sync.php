<?php

declare(strict_types=1);

require_once __DIR__ . '/../custom/Espo/Custom/Services/IvaDualPriceSync.php';

use Espo\Custom\Services\IvaDualPriceSync;

$aliquota = 10.0;
$ivi = 4400.0;
$net = IvaDualPriceSync::toEsclusa($ivi, $aliquota);
$back = IvaDualPriceSync::toInclusa($net, $aliquota);

$ok = abs($net - 4000.0) < 0.02 && abs($back - $ivi) < 0.02;

fwrite(STDOUT, "IVI {$ivi} → netto {$net} → IVI {$back} → " . ($ok ? 'OK' : 'FAIL') . PHP_EOL);

exit($ok ? 0 : 1);
