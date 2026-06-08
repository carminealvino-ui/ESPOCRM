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

namespace Espo\Modules\Sales\Classes\ConsoleCommands;

use Espo\Core\AclManager;
use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Entities\User;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class InventoryAclCheck implements Command
{
    public function __construct(
        private AclManager $aclManager,
        private EntityManager $entityManager,
        private ConfigDataProvider $configDataProvider
    ) {}

    public function run(Params $params, IO $io): void
    {
        $io->setExitStatus(1);

        if (!$this->configDataProvider->isInventoryTransactionsEnabled()) {
            return;
        }

        $userId = $params->getOption('userId');

        if (!$userId) {
            return;
        }

        /** @var ?User $user */
        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if (!$user) {
            return;
        }

        if ($user->isPortal()) {
            return;
        }

        if ($this->aclManager->checkScope($user, Product::ENTITY_TYPE)) {
            $io->setExitStatus(0);

            $io->write('true');
        }
    }
}
