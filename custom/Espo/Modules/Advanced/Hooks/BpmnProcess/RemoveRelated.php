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
 * License ID: 3fed3a66185f29dcda0e14c6ee94bf07
 ************************************************************************************/

namespace Espo\Modules\Advanced\Hooks\BpmnProcess;

use Espo\Core\Field\DateTime;
use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Modules\Advanced\Entities\BpmnFlowNode;
use Espo\Modules\Advanced\Entities\BpmnProcess;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\UpdateBuilder;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * @implements AfterRemove<BpmnProcess>
 */
class RemoveRelated implements AfterRemove
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $query = UpdateBuilder::create()
            ->in(BpmnFlowNode::ENTITY_TYPE)
            ->where([
                BpmnFlowNode::ATTR_PROCESS_ID => $entity->getId(),
            ])
            ->set([
                Attribute::DELETED => true,
                'modifiedAt' => DateTime::createNow()->toString(),
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }
}
