#!/usr/bin/env php
<?php
/**
 * Diagnostica perché le Disponibilità non compaiono nel calendario.
 *
 * Uso:
 *   php tools/diagnose-disponibilita-calendario.php
 *   php tools/diagnose-disponibilita-calendario.php --from=2026-06-29 --to=2026-07-05
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';
require_once __DIR__ . '/disponibilita-date-helpers.php';

use Espo\Core\Application;

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');

$dateFrom = '2026-06-29';
$dateTo = '2026-07-05';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $dateFrom = substr($arg, 7);
    }

    if (str_starts_with($arg, '--to=')) {
        $dateTo = substr($arg, 5);
    }
}

$timezone = new DateTimeZone('Europe/Rome');
$calendarJsonPath = 'custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json';

fwrite(STDOUT, "=== Diagnostica Disponibilità calendario ===\n\n");

if (is_readable($calendarJsonPath)) {
    $calendarDef = json_decode((string) file_get_contents($calendarJsonPath), true, 512, JSON_THROW_ON_ERROR);
    $scopes = $calendarDef['scopeList'] ?? [];
    $hasScope = in_array('Disponibilita', $scopes, true);

    fwrite(STDOUT, sprintf(
        "Calendar scopeList: %s\n",
        implode(', ', $scopes)
    ));
    fwrite(STDOUT, $hasScope
        ? "OK Disponibilita presente nello scopeList\n"
        : "ERRORE Disponibilita ASSENTE dallo scopeList — il calendario non le carica\n");
} else {
    fwrite(STDOUT, "WARN: {$calendarJsonPath} non trovato\n");
}

$calendarJsPaths = [
    'client/custom/src/views/calendar/calendar.js',
    'custom/Espo/Custom/Resources/client/custom/src/views/calendar/calendar.js',
    'custom/Espo/Custom/client/custom/src/views/calendar/calendar.js',
];

fwrite(STDOUT, "\nVerifica calendar.js:\n");

foreach ($calendarJsPaths as $path) {
    if (!is_readable($path)) {
        fwrite(STDOUT, "  - {$path}: assente\n");
        continue;
    }

    $content = file_get_contents($path);
    $hasCustom = str_contains($content, 'buildDisponibilitaEvents');

    fwrite(STDOUT, sprintf(
        "  - %s: %s\n",
        $path,
        $hasCustom ? 'VECCHIA logica custom (cache/deploy incompleto)' : 'OK standard'
    ));
}

$appCalendarMeta = 'custom/Espo/Custom/Resources/metadata/app/calendar.json';

if (is_readable($appCalendarMeta)) {
    fwrite(STDOUT, "\nWARN: {$appCalendarMeta} ancora presente (deploy rollback incompleto)\n");
}

$total = 0;
$inWeek = 0;
$missingColor = 0;
$missingName = 0;
$missingDate = 0;

$collection = $entityManager
    ->getRDBRepository('Disponibilita')
    ->where(['deleted' => false])
    ->find();

foreach ($collection as $entity) {
    $total++;
    $target = disponibilitaResolveTargetDateFromEntity($entity, $timezone);

    if ($target === null) {
        $missingDate++;
        continue;
    }

    if ($target < $dateFrom || $target > $dateTo) {
        continue;
    }

    $inWeek++;

    if (trim((string) ($entity->get('name') ?: '')) === '') {
        $missingName++;
    }

    if (trim((string) ($entity->get('color') ?: '')) === '') {
        $missingColor++;
    }
}

fwrite(STDOUT, sprintf(
    "\nRecord DB: totale=%d | settimana %s→%s=%d | senza data=%d\n",
    $total,
    $dateFrom,
    $dateTo,
    $inWeek,
    $missingDate
));
fwrite(STDOUT, sprintf(
    "Settimana: senza nome=%d | senza colore=%d\n",
    $missingName,
    $missingColor
));

$sessionsDir = 'backup_dev/_sessions';
if (is_dir($sessionsDir)) {
    $sessions = glob($sessionsDir . '/*disponibilita*') ?: [];
    rsort($sessions);
    fwrite(STDOUT, "\nBackup disponibilità in backup_dev:\n");

    if ($sessions === []) {
        fwrite(STDOUT, "  NESSUNO — i deploy precedenti non hanno rispettato Passo 0\n");
    } else {
        foreach (array_slice($sessions, 0, 5) as $session) {
            fwrite(STDOUT, '  - ' . basename($session) . "\n");
        }
    }
} else {
    fwrite(STDOUT, "\nWARN: cartella backup_dev/_sessions assente\n");
}

fwrite(STDOUT, "\nSuggerimento: se scopeList OK ma barre assenti, eseguire ripristina-record-calendario.php\n");
