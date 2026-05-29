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

namespace Espo\Modules\Sales\Tools\Tax\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Tools\Product\AccessHelper;
use Espo\Modules\Sales\Tools\Tax\ProductRateService;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class PostProductRates implements Action
{
    private const LIMIT = 500;

    public function __construct(
        private ProductRateService $service,
        private AccessHelper $accessHelper,
        private EntityManager $entityManager,
        private Acl $acl,
    ) {}

    public function process(Request $request): Response
    {
        $tax = $this->fetchTax($request);
        $rates = $this->service->getProductTaxes($tax, $this->fetchProductIds($request));

        return ResponseComposer::json(
            array_map(
                fn ($rate) => [
                    'productId' => $rate->productId,
                    'rate' => $rate->rate,
                    'taxCodeId' => $rate->taxCode?->getId(),
                    'taxCodeName' => $rate->taxCode?->getName(),
                ],
                $rates
            )
        );
    }

    /**
     * @return string[]
     * @throws BadRequest
     * @throws Forbidden
     */
    private function fetchProductIds(Request $request): array
    {
        $productIds = $request->getParsedBody()->ids;

        if (!is_array($productIds)) {
            throw new BadRequest("No ids.");
        }

        foreach ($productIds as $productId) {
            if (!is_string($productId)) {
                throw new BadRequest("Bad ids.");
            }
        }

        if (count($productIds) > self::LIMIT) {
            throw new Forbidden("Too many products.");
        }

        if (!$this->acl->checkScope(Product::ENTITY_TYPE)) {
            return [];
        }

        return $this->accessHelper->filterIds($productIds);
    }

    /**
     * @throws BadRequest
     * @throws NotFound
     */
    private function fetchTax(Request $request): Tax
    {
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $tax = $this->entityManager->getRDBRepositoryByClass(Tax::class)->getById($id);

        if (!$tax) {
            throw new NotFound("Tax not found.");
        }

        return $tax;
    }
}


