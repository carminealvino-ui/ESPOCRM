<?php

namespace Espo\Custom\Tools\Activities;

use DateTime;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Crm\Entities\Reminder;
use Espo\Modules\Crm\Entities\Task;
use Espo\Modules\Crm\Tools\Activities\PopupNotificationsProvider as BasePopupNotificationsProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\PopupNotification\Item;
use Exception;
use Throwable;

class PopupNotificationsProvider extends BasePopupNotificationsProvider
{
    /**
     * @var array<string, string[]>
     */
    private const PLANNED_STATUS_BY_ENTITY_TYPE = [
        'Appuntamento' => ['Planned'],
        'Meeting' => ['Planned'],
        'Call' => ['Planned'],
        'Task' => ['Not Started', 'Started'],
    ];

    public function __construct(
        private Config $config,
        private EntityManager $entityManager,
        private Log $log,
        private AppuntamentoPendingCallCreator $callCreator,
    ) {
        parent::__construct($config, $entityManager);
    }

    /**
     * @return Item[]
     * @throws Exception
     */
    public function get(User $user): array
    {
        $items = array_values(array_filter(
            parent::get($user),
            fn (Item $item): bool => $this->isItemVisible($item)
        ));

        $seenReminderIds = [];
        $seenEntityKeys = [];
        $seenCallSignatures = [];

        foreach ($items as $item) {
            $reminderId = $item->getId();

            if ($reminderId) {
                $seenReminderIds[$reminderId] = true;
            }

            $entityKey = $this->getItemEntityKey($item);

            if ($entityKey) {
                $seenEntityKeys[$entityKey] = true;
            }

            $this->rememberCallSignature($item, $seenCallSignatures);
        }

        foreach ($this->findPlannedReminderItems($user) as $item) {
            $reminderId = $item->getId();

            if ($reminderId && isset($seenReminderIds[$reminderId])) {
                continue;
            }

            $entityKey = $this->getItemEntityKey($item);

            if ($entityKey && isset($seenEntityKeys[$entityKey])) {
                continue;
            }

            if ($this->isDuplicateCallSignature($item, $seenCallSignatures)) {
                continue;
            }

            $items[] = $item;

            if ($reminderId) {
                $seenReminderIds[$reminderId] = true;
            }

            if ($entityKey) {
                $seenEntityKeys[$entityKey] = true;
            }

            $this->rememberCallSignature($item, $seenCallSignatures);
        }

        foreach ($this->findPastPlannedActivityEntityItems($user, $seenEntityKeys, $seenCallSignatures) as $item) {
            $items[] = $item;
        }

        usort($items, function (Item $a, Item $b): int {
            return $this->compareItems($a, $b);
        });

        return array_values(array_filter(
            $items,
            fn (Item $item): bool => $this->isItemVisible($item)
        ));
    }

    /**
     * @return Item[]
     */
    private function findPlannedReminderItems(User $user): array
    {
        $userId = $user->getId();
        $now = (new DateTime())->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        /** @var iterable<Reminder> $reminderCollection */
        $reminderCollection = $this->entityManager
            ->getRDBRepositoryByClass(Reminder::class)
            ->select([
                'id',
                'entityType',
                'entityId',
            ])
            ->where([
                'type' => Reminder::TYPE_POPUP,
                'userId' => $userId,
                'remindAt<=' => $now,
            ])
            ->find();

        $resultList = [];

        foreach ($reminderCollection as $reminder) {
            $item = $this->buildReminderItem($reminder, $userId);

            if ($item === null) {
                continue;
            }

            $resultList[] = $item;
        }

        return $resultList;
    }

    /**
     * @param array<string, bool> $seenEntityKeys
     * @return Item[]
     */
    private function findPastPlannedActivityEntityItems(
        User $user,
        array $seenEntityKeys,
        array &$seenCallSignatures
    ): array {
        $resultList = [];
        $now = (new DateTime())->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        foreach (self::PLANNED_STATUS_BY_ENTITY_TYPE as $entityType => $statusList) {
            try {
                $items = $this->findPastPlannedEntityItemsForType(
                    $user,
                    $entityType,
                    $statusList,
                    $now,
                    $seenEntityKeys,
                    $seenCallSignatures
                );
            } catch (Throwable $e) {
                $this->log->error('PopupNotificationsProvider past planned query failed for ' . $entityType, [
                    'exception' => $e,
                ]);

                continue;
            }

            foreach ($items as $item) {
                $resultList[] = $item;
            }
        }

        return $resultList;
    }

