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

namespace Espo\Modules\Sales\Hooks\Opportunity;

use Espo\Core\Currency\CalculatorUtil;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Tools\Quote\ItemsRemoveProcessor;
use Espo\Modules\Sales\Tools\Quote\ItemsSaveProcessor;
use Espo\Modules\Sales\Tools\Quote\RoundingUtil;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Entity;
use stdClass;

/**
 * @noinspection PhpUnused
 */
class OpportunityItem
{
    public function __construct(
        private ItemsSaveProcessor $itemsSaveProcessor,
        private ItemsRemoveProcessor $itemsRemoveProcessor,
        private RoundingUtil $roundingUtil,
    ) {}

    /**
     * @param Opportunity $entity
     */
    public function beforeSave(Entity $entity): void
    {
        if (!$entity->has(OrderEntity::ATTR_ITEM_LIST)) {
            return;
        }

        if (
            !$entity->isAttributeChanged(OrderEntity::ATTR_ITEM_LIST) &&
            !$entity->isAttributeChanged(OrderEntity::FIELD_AMOUNT) &&
            !$entity->isAttributeChanged(OrderEntity::ATTR_AMOUNT_CURRENCY)
        ) {
            return;
        }

        /** @var stdClass[] $itemList */
        $itemList = $entity->get(OrderEntity::ATTR_ITEM_LIST);

        if (!is_array($itemList)) {
            return;
        }

        $code = $entity->getAmount()?->getCode() ?? 'USD';

        if ($entity->has(OrderEntity::ATTR_AMOUNT_CURRENCY)) {
            foreach ($itemList as $o) {
                $o->listPriceCurrency = $code;
                $o->unitPriceCurrency = $code;
                $o->amountCurrency = $code;
            }
        }

        foreach ($itemList as $o) {
            if (!isset($o->quantity)) {
                $o->quantity = 1;
            }

            if (isset($o->unitPrice)) {
                /** @var float $itAmountFloat */
                $itAmountFloat = $o->unitPrice * $o->quantity;

                $itAmount = (string) $itAmountFloat;
                $itAmount = $this->roundingUtil->roundAmount($itAmount, $code);

                $o->amount = $itAmount;
            } else {
                $o->amount = '0';
            }
        }

        if (count($itemList)) {
            $amount = '0.0';

            foreach ($itemList as $o) {
                /** @var numeric-string $itemAmount */
                $itemAmount = $o->amount ?? '0';

                $amount = CalculatorUtil::add($amount, $itemAmount);
            }

            $amount = $this->roundingUtil->roundAmount($amount, $code);

            $entity->set(OrderEntity::FIELD_AMOUNT, $amount);
        }

        $entity->set(OrderEntity::ATTR_ITEM_LIST, $itemList);
    }

    /**
     * @param Opportunity $entity
     * @param array<string, mixed> $options
     */
    public function afterSave(Entity $entity, array $options = []): void
    {
        if (!empty($options['skipWorkflow']) && empty($options['addItemList'])) {
            return;
        }

        $isNew = $entity->isNew();

        if ($options['forceIsNotNew'] ?? false) {
            $isNew = false;
        }

        $this->itemsSaveProcessor->process($entity, $isNew);
    }

    /**
     * @param Opportunity $entity
     */
    public function afterRemove(Entity $entity): void
    {
        $this->itemsRemoveProcessor->process($entity);
    }
}
