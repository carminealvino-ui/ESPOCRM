#!/usr/bin/env php
<?php
/**
 * Diagnostica perché un calendario ricorrente non genera disponibilità in un periodo.
 *
 * Uso:
 *   php tools/diagnose-calendario-generazione.php --calendar=CALENDAR_ID --from=2026-07-20 --to=2026-08-02
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\WorkingTimeCalendarDisponibilitaGenerator;

$calendarId = null;
$from = null;
$to = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--calendar=')) {
        $calendarId = substr($arg, 11);
    }
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

if (!$calendarId || !$from || !$to) {
    fwrite(STDERR, "Uso: php tools/diagnose-calendario-generazione.php --calendar=ID --from=YYYY-MM-DD --to=YYYY-MM-DD\n");
    exit(1);
}

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');
$calendar = $entityManager->getEntityById('WorkingTimeCalendar', $calendarId);

if (!$calendar) {
    fwrite(STDERR, "Calendario non trovato: {$calendarId}\n");
    exit(1);
}

$generator = new WorkingTimeCalendarDisponibilitaGenerator($entityManager);
$diagnosis = $generator->diagnoseSlots($calendar, $from, $to);

fwrite(STDOUT, "Calendario: " . ($calendar->get('name') ?: $calendarId) . "\n");
fwrite(STDOUT, "Periodo: {$from} → {$to}\n");
fwrite(STDOUT, "timeRanges: " . json_encode($calendar->get('timeRanges')) . "\n\n");

if ($diagnosis['blockingExceptions'] !== []) {
    fwrite(STDOUT, "Eccezioni NON lavorative che coprono il periodo:\n");

    foreach ($diagnosis['blockingExceptions'] as $range) {
        fwrite(STDOUT, sprintf(
            "  - %s | %s → %s | tipo: %s\n",
            $range['name'] !== '' ? $range['name'] : '(senza nome)',
            $range['dateStart'],
            $range['dateEnd'],
            $range['type']
        ));
    }

    fwrite(STDOUT, "\n→ Rimuovere o accorciare queste eccezioni, oppure impostarle come «Working» con fascia oraria.\n\n");
}

fwrite(STDOUT, "Dettaglio giorni:\n");

foreach ($diagnosis['days'] as $day) {
    $line = sprintf('  %s: %s (%d fasce)', $day['date'], $day['reason'], $day['slots']);

    if (!empty($day['detail'])) {
        $line .= ' — ' . $day['detail'];
    }

    fwrite(STDOUT, $line . "\n");
}

$dryRun = $generator->generateFromCalendar($calendar, true);

fwrite(STDOUT, sprintf(
    "\nSimulazione: create %d, saltate %d, bloccati %d, weekday off %d, senza fascia %d\n",
    $dryRun['created'],
    $dryRun['skipped'],
    $dryRun['daysBlocked'] ?? 0,
    $dryRun['daysWeekdayOff'] ?? 0,
    $dryRun['daysNoSlots'] ?? 0
));
