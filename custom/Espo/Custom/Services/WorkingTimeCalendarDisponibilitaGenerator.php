<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Genera record Disponibilita da un WorkingTimeCalendar e un intervallo di date.
 *
 * v2.0.0:
 * - Utenti presi automaticamente dal link users del calendario
 * - Una disponibilita per fascia oraria con tutti gli utenti del calendario
 * - Area e collaboratori dal pannello generazione del calendario
 */
class WorkingTimeCalendarDisponibilitaGenerator
{
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{
     *   created: int,
     *   skipped: int,
     *   errors: string[],
     *   userCount?: int,
     *   daysBlocked: int,
     *   daysNoSlots: int
     * }
     */
    public function generateFromCalendar(Entity $calendar, bool $dryRun = false): array
    {
        $dateFrom = $calendar->get('dataInizioGenerazione');
        $dateTo = $calendar->get('dataFineGenerazione');
        $productBrandId = $this->resolveCalendarProductBrandId($calendar);
        $status = $calendar->get('generazioneStatus') ?: 'Presente';
        $area = $calendar->get('generazioneArea') ?? [];
        $collaboratorIds = $calendar->getLinkMultipleIdList('generazioneCollaborators');

        if (!is_array($area)) {
            $area = $area !== null && $area !== '' ? [$area] : [];
        }

        $assignedUserIds = $this->resolveAssignedUserIds($calendar);

        if (!$dateFrom || !$dateTo) {
            throw new \InvalidArgumentException('Compilare Data inizio e Data fine generazione.');
        }

        if ($area === []) {
            throw new \InvalidArgumentException('Selezionare almeno un\'area di lavoro.');
        }

        if ($assignedUserIds === []) {
            throw new \InvalidArgumentException(
                'Selezionare almeno un collaboratore oppure collegare utenti al calendario lavorativo.'
            );
        }

        $result = $this->generate(
            $calendar,
            $dateFrom,
            $dateTo,
            $assignedUserIds,
            $productBrandId,
            $status,
            $area,
            $collaboratorIds,
            $dryRun
        );

        $result['userCount'] = count($assignedUserIds);

        return $result;
    }

    /**
     * Utenti collegati al calendario; se assenti, usa i collaboratori del pannello generazione.
     *
     * @return string[]
     */
    public function resolveAssignedUserIds(Entity $calendar): array
    {
        $userIds = $this->resolveCalendarUserIds($calendar);

        if ($userIds !== []) {
            return $userIds;
        }

        $collaboratorIds = $calendar->getLinkMultipleIdList('generazioneCollaborators');
        sort($collaboratorIds);

        return array_values(array_unique($collaboratorIds));
    }

    /**
     * @return string[]
     */
    public function resolveCalendarUserIds(Entity $calendar): array
    {
        $calendarId = $calendar->getId();

        if (!$calendarId) {
            return [];
        }

        $userIds = [];

        $users = $this->entityManager
            ->getRDBRepository('User')
            ->where([
                'workingTimeCalendarId' => $calendarId,
            ])
            ->find();

        foreach ($users as $user) {
            $userIds[] = $user->getId();
        }

        sort($userIds);

        return array_values(array_unique($userIds));
    }

