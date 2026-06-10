#!/usr/bin/env php
<?php
/**
 * Wrapper: duplica report e dashboard "Appuntamenti Mese Precedente".
 *
 *   php tools/duplica-report-appuntamenti-mese-precedente.php --reports-only --force
 *   php tools/duplica-report-appuntamenti-mese-precedente.php --dashboard-only --force --user=carmine_alvino
 */
declare(strict_types=1);

$argv = $GLOBALS['argv'] ?? [];
array_splice($argv, 1, 0, ['--profile=mese-precedente']);
$GLOBALS['argv'] = $argv;

require __DIR__ . '/duplica-report-appuntamenti-trimestre.php';
