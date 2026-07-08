<?php

namespace Espo\Custom\Tools\Quote\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\EntityManager;

/**
 * Prezzi listino e codice per righe contratto (da ProductPrice + listino selezionato).
 *
 * @noinspection PhpUnused
 */
class PostGetItemCatalogPrices implements Action
{
    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager,
        private QuotePricingCalculator $pricingCalculator,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->checkScope('Quote', Acl\Table::ACTION_READ)) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
        $productIds = $body->productIds ?? null;

        if (!is_array($productIds) || $productIds === []) {
            throw new BadRequest('productIds required');
        }

        $quote = $this->entityManager->getNewEntity('Quote');
        $quote->set([
            'priceBookId' => $body->priceBookId ?? null,
            'isTaxInclusive' => (bool) ($body->isTaxInclusive ?? false),
            'taxId' => $body->taxId ?? null,
            'dateQuoted' => $body->dateQuoted ?? null,
            'aliquotaIVA' => $body->aliquotaIVA ?? null,
        ]);

        $response = [];

        foreach ($productIds as $productId) {
            if (!is_string($productId) || $productId === '') {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if (!$product) {
                $response[] = (object) [
                    'productId' => $productId,
                    'listPrice' => null,
                    'prezzoCodice' => null,
                ];

                continue;
            }

            $prices = $this->pricingCalculator->resolveItemCatalogPricesForProduct($quote, $product);

            $response[] = (object) [
                'productId' => $productId,
                'listPrice' => $prices['listPrice'],
                'prezzoCodice' => $prices['prezzoCodice'],
            ];
        }

        return ResponseComposer::json($response);
    }
}
