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

namespace Espo\Modules\Sales\Hooks\Subscription;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionTemplate;
use Espo\Modules\Sales\Tools\Subscription\TemplateApplier;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;
use RuntimeException;

/**
 * @implements AfterSave<Subscription>
 */
class ApplyTemplate implements AfterSave
{
    public static int $order = 100;

    public function __construct(
        private EntityManager $entityManager,
        private TemplateApplier $templateApplier,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            return;
        }

        $id = $entity->get(Subscription::ATTR_TEMPLATE_ID);

        if (!$id) {
            return;
        }

        $template = $this->entityManager->getRDBRepositoryByClass(SubscriptionTemplate::class)->getById($id);

        if (!$template) {
            throw new RuntimeException("Template not found.");
        }

        $this->templateApplier->apply($entity, $template);
    }
}
