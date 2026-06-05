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

namespace Espo\Modules\Sales\Classes\MassActions;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\MassAction\Data;
use Espo\Core\MassAction\MassAction;
use Espo\Core\MassAction\Params;
use Espo\Core\MassAction\QueryBuilder;
use Espo\Core\MassAction\Result;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class Unlock implements MassAction
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
        private Config $config,
    ) {}

    public function process(Params $params, Data $data): Result
    {
        $entityType = $params->getEntityType();

        if (!$this->acl->check($entityType, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden("No edit access to '$entityType'.");
        }

        if ($this->acl->getPermissionLevel(Acl\Permission::MASS_UPDATE) !== Acl\Table::LEVEL_YES) {
            throw new Forbidden("No mass-update permission.");
        }

        if ($this->config->get('salesForbidOrderUnlock') && !$this->user->isAdmin()) {
            throw BadRequest::createWithBody(
                'cannotUnlockByRegularUser',
                Body::create()->withMessageTranslation('cannotUnlockByRegularUser', 'Quote')
            );
        }

        $query = $this->queryBuilder->build($params);

        /** @var iterable<OrderEntity|PaymentEntry|PaymentRequest|WriteOffEntry> $collection */
        $collection = $this->entityManager
            ->getRDBRepository($entityType)
            ->clone($query)
            ->sth()
            ->find();

        $ids = [];

        foreach ($collection as $entity) {
            $this->processItem($entity, $ids);
        }

        return new Result(count($ids), $ids);
    }

    /**
     * @param string[] $ids
     */
    private function processItem(OrderEntity|PaymentEntry|PaymentRequest|WriteOffEntry $entity, array &$ids): void
    {
        if (!$this->acl->checkEntityEdit($entity)) {
            return;
        }

        if (!$entity->isLocked() || $entity->get('isHardLocked')) {
            return;
        }

        $entity->set('isLocked', false);

        $this->entityManager->saveEntity($entity, [
            'massUpdate' => true,
            'skipStreamNotesAcl' => true,
            'modifiedById' => $this->user->getId(),
        ]);

        $ids[] = $entity->getId();
    }
}
