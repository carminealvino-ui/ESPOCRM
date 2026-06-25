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

namespace Espo\Modules\Sales\Tools\Sales;

use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\Field\DateTime;
use Espo\Core\Field\Link;
use Espo\Core\Field\LinkMultiple;
use Espo\Core\ORM\Entity;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\QuoteItem;
use RuntimeException;
use stdClass;

/**
 * @template Item of OrderItem = OrderItem
 * @implements HasItems<Item>
 */
abstract class OrderEntity extends Entity implements HasItems
{
    public const INVENTORY_STATUS_AVAILABLE = 'Available';
    public const INVENTORY_STATUS_ON_HAND = 'On Hand';
    public const INVENTORY_STATUS_NOT_AVAILABLE = 'Not Available';

    public const ATTR_ITEM_LIST = 'itemList';
    public const ATTR_AMOUNT_CURRENCY = 'amountCurrency';
    public const ATTR_ACCOUNT_ID = 'accountId';
    public const ATTR_PRICE_BOOK_ID = 'priceBookId';
    public const ATTR_TAX_ID = 'taxId';
    public const ATTR_TAX_CODE_ID = 'taxCodeId';
    public const ATTR_AMOUNT_LOCAL_CURRENCY = 'amountLocalCurrency';
    private const ATTR_ROUNDING_PROFILE_ID = 'roundingProfileId';

    public const FIELD_AMOUNT = 'amount';
    public const FIELD_STATUS = 'status';
    public const FIELD_GRAND_TOTAL_AMOUNT = 'grandTotalAmount';
    public const FIELD_DISCOUNT_AMOUNT = 'discountAmount';
    public const FIELD_TAX_AMOUNT = 'taxAmount';
    public const FIELD_PRE_DISCOUNTED_AMOUNT = 'preDiscountedAmount';
    public const FIELD_ROUNDING_AMOUNT = 'roundingAmount';
    public const FIELD_SHIPPING_COST = 'shippingCost';
    public const FIELD_SHIPPING_AMOUNT = 'shippingAmount';
    public const FIELD_IS_TAX_INCLUSIVE = 'isTaxInclusive';
    public const FIELD_WEIGHT = 'weight';
    public const FIELD_ROUNDING_PROFILE = 'roundingProfile';
    public const FIELD_CURRENCY_RATE = 'currencyRate';
    public const FIELD_AMOUNT_LOCAL = 'amountLocal';
    public const FIELD_GRAND_TOTAL_AMOUNT_LOCAL = 'grandTotalAmountLocal';
    public const FIELD_ROUNDING_AMOUNT_LOCAL = 'roundingAmountLocal';
    public const FIELD_SHIPPING_AMOUNT_LOCAL = 'shippingAmountLocal';
    public const FIELD_TAX_AMOUNT_LOCAL = 'taxAmountLocal';
    public const FIELD_IS_ISSUED = 'isIssued';
    public const FIELD_WAS_ISSUED = 'wasIssued';
    public const FIELD_ISSUED_AT = 'issuedAt';
    public const FIELD_ISSUED_BY = 'issuedBy';
    public const FIELD_POSTING_DATE = 'postingDate';
    public const FIELD_PAYMENT_TERMS_PROFILE = 'paymentTermsProfile';
    public const FIELD_SHIPPING_TAX_MODE = 'shippingTaxMode';
    private const FIELD_TAX_RATE = 'taxRate';
    public const FIELD_INSTALLMENTS = 'installments';
    public const FIELD_PAYMENT_TERMS_NOTE = 'paymentTermsNote';
    public const FIELD_IS_NOT_ACTUAL = 'isNotActual';
    public const FIELD_NUMBER_A = 'numberA';
    public const FIELD_NUMBER_DRAFT_A = 'numberDraftA';

    public function isChangedToRecalculateItems(): bool
    {
        return
            $this->isAttributeChanged(self::FIELD_SHIPPING_COST) ||
            $this->isAttributeChanged(self::FIELD_SHIPPING_TAX_MODE) ||
            $this->isAttributeChanged(self::ATTR_TAX_ID) ||
            $this->isAttributeChanged(self::FIELD_TAX_RATE) ||
            $this->isAttributeChanged(self::ATTR_ROUNDING_PROFILE_ID) ||
            $this->isAttributeChanged(self::FIELD_CURRENCY_RATE) ||
            $this->isAttributeChanged(self::ATTR_AMOUNT_LOCAL_CURRENCY);
    }

