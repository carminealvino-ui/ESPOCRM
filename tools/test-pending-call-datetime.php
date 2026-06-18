#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php';

use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;

/**
 * Appuntamento dateStart nei test: valori come salvati in DB (UTC).
 * Attesi: dateStart Call in timezone applicazione (Europe/Rome).
 */
$cases = [
    ['2026-04-15 18:00:00', '2026-04-17 09:30:00'],
    ['2026-04-16 10:00:00', '2026-04-20 09:30:00'],
    ['2026-04-17 18:00:00', '2026-04-20 09:30:00'],
    ['2026-04-18 18:00:00', '2026-04-20 09:30:00'],
    ['2026-04-14 09:00:00', '2026-04-16 09:30:00'],
];

$failed = 0;
$appTz = PendingCallDateTime::BUSINESS_TIMEZONE;

foreach ($cases as [$input, $expected]) {
    $actual = PendingCallDateTime::fromAppointmentDateStart($input, null, $appTz);

    if ($actual !== $expected) {
        echo "FAIL: {$input} => {$actual}, expected {$expected}\n";
        $failed++;
    } else {
        echo "OK: {$input} => {$actual}\n";
    }
}

$notBefore = new DateTimeImmutable('2026-06-17', new DateTimeZone('Europe/Rome'));
$actualPast = PendingCallDateTime::fromAppointmentDateStart('2024-01-10 10:00:00', $notBefore, $appTz);

if ($actualPast !== '2026-06-17 09:30:00') {
    echo "FAIL notBefore: {$actualPast}, expected 2026-06-17 09:30:00\n";
    $failed++;
} else {
    echo "OK notBefore: {$actualPast}\n";
}

$storedUtc = PendingCallDateTime::toStoredUtc('2026-06-19 09:30:00', $appTz);

if ($storedUtc !== '2026-06-19 07:30:00') {
    echo "FAIL toStoredUtc: {$storedUtc}, expected 2026-06-19 07:30:00\n";
    $failed++;
} else {
    echo "OK toStoredUtc: {$storedUtc}\n";
}

$instant = PendingCallDateTime::computeCallInstant(
    new DateTimeImmutable('2026-06-17 08:30:00', new DateTimeZone('UTC'))
);
$label = PendingCallDateTime::formatBusinessDateTime($instant);

if ($label !== '19/06/2026 09:30') {
    echo "FAIL label: {$label}, expected 19/06/2026 09:30\n";
    $failed++;
} else {
    echo "OK label: {$label}\n";
}

exit($failed > 0 ? 1 : 0);
