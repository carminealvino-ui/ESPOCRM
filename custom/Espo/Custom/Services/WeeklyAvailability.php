<?php
namespace Espo\Custom\Services;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Custom\Tools\WeeklyAvailability\Schedule;

class WeeklyAvailability
{
    public function __construct(private EntityManager $entityManager, private Acl $acl, private User $user, private \Espo\Core\Utils\Metadata $metadata) {}

    private function authorize(string $userId, bool $write = false): void
    {
        if (!$this->acl->checkScope('Disponibilita', $write ? 'edit' : 'read') ||
            !$this->acl->checkUserPermission($userId, 'userCalendar') ||
            ($write && !$this->user->isAdmin() && $this->user->getId() !== $userId)) {
            throw new Forbidden('Non autorizzato a gestire la disponibilità di questo collaboratore.');
        }
        $target = $this->entityManager->getEntityById('User', $userId);
        if (!$target || $target->get('type') === 'portal') {
            throw new BadRequest('Collaboratore non valido.');
        }
    }

    private function row(string $userId, string $week): ?array
    {
        $stmt = $this->entityManager->getPDO()->prepare(
            'SELECT * FROM mec_weekly_availability WHERE user_id = ? AND week_start <= ? ORDER BY week_start DESC LIMIT 1'
        );
        $stmt->execute([$userId, $week]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function load(string $userId, string $date): array
    {
        $this->authorize($userId);
        try {
            $week = Schedule::monday($date);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }
        $row = $this->row($userId, $week);
        $exact = $row && $row['week_start'] === $week;
        $result = [
            'userId' => $userId, 'week' => $week,
            'version' => $exact ? (int) $row['version'] : 0,
            'sourceWeek' => $row['week_start'] ?? null,
            'source' => $exact ? 'saved' : ($row ? 'inherited' : 'empty'),
            'days' => $row ? json_decode($row['days_json'], true) : array_fill(1, 7, []),
            'exceptions' => $exact ? (object) json_decode($row['exceptions_json'], true) : (object) [],
            'brandId' => $row['brand_id'] ?? null,
            'area' => $row ? json_decode($row['area_json'], true) : [],
            'warnings' => [],
            'canEdit' => $this->acl->checkScope('Disponibilita', 'edit') &&
                ($this->user->isAdmin() || $this->user->getId() === $userId),
        ];
        // Existing recurring rules are a reviewable starting point, never migrated on GET.
        if (!$row) {
            $result = $this->legacy($result);
        }
        if (!$exact) {
            $end = Schedule::date($week)->modify('+7 days')->format('Y-m-d');
            $absences = $this->entityManager->getRDBRepository('Disponibilita')->where([
                'datadisponibilita>=' => $week, 'datadisponibilita<' => $end, 'status!=' => 'Presente',
            ])->find();
            foreach ($absences as $absence) {
                if (!$this->acl->checkEntity($absence, 'read')) continue;
                $ids = array_merge($absence->getLinkMultipleIdList('assignedUsers'), $absence->getLinkMultipleIdList('collaborators'));
                if (!in_array($userId, $ids, true) && $absence->get('assignedUserId') !== $userId) continue;
                $date = $absence->get('datadisponibilita');
                $result['exceptions']->{$date} = [];
            }
            if ((array) $result['exceptions']) $result['warnings'][] = 'Le assenze già registrate in questa settimana sono riportate come eccezioni.';
        }
        $result['token'] = hash('sha256', json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    private function legacy(array $result, bool $fallback = true): array
    {
        if (!$this->acl->checkScope('WorkingTimeCalendar', 'read')) {
            return $result;
        }
        $end = Schedule::date($result['week'])->modify('+6 days')->format('Y-m-d');
        $records = $this->entityManager->getRDBRepository('WorkingTimeCalendar')
            ->where(['dataInizioGenerazione<=' => $end, 'dataFineGenerazione>=' => $result['week']])->find();
        $contexts = [];
        $count = 0;
        foreach ($records as $record) {
            if (!$this->acl->checkEntity($record, 'read')) {
                continue;
            }
            $ids = $record->getLinkMultipleIdList('generazioneCollaborators');
            if (!in_array($result['userId'], $ids, true)) {
                continue;
            }
            $count++;
            $brand = $record->get('generazioneProductBrandId');
            $area = $record->get('generazioneArea') ?: [];
            sort($area);
            $contexts[json_encode([$brand, $area])] = [$brand, $area];
            if (($record->get('generazioneStatus') ?: 'Presente') !== 'Presente') {
                $result['warnings'][] = 'Una ricorrenza storica contiene un’assenza: inserirla come eccezione, non come orario da ereditare.';
                continue;
            }
            for ($day = 1; $day <= 7; $day++) {
                $date = Schedule::date($result['week'])->modify('+' . ($day - 1) . ' days')->format('Y-m-d');
                if ($date < $record->get('dataInizioGenerazione') || $date > $record->get('dataFineGenerazione')) {
                    continue;
                }
                $weekday = $day % 7;
                if (!$record->get('weekday' . $weekday)) {
                    continue;
                }
                $ranges = $record->get('weekday' . $weekday . 'TimeRanges') ?: $record->get('timeRanges') ?: [];
                if (is_string($ranges)) {
                    $ranges = json_decode($ranges, true) ?: [];
                }
                foreach ($ranges as $range) {
                    $range = (array) $range;
                    $slot = [substr((string) ($range[0] ?? $range['from'] ?? $range['start'] ?? ''), 0, 5),
                        substr((string) ($range[1] ?? $range['to'] ?? $range['end'] ?? ''), 0, 5)];
                    try {
                        Schedule::slots([$slot]);
                        if (!in_array($slot, $result['days'][$day], true)) {
                            $result['days'][$day][] = $slot;
                        }
                    } catch (\InvalidArgumentException) {
                        $result['warnings'][] = 'Una fascia storica non valida richiede correzione.';
                    }
                }
            }
        }
        if ($count) {
            $result['source'] = 'legacy';
            $result['warnings'][] = 'Bozza da ' . $count . ' ricorrenze storiche: verificare fasce ed eventuali eccezioni. Il salvataggio pubblica solo la settimana selezionata.';
        }
        if (!$count && $fallback) {
            $previous = $this->entityManager->getRDBRepository('WorkingTimeCalendar')
                ->where(['dataFineGenerazione<' => $result['week']])->order('dataFineGenerazione', 'DESC')->find();
            foreach ($previous as $record) {
                if (!$this->acl->checkEntity($record, 'read') ||
                    !in_array($result['userId'], $record->getLinkMultipleIdList('generazioneCollaborators'), true) ||
                    ($record->get('generazioneStatus') ?: 'Presente') !== 'Presente') continue;
                $requestedWeek = $result['week'];
                $result['week'] = Schedule::monday($record->get('dataFineGenerazione'));
                $result = $this->legacy($result, false);
                $result['sourceWeek'] = $result['week'];
                $result['week'] = $requestedWeek;
                $result['source'] = 'inheritedLegacy';
                $result['warnings'][] = 'Orari proposti dall’ultima settimana storica disponibile; nessuna assenza viene trascinata.';
                break;
            }
        }
        if (count($contexts) === 1) {
            [$result['brandId'], $result['area']] = array_values($contexts)[0];
        } elseif (count($contexts) > 1) {
            $result['warnings'][] = 'Le ricorrenze hanno brand o aree differenti. Selezionare il contesto corretto prima del salvataggio.';
        }
        $result['warnings'] = array_values(array_unique($result['warnings']));
        return $result;
    }

    public function review(object $data): array
    {
        $userId = (string) ($data->userId ?? '');
        $this->authorize($userId, true);
        try {
            $week = Schedule::monday((string) ($data->week ?? ''));
            $days = Schedule::week(json_decode(json_encode($data->days ?? []), true));
            $exceptions = Schedule::exceptions(json_decode(json_encode($data->exceptions ?? []), true), $week);
            $intervals = [];
            for ($i = 0; $i < 7; $i++) {
                $date = Schedule::date($week)->modify("+$i days")->format('Y-m-d');
                foreach (Schedule::effective($days, $exceptions, $date) as [$a, $b]) {
                    $intervals[] = [strtotime(Schedule::instant($date, $a)), strtotime(Schedule::instant($date, $b))];
                }
            }
        } catch (\InvalidArgumentException $e) { throw new BadRequest($e->getMessage()); }
        $start = Schedule::date($week);
        $end = $start->modify('+7 days');
        $rules = $this->events($userId, $week, $end->format('Y-m-d'))['rules'];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->modify("+$i days")->format('Y-m-d');
            $rules[$date][(string) ($data->brandId ?? '')] = Schedule::effective($days, $exceptions, $date);
        }
        $utc = new \DateTimeZone('UTC');
        $items = [];
        foreach (['Appuntamento', 'Meeting', 'Call', 'Task'] as $scope) {
            if (!$this->acl->checkScope($scope, 'read')) continue;
            $fields = (array) $this->metadata->get(['entityDefs', $scope, 'fields']);
            $query = [['dateStart<' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
                'dateEnd>' => $start->setTimezone($utc)->format('Y-m-d H:i:s')]];
            if (isset($fields['dateStartDate'], $fields['dateEndDate'])) {
                $query[] = ['dateStartDate<' => $end->format('Y-m-d'), 'dateEndDate>=' => $week];
            }
            $canceled = $this->metadata->get(['scopes', $scope, 'canceledStatusList']) ?: ['Not Held', 'Canceled', 'Cancelled', 'Ingestibile'];
            foreach ($this->entityManager->getRDBRepository($scope)->where(['OR' => $query])->find() as $record) {
                if (!$this->acl->checkEntity($record, 'read') || in_array($record->get('status'), $canceled, true)) continue;
                $ids = [$record->get('assignedUserId')];
                foreach (['assignedUsers', 'collaborators', 'users'] as $link) {
                    if (isset($fields[$link])) $ids = array_merge($ids, $record->getLinkMultipleIdList($link));
                }
                if (!in_array($userId, $ids, true)) continue;
                $allDay = (bool) $record->get('dateStartDate');
                if ($allDay) {
                    $a = Schedule::date($record->get('dateStartDate'))->getTimestamp();
                    $b = Schedule::date($record->get('dateEndDate') ?: $record->get('dateStartDate'))->modify('+1 day')->getTimestamp();
                } else {
                    $a = (new \DateTimeImmutable($record->get('dateStart'), $utc))->getTimestamp();
                    $b = (new \DateTimeImmutable($record->get('dateEnd'), $utc))->getTimestamp();
                }
                $left = max($a, $start->getTimestamp()); $right = min($b, $end->getTimestamp());
                if ($right <= $left) continue;
                $items[] = ['scope' => $scope, 'id' => $record->getId(), 'name' => $record->get('name'),
                    'personal' => $scope === 'Meeting' && !$record->get('parentId') && !$record->get('appuntamentoId') && !$record->get('accountId'),
                    'start' => date('c', $a), 'end' => date('c', $b), 'allDay' => $allDay,
                    'startAvailability' => $allDay ? 'manual' : $this->startAvailability($rules, $this->recordBrandId($record), $a)];
            }
        }
        usort($items, fn ($a, $b) => [$a['start'], $a['scope'], $a['id']] <=> [$b['start'], $b['scope'], $b['id']]);
        return ['items' => $items, 'token' => hash('sha256', json_encode([$userId, $week, $days, $exceptions, $data->brandId ?? null, $data->area ?? [], $items]))];
    }

    public function save(object $data): array
    {
        $userId = (string) ($data->userId ?? '');
        $this->authorize($userId, true);
        try {
            $week = Schedule::monday((string) ($data->week ?? ''));
            $days = Schedule::week(json_decode(json_encode($data->days ?? null), true) ?? []);
            $exceptions = Schedule::exceptions(json_decode(json_encode($data->exceptions ?? []), true), $week);
            for ($i = 0; $i < 7; $i++) {
                $date = Schedule::date($week)->modify("+$i days")->format('Y-m-d');
                foreach (Schedule::effective($days, $exceptions, $date) as [$start, $end]) {
                    Schedule::instant($date, $start);
                    Schedule::instant($date, $end);
                }
            }
        } catch (\InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }
        $brandId = $data->brandId ?? null;
        if ($brandId) {
            $brand = $this->entityManager->getEntityById('ProductBrand', $brandId);
            if (!$brand || !$this->acl->checkEntity($brand, 'read')) {
                throw new BadRequest('Brand non disponibile.');
            }
        }
        $area = $data->area ?? [];
        if (!is_array($area) || array_diff($area, ['ROMA SUD', 'ROMA EST', 'ROMA NORD', 'PESCARA', 'CHIETI', 'LATINA', 'FROSINONE'])) {
            throw new BadRequest('Area non valida.');
        }
        if (!$brandId || !$area) {
            throw new BadRequest('Selezionare brand e almeno un’area.');
        }
        $pdo = $this->entityManager->getPDO();
        // Serialize edits for one collaborator, including first insert and inherited weeks.
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $lockName = 'mec-week-' . hash('sha256', $userId);
        $lockName = substr($lockName, 0, 64);
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new Conflict('Un altro salvataggio è in corso. Riprova.');
        }
        try {
            $pdo->beginTransaction();
            $current = $this->load($userId, $week);
            if (!hash_equals($current['token'], (string) ($data->token ?? ''))) {
                throw new Conflict('La disponibilità è cambiata. Ricarica prima di salvare.');
            }
            $review = $this->review($data);
            if (!hash_equals($review['token'], (string) ($data->commitmentsToken ?? ''))) {
                throw new Conflict('Verificare gli impegni aggiornati prima di salvare.');
            }
            $stmt = $pdo->prepare('INSERT INTO mec_weekly_availability
                (user_id, week_start, days_json, exceptions_json, brand_id, area_json, version, modified_by)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE days_json = VALUES(days_json), exceptions_json = VALUES(exceptions_json),
                brand_id = VALUES(brand_id), area_json = VALUES(area_json), version = version + 1,
                modified_by = VALUES(modified_by), modified_at = CURRENT_TIMESTAMP');
            $stmt->execute([$userId, $week, json_encode($days), json_encode((object) $exceptions),
                $brandId, json_encode(array_values(array_unique($area))), $this->user->getId()]);
            $audit = $pdo->prepare('INSERT INTO mec_weekly_availability_audit (user_id, week_start, actor_id, before_json, after_json) VALUES (?, ?, ?, ?, ?)');
            $audit->execute([$userId, $week, $this->user->getId(), json_encode($current),
                json_encode(['days' => $days, 'exceptions' => (object) $exceptions, 'brandId' => $brandId, 'area' => $area])]);
            $this->syncSavedWeek($userId, $week);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
        return $this->load($userId, $week);
    }

    public function syncSavedWeek(string $userId, string $week): array
    {
        $this->authorize($userId, true);
        $row = $this->row($userId, $week);
        if (!$row || $row['week_start'] !== $week) throw new BadRequest('La settimana non è salvata.');
        $marker = 'Gestita dal piano settimanale. Modificare in Disponibilità Ricorrenti. [MEC-WEEKLY:' . $userId . ':' . $week . ']';
        $records = iterator_to_array($this->entityManager->getRDBRepository('Disponibilita')->where([
            'description' => $marker, 'assignedUserId' => $userId,
        ])->order('datadisponibilita')->order('orarioInizio')->find());
        $records = array_values($records);
        $days = json_decode($row['days_json'], true);
        $exceptions = json_decode($row['exceptions_json'], true);
        $index = 0; $created = 0; $removed = 0;
        for ($i = 0; $i < 7; $i++) {
            $date = Schedule::date($week)->modify("+$i days")->format('Y-m-d');
            foreach (Schedule::effective($days, $exceptions, $date) as [$a, $b]) {
                $record = $records[$index] ?? $this->entityManager->getNewEntity('Disponibilita');
                if (!isset($records[$index])) $created++;
                $utc = new \DateTimeZone('UTC');
                $record->set(['description' => $marker, 'datadisponibilita' => $date,
                    'orarioInizio' => (new \DateTimeImmutable(Schedule::instant($date, $a)))->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'orarioFine' => (new \DateTimeImmutable(Schedule::instant($date, $b)))->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'productBrandId' => $row['brand_id'], 'area' => json_decode($row['area_json'], true), 'status' => 'Presente',
                    'assignedUserId' => $userId, 'assignedUsersIds' => [$userId], 'collaboratorsIds' => [$userId]]);
                $this->entityManager->saveEntity($record);
                $index++;
            }
        }
        foreach (array_slice($records, $index) as $record) {
            $this->entityManager->removeEntity($record); $removed++;
        }
        return ['week' => $week, 'rows' => $index, 'created' => $created, 'removed' => $removed];
    }

    public function events(string $userId, string $from, string $to): array
    {
        $this->authorize($userId);
        try {
            $start = Schedule::date($from);
            $end = Schedule::date($to);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }
        if ($end <= $start || $start->diff($end)->days > 366) {
            throw new BadRequest('Intervallo massimo: 366 giorni.');
        }
        $events = [];
        $rules = [];
        $absentRules = [];
        $legacy = [];
        $records = $this->entityManager->getRDBRepository('Disponibilita')->where([
            'datadisponibilita>=' => $from, 'datadisponibilita<' => $to,
        ])->find();
        foreach ($records as $record) {
            if (!$this->acl->checkEntity($record, 'read')) continue;
            $ids = array_merge($record->getLinkMultipleIdList('assignedUsers'), $record->getLinkMultipleIdList('collaborators'));
            if (!in_array($userId, $ids, true) && $record->get('assignedUserId') !== $userId) continue;
            $dateKey = $record->get('datadisponibilita');
            $recordBrandId = $this->recordBrandId($record);
            $rules[$dateKey][$recordBrandId] ??= [];
            $legacy[$dateKey] ??= ['slots' => [], 'absent' => false, 'brands' => [], 'brandIds' => []];
            if ($record->get('productBrandId')) $legacy[$dateKey]['brandIds'][] = $record->get('productBrandId');
            $brandName = $record->get('productBrandName') ?: $record->get('azienda');
            if ($brandName) $legacy[$dateKey]['brands'][] = $brandName;
            if (($record->get('status') ?: 'Presente') !== 'Presente') {
                $legacy[$dateKey]['absent'] = true;
                $absentRules[$dateKey][$recordBrandId] = true;
                continue;
            }
            if (!$record->get('orarioInizio') || !$record->get('orarioFine')) continue;
            try {
                $tz = new \DateTimeZone('Europe/Rome');
                $a = (new \DateTimeImmutable($record->get('orarioInizio'), new \DateTimeZone('UTC')))->setTimezone($tz)->format('H:i');
                $b = (new \DateTimeImmutable($record->get('orarioFine'), new \DateTimeZone('UTC')))->setTimezone($tz)->format('H:i');
                $slot = Schedule::slots([[$a, $b]])[0];
                $legacy[$dateKey]['slots'][] = $slot;
                $rules[$dateKey][$recordBrandId][] = $slot;
            } catch (\Exception) { /* Invalid historical rows never become fabricated time ranges. */ }
        }
        $managedDates = [];
        foreach ($absentRules as $dateKey => $brandMap) foreach ($brandMap as $brandId => $_) $rules[$dateKey][$brandId] = [];
        $brands = [];
        $brandColors = [];
        $weeks = [];
        for ($date = $start; $date < $end; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $week = Schedule::monday($key);
            if (!array_key_exists($week, $weeks)) {
                $weeks[$week] = $this->row($userId, $week);
            }
            $row = $weeks[$week];
            // Inheritance is an editor draft. Only an exact saved week is published.
            if ($row && $row['week_start'] !== $week) $row = null;
            if (!$row && !isset($legacy[$key])) {
                continue;
            }
            $managedDates[] = $key;
            $brandId = $row['brand_id'] ?? (array_values(array_unique($legacy[$key]['brandIds'] ?? []))[0] ?? null);
            $brand = $brandId ? $this->entityManager->getEntityById('ProductBrand', $brandId) : null;
            $color = $brand ? (string) $brand->get('color') : '';
            $color = preg_match('/^#[0-9a-fA-F]{6}$/D', $color) ? $color : '#808080';
            $brandColors[$key] = $color;
            $brandName = $brand ? $brand->get('name') : implode(' / ', array_unique($legacy[$key]['brands'] ?? []));
            $brands[$key] = $brandName;
            if ($row) {
                $days = json_decode($row['days_json'], true);
                $exceptions = $row['week_start'] === $week ? json_decode($row['exceptions_json'], true) : [];
                $slots = $row['week_start'] !== $week && ($legacy[$key]['absent'] ?? false) ? [] : Schedule::effective($days, $exceptions, $key);
                $rules[$key][(string) $row['brand_id']] = $slots;
            } else {
                $slots = $legacy[$key]['absent'] ? [] : $legacy[$key]['slots'];
                usort($slots, fn ($a, $b) => strcmp($a[0], $b[0]));
                $merged = [];
                foreach ($slots as $slot) {
                    $last = count($merged) - 1;
                    if ($last >= 0 && $slot[0] <= $merged[$last][1]) $merged[$last][1] = max($merged[$last][1], $slot[1]);
                    else $merged[] = $slot;
                }
                $slots = $merged;
            }
            foreach ($slots as $index => [$startTime, $endTime]) {
                $events[] = [
                    'id' => 'weekly-' . $userId . '-' . $key . '-' . $index,
                    'start' => Schedule::instant($key, $startTime), 'end' => Schedule::instant($key, $endTime),
                    'title' => ($brandName ? $brandName . ' · ' : '') . "Dalle $startTime alle $endTime", 'allDay' => false,
                    'display' => 'background', 'backgroundColor' => $color, 'brandColor' => $color, 'editable' => false,
                    'weeklyAvailability' => true, 'userId' => $userId, 'brandName' => $brandName,
                    'inherited' => $row && $row['week_start'] !== $week,
                ];
            }
        }
        $appointmentAvailability = [];
        if ($this->acl->checkScope('Appuntamento', 'read')) {
            $utc = new \DateTimeZone('UTC');
            $canceled = $this->metadata->get(['scopes', 'Appuntamento', 'canceledStatusList']) ?: ['Not Held', 'Ingestibile'];
            foreach ($this->entityManager->getRDBRepository('Appuntamento')->where([
                'dateStart>=' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
                'dateStart<' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
            ])->find() as $record) {
                if (!$this->acl->checkEntity($record, 'read') || in_array($record->get('status'), $canceled, true) || $record->get('dateStartDate')) continue;
                $ids = array_merge([$record->get('assignedUserId')], $record->getLinkMultipleIdList('assignedUsers'), $record->getLinkMultipleIdList('collaborators'));
                if (!in_array($userId, $ids, true)) continue;
                $appointmentAvailability[$record->getId()] = $this->startAvailability($rules, $this->recordBrandId($record), (new \DateTimeImmutable($record->get('dateStart'), $utc))->getTimestamp());
            }
        }
        $meetingColors = [];
        if ($this->acl->checkScope('Meeting', 'read')) {
            $utc = new \DateTimeZone('UTC');
            foreach ($this->entityManager->getRDBRepository('Meeting')->where([
                'dateStart<' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
                'dateEnd>' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            ])->find() as $meeting) {
                if (!$this->acl->checkEntity($meeting, 'read')) continue;
                if ($meeting->get('assignedUserId') !== $userId && !in_array($userId, $meeting->getLinkMultipleIdList('users'), true)) continue;
                $isPrivate = (bool) $meeting->get('privato');
                if ($isPrivate) {
                    $meetingColors[$meeting->getId()] = '#ffdf00';
                    continue;
                }
                // Non-private meetings without a business link still need a non-yellow color.
                $meetingColors[$meeting->getId()] = 'rgba(184, 169, 217, 0.5)';
                $type = $meeting->get('parentType'); $id = $meeting->get('parentId');
                if (!$id && $meeting->get('appuntamentoId')) { $type = 'Appuntamento'; $id = $meeting->get('appuntamentoId'); }
                if (!$id && $meeting->get('accountId')) { $type = 'Account'; $id = $meeting->get('accountId'); }
                if (!$type || !$id) continue;
                // A business relationship is never treated as a personal event.
                $parent = $this->entityManager->getEntityById($type, $id);
                if (!$parent || !$this->acl->checkEntity($parent, 'read')) continue;
                $brand = null;
                if ($type === 'ProductBrand') {
                    $brand = $parent;
                } elseif ($type === 'FornitorePartner') {
                    $brandsForPartner = iterator_to_array($this->entityManager->getRDBRepository('ProductBrand')->where(['fornitorePartnerId' => $id])->find());
                    if (count($brandsForPartner) === 1) $brand = array_values($brandsForPartner)[0];
                } elseif ($parent->get('productBrandId')) {
                    $brand = $this->entityManager->getEntityById('ProductBrand', $parent->get('productBrandId'));
                }
                $color = $brand && $this->acl->checkEntity($brand, 'read') ? (string) $brand->get('color') : '';
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
                    $partner = $type === 'FornitorePartner' ? $parent : null;
                    $partnerId = $parent->get('fornitorePartnerId');
                    if (!$partner && $partnerId) $partner = $this->entityManager->getEntityById('FornitorePartner', $partnerId);
                    if ($partner && $this->acl->checkEntity($partner, 'read')) $color = (string) $partner->get('color');
                }
                if (preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
                    $meetingColors[$meeting->getId()] = sprintf('rgba(%d, %d, %d, 0.5)', hexdec(substr($color,1,2)), hexdec(substr($color,3,2)), hexdec(substr($color,5,2)));
                }
            }
        }
        return ['events' => $events, 'managedDates' => $managedDates, 'brands' => $brands, 'brandColors' => $brandColors, 'meetingColors' => $meetingColors,
            'rules' => $rules, 'appointmentAvailability' => $appointmentAvailability];
    }

    private function recordBrandId(\Espo\ORM\Entity $record): string
    {
        if ($record->get('productBrandId')) return (string) $record->get('productBrandId');
        $name = trim((string) $record->get('azienda'));
        if (!$name) return '';
        return $this->entityManager->getRDBRepository('ProductBrand')->where(['name' => $name])->findOne()?->getId() ?? '';
    }

    public function startAvailability(array $rules, string $brandId, int $timestamp): string
    {
        $local = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('Europe/Rome'));
        $date = $local->format('Y-m-d');
        if (!$brandId || !array_key_exists($brandId, $rules[$date] ?? [])) return 'unrestricted';
        foreach ($rules[$date][$brandId] as [$a, $b]) {
            if ($timestamp >= strtotime(Schedule::instant($date, $a)) && $timestamp <= strtotime(Schedule::instant($date, $b))) return 'inside';
        }
        return 'outside';
    }

    public function teamEvents(array $teamIds, string $from, string $to): array
    {
        if (count($teamIds) > 20) throw new BadRequest('Troppi team.');
        $users = [];
        foreach (array_unique($teamIds) as $teamId) {
            $team = $this->entityManager->getEntityById('Team', $teamId);
            if (!$team || !$this->acl->checkEntity($team, 'read')) throw new Forbidden();
            foreach ($this->entityManager->getRDBRepository('Team')->getRelation($team, 'users')->find() as $user) {
                if (!$user->get('isActive') || !$this->acl->checkUserPermission($user->getId(), 'userCalendar')) continue;
                $users[$user->getId()] = $user;
            }
        }
        if (count($users) > 100) throw new BadRequest('Selezionare al massimo 100 collaboratori.');
        $result = [];
        foreach ($users as $user) {
            $result[] = ['userId' => $user->getId(), 'name' => $user->get('name')] + $this->events($user->getId(), $from, $to);
        }
        return $result;
    }
}