    public function getName(): ?string
    {
        return $this->get('name');
    }

    public function getNumber(): ?string
    {
        return $this->get('number');
    }

    public function setNumber(?string $number): self
    {
        return $this->set('number', $number);
    }

    public function setName(?string $name): static
    {
        return $this->set('name', $name);
    }

    /**
     * @return OrderItem[]
     */
    public function getItems(): array
    {
        $this->ensureItemItemListLoaded();

        return array_map(
            fn ($item) => OrderItem::fromRaw($item),
            $this->get(OrderEntity::ATTR_ITEM_LIST) ?? []
        );
    }

    /**
     * @param OrderItem[] $items
     */
    public function setItems(array $items): static
    {
        $rawItems = array_map(
            fn ($item) => $item->toRaw(),
            $items
        );

        $this->set(OrderEntity::ATTR_ITEM_LIST, $rawItems);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->get('status');
    }

    public function getFetchedStatus(): ?string
    {
        return $this->getFetched('status');
    }

    public function getAccount(): ?Link
    {
        /** @var ?Link */
        return $this->getValueObject('account');
    }

    public function setAccount(?Account $account): static
    {
        return $this->setRelatedLinkOrEntity('account', $account);
    }

    /**
     * Will be replaced with `getAccount` in the future.
     * @internal
     * @todo Replace with `getAccount` when v9.0 is min supported.
     */
    public function getAccountEntity(): ?Account
    {
        $account = $this->relations->getOne('account');

        if (!$account) {
            return null;
        }

        if (!$account instanceof Account) {
            throw new RuntimeException();
        }

        return $account;
    }

    public function isStatusChanged(): bool
    {
        return $this->isAttributeChanged(self::FIELD_STATUS);
    }

    public function hasItemList(): bool
    {
        return $this->has(OrderEntity::ATTR_ITEM_LIST);
    }

    public function isItemListChanged(): bool
    {
        $this->ensureItemItemListLoaded();

        return $this->isAttributeChanged(self::ATTR_ITEM_LIST);
    }

    public function ensureItemItemListLoaded(): void
    {
        if ($this->hasItemList()) {
            if (!$this->hasFetched(self::ATTR_ITEM_LIST) && !$this->isNew()) {
                $this->setFetched(self::ATTR_ITEM_LIST, $this->fetchItemList());
            }

            return;
        }

        if ($this->isNew()) {
            $this->setItemListInContainerNotWritten([]);

            return;
        }

        $this->loadItemListField();
    }

    public function loadItemListField(): void
    {
        $mapList = $this->fetchItemList();

        $this->setItemListInContainerNotWritten($mapList);

        if (!$this->hasFetched(self::ATTR_ITEM_LIST)) {
            $this->setFetched(self::ATTR_ITEM_LIST, $mapList);
        }
    }

    public function syncCurrency(): void
    {
        $fieldList = [
            self::FIELD_DISCOUNT_AMOUNT,
            self::FIELD_GRAND_TOTAL_AMOUNT,
            self::FIELD_TAX_AMOUNT,
            self::FIELD_PRE_DISCOUNTED_AMOUNT,
            self::FIELD_SHIPPING_COST,
            self::FIELD_SHIPPING_AMOUNT,
        ];

        foreach ($fieldList as $field) {
            $this->set($field . 'Currency', $this->getAmountCurrency());
        }
    }

    /**
     * @return array<string, float>
     */
    public function getProductIdQuantityMap(): array
    {
        $map = [];

        /** @var stdClass[] $itemList */
        $itemList = $this->get(OrderEntity::ATTR_ITEM_LIST) ?? [];

        foreach ($itemList as $item) {
            /** @var ?string $productId */
            $productId = $item->productId ?? null;
            $quantity = $item->quantity ?? null;

            if (!$productId || !$quantity) {
                continue;
            }

            $map[$productId] ??= 0.0;
            $map[$productId] += $quantity;
        }

        return $map;
    }

    /**
     * @return string[]
     */
    public function getInventoryProductIds(): array
    {
        return array_keys($this->getProductIdQuantityMap());
    }

    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        $ids = [];

