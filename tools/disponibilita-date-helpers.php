<?php
/**
 * Lettura date Disponibilità allineata a SetName hook e alla UI Espo.
 */
declare(strict_types=1);

use Espo\ORM\Entity;

function disponibilitaNormalizeDate(mixed $value): ?string
{
    if ($value instanceof \DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    if (!is_string($value) || $value === '') {
        return null;
    }

    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
        return $matches[1];
    }

    return null;
}

function disponibilitaExtractDateUtcToRome(string $dateTime, \DateTimeZone $timezone): string
{
    $dt = new \DateTime($dateTime, new \DateTimeZone('UTC'));
    $dt->setTimezone($timezone);

    return $dt->format('Y-m-d');
}

function disponibilitaResolveTargetDateFromEntity(Entity $entity, \DateTimeZone $timezone): ?string
{
    foreach (['datadisponibilita', 'dateStartDate', 'dateStart'] as $field) {
        $date = disponibilitaNormalizeDate($entity->get($field));

        if ($date !== null) {
            return $date;
        }
    }

    foreach (['orarioInizio', 'orarioFine'] as $field) {
        $value = $entity->get($field);

        if ($value instanceof \DateTimeInterface) {
            $local = \DateTimeImmutable::createFromInterface($value)
                ->setTimezone($timezone);

            return $local->format('Y-m-d');
        }

        if (!is_string($value) || $value === '') {
            continue;
        }

        try {
            return disponibilitaExtractDateUtcToRome($value, $timezone);
        } catch (\Throwable) {
            $date = disponibilitaNormalizeDate($value);

            if ($date !== null) {
                return $date;
            }
        }
    }

    return null;
}

function disponibilitaOrarioMatchesTarget(mixed $orario, string $targetDate, \DateTimeZone $timezone): bool
{
    if ($orario instanceof \DateTimeInterface) {
        $local = \DateTimeImmutable::createFromInterface($orario)
            ->setTimezone($timezone);

        return $local->format('Y-m-d') === $targetDate;
    }

    if (!is_string($orario) || $orario === '') {
        return true;
    }

    try {
        $dt = new \DateTime($orario, new \DateTimeZone('UTC'));
        $dt->setTimezone($timezone);

        return $dt->format('Y-m-d') === $targetDate;
    } catch (\Throwable) {
        return disponibilitaNormalizeDate($orario) === $targetDate;
    }
}

function disponibilitaDescribeEntityFields(Entity $entity): string
{
    $parts = [];

    foreach ([
        'datadisponibilita' => 'disp',
        'dateStartDate' => 'startDate',
        'dateStart' => 'start',
        'orarioInizio' => 'orarioIni',
        'orarioFine' => 'orarioFin',
    ] as $field => $label) {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            $parts[] = $label . '=null';
            continue;
        }

        if ($value instanceof \DateTimeInterface) {
            $parts[] = $label . '=' . $value->format('Y-m-d H:i:s');
            continue;
        }

        $parts[] = $label . '=' . $value;
    }

    return implode(' ', $parts);
}