    /**
     * @param string[] $statusList
     * @param array<string, bool> $seenEntityKeys
     * @return Item[]
     */
    private function findPastPlannedEntityItemsForType(
        User $user,
        string $entityType,
        array $statusList,
        string $now,
        array &$seenEntityKeys,
        array &$seenCallSignatures
    ): array {
        if (!$this->entityManager->hasRepository($entityType)) {
            return [];
        }

        $userId = $user->getId();
        $dateField = $entityType === Task::ENTITY_TYPE ? 'dateEnd' : 'dateStart';
        $popupCutoff = $entityType === 'Appuntamento'
            ? PendingCallDateTime::popupEligibilityCutoff()
            : $now;
        $resultList = [];

        $collection = $this->entityManager
            ->getRDBRepository($entityType)
            ->select($this->getPastPlannedSelectFields($entityType, $dateField))
            ->where([
                'status' => $statusList,
                'assignedUserId' => $userId,
                $dateField . '<=' => $popupCutoff,
            ])
            ->order($dateField, 'DESC')
            ->limit(0, 50)
            ->find();

        foreach ($collection as $entity) {
            $item = $this->buildEntityItemIfNew($entity, $seenEntityKeys, $seenCallSignatures);

            if ($item !== null) {
                $resultList[] = $item;
            }
        }

        return $resultList;
    }

    private function buildReminderItem(Reminder $reminder, string $userId): ?Item
    {
        $reminderId = $reminder->getId();
        $entityType = $reminder->getTargetEntityType();
        $entityId = $reminder->getTargetEntityId();

        if (!$entityId || !$entityType) {
            return null;
        }

        $entity = $this->entityManager->getEntityById($entityType, $entityId);

        if (!$entity || !$this->isPlannedActivity($entity) || !$this->userCanSeeActivity($entity, $userId)) {
            return null;
        }

        if (!$this->isPopupEligible($entity)) {
            return null;
        }

        if (!$this->isCallPopupVisible($entity)) {
            return null;
        }

        if (
            $entity instanceof CoreEntity &&
            $entity->hasLinkMultipleField('users') &&
            $entity->hasAttribute('usersColumns')
        ) {
            $status = $entity->getLinkMultipleColumn('users', 'status', $userId);

            if ($status === Meeting::ATTENDEE_STATUS_DECLINED) {
                return null;
            }
        }

        return new Item($reminderId, $this->buildActivityData($entity));
    }

    /**
     * @param array<string, bool> $seenEntityKeys
     */
    private function buildEntityItemIfNew(
        Entity $entity,
        array &$seenEntityKeys,
        array &$seenCallSignatures
    ): ?Item {
        $entityKey = $this->getEntityKey($entity->getEntityType(), $entity->getId());

        if (!$entityKey || isset($seenEntityKeys[$entityKey])) {
            return null;
        }

        if (!$this->isPlannedActivity($entity)) {
            return null;
        }

        if (!$this->isPopupEligible($entity)) {
            return null;
        }

        if (!$this->isCallPopupVisible($entity)) {
            return null;
        }

        $signature = $this->getCallSignatureForEntity($entity);

        if ($signature && isset($seenCallSignatures[$signature])) {
            return null;
        }

        $seenEntityKeys[$entityKey] = true;

        if ($signature) {
            $seenCallSignatures[$signature] = true;
        }

        return new Item(
            $this->buildPastPlannedItemId($entity->getEntityType(), $entity->getId()),
            $this->buildActivityData($entity)
        );
    }

    private function buildPastPlannedItemId(string $entityType, string $entityId): string
    {
        return 'past-' . $entityType . '-' . $entityId;
    }

    private function buildActivityData(Entity $entity): object
    {
        $entityType = $entity->getEntityType();
        $dateField = $entityType === Task::ENTITY_TYPE ? 'dateEnd' : 'dateStart';

        return (object) [
            'id' => $entity->getId(),
            'entityType' => $entityType,
            'name' => $entity->get(Field::NAME),
            'dateField' => $dateField,
            'attributes' => (object) [
                $dateField => $entity->get($dateField),
                $dateField . 'Date' => $entity->get($dateField . 'Date'),
            ],
        ];
    }

    private function isPlannedActivity(Entity $entity): bool
    {
        $entityType = $entity->getEntityType();
        $statusList = self::PLANNED_STATUS_BY_ENTITY_TYPE[$entityType] ?? null;

        if ($statusList === null) {
            return false;
        }

        return in_array($entity->get('status'), $statusList, true);
    }

    private function isCallPopupVisible(Entity $entity): bool
    {
        if ($entity->getEntityType() !== 'Call') {
            return true;
        }

        return $this->callCreator->shouldShowAutoPendingCallInPopup($entity);
    }

    private function isPopupEligible(Entity $entity): bool
    {
        $entityType = $entity->getEntityType();

        if ($entityType !== 'Appuntamento') {
            return true;
        }

        $dateStart = $entity->get('dateStart');

        if (!$dateStart) {
            return false;
        }

        return $dateStart <= PendingCallDateTime::popupEligibilityCutoff();
    }

