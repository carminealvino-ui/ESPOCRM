#!/usr/bin/env php
<?php
/**
 * Ripara Disponibilità per il calendario: date, isAllDay, nome e colore.
 *
 * Uso:
 *   php tools/fix-disponibilita-calendario-display.php --dry-run
 *   php tools/fix-disponibilita-calendario-display.php
 *   php tools/fix-disponibilita-calendario-display.php --from=2026-06-29 --to=2026-07-05
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
$dateFrom = null;
$dateTo = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $dateFrom = substr($arg, 7);
    }

    if (str_starts_with($arg, '--to=')) {
        $dateTo = substr($arg, 5);
    }
}

$timezone = new DateTimeZone('Europe/Rome');
$fixed = 0;
$skipped = 0;
$unrepairable = 0;

$sql = 'SELECT id, name, datadisponibilita, date_start, date_start_date, orario_inizio, orario_fine, is_all_day
        FROM disponibilita
        WHERE deleted = 0
        ORDER BY date_start_date DESC, modified_at DESC';

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $target = resolveTargetDate($row, $timezone);

    if ($target === null) {
        fwrite(STDOUT, sprintf(
            "SKIP (no date) %s | %s\n",
            $row['id'],
            $row['name'] ?: '(no name)'
        ));
        $unrepairable++;
        continue;
    }

    if ($dateFrom !== null && $target < $dateFrom) {
        $skipped++;
        continue;
    }

    if ($dateTo !== null && $target > $dateTo) {
        $skipped++;
        continue;
    }

    $needsFix = !((bool) ($row['is_all_day'] ?? false))
        || normalizeDate($row['datadisponibilita'] ?? null) !== $target
        || normalizeDate($row['date_start_date'] ?? null) !== $target
        || !orarioDateMatchesTarget($row['orario_inizio'] ?? null, $target, $timezone)
        || !orarioDateMatchesTarget($row['orario_fine'] ?? null, $target, $timezone)
        || trim((string) ($row['name'] ?? '')) === '';

    if (!$needsFix) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        fwrite(STDOUT, sprintf(
            "FIX %s | %s -> %s | %s\n",
            $row['id'],
            normalizeDate($row['date_start_date'] ?? null) ?? '(null)',
            $target,
            $row['name'] ?: '(no name)'
        ));
        $fixed++;
        continue;
    }

    $entity = $entityManager->getEntityById('Disponibilita', $row['id']);

    if (!$entity) {
        $unrepairable++;
        continue;
    }

    $entity->set('datadisponibilita', $target);

    if ($entity->get('orarioInizio')) {
        $entity->set('orarioInizio', $entity->get('orarioInizio'));
    }

    if ($entity->get('orarioFine')) {
        $entity->set('orarioFine', $entity->get('orarioFine'));
    }

    $entityManager->saveEntity($entity);

    fwrite(STDOUT, sprintf(
        "OK %s | %s | %s | allDay=%s\n",
        $entity->getId(),
        $entity->get('dateStartDate'),
        $entity->get('name'),
        $entity->get('isAllDay') ? '1' : '0'
    ));
    $fixed++;
}

fwrite(STDOUT, sprintf(
    "Fatto. Riparati: %d | Invariati: %d | Non riparabili: %d%s\n",
    $fixed,
    $skipped,
    $unrepairable,
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
    foreach (['datadisponibilita', 'date_start_date', 'date_start'] as $field) {
        $value = $row[$field] ?? null;

        if (!is_string($value) || $value === '') {
            continue;
        }

        $date = normalizeDate($value);

        if ($date !== null) {
            return $date;
        }
    }

    foreach (['orario_inizio', 'orario_fine'] as $field) {
        $value = $row[$field] ?? null;

        if (!is_string($value) || $value === '') {
            continue;
        }

        try {
            return extractDateUtcToRome($value, $timezone);
        } catch (\Throwable) {
            $date = normalizeDate($value);

            if ($date !== null) {
                return $date;
            }
        }
    }

    return null;
}

function orarioDateMatchesTarget(?string $orario, string $targetDate, DateTimeZone $timezone): bool
{
    if (!is_string($orario) || $orario === '') {
        return true;
    }

    try {
        $dt = new DateTime($orario, new DateTimeZone('UTC'));
        $dt->setTimezone($timezone);

        return $dt->format('Y-m-d') === $targetDate;
    } catch (\Throwable) {
        return normalizeDate($orario) === $targetDate;
    }
}
