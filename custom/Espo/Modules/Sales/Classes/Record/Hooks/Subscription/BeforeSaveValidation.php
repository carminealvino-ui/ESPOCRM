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

namespace Espo\Modules\Sales\Classes\Record\Hooks\Subscription;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionTemplate;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @implements SaveHook<Subscription>
 */
class BeforeSaveValidation implements SaveHook
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
    ) {}

    public function process(Entity $entity): void
    {
        $this->checkTemplate($entity);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    private function checkTemplate(Subscription $entity): void
    {
        if (!$entity->isNew()) {
            return;
        }

        $templateId = $entity->get(Subscription::ATTR_TEMPLATE_ID);

        if (!$templateId) {
            return;
        }

        $template = $this->entityManager->getRDBRepositoryByClass(SubscriptionTemplate::class)->getById($templateId);

        if (!$template) {
            throw new BadRequest("Template not found.");
        }

        if (!$this->acl->checkEntityRead($template)) {
            throw new Forbidden("No access to template.");
        }

        if (!$entity->getStartDate()) {
            throw new BadRequest("No start date.");
        }
    }
}
