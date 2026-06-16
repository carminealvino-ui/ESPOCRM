<?php

namespace Espo\Custom\Tools\Activities;

use DateTime;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
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
    ) {
        parent::__construct($config, $entityManager);
    }

    /**
     * @return Item[]
     * @throws Exception
     */
    public function get(User $user): array
    {
        $items = parent::get($user);

        $seenReminderIds = [];
        $seenEntityKeys = [];

        foreach ($items as $item) {
            $reminderId = $item->getId();

            if ($reminderId) {
                $seenReminderIds[$reminderId] = true;
            }

            $entityKey = $this->getItemEntityKey($item);

            if ($entityKey) {
                $seenEntityKeys[$entityKey] = true;
            }
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

            $items[] = $item;

            if ($reminderId) {
                $seenReminderIds[$reminderId] = true;
            }

            if ($entityKey) {
                $seenEntityKeys[$entityKey] = true;
            }
        }

        foreach ($this->findPastPlannedActivityEntityItems($user, $seenEntityKeys) as $item) {
            $items[] = $item;
        }

        usort($items, function (Item $a, Item $b): int {
            return $this->getItemSortDate($a) <=> $this->getItemSortDate($b);
        });

        return $items;
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
    private function findPastPlannedActivityEntityItems(User $user, array $seenEntityKeys): array
    {
        $resultList = [];
        $now = (new DateTime())->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        foreach (self::PLANNED_STATUS_BY_ENTITY_TYPE as $entityType => $statusList) {
            try {
                $items = $this->findPastPlannedEntityItemsForType(
                    $user,
                    $entityType,
                    $statusList,
                    $now,
                    $seenEntityKeys
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
        array &$seenEntityKeys
    ): array {
        if (!$this->entityManager->hasRepository($entityType)) {
            return [];
        }

        $userId = $user->getId();
        $dateField = $entityType === Task::ENTITY_TYPE ? 'dateEnd' : 'dateStart';
        $resultList = [];

        $collection = $this->entityManager
            ->getRDBRepository($entityType)
            ->select([
                Field::ID,
                Field::NAME,
                'status',
                'dateStart',
                'dateStartDate',
                'dateEnd',
                'dateEndDate',
                'assignedUserId',
            ])
            ->where([
                'status' => $statusList,
                'assignedUserId' => $userId,
                $dateField . '<' => $now,
            ])
            ->order($dateField, 'ASC')
            ->limit(0, 50)
            ->find();

        foreach ($collection as $entity) {
            $item = $this->buildEntityItemIfNew($entity, $seenEntityKeys);

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
    private function buildEntityItemIfNew(Entity $entity, array &$seenEntityKeys): ?Item
    {
        $entityKey = $this->getEntityKey($entity->getEntityType(), $entity->getId());

        if (!$entityKey || isset($seenEntityKeys[$entityKey])) {
            return null;
        }

        if (!$this->isPlannedActivity($entity)) {
            return null;
        }

        $seenEntityKeys[$entityKey] = true;

        return new Item(null, $this->buildActivityData($entity));
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
}