    private function isItemVisible(Item $item): bool
    {
        $data = $item->getData();

        if (!is_object($data)) {
            return true;
        }

        $entityType = $data->entityType ?? null;
        $entityId = $data->id ?? null;

        if ($entityType === 'Call' && $entityId) {
            $entity = $this->entityManager->getEntityById('Call', $entityId);

            if (!$entity) {
                return false;
            }

            return $this->isCallPopupVisible($entity);
        }

        return $this->isItemPopupEligible($item);
    }

    /**
     * @param array<string, bool> $seenCallSignatures
     */
    private function rememberCallSignature(Item $item, array &$seenCallSignatures): void
    {
        $signature = $this->getCallSignatureForItem($item);

        if ($signature) {
            $seenCallSignatures[$signature] = true;
        }
    }

    /**
     * @param array<string, bool> $seenCallSignatures
     */
    private function isDuplicateCallSignature(Item $item, array $seenCallSignatures): bool
    {
        $signature = $this->getCallSignatureForItem($item);

        return $signature !== null && isset($seenCallSignatures[$signature]);
    }

    private function getCallSignatureForItem(Item $item): ?string
    {
        $data = $item->getData();

        if (!is_object($data) || ($data->entityType ?? null) !== 'Call' || !($data->id ?? null)) {
            return null;
        }

        $entity = $this->entityManager->getEntityById('Call', (string) $data->id);

        return $entity ? $this->getCallSignatureForEntity($entity) : null;
    }

    private function getCallSignatureForEntity(Entity $entity): ?string
    {
        if ($entity->getEntityType() !== 'Call') {
            return null;
        }

        if (!$this->callCreator->isAutoManagedRichiamoCall($entity)) {
            return null;
        }

        return $this->callCreator->buildCallAppointmentSignature($entity);
    }

    private function isItemPopupEligible(Item $item): bool
    {
        $data = $item->getData();

        if (!is_object($data) || ($data->entityType ?? null) !== 'Appuntamento') {
            return true;
        }

        $dateStart = $data->attributes->dateStart ?? null;

        if (!$dateStart) {
            return false;
        }

        return $dateStart <= PendingCallDateTime::popupEligibilityCutoff();
    }

    private function userCanSeeActivity(Entity $entity, string $userId): bool
    {
        if ($entity->get('assignedUserId') === $userId) {
            return true;
        }

        foreach (['assignedUsers', 'collaborators', 'users'] as $link) {
            if (!$entity->hasRelation($link)) {
                continue;
            }

            $ids = $entity->getLinkMultipleIdList($link);

            if (in_array($userId, $ids, true)) {
                return true;
            }
        }

        return false;
    }

    private function getItemEntityKey(Item $item): ?string
    {
        $data = $item->getData();
        $entityType = $data->entityType ?? null;
        $entityId = $data->id ?? null;

        if (!$entityType || !$entityId) {
            return null;
        }

        return $this->getEntityKey($entityType, $entityId);
    }

    private function getEntityKey(string $entityType, string $entityId): string
    {
        return $entityType . ':' . $entityId;
    }

    private function getItemSortDate(Item $item): string
    {
        $data = $item->getData();
        $dateField = $data->dateField ?? 'dateStart';
        $attributes = $data->attributes ?? null;

        if (!$attributes) {
            return '';
        }

        return $attributes->{$dateField} ?? $attributes->dateStart ?? '';
    }

    private function compareItems(Item $a, Item $b): int
    {
        $dateCompare = $this->getItemSortTimestamp($b) <=> $this->getItemSortTimestamp($a);

        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return ($this->getItemEntityKey($a) ?? '') <=> ($this->getItemEntityKey($b) ?? '');
    }

    private function getItemSortTimestamp(Item $item): int
    {
        $data = $item->getData();
        $entityType = $data->entityType ?? null;

        if ($entityType === 'Call') {
            $name = (string) ($data->name ?? '');
            $timestamp = $this->parseItalianDateTimeFromCallName($name);

            if ($timestamp !== null) {
                return $timestamp;
            }
        }

        $date = $this->getItemSortDate($item);

        if ($date === '') {
            return 0;
        }

        try {
            return (new DateTime($date))->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function parseItalianDateTimeFromCallName(string $name): ?int
    {
        if (!preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})/', $name, $matches)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat(
            'd/m/Y H:i',
            $matches[1] . ' ' . $matches[2],
            new \DateTimeZone('Europe/Rome')
        );

        if (!$parsed) {
            return null;
        }

        return $parsed->getTimestamp();
    }

    /**
     * @return string[]
     */
    private function getPastPlannedSelectFields(string $entityType, string $dateField): array
    {
        $select = [
            Field::ID,
            Field::NAME,
            'status',
            'assignedUserId',
        ];

        $defs = $this->entityManager->getDefs()->getEntity($entityType);

        if ($defs->hasAttribute($dateField)) {
            $select[] = $dateField;
        }

        $dateOnlyField = $dateField . 'Date';

        if ($defs->hasAttribute($dateOnlyField)) {
            $select[] = $dateOnlyField;
        }

        return $select;
    }
}
