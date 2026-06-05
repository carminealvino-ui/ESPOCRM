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

namespace Espo\Modules\Sales\Tools\ProductAttribute\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Sales\Entities\ProductAttribute;
use Espo\Modules\Sales\Tools\ProductAttribute\Service;

class GetOptions implements Action
{
    public function __construct(
        private Acl $acl,
        private Service $service
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->checkScope(ProductAttribute::ENTITY_TYPE)) {
            throw new Forbidden();
        }

        $ids = $request->getQueryParams()['ids'];

        if (!is_array($ids)) {
            throw new BadRequest("No IDs.");
        }

        foreach ($ids as $id) {
            if (!is_string($id)) {
                throw new BadRequest("Bad ID.");
            }
        }

        $map = $this->service->getOptionsForIds($ids);

        return ResponseComposer::json($map);
    }
}
