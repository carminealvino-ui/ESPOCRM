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

use Espo\Core\ORM\Entity;
use Espo\Tools\DynamicLogic\Exceptions\BadCondition;
use Espo\Tools\DynamicLogic\Item;
use UnexpectedValueException;
use stdClass;

class TaxPurchaseRule extends Entity
{
    public const ENTITY_TYPE = 'TaxPurchaseRule';

    public const STATUS_ACTIVE = 'Active';

    public function getLogicItem(): ?Item
    {
        $logic = $this->get('logic');

        if (!$logic instanceof stdClass) {
            return null;
        }

        if (!property_exists($logic, 'conditionGroup')) {
            throw new UnexpectedValueException("No condition group.");
        }

        if (!is_array($logic->conditionGroup)) {
            throw new UnexpectedValueException("Bad condition group.");
        }

        try {
            return Item::fromGroupDefinition($logic->conditionGroup);
        } catch (BadCondition $e) {
            throw new UnexpectedValueException($e->getMessage(), 0, $e);
        }
    }

    public function getTax(): Tax
    {
        $tax = $this->relations->getOne('tax');

        if (!$tax instanceof Tax) {
            throw new UnexpectedValueException("No tax.");
        }

        return $tax;
    }

    public function getOrder(): int
    {
        return (int) $this->get('order');
    }
}
