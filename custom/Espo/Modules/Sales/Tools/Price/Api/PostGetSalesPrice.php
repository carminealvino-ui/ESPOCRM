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

namespace Espo\Modules\Sales\Tools\Price\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Tax;
use Espo\Modules\Sales\Tools\Price\PricePairFetcher;
use Espo\Modules\Sales\Tools\Price\PriceService;
use Espo\Modules\Sales\Tools\Price\Service\SalesData;
use Espo\Modules\Sales\Tools\Product\AccessHelper;
use Espo\Modules\Sales\Tools\Product\ProductQuantityPair;
use Espo\Modules\Sales\Tools\Tax\ProductTax;
use Espo\Modules\Sales\Tools\Tax\ProductRateService;
use Espo\ORM\EntityManager;
use LogicException;

/**
 * @noinspection PhpUnused
 */
class PostGetSalesPrice implements Action
{
    public function __construct(
        private PriceService $service,
        private PricePairFetcher $pricePairFetcher,
        private EntityManager $entityManager,
        private AccessHelper $accessHelper,
        private ProductRateService $productRateService,
        private Acl $acl,
    ) {}

    public function process(Request $request): Response
    {
        $pairs = $this->pricePairFetcher->fetch($request);
        $accountId = $request->getParsedBody()->accountId ?? null;
        $priceBookId = $request->getParsedBody()->priceBookId ?? null;
        $applyAccountPriceBook = $request->getParsedBody()->applyAccountPriceBook ?? false;
        $taxId = $request->getParsedBody()->taxId ?? false;

        if ($applyAccountPriceBook && !$priceBookId && $accountId) {
            $priceBookId = $this->obtainPriceBookId($accountId);
        }

        $data = new SalesData(
            accountId: $accountId,
            orderType: $request->getParsedBody()->orderType ?? null,
            orderId: $request->getParsedBody()->orderId ?? null,
            currency: $request->getParsedBody()->currency ?? null,
            billingPlanId: $request->getParsedBody()->billingPlanId ?? null,
        );

        $results = $this->service->getSalesMultiple($pairs, $priceBookId, $data);

        $taxMap = $this->getProductTaxes($pairs, $taxId);

        $response = [];

        foreach ($pairs as $i => $pair) {
            $result = $results[$i] ?? throw new LogicException();

            $taxData = null;

            $productTax = $taxMap[$pair->getProductId()] ?? null;

            if ($productTax !== null) {
                $taxData = (object) [
                    'rate' => $productTax->rate,
                    'taxCodeId' => $productTax->taxCode?->getId(),
                    'taxCodeName' => $productTax->taxCode?->getName(),
                ];
            }

            $response[] = (object) [
                'unitPrice' => $result->getUnit()?->getAmount(),
                'listPrice' => $result->getList()?->getAmount(),
                'unitPriceCurrency' => $result->getUnit()?->getCode(),
                'listPriceCurrency' => $result->getList()?->getCode(),
                'tax' => $taxData,
            ];
        }

        return ResponseComposer::json($response);
    }

    private function obtainPriceBookId(string $accountId): ?string
    {
        $account = $this->entityManager->getEntityById(Account::ENTITY_TYPE, $accountId);

        return $account?->get('priceBookId');
    }

    /**
     * @param ProductQuantityPair[] $pairs
     * @return array<string, ProductTax>
     */
    private function getProductTaxes(array $pairs, ?string $taxId): array
    {
        if (!$taxId) {
            return [];
        }

        if (!$this->acl->checkScope(Product::ENTITY_TYPE)) {
            return [];
        }

        $tax = $this->entityManager->getRDBRepositoryByClass(Tax::class)->getById($taxId);

        if (!$tax) {
            return [];
        }

        $ids = [];

        foreach ($pairs as $pair) {
            $ids[] = $pair->getProductId();
        }

        $ids = array_values(array_unique($ids));

        $ids = $this->accessHelper->filterIds($ids);

        $productTaxes = $this->productRateService->getProductTaxes($tax, $ids);

        $output = [];

        foreach ($productTaxes as $rate) {
            $output[$rate->productId] = $rate;
        }

        return $output;
    }
}