        /** @var stdClass[] $itemList */
        $itemList = $this->get(OrderEntity::ATTR_ITEM_LIST) ?? [];

        foreach ($itemList as $item) {
            $productId = $item->productId ?? null;

            if (!$productId || in_array($productId, $ids)) {
                continue;
            }

            $ids[] = $productId;
        }

        return $ids;
    }

    public function getDateCreatedAt(): Date
    {
        /** @var DateTime $createdAt */
        $createdAt = $this->getValueObject('createdAt');

        return Date::fromDateTime($createdAt->toDateTime());
    }

    public function setStatus(string $value): static
    {
        $this->set('status', $value);

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->get('isLocked');
    }

    public function isNotActual(): bool
    {
        return $this->get(self::FIELD_IS_NOT_ACTUAL);
    }

    public function isDone(): bool
    {
        return $this->get('isDone');
    }

    public function hasAmountCurrency(): bool
    {
        return $this->has(self::ATTR_AMOUNT_CURRENCY);
    }

    public function getAmountCurrency(): ?string
    {
        return $this->get(self::ATTR_AMOUNT_CURRENCY);
    }

    public function setAmountCurrency(?string $currency): static
    {
        return $this->set(self::ATTR_AMOUNT_CURRENCY, $currency);
    }

    public function getAssignedUser(): ?Link
    {
        $id = $this->get('assignedUserId');
        $name = $this->get('assignedUserName');

        return $id ? Link::create($id, $name) : null;
    }

    public function getTeams(): LinkMultiple
    {
        /** @var LinkMultiple */
        return $this->getValueObject('teams');
    }

    public function setTeams(LinkMultiple $teams): static
    {
        return $this->setValueObject('teams', $teams);
    }

    public function getModifiedAt(): ?DateTime
    {
        /** @var ?DateTime */
        return $this->getValueObject('modifiedAt');
    }

    public function getItemEntityType(): string
    {
        return OrderEntityUtil::getItemEntityType($this->getEntityType());
    }

    public function getItemForeignKey(): string
    {
        return lcfirst($this->getEntityType()) . 'Id';
    }

    public function setIsLocked(bool $isLocked): static
    {
        return $this->set('isLocked', $isLocked);
    }

    /**
     * @return stdClass[]
     */
    private function fetchItemList(): array
    {
        $itemParentIdAttribute = lcfirst($this->getEntityType()) . 'Id';

        $items = $this->entityManager
            ->getRDBRepository($this->getItemEntityType())
            ->where([$itemParentIdAttribute => $this->getId()])
            ->order('order')
            ->find();

        foreach ($items as $item) {
            /** @var QuoteItem $item */
            $item->loadAllLinkMultipleFields();
        }

        return $items->getValueMapList();
    }

    /**
     * @param stdClass[] $mapList
     */
    private function setItemListInContainerNotWritten(array $mapList): void
    {
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        if (method_exists($this, 'setInContainerNotWritten')) {
            /** @noinspection PhpInternalEntityUsedInspection */
            $this->setInContainerNotWritten(self::ATTR_ITEM_LIST, $mapList);
        } else {
            $this->setInContainer(self::ATTR_ITEM_LIST, $mapList);
        }
    }

    public function setGrandTotalAmount(?Currency $amount): self
    {
        if (!$this->hasAttribute(self::FIELD_GRAND_TOTAL_AMOUNT)) {
            return $this;
        }

        return $this->setValueObject(self::FIELD_GRAND_TOTAL_AMOUNT, $amount);
    }

    public function getAmount(): ?Currency
    {
        if (!$this->hasAttribute(self::FIELD_AMOUNT)) {
            return null;
        }

        /** @var ?Currency */
        return $this->getValueObject(self::FIELD_AMOUNT);
    }

    public function setAmount(?Currency $amount): self
    {
        if (!$this->hasAttribute(self::FIELD_AMOUNT)) {
            return $this;
        }

        return $this->setValueObject(self::FIELD_AMOUNT, $amount);
    }

    public function getShippingCost(): ?Currency
    {
        if (!$this->hasAttribute(self::FIELD_SHIPPING_COST)) {
            return null;
        }

        /** @var ?Currency */
        return $this->getValueObject(self::FIELD_SHIPPING_COST);
    }
}
