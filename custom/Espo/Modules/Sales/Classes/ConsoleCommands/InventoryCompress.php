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

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Core\Field\Date;
use Espo\Modules\Sales\Tools\Inventory\Archive\Compressor;
use Espo\Modules\Sales\Tools\Inventory\Archive\Params as ArchiveParams;

/**
 * @noinspection PhpUnused
 */
class InventoryCompress implements Command
{
    public function __construct(
        private Compressor $compressor
    ) {}

    public function run(Params $params, IO $io): void
    {
        $before = $params->getOption('before') ?
            Date::fromString($params->getOption('before')) :
            null;

        $archiveParams = new ArchiveParams($before);

        if ($before) {
            $io->writeLine('Before: ' . $before->getString());
        }

        $io->write('Running...');

        $result = $this->compressor->run($archiveParams);

        $io->writeLine('');
        $io->writeLine("Done. {$result->getCount()} balanced transactions deleted.");
    }
}
