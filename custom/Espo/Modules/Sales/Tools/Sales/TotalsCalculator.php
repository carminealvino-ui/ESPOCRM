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

use Espo\Core\Utils\FieldUtil;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;
use Espo\ORM\Name\Attribute;
use stdClass;

class TotalsCalculator
{
    /** @var string[] */
    private array $fieldList = [
        OrderEntity::FIELD_PRE_DISCOUNTED_AMOUNT,
        OrderEntity::FIELD_AMOUNT,
        OrderEntity::FIELD_TAX_AMOUNT,
        OrderEntity::FIELD_ROUNDING_AMOUNT,
        OrderEntity::FIELD_DISCOUNT_AMOUNT,
        OrderEntity::FIELD_GRAND_TOTAL_AMOUNT,
        OrderEntity::FIELD_SHIPPING_AMOUNT,
        OrderEntity::FIELD_WEIGHT,
        OrderEntity::FIELD_AMOUNT_LOCAL,
        OrderEntity::FIELD_GRAND_TOTAL_AMOUNT_LOCAL,
        OrderEntity::FIELD_ROUNDING_AMOUNT_LOCAL,
        OrderEntity::FIELD_SHIPPING_AMOUNT_LOCAL,
        OrderEntity::FIELD_TAX_AMOUNT_LOCAL,
        OrderEntity::FIELD_INSTALLMENTS,
    ];

    public function __construct(
        private BeforeSaveProcessor $beforeSaveProcessor,
        private FieldUtil $fieldUtil,
    ) {}

    /**
     * Important: The entity is not to be saved. It does not have an ID.
     *
     * @return stdClass
     */
    public function calculate(OrderEntity $order): stdClass
    {
        $this->beforeSaveProcessor->calculateItems($order);
        $this->beforeSaveProcessor->calculatePaymentTerms($order);

        $output = (object) [];

        foreach ($this->fieldList as $field) {
            foreach ($this->fieldUtil->getAttributeList($order->getEntityType(), $field) as $attribute) {
                if (!$order->hasAttribute($attribute)) {
                    continue;
                }

                $output->$attribute = $order->get($attribute);
            }
        }

        $output->itemList = array_map(function ($it) {
            return (object) [
                QuoteItem::FIELD_AMOUNT => $it->get(QuoteItem::FIELD_AMOUNT),
                Attribute::ID => $it->getId(),
            ];
        }, $order->getItems());

        return $output;
    }
}
