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
 * License ID: 77350457a8d35522431c4daeee1dd4ad
 ************************************************************************************/

namespace Espo\Modules\Outlook\Core\AppParams;

use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class OutlookParams
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
    ) {}

    public function get()
    {
        $integration = $this->entityManager->getEntityById('Integration', 'Outlook');

        if (!$integration) {
            return null;
        }

        $mailScope = $this->metadata->get('integrations.Outlook.params.scopeMail');

        $mailGraphApiScope = $integration->get('graphApiSendEmail') ?
            $this->metadata->get('integrations.Outlook.params.scopeMailGraphApi') :
            null;

        return [
            'tenant' => $integration->get('tenant') ?? 'common',
            'authorizationPrompt' => $integration->get('authorizationPrompt') ?? 'consent',
            'mailScope' => $mailScope,
            'mailGraphApiScope' => $mailGraphApiScope,
        ];
    }
}
