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

namespace Espo\Modules\Sales\Tools\Product\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Record\SearchParamsFetcher;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Tools\Product\VariantInventoryNumbersService;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class GetVariantInventoryNumbers implements Action
{
    public function __construct(
        private Acl $acl,
        private SearchParamsFetcher $searchParamsFetcher,
        private EntityManager $entityManager,
        private VariantInventoryNumbersService $service
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->checkScope(Product::ENTITY_TYPE)) {
            throw new Forbidden("Not access to Product scope.");
        }

        if (!$this->acl->checkScope(InventoryNumber::ENTITY_TYPE)) {
            throw new Forbidden("Not access to InventoryNumber scope.");
        }

        $id = $request->getRouteParam('id');
        $searchParams = $this->searchParamsFetcher->fetch($request);

        if (!$id) {
            throw new BadRequest();
        }

        $product = $this->getProduct($id);

        $collection = $this->service->findTemplateInventoryNumbers($product, $searchParams);

        return ResponseComposer::json([
            'list' => $collection->getValueMapList(),
            'total' => $collection->getTotal(),
        ]);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function getProduct(string $id): Product
    {
        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($id);

        if (!$product) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($product)) {
            throw new Forbidden();
        }

        return $product;
    }
}
