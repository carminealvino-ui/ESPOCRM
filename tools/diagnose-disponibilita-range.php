#!/usr/bin/env php
<?php
/**
 * Conta Disponibilità per giorno in un intervallo (diagnostica calendario vuoto).
 *
 * Uso:
 *   php tools/diagnose-disponibilita-range.php --from=2026-07-27 --to=2026-08-02
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;

$from = null;
$to = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

if (!$from || !$to) {
    fwrite(STDERR, "Uso: php tools/diagnose-disponibilita-range.php --from=YYYY-MM-DD --to=YYYY-MM-DD\n");
    exit(1);
}

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');

$current = new \DateTimeImmutable($from);
$end = new \DateTimeImmutable($to);
$total = 0;

fwrite(STDOUT, "Disponibilità dal {$from} al {$to}:\n");

while ($current <= $end) {
    $day = $current->format('Y-m-d');
    $count = (int) $entityManager
        ->getRDBRepository('Disponibilita')
        ->where([
            'OR' => [
                ['dateStartDate' => $day],
                ['datadisponibilita' => $day],
            ],
        ])
        ->count();

    fwrite(STDOUT, sprintf("  %s: %d\n", $day, $count));
    $total += $count;
    $current = $current->modify('+1 day');
}

fwrite(STDOUT, sprintf("Totale: %d\n", $total));

if ($total === 0) {
    fwrite(STDOUT, "\nNessun record nel periodo → rigenera da Disponibilità Ricorrenti con quelle date.\n");
} else {
    fwrite(STDOUT, "\nRecord presenti. Se il calendario è vuoto: php tools/fix-disponibilita-calendario-display.php\n");
}
