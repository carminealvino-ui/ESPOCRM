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

namespace Espo\Modules\Advanced\Tools\Report;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Metadata;

class TargetEntityTypeChecker
{
    public function __construct(
        private Metadata $metadata,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function check(string $entityType): void
    {
        $defs = $this->metadata->get("scopes.$entityType") ?? [];

        if (!($defs['entity'] ?? false)) {
            throw new BadRequest("Non-entity scope.");
        }

        $allowedList = $this->metadata->get("entityDefs.Report.entityListAllowed") ?? [];
        $disallowedList = $this->metadata->get("entityDefs.Report.entityListToIgnore") ?? [];

        if (in_array($entityType, $disallowedList)) {
            throw new Forbidden();
        }

        if (in_array($entityType, $allowedList)) {
            return;
        }

        if (
            ($defs['tab'] ?? false) ||
            ($defs['object'] ?? false) ||
            ($defs['reports'] ?? false)
        ) {
            return;
        }

        throw new BadRequest("Not supported entity type.");
    }
}
