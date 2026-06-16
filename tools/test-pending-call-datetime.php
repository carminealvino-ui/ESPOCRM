#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php';

use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;

$cases = [
    ['2026-04-15 18:00:00', '2026-04-17 09:30:00'],
    ['2026-04-16 10:00:00', '2026-04-20 09:30:00'],
    ['2026-04-17 18:00:00', '2026-04-20 09:30:00'],
    ['2026-04-18 18:00:00', '2026-04-20 09:30:00'],
    ['2026-04-14 09:00:00', '2026-04-16 09:30:00'],
];

$failed = 0;

foreach ($cases as [$input, $expected]) {
    $actual = PendingCallDateTime::fromAppointmentDateStart($input);

    if ($actual !== $expected) {
        echo "FAIL: {$input} => {$actual}, expected {$expected}\n";
        $failed++;
    } else {
        echo "OK: {$input} => {$actual}\n";
    }
}

exit($failed > 0 ? 1 : 0);
