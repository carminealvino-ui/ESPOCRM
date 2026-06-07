<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Genera record Disponibilita da un WorkingTimeCalendar e un intervallo di date.
 */
class WorkingTimeCalendarDisponibilitaGenerator
{
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{created: int, skipped: int, errors: string[]}
     */
    public function generate(
        Entity $calendar,
        string $dateFrom,
        string $dateTo,
        array $assignedUserIds,
        ?string $azienda = null,
        string $status = 'Presente',
        bool $dryRun = false
    ): array {
        $dateFrom = $this->normalizeDate($dateFrom);
        $dateTo = $this->normalizeDate($dateTo);

        if ($dateFrom === null || $dateTo === null) {
            throw new \InvalidArgumentException('Intervallo date non valido.');
        }

        if ($dateFrom > $dateTo) {
            throw new \InvalidArgumentException('La data inizio deve essere precedente o uguale alla data fine.');
        }

        $assignedUserIds = array_values(array_filter(array_unique($assignedUserIds)));

        if ($assignedUserIds === []) {
            throw new \InvalidArgumentException('Selezionare almeno un utente assegnato.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        $current = new \DateTimeImmutable($dateFrom, new \DateTimeZone(self::TIMEZONE));
        $end = new \DateTimeImmutable($dateTo, new \DateTimeZone(self::TIMEZONE));

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $slots = $this->resolveTimeSlotsForDate($calendar, $dateStr);

            foreach ($slots as $slot) {
                if ($this->existsDisponibilita($dateStr, $slot['start'], $slot['end'], $assignedUserIds)) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $created++;
                    continue;
                }

                try {
                    $this->createDisponibilita(
                        $dateStr,
                        $slot['start'],
                        $slot['end'],
                        $assignedUserIds,
                        $azienda,
                        $status
                    );
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = $dateStr . ' ' . $slot['start'] . '-' . $slot['end'] . ': ' . $e->getMessage();
                }
            }

            $current = $current->modify('+1 day');
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    private function resolveTimeSlotsForDate(Entity $calendar, string $dateStr): array
    {
        $exceptionSlots = $this->resolveExceptionSlots($calendar, $dateStr);

        if ($exceptionSlots === false) {
            return [];
        }

        if ($exceptionSlots !== null) {
            return $exceptionSlots;
        }

        $weekday = (int) (new \DateTimeImmutable($dateStr))->format('w');
        $weekdayField = 'weekday' . $weekday;

        if (!$calendar->get($weekdayField)) {
            return [];
        }

        $ranges = $calendar->get('weekday' . $weekday . 'TimeRanges');

        if (!is_array($ranges) || $ranges === []) {
            $ranges = $calendar->get('timeRanges');
        }

        return $this->parseTimeRanges($ranges, $dateStr);
    }

    /**
     * @return array<int, array{start: string, end: string}>|null|false
     *         null = nessuna eccezione, false = giorno non lavorativo
     */
    private function resolveExceptionSlots(Entity $calendar, string $dateStr): array|null|false
    {
        $ranges = $this->entityManager
            ->getRDBRepository('WorkingTimeCalendar')
            ->getRelation($calendar, 'ranges')
            ->where([
                'dateStart<=' => $dateStr,
                'dateEnd>=' => $dateStr,
            ])
            ->find();

        $workingSlots = null;

        foreach ($ranges as $range) {
            $type = (string) ($range->get('type') ?: 'Non-working');

            if ($this->isNonWorkingType($type)) {
                return false;
            }

            $parsed = $this->parseTimeRanges($range->get('timeRanges'), $dateStr);

            if ($parsed !== []) {
                $workingSlots = $parsed;
            }
        }

        return $workingSlots;
    }

    private function isNonWorkingType(string $type): bool
    {
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $type));

        return in_array($normalized, ['nonworking', 'notworking', 'holiday', 'leave'], true);
    }

    /**
     * @param mixed $ranges
     * @return array<int, array{start: string, end: string}>
     */
    private function parseTimeRanges(mixed $ranges, string $dateStr): array
    {
        if (is_string($ranges) && $ranges !== '') {
            $decoded = json_decode($ranges, true);
            $ranges = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($ranges)) {
            return [];
        }

        $slots = [];

        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }

            $start = $this->normalizeTime((string) $range[0]);
            $end = $this->normalizeTime((string) $range[1]);

            if ($start === null || $end === null || $start >= $end) {
                continue;
            }

            $slots[] = [
                'start' => $dateStr . ' ' . $start,
                'end' => $dateStr . ' ' . $end,
            ];
        }

        return $slots;
    }

    /**
     * @param string[] $assignedUserIds
     */
    private function existsDisponibilita(
        string $dateStr,
        string $orarioInizio,
        string $orarioFine,
        array $assignedUserIds
    ): bool {
        $startTime = $this->extractTimeFromDateTime($orarioInizio);
        $endTime = $this->extractTimeFromDateTime($orarioFine);

        $collection = $this->entityManager
            ->getRDBRepository('Disponibilita')
            ->where([
                'dateStartDate' => $dateStr,
            ])
            ->find();

        sort($assignedUserIds);

        foreach ($collection as $entity) {
            $existingStart = $this->extractTimeFromDateTime((string) $entity->get('orarioInizio'));
            $existingEnd = $this->extractTimeFromDateTime((string) $entity->get('orarioFine'));

            if ($existingStart !== $startTime || $existingEnd !== $endTime) {
                continue;
            }

            $existingIds = $entity->getLinkMultipleIdList('assignedUsers');
            sort($existingIds);

            if ($existingIds === $assignedUserIds) {
                return true;
            }
        }

        return false;
    }

    private function extractTimeFromDateTime(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d{2}:\d{2}:\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param string[] $assignedUserIds
     */
    private function createDisponibilita(
        string $dateStr,
        string $orarioInizio,
        string $orarioFine,
        array $assignedUserIds,
        ?string $azienda,
        string $status
    ): void {
        $entity = $this->entityManager->createEntity('Disponibilita');

        $entity->set([
            'dateStartDate' => $dateStr,
            'dateEndDate' => $dateStr,
            'orarioInizio' => $orarioInizio,
            'orarioFine' => $orarioFine,
            'azienda' => $azienda ?: '',
            'status' => $status,
            'assignedUsersIds' => $assignedUserIds,
        ]);

        $this->entityManager->saveEntity($entity);
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
            $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $minute = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $second = isset($matches[3]) ? str_pad($matches[3], 2, '0', STR_PAD_LEFT) : '00';

            return $hour . ':' . $minute . ':' . $second;
        }

        return null;
    }
}
