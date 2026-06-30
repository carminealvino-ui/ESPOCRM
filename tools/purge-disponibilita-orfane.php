#!/usr/bin/env php
<?php
/**
 * Elimina (soft-delete) Disponibilità orfane: senza data e senza orari.
 *
 * Uso:
 *   php tools/purge-disponibilita-orfane.php --dry-run
 *   php tools/purge-disponibilita-orfane.php
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
$timezone = new DateTimeZone('Europe/Rome');
$purged = 0;
$kept = 0;

$sql = 'SELECT id, name, datadisponibilita, date_start, date_start_date, orario_inizio, orario_fine
        FROM disponibilita
        WHERE deleted = 0';

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    if (resolveTargetDate($row, $timezone) !== null) {
        $kept++;
        continue;
    }

    if ($dryRun) {
        fwrite(STDOUT, sprintf("PURGE %s | %s\n", $row['id'], $row['name'] ?: '(no name)'));
        $purged++;
        continue;
    }

    $entity = $entityManager->getEntityById('Disponibilita', $row['id']);

    if (!$entity) {
        continue;
    }

    $entityManager->removeEntity($entity);
    fwrite(STDOUT, sprintf("OK deleted %s\n", $row['id']));
    $purged++;
}

fwrite(STDOUT, sprintf(
    "Fatto. Orfane: %d | Valide: %d%s\n",
    $purged,
    $kept,
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
