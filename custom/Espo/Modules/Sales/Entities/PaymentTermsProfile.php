<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 11af5a568c1a72dce4e164257d1a0207
 ************************************************************************************/

namespace Espo\Modules\Sales\Entities;

use Espo\Core\Field\DateTime;
use Espo\Core\ORM\Entity;
use Espo\Modules\Sales\Tools\PaymentTerms\ProfileTermItem;
use Espo\ORM\EntityCollection;
use RuntimeException;
use stdClass;

class PaymentTermsProfile extends Entity
{
    public const ENTITY_TYPE = 'PaymentTermsProfile';

    public const FIELD_STATUS = 'status';
    public const FIELD_NAME = 'name';
    public const FIELD_NOTE = 'note';
    public const FIELD_ORDER = 'order';
    public const FIELD_ITEMS = 'items';

    public const STATUS_ACTIVE = 'Active';

    public function isActive(): bool
    {
        return $this->get(self::FIELD_STATUS) === self::STATUS_ACTIVE;
    }

    public function getName(): ?string
    {
        return $this->get(self::FIELD_NAME);
    }

    public function setName(?string $name): self
    {
        return $this->set(self::FIELD_NAME, $name);
    }

    public function getNote(): ?string
    {
        return $this->get(self::FIELD_NOTE);
    }

    /**
     * @param int $order
     */
    public function setOrder(int $order): self
    {
        return $this->set(self::FIELD_ORDER, $order);
    }

    public function getOrder(): int
    {
        return (int) $this->get(self::FIELD_ORDER);
    }

    /**
     * @param ProfileTermItem[] $items
     */
    public function setItems(array $items): self
    {
        $this->set(self::FIELD_ITEMS, self::serializeItems($items));

        return $this;
    }

    public function isItemsChanged(): bool
    {
        if (!$this->hasFetched(self::FIELD_ITEMS)) {
            $this->getFetchedItems();
        }

        return $this->isAttributeChanged(self::FIELD_ITEMS);
    }

    /**
     * @return ProfileTermItem[]
     * @throws RuntimeException
     */
    public function getFetchedItems(): array
    {
        if ($this->isNew()) {
            return [];
        }

        if (!$this->hasFetched(self::FIELD_ITEMS)) {
            $serialized = self::serializeItems($this->loadItems());

            $this->setFetched(self::FIELD_ITEMS, $serialized);
        }

        $serialized = $this->getFetched(self::FIELD_ITEMS) ?? [];

        return self::unserializeItems($serialized);
    }

    /**
     * @return ProfileTermItem[]
     * @throws RuntimeException
     */
    public function getItems(): array
    {
        if (!$this->has(self::FIELD_ITEMS) && !$this->isNew()) {
            $items = $this->loadItems();

            $serialized = self::serializeItems($items);

            $this->setInContainerNotWritten(self::FIELD_ITEMS, $serialized);

            $this->setFetched(self::FIELD_ITEMS, $serialized);

            return $items;
        }

        /** @var stdClass[] $serialized */
        $serialized = $this->has(self::FIELD_ITEMS) ?
            ($this->get(self::FIELD_ITEMS) ?? []) :
            [];

        return self::unserializeItems($serialized);
    }

    /**
     * @return ProfileTermItem[]
     */
    private function loadItems(): array
    {
        $list = [];

        /** @var EntityCollection<PaymentTermsProfileItem> $collection */
        $collection = $this->relations->getMany(self::FIELD_ITEMS);

        foreach ($collection as $entity) {
            $list[] = new ProfileTermItem(
                percentage: $entity->getPercentage(),
                days: $entity->getDays(),
            );
        }

        return $list;
    }

    /**
     * @param ProfileTermItem[] $items
     * @return stdClass[]
     */
    private static function serializeItems(array $items): array
    {
        $output = [];

        foreach ($items as $item) {
            $output[] = (object) [
                'percentage' => $item->percentage,
                'days' => $item->days,
            ];
        }

        return $output;
    }

    /**
     * @param stdClass[] $items
     * @return ProfileTermItem[]
     */
    private static function unserializeItems(array $items): array
    {
        $output = [];

        foreach ($items as $item) {
            $output[] = self::unserializeItem($item);
        }

        return $output;
    }

    private static function unserializeItem(stdClass $item): ProfileTermItem
    {
        $percentage = $item->percentage ?? '0.00';
        $days = $item->days ?? 0;

        return new ProfileTermItem(
            percentage: $percentage,
            days: $days,
        );
    }

    public function getModifiedAt(): ?DateTime
    {
        /** @var ?DateTime */
        return $this->getValueObject('modifiedAt');
    }
}
