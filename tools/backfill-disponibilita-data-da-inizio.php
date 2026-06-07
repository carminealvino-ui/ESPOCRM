#!/usr/bin/env php
<?php
/**
 * Allinea datadisponibilita = data di inizio (orarioInizio > dateStart > dateStartDate).
 *
 * Uso:
 *   php tools/backfill-disponibilita-data-da-inizio.php --dry-run --verbose
 *   php tools/backfill-disponibilita-data-da-inizio.php --verbose
 *   php tools/backfill-disponibilita-data-da-inizio.php --sample
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');
$pdo = $entityManager->getPDO();

$dryRun = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$sample = in_array('--sample', $argv, true);

$timezone = new DateTimeZone('Europe/Rome');
$updated = 0;
$skipped = 0;

$sql = 'SELECT id, name, datadisponibilita, date_start, date_start_date, date_end_date, orario_inizio, orario_fine
        FROM disponibilita
        WHERE deleted = 0
        ORDER BY modified_at DESC';

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if ($sample) {
    fwrite(STDOUT, "=== CAMPIONE (max 15 record) ===\n");

    foreach (array_slice($rows, 0, 15) as $row) {
        $target = resolveTargetDate($row, $timezone);
        fwrite(STDOUT, sprintf(
            "%s | disp=%s target=%s | startDate=%s | start=%s | orario=%s | %s\n",
            $row['id'],
            normalizeDate($row['datadisponibilita'] ?? null) ?? '(null)',
            $target ?? '(null)',
            $row['date_start_date'] ?? '(null)',
            $row['date_start'] ?? '(null)',
            $row['orario_inizio'] ?? '(null)',
            $row['name'] ?? ''
        ));
    }

    exit(0);
}

foreach ($rows as $row) {
    $target = resolveTargetDate($row, $timezone);
    $current = normalizeDate($row['datadisponibilita'] ?? null);

    if ($target === null) {
        $skipped++;
        continue;
    }

    if ($current === $target) {
        $skipped++;
        continue;
    }

    if ($verbose || $dryRun) {
        fwrite(STDOUT, sprintf(
            "%s | %s -> %s | orarioInizio=%s | dateStartDate=%s | dateStart=%s\n",
            $row['id'],
            $current ?? '(null)',
            $target,
            $row['orario_inizio'] ?? '(null)',
            $row['date_start_date'] ?? '(null)',
            $row['date_start'] ?? '(null)'
        ));
    }

    if ($dryRun) {
        $updated++;
        continue;
    }

    $startDateTime = $target . ' 00:00:00';
    $endDateTime = $target . ' 23:59:59';
    $orarioInizio = rebuildOrarioDateTime($row['orario_inizio'] ?? null, $target, $timezone) ?? $startDateTime;
    $orarioFine = rebuildOrarioDateTime($row['orario_fine'] ?? null, $target, $timezone) ?? $endDateTime;

    $update = $pdo->prepare(
        'UPDATE disponibilita SET
            datadisponibilita = :target,
            date_start_date = :target,
            date_end_date = :target,
            date_start = :start,
            date_end = :end,
            orario_inizio = :orarioInizio,
            orario_fine = :orarioFine,
            modified_at = NOW()
         WHERE id = :id AND deleted = 0'
    );
    $update->execute([
        'target' => $target,
        'start' => $startDateTime,
        'end' => $endDateTime,
        'orarioInizio' => $orarioInizio,
        'orarioFine' => $orarioFine,
        'id' => $row['id'],
    ]);

    $updated++;
}

fwrite(STDOUT, sprintf(
    "Fatto. Aggiornati: %d | Invariati: %d%s\n",
    $updated,
    $skipped,
    $dryRun ? ' (dry-run)' : ''
));

function normalizeDate(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
        return $matches[1];
    }

    return null;
}

function extractDateUtcToRome(string $dateTime, DateTimeZone $timezone): string
{
    $dt = new DateTime($dateTime, new DateTimeZone('UTC'));
    $dt->setTimezone($timezone);

    return $dt->format('Y-m-d');
}

function extractTimeUtcToRome(string $dateTime, DateTimeZone $timezone): string
{
    $dt = new DateTime($dateTime, new DateTimeZone('UTC'));
    $dt->setTimezone($timezone);

    return $dt->format('H:i:s');
}

function rebuildOrarioDateTime(?string $value, string $targetDate, DateTimeZone $timezone): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }

    return $targetDate . ' ' . extractTimeUtcToRome($value, $timezone);
}

/**
 * @param array<string, mixed> $row
 */
function resolveTargetDate(array $row, DateTimeZone $timezone): ?string
{
    foreach (['orario_inizio', 'date_start', 'date_start_date'] as $field) {
        $value = $row[$field] ?? null;

        if (!is_string($value) || $value === '') {
            continue;
        }

        $date = normalizeDate($value);

        if ($date !== null) {
            return $date;
        }
    }

    return null;
}
