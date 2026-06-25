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

namespace Espo\Modules\Sales\Tools\Quote;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\PaymentEntry;
use Espo\Modules\Sales\Entities\PaymentRequest;
use Espo\Modules\Sales\Entities\WriteOffEntry;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\ORM\EntityManager;

class LockService
{
    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager,
        private Config $config,
        private User $user,
    ) {}

    /**
     * @throws Forbidden
     */
    public function lock(OrderEntity|PaymentEntry|PaymentRequest|WriteOffEntry $order): void
    {
        $this->checkAccess($order);

        if ($order->isLocked()) {
            throw new Forbidden("Cannot lock an already locked record.");
        }

        if (!$order->isNotActual()) {
            throw new Forbidden("Cannot lock an actual record.");
        }

        $order->set('isLocked', true);

        $this->entityManager->saveEntity($order);
    }

    /**
     * @throws Forbidden
     * @throws BadRequest
     */
    public function unlock(OrderEntity|PaymentEntry|PaymentRequest|WriteOffEntry $order): void
    {
        $this->checkAccess($order);

        if (!$order->isLocked()) {
            throw new Forbidden("Cannot unlock a not locked record.");
        }

        if ($order->get('isHardLocked')) {
            throw new Forbidden("Cannot unlock a hard-locked record.");
        }

        if ($this->config->get('salesForbidOrderUnlock') && !$this->user->isAdmin()) {
            throw BadRequest::createWithBody(
                'cannotUnlockByRegularUser',
                Body::create()->withMessageTranslation('cannotUnlockByRegularUser', 'Quote')
            );
        }

        $order->set('isLocked', false);

        $this->entityManager->saveEntity($order);
    }

    /**
     * @throws Forbidden
     */
    private function checkAccess(OrderEntity|PaymentEntry|PaymentRequest|WriteOffEntry $order): void
    {
        if (!$this->acl->checkEntityEdit($order)) {
            throw new Forbidden();
        }

        // Does not work as read-only fields are forbidden.
        /*if (
            in_array('isLocked',
                $this->acl->getScopeForbiddenFieldList($order->getEntityType(), Acl\Table::ACTION_EDIT))
        ) {
            throw new Forbidden();
        }*/
    }
}