    /**
     * @return array{created: int, skipped: int, errors: string[]}
     */
    public function generate(
        Entity $calendar,
        string $dateFrom,
        string $dateTo,
        array $assignedUserIds,
        ?string $productBrandId = null,
        string $status = 'Presente',
        array $area = [],
        array $collaboratorIds = [],
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
        $area = array_values(array_filter(array_unique($area)));
        $collaboratorIds = array_values(array_filter(array_unique($collaboratorIds)));

        sort($assignedUserIds);
        sort($collaboratorIds);

        if ($assignedUserIds === []) {
            throw new \InvalidArgumentException('Selezionare almeno un utente assegnato.');
        }

        if ($area === []) {
            throw new \InvalidArgumentException('Selezionare almeno un\'area di lavoro.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $daysBlocked = 0;
        $daysWeekdayOff = 0;
        $daysNoSlots = 0;
        $daysWithSlots = 0;

        $current = new \DateTimeImmutable($dateFrom, new \DateTimeZone(self::TIMEZONE));
        $end = new \DateTimeImmutable($dateTo, new \DateTimeZone(self::TIMEZONE));

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $analysis = $this->analyzeSlotsForDate($calendar, $dateStr);
            $slots = $analysis['slots'];

            if ($analysis['reason'] === 'exception_non_working') {
                $daysBlocked++;
            } elseif ($analysis['reason'] === 'weekday_off') {
                $daysWeekdayOff++;
            } elseif ($slots === []) {
                $daysNoSlots++;
            } else {
                $daysWithSlots++;
            }

            foreach ($slots as $slot) {
                if ($this->existsDisponibilita(
                    $dateStr,
                    $slot['start'],
                    $slot['end'],
                    $assignedUserIds,
                    $area,
                    $collaboratorIds
                )) {
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
                        $productBrandId,
                        $status,
                        $area,
                        $collaboratorIds
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
            'daysBlocked' => $daysBlocked,
            'daysWeekdayOff' => $daysWeekdayOff,
            'daysNoSlots' => $daysNoSlots,
            'daysWithSlots' => $daysWithSlots,
        ];
    }

    /**
     * Diagnostica giorno per giorno (CLI / supporto).
     *
     * @return array{
     *   days: array<int, array{date: string, reason: string, slots: int, detail?: string}>,
     *   blockingExceptions: array<int, array{name: string, dateStart: string, dateEnd: string, type: string}>
     * }
     */
    public function diagnoseSlots(Entity $calendar, string $dateFrom, string $dateTo): array
    {
        $dateFrom = $this->normalizeDate($dateFrom);
        $dateTo = $this->normalizeDate($dateTo);

        if ($dateFrom === null || $dateTo === null) {
            throw new \InvalidArgumentException('Intervallo date non valido.');
        }

        $days = [];
        $current = new \DateTimeImmutable($dateFrom, new \DateTimeZone(self::TIMEZONE));
        $end = new \DateTimeImmutable($dateTo, new \DateTimeZone(self::TIMEZONE));

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $analysis = $this->analyzeSlotsForDate($calendar, $dateStr);

            $days[] = [
                'date' => $dateStr,
                'reason' => $analysis['reason'],
                'slots' => count($analysis['slots']),
                'detail' => $analysis['detail'] ?? null,
            ];

            $current = $current->modify('+1 day');
        }

        return [
            'days' => $days,
            'blockingExceptions' => $this->findBlockingExceptions($calendar, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{slots: array<int, array{start: string, end: string}>, reason: string, detail?: string}
     */
    private function analyzeSlotsForDate(Entity $calendar, string $dateStr): array
    {
        $exceptionSlots = $this->resolveExceptionSlots($calendar, $dateStr);

        if ($exceptionSlots === false) {
            $detail = $this->describeBlockingException($calendar, $dateStr);

            return [
                'slots' => [],
                'reason' => 'exception_non_working',
                'detail' => $detail,
            ];
        }

        if ($exceptionSlots !== null) {
            return [
                'slots' => $exceptionSlots,
                'reason' => 'exception_working',
            ];
        }

        $weekday = (int) (new \DateTimeImmutable($dateStr))->format('w');
        $weekdayField = 'weekday' . $weekday;

        if (!$calendar->get($weekdayField)) {
            return [
                'slots' => [],
                'reason' => 'weekday_off',
                'detail' => 'Giorno settimana disabilitato (' . $weekdayField . ')',
            ];
        }

        $slots = $this->resolveTimeSlotsForDate($calendar, $dateStr);

        if ($slots === []) {
            return [
                'slots' => [],
                'reason' => 'no_time_ranges',
                'detail' => 'Nessuna fascia oraria (timeRanges / weekday' . $weekday . 'TimeRanges)',
            ];
        }

        return [
            'slots' => $slots,
            'reason' => 'schedule',
        ];
    }

    /**
     * @return array<int, array{name: string, dateStart: string, dateEnd: string, type: string}>
     */
    private function findBlockingExceptions(Entity $calendar, string $dateFrom, string $dateTo): array
    {
        $ranges = $this->entityManager
            ->getRDBRepository('WorkingTimeCalendar')
            ->getRelation($calendar, 'ranges')
            ->where([
                'dateStart<=' => $dateTo,
                'dateEnd>=' => $dateFrom,
            ])
            ->find();

        $list = [];

        foreach ($ranges as $range) {
            $type = (string) ($range->get('type') ?: 'Non-working');

            if (!$this->isNonWorkingType($type)) {
                continue;
            }

            $list[] = [
                'name' => (string) ($range->get('name') ?: ''),
                'dateStart' => (string) ($range->get('dateStart') ?: ''),
                'dateEnd' => (string) ($range->get('dateEnd') ?: ''),
                'type' => $type,
            ];
        }

        return $list;
    }

    private function describeBlockingException(Entity $calendar, string $dateStr): string
    {
        foreach ($this->findBlockingExceptions($calendar, $dateStr, $dateStr) as $range) {
            $label = $range['name'] !== '' ? $range['name'] : 'Eccezione non lavorativa';

            return $label . ' (' . $range['dateStart'] . ' → ' . $range['dateEnd'] . ')';
        }

        return 'Eccezione non lavorativa';
    }

    /**
     * @param array{
     *   created: int,
     *   skipped: int,
     *   errors: string[],
     *   userCount?: int,
     *   daysBlocked?: int,
     *   daysWeekdayOff?: int,
     *   daysNoSlots?: int,
     *   daysWithSlots?: int
     * } $result
     */
    public function formatGenerationMessage(
        array $result,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): string {
        $parts = [];

        if ($dateFrom && $dateTo) {
            $parts[] = sprintf('Periodo %s → %s', $dateFrom, $dateTo);
        }

        $parts[] = sprintf(
            'create %d, %d già presenti',
            $result['created'],
            $result['skipped']
        );

        if (($result['daysBlocked'] ?? 0) > 0) {
            $parts[] = ($result['daysBlocked'] ?? 0) . ' giorni esclusi (eccezione non lavorativa nel calendario)';
        }

        if (($result['daysWeekdayOff'] ?? 0) > 0) {
            $parts[] = ($result['daysWeekdayOff'] ?? 0) . ' giorni con settimana disabilitata';
        }

        if (($result['daysNoSlots'] ?? 0) > 0) {
            $parts[] = ($result['daysNoSlots'] ?? 0) . ' giorni senza fascia oraria';
        }

        if ($result['errors'] !== []) {
            $parts[] = count($result['errors']) . ' errori';
        }

        return implode(' · ', $parts) . '.';
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

        $weekdayRanges = $this->decodeRanges($calendar->get('weekday' . $weekday . 'TimeRanges'));
        $globalRanges = $this->decodeRanges($calendar->get('timeRanges'));

        if ($weekdayRanges !== []) {
            $slots = $this->parseTimeRanges($weekdayRanges, $dateStr);

            if ($slots !== []) {
                return $slots;
            }
        }

        return $this->parseTimeRanges($globalRanges, $dateStr);
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeRanges(mixed $ranges): array
    {
        if (is_string($ranges) && $ranges !== '') {
            $decoded = json_decode($ranges, true);
            $ranges = is_array($decoded) ? $decoded : [];
        }

        return is_array($ranges) ? $ranges : [];
    }

    /**
     * @return array<int, array{start: string, end: string}>|null|false
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
        $ranges = $this->decodeRanges($ranges);

        $slots = [];

        foreach ($ranges as $range) {
            [$startRaw, $endRaw] = $this->extractRangeBounds($range);

            if ($startRaw === null || $endRaw === null) {
                continue;
            }

            $start = $this->normalizeTime($startRaw);
            $end = $this->normalizeTime($endRaw);

            if ($start === null || $end === null || $start >= $end) {
                continue;
            }

            $slots[] = [
                'start' => $this->toUtcDateTime($dateStr, $start),
                'end' => $this->toUtcDateTime($dateStr, $end),
            ];
        }

        return $slots;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractRangeBounds(mixed $range): array
    {
        if (!is_array($range)) {
            return [null, null];
        }

        if (isset($range[0], $range[1])) {
            return [(string) $range[0], (string) $range[1]];
        }

        foreach ([['from', 'to'], ['start', 'end']] as [$startKey, $endKey]) {
            if (isset($range[$startKey], $range[$endKey])) {
                return [(string) $range[$startKey], (string) $range[$endKey]];
            }
        }

        return [null, null];
    }

    /**
     * @param string[] $assignedUserIds
     * @param string[] $area
     * @param string[] $collaboratorIds
     */
    private function existsDisponibilita(
        string $dateStr,
        string $orarioInizio,
        string $orarioFine,
        array $assignedUserIds,
        array $area,
        array $collaboratorIds
    ): bool {
        $startTime = $this->extractTimeFromDateTime($orarioInizio);
        $endTime = $this->extractTimeFromDateTime($orarioFine);

        $collection = $this->entityManager
            ->getRDBRepository('Disponibilita')
            ->where([
                'OR' => [
                    ['dateStartDate' => $dateStr],
                    ['datadisponibilita' => $dateStr],
                ],
            ])
            ->find();

        sort($assignedUserIds);
        sort($area);
        sort($collaboratorIds);

        foreach ($collection as $entity) {
            $existingStart = $this->extractTimeFromDateTime((string) $entity->get('orarioInizio'));
            $existingEnd = $this->extractTimeFromDateTime((string) $entity->get('orarioFine'));

            if ($existingStart !== $startTime || $existingEnd !== $endTime) {
                continue;
            }

            $existingIds = $entity->getLinkMultipleIdList('assignedUsers');
            sort($existingIds);

            if ($existingIds !== $assignedUserIds) {
                continue;
            }

            $existingArea = $entity->get('area') ?? [];
            if (!is_array($existingArea)) {
                $existingArea = $existingArea !== null && $existingArea !== '' ? [$existingArea] : [];
            }
            sort($existingArea);

            if ($existingArea !== $area) {
                continue;
            }

            $existingCollaborators = $entity->getLinkMultipleIdList('collaborators');
            sort($existingCollaborators);

            if ($existingCollaborators === $collaboratorIds) {
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

        try {
            $dt = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            $dt = $dt->setTimezone(new \DateTimeZone(self::TIMEZONE));

            return $dt->format('H:i:s');
        } catch (\Throwable) {
            if (preg_match('/(\d{2}:\d{2}:\d{2})/', $value, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function toUtcDateTime(string $dateStr, string $time): string
    {
        $local = new \DateTimeImmutable(
            $dateStr . ' ' . $time,
            new \DateTimeZone(self::TIMEZONE)
        );

        return $local
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * @param string[] $assignedUserIds
     * @param string[] $area
     * @param string[] $collaboratorIds
     */
    private function createDisponibilita(
        string $dateStr,
        string $orarioInizio,
        string $orarioFine,
        array $assignedUserIds,
        ?string $productBrandId,
        string $status,
        array $area,
        array $collaboratorIds
    ): void {
        $entity = $this->entityManager->createEntity('Disponibilita');

        $entity->set([
            'datadisponibilita' => $dateStr,
            'dateStartDate' => $dateStr,
            'dateEndDate' => $dateStr,
            'dateStart' => $dateStr . ' 00:00:00',
            'dateEnd' => $dateStr . ' 23:59:59',
            'orarioInizio' => $orarioInizio,
            'orarioFine' => $orarioFine,
            'productBrandId' => $productBrandId,
            'status' => $status,
            'area' => $area,
            'assignedUsersIds' => $assignedUserIds,
            'assignedUserId' => $assignedUserIds[0] ?? null,
            'collaboratorsIds' => $collaboratorIds,
        ]);

        $this->entityManager->saveEntity($entity);
    }

    private function resolveCalendarProductBrandId(Entity $calendar): ?string
    {
        $brandId = $calendar->get('generazioneProductBrandId');

        if ($brandId) {
            return $brandId;
        }

        $legacyAzienda = trim((string) ($calendar->get('generazioneAzienda') ?: ''));

        if ($legacyAzienda === '') {
            return null;
        }

        $brand = $this->entityManager
            ->getRDBRepository('ProductBrand')
            ->where(['name' => $legacyAzienda])
            ->findOne();

        return $brand?->getId();
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
