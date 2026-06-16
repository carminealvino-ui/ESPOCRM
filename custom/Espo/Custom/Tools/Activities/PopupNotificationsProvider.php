<?php

namespace Espo\Custom\Tools\Activities;

use Espo\Core\Name\Field;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Crm\Entities\Reminder;
use Espo\Modules\Crm\Tools\Activities\PopupNotificationsProvider as BasePopupNotificationsProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\PopupNotification\Item;
use Exception;

class PopupNotificationsProvider extends BasePopupNotificationsProvider
{
    private const APPUNTAMENTO_ENTITY_TYPE = 'Appuntamento';

    private const APPUNTAMENTO_STATUS_PLANNED = 'Planned';

    public function __construct(
        private Config $config,
        private EntityManager $entityManager,
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
        $seenEntityIds = [];

        foreach ($items as $item) {
            $reminderId = $item->getId();

            if ($reminderId) {
                $seenReminderIds[$reminderId] = true;
            }

            $entityId = $this->getItemEntityId($item);

            if ($entityId) {
                $seenEntityIds[$entityId] = true;
            }
        }

        foreach ($this->findPlannedAppuntamentoReminderItems($user) as $item) {
            $reminderId = $item->getId();

            if ($reminderId && isset($seenReminderIds[$reminderId])) {
                continue;
            }

            $entityId = $this->getItemEntityId($item);

            if ($entityId && isset($seenEntityIds[$entityId])) {
                continue;
            }

            $items[] = $item;

            if ($reminderId) {
                $seenReminderIds[$reminderId] = true;
            }

            if ($entityId) {
                $seenEntityIds[$entityId] = true;
            }
        }

        foreach ($this->findPlannedAppuntamentoEntityItems($user, $seenEntityIds) as $item) {
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
    private function findPlannedAppuntamentoReminderItems(User $user): array
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
                'entityType' => self::APPUNTAMENTO_ENTITY_TYPE,
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
     * @param array<string, bool> $seenEntityIds
     * @return Item[]
     */
    private function findPlannedAppuntamentoEntityItems(User $user, array $seenEntityIds): array
    {
        $userId = $user->getId();

        $collection = $this->entityManager
            ->getRDBRepository(self::APPUNTAMENTO_ENTITY_TYPE)
            ->distinct()
            ->select([
                Field::ID,
                Field::NAME,
                'dateStart',
                'dateStartDate',
                'status',
            ])
            ->leftJoin('assignedUsers')
            ->leftJoin('collaborators')
            ->where([
                'status' => self::APPUNTAMENTO_STATUS_PLANNED,
                [
                    'assignedUserId' => $userId,
                    'assignedUsers.id' => $userId,
                    'collaborators.id' => $userId,
                ],
            ])
            ->find();

        $resultList = [];

        foreach ($collection as $entity) {
            $entityId = $entity->getId();

            if (!$entityId || isset($seenEntityIds[$entityId])) {
                continue;
            }

            $resultList[] = $this->buildAppuntamentoItem($entity);

            $seenEntityIds[$entityId] = true;
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

        if (!$entity) {
            return null;
        }

        if (!$this->isPlannedAppuntamento($entity)) {
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

        return new Item($reminderId, $this->buildAppuntamentoData($entity));
    }

    private function buildAppuntamentoItem(Entity $entity): Item
    {
        return new Item(null, $this->buildAppuntamentoData($entity));
    }

    private function buildAppuntamentoData(Entity $entity): object
    {
        return (object) [
            'id' => $entity->getId(),
            'entityType' => self::APPUNTAMENTO_ENTITY_TYPE,
            'name' => $entity->get(Field::NAME),
            'dateField' => 'dateStart',
            'attributes' => (object) [
                'dateStart' => $entity->get('dateStart'),
                'dateStartDate' => $entity->get('dateStartDate'),
            ],
        ];
    }

    private function isPlannedAppuntamento(Entity $entity): bool
    {
        return $entity->getEntityType() === self::APPUNTAMENTO_ENTITY_TYPE &&
            $entity->get('status') === self::APPUNTAMENTO_STATUS_PLANNED;
    }

    private function getItemEntityId(Item $item): ?string
    {
        $data = $item->getData();

        return $data->id ?? null;
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
