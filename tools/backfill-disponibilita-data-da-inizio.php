#!/usr/bin/env php
<?php
/**
 * Allinea datadisponibilita = data di inizio (dateStartDate / orarioInizio / dateStart).
 *
 * Uso: php tools/backfill-disponibilita-data-da-inizio.php [--dry-run] [--verbose]
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

$timezone = new DateTimeZone('Europe/Rome');
$updated = 0;
$skipped = 0;

$sql = "SELECT id, datadisponibilita, date_start, date_start_date, date_end_date, orario_inizio
        FROM disponibilita
        WHERE deleted = 0";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
            "%s | %s -> %s | dateStartDate=%s | orarioInizio=%s | dateStart=%s\n",
            $row['id'],
            $current ?? '(null)',
            $target,
            $row['date_start_date'] ?? '(null)',
            $row['orario_inizio'] ?? '(null)',
            $row['date_start'] ?? '(null)'
        ));
    }

    if (!$dryRun) {
        $update = $pdo->prepare(
            'UPDATE disponibilita SET
                datadisponibilita = :target,
                date_start_date = :target,
                date_end_date = :target,
                date_start = :start,
                date_end = :end,
                modified_at = NOW()
             WHERE id = :id AND deleted = 0'
        );
        $update->execute([
            'target' => $target,
            'start' => $target . ' 00:00:00',
            'end' => $target . ' 23:59:59',
            'id' => $row['id'],
        ]);
    }

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

/**
 * @param array<string, mixed> $row
 */
function resolveTargetDate(array $row, DateTimeZone $timezone): ?string
{
    $dateStartDate = normalizeDate($row['date_start_date'] ?? null);

    if ($dateStartDate !== null) {
        return $dateStartDate;
    }

    $orarioInizio = $row['orario_inizio'] ?? null;

    if (is_string($orarioInizio) && $orarioInizio !== '') {
        return extractDateUtcToRome($orarioInizio, $timezone);
    }

    $dateStart = $row['date_start'] ?? null;

    if (is_string($dateStart) && $dateStart !== '') {
        return extractDateUtcToRome($dateStart, $timezone);
    }

    return null;
}
