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

namespace Espo\Modules\Sales\Classes\Select\Quote\WhereItemConverters;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemConverter;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderReceivedItem;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Where\AndGroup;
use Espo\ORM\Query\Part\WhereItem as WhereClauseItem;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class InventoryNumbers implements ItemConverter
{
    public function __construct(
        private string $entityType,
    ) {}

    /**
     * @throws BadRequest
     */
    public function convert(SelectBuilder $queryBuilder, Item $item): WhereClauseItem
    {
        if (
            $item->getType() !== Item\Type::ARRAY_ANY_OF &&
            $item->getType() !== Item\Type::ARRAY_ALL_OF
        ) {
            throw new RuntimeException("Bad where item.");
        }

        $itemEntityType = OrderEntityUtil::getItemEntityType($this->entityType);
        $idAttribute = lcfirst($this->entityType) . 'Id';

        if ($this->entityType === ReceiptOrder::ENTITY_TYPE) {
            $itemEntityType = ReceiptOrderReceivedItem::ENTITY_TYPE;
        }

        $numberIds = $item->getValue();

        if (!is_array($numberIds)) {
            throw new BadRequest("No IDs in value.");
        }

        foreach ($numberIds as $id) {
            if (!is_string($id)) {
                throw new BadRequest();
            }
        }

        if ($item->getType() === Item\Type::ARRAY_ANY_OF) {
            $subQuery = SelectBuilder::create()
                ->from($itemEntityType)
                ->select($idAttribute)
                ->where([
                    'inventoryNumberId' => $numberIds,
                    $idAttribute . '!=' => null,
                ])
                ->build();

            return Cond::in(Cond::column('id'), $subQuery);
        }

        $andGroup = AndGroup::createBuilder();

        foreach ($numberIds as $numberId) {
            $subQuery = SelectBuilder::create()
                ->from($itemEntityType)
                ->select($idAttribute)
                ->where([
                    'inventoryNumberId' => $numberId,
                    $idAttribute . '!=' => null,
                ])
                ->build();

            $andGroup->add(
                Cond::in(Cond::column('id'), $subQuery)
            );
        }

        return $andGroup->build();
    }
}
