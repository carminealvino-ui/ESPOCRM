#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../custom/Espo/Custom/Tools/DateTime/BusinessDateTime.php';
require_once __DIR__ . '/../custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php';

use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Custom\Tools\DateTime\BusinessDateTime;

/**
 * Appuntamento dateStart nei test: valori come salvati in DB (UTC).
 * Attesi: dateStart Call in UTC (09:30 Europe/Rome).
 */
$cases = [
    ['2026-04-15 18:00:00', '2026-04-17 07:30:00'],
    ['2026-04-16 10:00:00', '2026-04-20 07:30:00'],
    ['2026-04-17 18:00:00', '2026-04-20 07:30:00'],
    ['2026-04-18 18:00:00', '2026-04-20 07:30:00'],
    ['2026-04-14 09:00:00', '2026-04-16 07:30:00'],
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

$notBefore = new DateTimeImmutable('2026-06-17', new DateTimeZone('Europe/Rome'));
$actualPast = PendingCallDateTime::fromAppointmentDateStart('2024-01-10 10:00:00', $notBefore);

if ($actualPast !== '2026-06-17 07:30:00') {
    echo "FAIL notBefore: {$actualPast}, expected 2026-06-17 07:30:00\n";
    $failed++;
} else {
    echo "OK notBefore: {$actualPast}\n";
}

$label = BusinessDateTime::formatBusiness('2026-06-19 07:30:00');

if ($label !== '19/06/2026 09:30') {
    echo "FAIL label: {$label}, expected 19/06/2026 09:30\n";
    $failed++;
} else {
    echo "OK label: {$label}\n";
}

exit($failed > 0 ? 1 : 0);
