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

namespace Espo\Modules\Sales\Classes\EntityCurrencyConverters;

use Espo\Core\Currency\Rates;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\PaymentAllocation;
use Espo\Modules\Sales\Entities\QuoteItem;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\Collection;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Currency\Conversion\DefaultEntityConverter;
use Espo\Tools\Currency\Conversion\EntityConverter;

/**
 * @implements EntityConverter<OrderEntity|Opportunity>
 */
class QuoteConverter implements EntityConverter
{
    public function __construct(
        private DefaultEntityConverter $defaultEntityConverter,
        private EntityManager $entityManager,
        private BeforeSaveProcessor $beforeSaveProcessor,
    ) {}

    public function convert(Entity $entity, string $targetCurrency, Rates $rates): void
    {
        if ($entity instanceof OrderEntity && $entity->isLocked()) {
            return;
        }

        if ($entity instanceof Invoice && $this->hasAllocations($entity)) {
            throw Forbidden::createWithBody(
                'Cannot convert if invoice with allocations.',
                Body::create()->withMessageTranslation('cannotConvertWithPaymentAllocations', 'Invoice')
            );
        }

        $this->defaultEntityConverter->convert($entity, $targetCurrency, $rates);

        /** @var Collection<QuoteItem> $items */
        $items = $this->entityManager
            ->getRelation($entity, 'items')
            ->order('order')
            ->find();

        $itemList = [];

        foreach ($items as $item) {
            $this->defaultEntityConverter->convert($item, $targetCurrency, $rates);

            $itemList[] = $item->getValueMap();
        }

        $entity->set(OrderEntity::ATTR_ITEM_LIST, $itemList);

        if ($entity instanceof OrderEntity) {
            $this->beforeSaveProcessor->calculateItems($entity);
        }
    }

    private function hasAllocations(Invoice $entity): bool
    {
        return (bool) $this->entityManager
            ->getRDBRepositoryByClass(PaymentAllocation::class)
            ->select('id')
            ->where([
                'targetId' => $entity->getId(),
                'targetType' => $entity->getEntityType(),
            ])
            ->findOne();
    }
}
