<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Contratto / Opportunity: prezzo codice da prodotto, Minus/Plus, totali provvigioni.
 *
 * Contratto (Quote):
 * - prezzoCodiceIvaInclusa / totalPrezzoCodice UI: 4.400 (IVI al 10%)
 * - prezzoCodiceIvaEsclusa: 4.000
 * - minusPlus = imponibile − codice net (es. 4.090,91 − 4.000 = +90,91)
 * - provvigioni: % sull'imponibile; totale = somma subpanel
 *
 * Opportunity: minusPlus = imponibile netto − prezzo codice netto (IVA escl.).
 */
class QuotePricingCalculator
{
    private const DEFAULT_ALIQUOTA_IVA = 10.0;

    public function __construct(
        private EntityManager $entityManager,
        private QuoteProvvigioniSync $quoteProvvigioniSync
    ) {}

    public function syncOnBeforeSave(Entity $quote): void
    {
        $this->ensureImportoContrattoOnQuote($quote);
        $this->syncItemListLinePricingFromImportoContratto($quote);
        $this->syncItemListPrezzoCodice($quote);
        $this->syncTotalsAndDerivedFields($quote, true);
    }

    /**
     * Importo venduto B2C: campo importoContratto, opportunità o nome contratto (es. «€. 4.500»).
     */
    public function resolveImportoContrattoForQuote(Entity $quote): ?float
    {
        $importo = $this->floatOrNull($quote->get('importoContratto'));

        if (($importo === null || $importo <= 0) && method_exists($quote, 'getFetched')) {
            $importo = $this->floatOrNull($quote->getFetched('importoContratto'));
        }

        if ($importo !== null && $importo > 0) {
            return $importo;
        }

        if ($quote->get('opportunityId')) {
            $opportunity = $this->entityManager->getEntityById(
                'Opportunity',
                $quote->get('opportunityId')
            );

            if ($opportunity) {
                $fromOpportunity = $this->resolveImportoVendutoFromOpportunity($quote, $opportunity);

                if ($fromOpportunity !== null && $fromOpportunity > 0) {
                    return $fromOpportunity;
                }
            }
        }

        $name = (string) ($quote->get('name') ?? '');

        if (preg_match('/€\.?\s*([\d.,]+)/u', $name, $m)) {
            return $this->parseItalianAmount($m[1]);
        }

        if (preg_match('/([\d.,]+)\s*€/u', $name, $m)) {
            return $this->parseItalianAmount($m[1]);
        }

        return null;
    }

    /**
     * Importo venduto da opportunità: non usare amount se coincide col listino IVI (es. 5200).
     */
    private function resolveImportoVendutoFromOpportunity(Entity $quote, Entity $opportunity): ?float
    {
        foreach (['importoOpportunit', 'importoContratto'] as $field) {
            $val = $this->floatOrNull($opportunity->get($field));

            if ($val !== null && $val > 0) {
                return $val;
            }
        }

        $amount = $this->floatOrNull($opportunity->get('amount'));

        if ($amount === null || $amount <= 0) {
            return null;
        }

        $listinoIvi = $this->sumListinoIvaInclusaFromProductsOnItems($quote);

        if ($listinoIvi > 0 && abs($amount - $listinoIvi) < 0.02) {
            return null;
        }

        $grandTotal = $this->floatOrNull($quote->get('grandTotalAmount'));

        if ($grandTotal !== null && abs($amount - $grandTotal) < 0.02 && $listinoIvi > 0
            && abs($grandTotal - $listinoIvi) < 0.02) {
            return null;
        }

        return $amount;
    }

    /**
     * Totale documento = listino invece di importoContratto (Sales Pack ha sovrascritto le righe).
     */
    public function needsContractPricingResync(Entity $quote): bool
    {
        if ($quote->getEntityType() !== 'Quote' || !$this->isQuotePricesTaxInclusive($quote)) {
            return false;
        }

        $importo = $this->resolveImportoContrattoForQuote($quote);

        if ($importo === null || $importo <= 0) {
            return false;
        }

        $itemList = $quote->get('itemList');

        if (!is_array($itemList) || $itemList === []) {
            return false;
        }

        $grandTotal = $this->floatOrNull($quote->get('grandTotalAmount'));

        if ($grandTotal === null) {
            return true;
        }

        if (abs($grandTotal - $importo) <= 0.02) {
            return false;
        }

        $listinoIvi = $this->sumListinoIvaInclusaFromProductsOnItems($quote);

        if ($listinoIvi > 0 && abs($grandTotal - $listinoIvi) <= 0.02) {
            return true;
        }

        $first = $itemList[0];
        $unit = $this->floatOrNull($this->itemValue($first, 'unitPrice'));
        $list = $this->floatOrNull($this->itemValue($first, 'listPrice'));

        if ($unit !== null && $list !== null && abs($unit - $list) < 0.02 && abs($unit - $importo) > 0.02) {
            return true;
        }

        $codiceIviProducts = $this->sumPrezzoCodiceIvaInclusaFromProductsOnItems($quote);
        $codiceIviStored = $this->floatOrNull($quote->get('prezzoCodiceIvaInclusa'));

        if ($codiceIviProducts > 0 && $codiceIviStored !== null
            && abs($codiceIviProducts - $codiceIviStored) > 0.02) {
            return true;
        }

        return abs($grandTotal - $importo) > 0.02;
    }

    private function ensureImportoContrattoOnQuote(Entity $quote): void
    {
        $current = $this->floatOrNull($quote->get('importoContratto'));

        if ($current !== null && $current > 0) {
            return;
        }

        $fromOpportunity = $this->resolveImportoContrattoFromOpportunityOnly($quote);

        if ($fromOpportunity !== null && $fromOpportunity > 0) {
            $quote->set('importoContratto', $fromOpportunity);

            return;
        }

        $resolved = $this->resolveImportoContrattoForQuote($quote);

        if ($resolved !== null && $resolved > 0) {
            $quote->set('importoContratto', $resolved);
        }
    }

    /**
     * Totale contratto IVI: solo opportunità (importoOpportunit / importoContratto).
     */
    public function resolveImportoContrattoFromOpportunityOnly(Entity $quote): ?float
    {
        if (!$quote->get('opportunityId')) {
            return null;
        }

        $opportunity = $this->entityManager->getEntityById(
            'Opportunity',
            $quote->get('opportunityId')
        );

        if (!$opportunity) {
            return null;
        }

        return $this->resolveImportoVendutoFromOpportunity($quote, $opportunity);
    }

    /**
     * Regole contratto IVA inclusa:
     * - listino/codice riga = catalogo
     * - unitario/importo riga = importo opportunità ripartito sul listino
     * - amount/taxAmount testata = somma righe articolo
     * - grandTotalAmount / importoContratto = importo opportunità (es. 4500 IVI)
     */
    private function syncItemListLinePricingFromImportoContratto(Entity $quote): void
    {
        $importo = $this->resolveImportoContrattoForQuote($quote);

        if ($importo === null || $importo <= 0) {
            return;
        }

        $itemList = $quote->get('itemList');

        if (!is_array($itemList) || $itemList === []) {
            return;
        }

        $aliquota = $this->resolveAliquotaIva($quote);
        $taxInclusive = $this->isQuotePricesTaxInclusive($quote);
        $split = $this->splitImportoContratto($importo, $aliquota, $taxInclusive);
        $importoPerRighe = $taxInclusive ? $split['gross'] : $split['net'];

        $weights = [];
        $lineIndices = [];

        foreach ($itemList as $index => $item) {
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($qty <= 0) {
                $qty = 1.0;
            }

            $productId = $this->itemValue($item, 'productId');
            $product = $productId
                ? $this->entityManager->getEntityById('Product', $productId)
                : null;

            $listForWeight = null;
            $listCatalog = null;
            $codiceLine = null;

            if ($product) {
                $productPrice = $this->findActiveProductPrice($product, $quote);
                $listCatalog = $taxInclusive
                    ? $this->resolveProductListinoIvaInclusa($product, $aliquota, $quote, $productPrice)
                    : $this->resolveProductListinoNet($product, $quote, $productPrice);
                $codiceLine = $taxInclusive
                    ? $this->resolveProductPrezzoCodiceIvaInclusa($product, $aliquota, $productPrice, true)
                    : $this->resolveProductPrezzoCodiceNet($product, $aliquota, $productPrice, false);
            }

            if ($listCatalog !== null && $listCatalog > 0) {
                $item = $this->itemSet($item, 'listPrice', $listCatalog);
            }

            if ($codiceLine !== null && $codiceLine > 0) {
                $item = $this->itemSet($item, 'prezzoCodice', $codiceLine);
            }

            $listForWeight = $this->floatOrNull($this->itemValue($item, 'listPrice')) ?? $listCatalog ?? 0.0;
            $weights[$index] = max(0.0, $listForWeight * $qty);
            $lineIndices[] = $index;
            $itemList[$index] = $item;
        }

        $totalWeight = array_sum($weights);
        $allocated = 0.0;
        $lastIndex = $lineIndices[array_key_last($lineIndices)] ?? null;

        foreach ($lineIndices as $pos => $index) {
            $item = $itemList[$index];
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($qty <= 0) {
                $qty = 1.0;
            }

            if ($totalWeight > 0) {
                if ($index === $lastIndex) {
                    $lineTotal = round($importoPerRighe - $allocated, 2);
                } else {
                    $lineTotal = round($importoPerRighe * ($weights[$index] / $totalWeight), 2);
                    $allocated += $lineTotal;
                }
            } else {
                $lineTotal = round($importoPerRighe / count($lineIndices), 2);
            }

            $unitPrice = round($lineTotal / $qty, 2);

            if ($taxInclusive) {
                $lineNet = round($lineTotal / (1 + $aliquota / 100), 2);
                $lineTax = round($lineTotal - $lineNet, 2);
                $itemList[$index] = $this->itemSet($item, 'unitPrice', $unitPrice);
                // Sales Pack IVA inclusa: unitPrice lordo, amount riga = imponibile netto
                $itemList[$index] = $this->itemSet($itemList[$index], 'amount', $lineNet);
                $itemList[$index] = $this->itemSet($itemList[$index], 'taxAmount', $lineTax);
            } else {
                $lineTax = round($lineTotal * $aliquota / 100, 2);
                $itemList[$index] = $this->itemSet($item, 'unitPrice', $unitPrice);
                $itemList[$index] = $this->itemSet($itemList[$index], 'amount', $lineTotal);
                $itemList[$index] = $this->itemSet($itemList[$index], 'taxAmount', $lineTax);

                if ($this->floatOrNull($this->itemValue($itemList[$index], 'listPrice')) === null) {
                    $itemList[$index] = $this->itemSet($itemList[$index], 'listPrice', $unitPrice);
                }
            }
        }

        $quote->set('itemList', $itemList);

        $lineTotals = $this->sumTotalsFromItemList($itemList, $taxInclusive);
        $importoLordo = round($split['gross'], 2);

        $quote->set([
            'amount' => $lineTotals['net'],
            'taxAmount' => $lineTotals['tax'],
            'grandTotalAmount' => $importoLordo,
            'importoContratto' => $importoLordo,
            'aliquotaIVA' => $aliquota,
        ]);

        if ($this->floatOrNull($quote->get('taxRate')) === null && $aliquota > 0) {
            $quote->set('taxRate', round($aliquota / 100, 4));
        }
    }

    /**
     * @param array<int, mixed> $itemList
     * @return array{net: float, tax: float, gross: float}
     */
    private function sumTotalsFromItemList(array $itemList, bool $taxInclusive): array
    {
        $net = 0.0;
        $tax = 0.0;

        foreach ($itemList as $item) {
            $lineNet = $this->floatOrNull($this->itemValue($item, 'amount')) ?? 0.0;
            $lineTax = $this->floatOrNull($this->itemValue($item, 'taxAmount')) ?? 0.0;

            if ($taxInclusive && $lineNet > 0 && $lineTax <= 0) {
                $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);
                $unit = $this->floatOrNull($this->itemValue($item, 'unitPrice')) ?? 0.0;
                $gross = $unit * ($qty > 0 ? $qty : 1);

                if ($gross > $lineNet) {
                    $lineTax = round($gross - $lineNet, 2);
                }
            }

            $net += $lineNet;
            $tax += $lineTax;
        }

        return [
            'net' => round($net, 2),
            'tax' => round($tax, 2),
            'gross' => round($net + $tax, 2),
        ];
    }

    public function syncOpportunityOnBeforeSave(Entity $opportunity): void
    {
        $this->syncItemListPrezzoCodice($opportunity);
        $this->syncTotalsAndDerivedFields($opportunity, false);
    }

    private function syncItemListPrezzoCodice(Entity $entity): void
    {
        $itemList = $entity->get('itemList');

        if (!is_array($itemList) || $itemList === []) {
            return;
        }

        $aliquota = $this->resolveAliquotaIva($entity);
        $taxInclusive = $entity->getEntityType() === 'Quote' && $this->isQuotePricesTaxInclusive($entity);
        $changed = false;

        foreach ($itemList as $index => $item) {
            $productId = $this->itemValue($item, 'productId');

            if (!$productId) {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if (!$product) {
                continue;
            }

            $productPrice = $entity->getEntityType() === 'Quote'
                ? $this->findActiveProductPrice($product, $entity)
                : null;

            $codiceNet = $this->resolveProductPrezzoCodiceNet($product, $aliquota, $productPrice, $taxInclusive);
            $codiceIvi = $this->resolveProductPrezzoCodiceIvaInclusa($product, $aliquota, $productPrice, $taxInclusive);

            if ($codiceNet === null && $codiceIvi === null) {
                continue;
            }

            $lineCodice = $this->floatOrNull($this->itemValue($item, 'prezzoCodice'));
            $targetCodice = $taxInclusive && $codiceIvi !== null && $codiceIvi > 0
                ? $codiceIvi
                : $codiceNet;

            if ($targetCodice === null && $codiceIvi !== null && $codiceIvi > 0) {
                $targetCodice = round($codiceIvi / (1 + $aliquota / 100), 2);
            }

            if ($targetCodice === null) {
                continue;
            }

            if ($lineCodice !== null && abs($lineCodice - $targetCodice) < 0.02) {
                continue;
            }

            if ($lineCodice !== null && $lineCodice > 0 && $codiceIvi !== null && $codiceIvi > 0) {
                $inflated = round($codiceIvi * (1 + $aliquota / 100), 2);

                if (abs($lineCodice - $inflated) < 0.02) {
                    $itemList[$index] = $this->itemSet($item, 'prezzoCodice', $codiceIvi);
                    $changed = true;

                    continue;
                }
            }

            if ($lineCodice !== null && $lineCodice > 0 && $codiceNet !== null) {
                if (!$taxInclusive && abs($lineCodice - $codiceNet) < 0.02) {
                    continue;
                }

                if ($taxInclusive && $codiceIvi !== null && abs($lineCodice - $codiceIvi) < 0.02) {
                    continue;
                }

                $listNet = $this->floatOrNull($product->get('listPrice'));

                if (!$taxInclusive && $listNet !== null
                    && abs($lineCodice - $listNet) < 0.02
                    && abs($lineCodice - $codiceNet) > 0.02) {
                    $itemList[$index] = $this->itemSet($item, 'prezzoCodice', $codiceNet);
                    $changed = true;

                    continue;
                }
            }

            $itemList[$index] = $this->itemSet($item, 'prezzoCodice', $targetCodice);
            $changed = true;
        }

        if ($changed) {
            $entity->set('itemList', $itemList);
        }
    }

    private function syncTotalsAndDerivedFields(Entity $entity, bool $isQuote): void
    {
        $taxInclusiveQuote = $isQuote && $this->isQuotePricesTaxInclusive($entity);
        $codiceNetFromProducts = $this->sumPrezzoCodiceNetFromProductsOnItems($entity);
        $codiceIviFromProducts = $this->sumPrezzoCodiceIvaInclusaFromProductsOnItems($entity);

        if ($taxInclusiveQuote) {
            if ($codiceIviFromProducts > 0) {
                $entity->set([
                    'prezzoCodiceIvaInclusa' => round($codiceIviFromProducts, 2),
                    'totalPrezzoCodice' => round($codiceIviFromProducts, 2),
                ]);
            } elseif ($codiceIvi = $this->sumPrezzoCodiceIvaInclusaFromItems($entity)) {
                if ($codiceIvi > 0) {
                    $entity->set([
                        'prezzoCodiceIvaInclusa' => round($codiceIvi, 2),
                        'totalPrezzoCodice' => round($codiceIvi, 2),
                    ]);
                }
            }

            if ($codiceNetFromProducts > 0) {
                $entity->set('prezzoCodiceIvaEsclusa', round($codiceNetFromProducts, 2));
            } elseif ($codiceNetFromProducts <= 0 && $codiceIviFromProducts > 0) {
                $aliquota = $this->resolveAliquotaIva($entity);
                $entity->set(
                    'prezzoCodiceIvaEsclusa',
                    round($codiceIviFromProducts / (1 + $aliquota / 100), 2)
                );
            }
        } else {
            $totalPrezzoCodice = $this->sumPrezzoCodiceFromItems($entity);

            if ($totalPrezzoCodice <= 0) {
                $totalPrezzoCodice = $this->floatOrNull($entity->get('totalPrezzoCodice')) ?? 0.0;
            }

            if ($totalPrezzoCodice <= 0) {
                $totalPrezzoCodice = $this->floatOrNull($entity->get('prezzoCodiceIvaEsclusa')) ?? 0.0;
            }

            if ($totalPrezzoCodice <= 0) {
                $totalPrezzoCodice = $codiceNetFromProducts;
            }

            if ($codiceNetFromProducts > 0) {
                $totalPrezzoCodice = $codiceNetFromProducts;
            }

            if ($totalPrezzoCodice > 0) {
                $entity->set([
                    'totalPrezzoCodice' => round($totalPrezzoCodice, 2),
                    'prezzoCodiceIvaEsclusa' => round($totalPrezzoCodice, 2),
                ]);
            }

            $this->syncPrezzoCodiceIvaInclusa($entity);
        }
        $this->syncListinoFromProducts($entity);

        if ($isQuote) {
            $this->syncAmountAndTaxFromImportoContratto($entity);
        }

        $minusPlus = $this->resolveMinusPlus($entity);

        if ($minusPlus !== null) {
            $entity->set('minusPlus', $minusPlus);
        }

        if ($isQuote && $entity->getId()) {
            $entity->set(
                'totaleProvvigioni',
                $this->quoteProvvigioniSync->sumTotaleProvvigioni($entity->getId())
            );
        }

        if (!$isQuote) {
            return;
        }

        $imponibile = $this->resolveImponibileNetto($entity);

        if ($imponibile === null) {
            return;
        }

        $prezzoListino = $this->floatOrNull($entity->get('prezzoListinoIvaEsclusa'));
        $totalListino = $this->sumListinoNetFromProductsOnItems($entity);

        if ($prezzoListino === null && $totalListino > 0) {
            $prezzoListino = $totalListino;
            $entity->set('prezzoListinoIvaEsclusa', round($totalListino, 2));
        }

        if ($prezzoListino !== null && $prezzoListino > 0) {
            $entity->set(
                'margineSuListino',
                round((($imponibile - $prezzoListino) / $prezzoListino) * 100, 2)
            );
        } elseif ($totalPrezzoCodice > 0) {
            $entity->set(
                'margineSuListino',
                round((($imponibile - $totalPrezzoCodice) / $totalPrezzoCodice) * 100, 2)
            );
        }

        if (!$entity->get('importoContratto') && $entity->get('grandTotalAmount')) {
            $grandTotal = $this->floatOrNull($entity->get('grandTotalAmount'));
            $listinoIvi = $this->sumListinoIvaInclusaFromProductsOnItems($entity);

            if ($grandTotal !== null && $grandTotal > 0
                && ($listinoIvi <= 0 || abs($grandTotal - $listinoIvi) > 0.02)) {
                $entity->set('importoContratto', $grandTotal);
            }
        }
    }

    private function syncListinoFromProducts(Entity $entity): void
    {
        $listinoNet = $this->sumListinoNetFromProductsOnItems($entity);
        $listinoIvi = $this->sumListinoIvaInclusaFromProductsOnItems($entity);

        if ($listinoNet > 0 && $entity->hasAttribute('prezzoListinoIvaEsclusa')) {
            $entity->set('prezzoListinoIvaEsclusa', round($listinoNet, 2));
        }

        if ($listinoIvi > 0) {
            if ($entity->hasAttribute('prezzoListinoIVAInclusa')) {
                $entity->set('prezzoListinoIVAInclusa', round($listinoIvi, 2));
            }

            if ($entity->hasAttribute('prezzoListinoIvaInclusa')) {
                $entity->set('prezzoListinoIvaInclusa', round($listinoIvi, 2));
            }
        }
    }

    private function syncPrezzoCodiceIvaInclusa(Entity $entity): void
    {
        $fromItems = $this->sumPrezzoCodiceIvaInclusaFromItems($entity);

        if ($fromItems > 0) {
            $entity->set('prezzoCodiceIvaInclusa', round($fromItems, 2));

            return;
        }

        $opportunity = null;

        if ($entity->getEntityType() === 'Quote' && $entity->get('opportunityId')) {
            $opportunity = $this->entityManager->getEntityById(
                'Opportunity',
                $entity->get('opportunityId')
            );
        }

        $ivi = $this->resolvePrezzoCodiceIvaInclusa($entity, $opportunity);

        if ($ivi !== null && $ivi > 0) {
            $entity->set('prezzoCodiceIvaInclusa', $ivi);
        }
    }

    public function resolveProductPrezzoCodiceNet(
        Entity $product,
        float $aliquotaPercent,
        ?Entity $productPrice = null,
        bool $pricesTaxInclusive = false
    ): ?float {
        if ($productPrice) {
            $fromPrice = $this->resolvePrezzoCodiceNetFromProductPrice($productPrice, $aliquotaPercent);

            if ($fromPrice !== null && $fromPrice > 0) {
                return $fromPrice;
            }
        }

        $ivi = $this->floatOrNull($product->get('prezzoCodiceIvaInclusa'));

        if ($ivi !== null && $ivi > 0) {
            return round($ivi / (1 + $aliquotaPercent / 100), 2);
        }

        $stored = $this->floatOrNull($product->get('prezzoCodice'));

        if ($stored !== null && $stored > 0) {
            if ($pricesTaxInclusive) {
                return round($stored / (1 + $aliquotaPercent / 100), 2);
            }

            return $stored;
        }

        return null;
    }

    public function resolveProductPrezzoCodiceIvaInclusa(
        Entity $product,
        float $aliquotaPercent,
        ?Entity $productPrice = null,
        bool $pricesTaxInclusive = false
    ): ?float {
        if ($productPrice) {
            $fromPrice = $this->resolvePrezzoCodiceIvaInclusaFromProductPrice($productPrice, $aliquotaPercent);

            if ($fromPrice !== null && $fromPrice > 0) {
                return $fromPrice;
            }
        }

        $ivi = $this->floatOrNull($product->get('prezzoCodiceIvaInclusa'));

        if ($ivi !== null && $ivi > 0) {
            return $ivi;
        }

        $stored = $this->floatOrNull($product->get('prezzoCodice'));

        if ($stored !== null && $stored > 0) {
            if ($pricesTaxInclusive) {
                return round($stored, 2);
            }

            return round($stored * (1 + $aliquotaPercent / 100), 2);
        }

        return null;
    }

    private function syncAmountAndTaxFromImportoContratto(Entity $quote): void
    {
        $importoLordo = $this->floatOrNull($quote->get('importoContratto'));

        if ($importoLordo === null || $importoLordo <= 0) {
            $importoLordo = $this->resolveImportoContrattoFromOpportunityOnly($quote);
        }

        if ($importoLordo === null || $importoLordo <= 0) {
            return;
        }

        $aliquota = $this->resolveAliquotaIva($quote);
        $taxInclusive = $this->isQuotePricesTaxInclusive($quote);
        $itemList = $quote->get('itemList');

        $patch = [
            'grandTotalAmount' => round($importoLordo, 2),
            'importoContratto' => round($importoLordo, 2),
            'aliquotaIVA' => $aliquota,
        ];

        if (is_array($itemList) && $itemList !== []) {
            $lineTotals = $this->sumTotalsFromItemList($itemList, $taxInclusive);
            $patch['amount'] = $lineTotals['net'];
            $patch['taxAmount'] = $lineTotals['tax'];
        } else {
            $split = $this->splitImportoContratto($importoLordo, $aliquota, $taxInclusive);
            $patch['amount'] = $split['net'];
            $patch['taxAmount'] = $split['tax'];
        }

        $quote->set($patch);

        if ($this->floatOrNull($quote->get('taxRate')) === null) {
            $quote->set('taxRate', round($aliquota / 100, 4));
        }
    }

    public function resolveImponibileNetto(Entity $entity): ?float
    {
        if ($entity->getEntityType() === 'Quote') {
            $amount = $this->floatOrNull($entity->get('amount'));

            if ($amount !== null && $amount > 0) {
                return $amount;
            }
        }

        $importoLordo = $this->resolveImportoVenditaLordo($entity);

        if ($importoLordo !== null && $importoLordo > 0) {
            $aliquota = $this->resolveAliquotaIva($entity);

            return $this->splitImportoContratto(
                $importoLordo,
                $aliquota,
                $this->isB2cContract($entity)
            )['net'];
        }

        if ($entity->getEntityType() !== 'Quote') {
            return null;
        }

        $amount = $this->floatOrNull($entity->get('amount'));

        if ($amount !== null && $amount > 0) {
            return $amount;
        }

        $taxAmount = $this->floatOrNull($entity->get('taxAmount')) ?? 0.0;
        $grandTotal = $this->floatOrNull($entity->get('grandTotalAmount'));

        if ($grandTotal !== null && $grandTotal > 0 && $taxAmount > 0) {
            return round($grandTotal - $taxAmount, 2);
        }

        return null;
    }

    public function resolveMinusPlus(Entity $entity): ?float
    {
        if ($entity->getEntityType() === 'Quote') {
            return $this->resolveMinusPlusForQuote($entity);
        }

        $opportunity = null;

        if ($entity->get('opportunityId')) {
            $opportunity = $this->entityManager->getEntityById(
                'Opportunity',
                $entity->get('opportunityId')
            );
        }

        return $this->resolveMinusPlusFromValues(
            $this->resolveImportoVenditaLordo($entity),
            $this->resolvePrezzoCodiceIvaInclusa($entity, $opportunity),
            $this->resolvePrezzoCodiceNetForMinusPlus($entity, $opportunity),
            $this->resolveAliquotaIva($entity),
            $this->isB2cContract($entity)
        );
    }

    /**
     * Contratto: imponibile − prezzo codice netto (IVA esclusa).
     */
    public function resolveMinusPlusForQuote(Entity $quote): ?float
    {
        $importoNet = $this->resolveImponibileNetto($quote);
        $prezzoCodiceNet = $this->floatOrNull($quote->get('prezzoCodiceIvaEsclusa'));

        if ($prezzoCodiceNet === null || $prezzoCodiceNet <= 0) {
            $prezzoCodiceNet = $this->sumPrezzoCodiceNetFromProductsOnItems($quote);
        }

        if ($prezzoCodiceNet === null || $prezzoCodiceNet <= 0) {
            $prezzoCodiceNet = $this->resolvePrezzoCodiceNetForMinusPlus($quote, null);
        }

        if ($prezzoCodiceNet === null || $prezzoCodiceNet <= 0
            || $importoNet === null || $importoNet <= 0) {
            return null;
        }

        return round($importoNet - $prezzoCodiceNet, 2);
    }

    /**
     * Prezzo codice netto per Minus/Plus (es. 4.400 IVI → 4.000).
     */
    public function resolvePrezzoCodiceNetForMinusPlus(Entity $entity, ?Entity $opportunity = null): ?float
    {
        $aliquota = $this->resolveAliquotaIva($entity);
        $fromProducts = $this->sumPrezzoCodiceNetFromProductsOnItems($entity);

        if ($fromProducts > 0) {
            return $fromProducts;
        }

        $net = $this->floatOrNull($entity->get('prezzoCodiceIvaEsclusa'))
            ?? $this->floatOrNull($opportunity?->get('prezzoCodiceIvaEsclusa'));
        $ivi = $this->floatOrNull($entity->get('prezzoCodiceIvaInclusa'))
            ?? $this->floatOrNull($opportunity?->get('prezzoCodiceIvaInclusa'));

        if ($net !== null && $net > 0 && $ivi !== null && $ivi > 0 && abs($net - $ivi) < 0.02) {
            return round($ivi / (1 + $aliquota / 100), 2);
        }

        if (($net === null || $net <= 0) && $ivi !== null && $ivi > 0) {
            return round($ivi / (1 + $aliquota / 100), 2);
        }

        return $net;
    }

    public function resolveImportoVenditaLordo(Entity $entity): ?float
    {
        if ($entity->getEntityType() === 'Quote') {
            $importo = $this->resolveImportoContrattoForQuote($entity);

            if ($importo !== null && $importo > 0) {
                return $importo;
            }
        }

        $importoOpportunit = $this->floatOrNull($entity->get('importoOpportunit'));

        if ($importoOpportunit !== null && $importoOpportunit > 0) {
            return $importoOpportunit;
        }

        return $this->floatOrNull($entity->get('importoContratto'));
    }

    public function resolveMinusPlusFromValues(
        ?float $importoContratto,
        ?float $prezzoCodiceIvaInclusa,
        ?float $prezzoCodiceIvaEsclusa,
        float $aliquotaPercent,
        bool $b2c
    ): ?float {
        if ($importoContratto === null || $importoContratto <= 0) {
            return null;
        }

        if ($b2c) {
            $imponibileNet = $this->splitImportoContratto(
                $importoContratto,
                $aliquotaPercent,
                true
            )['net'];

            $codiceNet = $prezzoCodiceIvaEsclusa;

            if (($codiceNet === null || $codiceNet <= 0) && $prezzoCodiceIvaInclusa !== null && $prezzoCodiceIvaInclusa > 0) {
                $codiceNet = round($prezzoCodiceIvaInclusa / (1 + $aliquotaPercent / 100), 2);
            }

            if ($codiceNet === null || $codiceNet <= 0) {
                return null;
            }

            if ($prezzoCodiceIvaInclusa !== null && $prezzoCodiceIvaInclusa > 0
                && abs($codiceNet - $prezzoCodiceIvaInclusa) < 0.02) {
                $codiceNet = round($prezzoCodiceIvaInclusa / (1 + $aliquotaPercent / 100), 2);
            }

            return round($imponibileNet - $codiceNet, 2);
        }

        $codiceNet = $prezzoCodiceIvaEsclusa;

        if ($codiceNet === null || $codiceNet <= 0) {
            return null;
        }

        return round($importoContratto - $codiceNet, 2);
    }

    private function resolvePrezzoCodiceIvaInclusa(Entity $entity, ?Entity $opportunity): ?float
    {
        $fromItems = $this->sumPrezzoCodiceIvaInclusaFromItems($entity);

        if ($fromItems > 0) {
            return $fromItems;
        }

        $ivi = $this->floatOrNull($entity->get('prezzoCodiceIvaInclusa'));

        if ($ivi !== null && $ivi > 0) {
            return $ivi;
        }

        if ($opportunity) {
            $ivi = $this->floatOrNull($opportunity->get('prezzoCodiceIvaInclusa'));

            if ($ivi !== null && $ivi > 0) {
                return $ivi;
            }
        }

        $net = $this->floatOrNull($entity->get('prezzoCodiceIvaEsclusa'))
            ?? $this->floatOrNull($opportunity?->get('prezzoCodiceIvaEsclusa'));

        if ($net === null || $net <= 0) {
            return null;
        }

        return round($net * (1 + $this->resolveAliquotaIva($entity) / 100), 2);
    }

    /**
     * Flag «Prezzi IVA inclusa» sul contratto: le colonne riga mostrano importi lordi.
     */
    public function isQuotePricesTaxInclusive(Entity $entity): bool
    {
        if ($entity->getEntityType() !== 'Quote') {
            return false;
        }

        return (bool) $entity->get('isTaxInclusive') || $this->isB2cContract($entity);
    }

    public function resolveProductListinoIvaInclusa(
        Entity $product,
        float $aliquotaPercent,
        ?Entity $quote = null,
        ?Entity $productPrice = null
    ): ?float {
        if ($productPrice === null && $quote !== null) {
            $productPrice = $this->findActiveProductPrice($product, $quote);
        }

        if ($productPrice) {
            $fromBook = $this->resolveListinoIvaInclusaFromProductPrice($productPrice, $aliquotaPercent);

            if ($fromBook !== null && $fromBook > 0) {
                return $fromBook;
            }
        }

        $ivi = $this->floatOrNull($product->get('prezzoListinoIvaInclusa'));

        if ($ivi !== null && $ivi > 0) {
            return $ivi;
        }

        $net = $this->floatOrNull($product->get('listPrice'));

        if ($net !== null && $net > 0) {
            return round($net * (1 + $aliquotaPercent / 100), 2);
        }

        return null;
    }

    public function resolveProductListinoNet(
        Entity $product,
        ?Entity $quote = null,
        ?Entity $productPrice = null
    ): ?float {
        if ($productPrice === null && $quote !== null) {
            $productPrice = $this->findActiveProductPrice($product, $quote);
        }

        if ($productPrice) {
            $price = $this->floatOrNull($productPrice->get('price'));

            if ($price !== null && $price > 0) {
                $priceBook = $this->entityManager->getEntityById(
                    'PriceBook',
                    $productPrice->get('priceBookId')
                );

                if ($priceBook && (bool) $priceBook->get('isTaxInclusive')) {
                    $aliquota = $quote
                        ? $this->resolveAliquotaIva($quote)
                        : self::DEFAULT_ALIQUOTA_IVA;

                    return round($price / (1 + $aliquota / 100), 2);
                }

                return $price;
            }
        }

        return $this->floatOrNull($product->get('listPrice'));
    }

    public function findActiveProductPrice(Entity $product, Entity $quote): ?Entity
    {
        $priceBookId = $quote->get('priceBookId');

        if (!$priceBookId) {
            return null;
        }

        $refDate = $this->resolveQuoteReferenceDate($quote);

        $collection = $this->entityManager
            ->getRDBRepository('ProductPrice')
            ->where([
                'productId' => $product->getId(),
                'priceBookId' => $priceBookId,
                'status' => 'Active',
            ])
            ->order('dateStart', 'DESC')
            ->find();

        foreach ($collection as $productPrice) {
            if ($this->isProductPriceValidOnDate($productPrice, $refDate)) {
                return $productPrice;
            }
        }

        return null;
    }

    private function resolveQuoteReferenceDate(Entity $quote): string
    {
        $dateQuoted = $quote->get('dateQuoted');

        if ($dateQuoted instanceof \DateTimeInterface) {
            return $dateQuoted->format('Y-m-d');
        }

        if (is_string($dateQuoted) && $dateQuoted !== '') {
            return substr($dateQuoted, 0, 10);
        }

        $createdAt = $quote->get('createdAt');

        if ($createdAt instanceof \DateTimeInterface) {
            return $createdAt->format('Y-m-d');
        }

        if (is_string($createdAt) && $createdAt !== '') {
            return substr($createdAt, 0, 10);
        }

        return date('Y-m-d');
    }

    private function isProductPriceValidOnDate(Entity $productPrice, string $refDate): bool
    {
        $start = $productPrice->get('dateStart');
        $end = $productPrice->get('dateEnd');

        if ($start) {
            $startStr = $start instanceof \DateTimeInterface
                ? $start->format('Y-m-d')
                : substr((string) $start, 0, 10);

            if ($startStr > $refDate) {
                return false;
            }
        }

        if ($end) {
            $endStr = $end instanceof \DateTimeInterface
                ? $end->format('Y-m-d')
                : substr((string) $end, 0, 10);

            if ($endStr < $refDate) {
                return false;
            }
        }

        return true;
    }

    private function resolveListinoIvaInclusaFromProductPrice(
        Entity $productPrice,
        float $aliquotaPercent
    ): ?float {
        $ivi = $this->floatOrNull($productPrice->get('prezzoListinoIvaInclusa'));

        if ($ivi !== null && $ivi > 0) {
            return round($ivi, 2);
        }

        $net = $this->floatOrNull($productPrice->get('prezzoListinoIvaEsclusa'));

        if ($net !== null && $net > 0) {
            return round($net * (1 + $aliquotaPercent / 100), 2);
        }

        $price = $this->floatOrNull($productPrice->get('price'));

        if ($price === null || $price <= 0) {
            return null;
        }

        $priceBook = $this->entityManager->getEntityById(
            'PriceBook',
            $productPrice->get('priceBookId')
        );

        if ($priceBook && (bool) $priceBook->get('isTaxInclusive')) {
            return round($price, 2);
        }

        return round($price * (1 + $aliquotaPercent / 100), 2);
    }

    private function resolvePrezzoCodiceNetFromProductPrice(
        Entity $productPrice,
        float $aliquotaPercent
    ): ?float {
        $ivi = $this->floatOrNull($productPrice->get('prezzoCodiceIvaInclusa'));
        $net = $this->floatOrNull($productPrice->get('prezzoCodice'));
        $priceBook = $this->entityManager->getEntityById(
            'PriceBook',
            $productPrice->get('priceBookId')
        );
        $bookTaxInclusive = $priceBook && (bool) $priceBook->get('isTaxInclusive');

        if ($ivi !== null && $ivi > 0) {
            return round($ivi / (1 + $aliquotaPercent / 100), 2);
        }

        if ($net !== null && $net > 0) {
            if ($bookTaxInclusive) {
                return round($net / (1 + $aliquotaPercent / 100), 2);
            }

            return round($net, 2);
        }

        return null;
    }

    private function resolvePrezzoCodiceIvaInclusaFromProductPrice(
        Entity $productPrice,
        float $aliquotaPercent
    ): ?float {
        $ivi = $this->floatOrNull($productPrice->get('prezzoCodiceIvaInclusa'));
        $net = $this->floatOrNull($productPrice->get('prezzoCodice'));
        $priceBook = $this->entityManager->getEntityById(
            'PriceBook',
            $productPrice->get('priceBookId')
        );
        $bookTaxInclusive = $priceBook && (bool) $priceBook->get('isTaxInclusive');

        if ($ivi !== null && $ivi > 0) {
            return round($ivi, 2);
        }

        if ($net !== null && $net > 0) {
            if ($bookTaxInclusive) {
                return round($net, 2);
            }

            return round($net * (1 + $aliquotaPercent / 100), 2);
        }

        return null;
    }

    public function isB2cContract(Entity $entity): bool
    {
        if ($entity->getEntityType() === 'Quote' && $entity->get('isTaxInclusive')) {
            return true;
        }

        $accountId = $entity->get('accountId');

        if (!$accountId) {
            return false;
        }

        $account = $this->entityManager->getEntityById('Account', $accountId);

        if (!$account) {
            return false;
        }

        if ($account->get('type') === 'B2C') {
            return true;
        }

        $segmento = (string) $account->get('segmento');

        if ($segmento === 'B2C' || str_starts_with(strtoupper($segmento), 'B2C')) {
            return true;
        }

        if ($account->get('b2B') === false) {
            return true;
        }

        return false;
    }

    /**
     * @return array{net: float, tax: float, gross: float}
     */
    public function splitImportoContratto(float $importoContratto, float $aliquotaPercent, bool $taxInclusive): array
    {
        if ($taxInclusive) {
            $net = round($importoContratto / (1 + $aliquotaPercent / 100), 2);
            $gross = round($importoContratto, 2);
            $tax = round($gross - $net, 2);

            return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
        }

        $net = round($importoContratto, 2);
        $tax = round($net * $aliquotaPercent / 100, 2);
        $gross = round($net + $tax, 2);

        return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
    }

    private function resolveAliquotaIva(Entity $entity): float
    {
        $aliquota = $this->floatOrNull($entity->get('aliquotaIVA'));

        if ($aliquota !== null && $aliquota > 0) {
            return $aliquota;
        }

        if ($entity->hasAttribute('iVA')) {
            $iva = $this->floatOrNull($entity->get('iVA'));

            if ($iva !== null && $iva > 0) {
                return $iva;
            }
        }

        $taxRate = $this->floatOrNull($entity->get('taxRate'));

        if ($taxRate !== null && $taxRate > 0) {
            return $taxRate < 1 ? $taxRate * 100 : $taxRate;
        }

        if ($entity->getEntityType() === 'Quote' && $entity->get('taxId')) {
            $tax = $this->entityManager->getEntityById('Tax', $entity->get('taxId'));

            if ($tax) {
                $rate = $this->floatOrNull($tax->get('rate'))
                    ?? $this->floatOrNull($tax->get('taxRate'));

                if ($rate !== null && $rate > 0) {
                    return $rate < 1 ? $rate * 100 : $rate;
                }
            }
        }

        return self::DEFAULT_ALIQUOTA_IVA;
    }

    private function sumPrezzoCodiceFromItems(Entity $entity): float
    {
        $itemList = $entity->get('itemList');

        if (!is_array($itemList)) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($itemList as $item) {
            $prezzo = $this->floatOrNull($this->itemValue($item, 'prezzoCodice')) ?? 0.0;
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($prezzo > 0 && $qty > 0) {
                $sum += $prezzo * $qty;
            }
        }

        return $sum;
    }

    private function sumPrezzoCodiceIvaInclusaFromItems(Entity $entity): float
    {
        $itemList = $entity->get('itemList');

        if (!is_array($itemList) || $itemList === []) {
            return 0.0;
        }

        $aliquota = $this->resolveAliquotaIva($entity);
        $taxInclusive = $entity->getEntityType() === 'Quote' && $this->isQuotePricesTaxInclusive($entity);
        $sum = 0.0;

        foreach ($itemList as $item) {
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($qty <= 0) {
                continue;
            }

            $productId = $this->itemValue($item, 'productId');

            if ($productId) {
                $product = $this->entityManager->getEntityById('Product', $productId);

                if ($product) {
                    $productPrice = $entity->getEntityType() === 'Quote'
                        ? $this->findActiveProductPrice($product, $entity)
                        : null;
                    $ivi = $this->resolveProductPrezzoCodiceIvaInclusa(
                        $product,
                        $aliquota,
                        $productPrice,
                        $taxInclusive
                    );

                    if ($ivi !== null && $ivi > 0) {
                        $sum += $ivi * $qty;

                        continue;
                    }
                }
            }

            $lineCodice = $this->floatOrNull($this->itemValue($item, 'prezzoCodice'));

            if ($lineCodice !== null && $lineCodice > 0) {
                if ($entity->getEntityType() === 'Quote' && $this->isQuotePricesTaxInclusive($entity)) {
                    $sum += $lineCodice * $qty;
                } else {
                    $sum += round($lineCodice * (1 + $aliquota / 100), 2) * $qty;
                }
            }
        }

        return $sum;
    }

    private function sumPrezzoCodiceNetFromProductsOnItems(Entity $entity): float
    {
        $itemList = $entity->get('itemList');

        if (!is_array($itemList)) {
            return 0.0;
        }

        $aliquota = $this->resolveAliquotaIva($entity);
        $taxInclusive = $entity->getEntityType() === 'Quote' && $this->isQuotePricesTaxInclusive($entity);
        $sum = 0.0;

        foreach ($itemList as $item) {
            $productId = $this->itemValue($item, 'productId');

            if (!$productId) {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if (!$product) {
                continue;
            }

            $productPrice = $entity->getEntityType() === 'Quote'
                ? $this->findActiveProductPrice($product, $entity)
                : null;

            $net = $this->resolveProductPrezzoCodiceNet($product, $aliquota, $productPrice, $taxInclusive);
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($net !== null && $net > 0 && $qty > 0) {
                $sum += $net * $qty;
            }
        }

        return $sum;
    }

    private function sumPrezzoCodiceIvaInclusaFromProductsOnItems(Entity $entity): float
    {
        $itemList = $entity->get('itemList');

        if (!is_array($itemList)) {
            return 0.0;
        }

        $aliquota = $this->resolveAliquotaIva($entity);
        $taxInclusive = $entity->getEntityType() === 'Quote' && $this->isQuotePricesTaxInclusive($entity);
        $sum = 0.0;

        foreach ($itemList as $item) {
            $productId = $this->itemValue($item, 'productId');

            if (!$productId) {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if (!$product) {
                continue;
            }

            $productPrice = $entity->getEntityType() === 'Quote'
                ? $this->findActiveProductPrice($product, $entity)
                : null;

            $ivi = $this->resolveProductPrezzoCodiceIvaInclusa($product, $aliquota, $productPrice, $taxInclusive);
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($ivi !== null && $ivi > 0 && $qty > 0) {
                $sum += $ivi * $qty;
            }
        }

        return $sum;
    }

    private function sumListinoNetFromProductsOnItems(Entity $entity): float
    {
        $itemList = $entity->get('itemList');

        if (!is_array($itemList)) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($itemList as $item) {
            $productId = $this->itemValue($item, 'productId');

            if (!$productId) {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if (!$product) {
                continue;
            }

            $net = $this->floatOrNull($product->get('listPrice'));
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($net !== null && $net > 0 && $qty > 0) {
                $sum += $net * $qty;
            }
        }

        return $sum;
    }

    private function sumListinoIvaInclusaFromProductsOnItems(Entity $entity): float
    {
        $itemList = $entity->get('itemList');

        if (!is_array($itemList)) {
            return 0.0;
        }

        $aliquota = $this->resolveAliquotaIva($entity);
        $sum = 0.0;

        foreach ($itemList as $item) {
            $productId = $this->itemValue($item, 'productId');

            if (!$productId) {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if (!$product) {
                continue;
            }

            $productPrice = $entity->getEntityType() === 'Quote'
                ? $this->findActiveProductPrice($product, $entity)
                : null;
            $ivi = $this->resolveProductListinoIvaInclusa(
                $product,
                $aliquota,
                $entity->getEntityType() === 'Quote' ? $entity : null,
                $productPrice
            );
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($ivi !== null && $ivi > 0 && $qty > 0) {
                $sum += $ivi * $qty;
            }
        }

        return $sum;
    }

    private function itemValue(mixed $item, string $key): mixed
    {
        if (is_object($item)) {
            return $item->$key ?? null;
        }

        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|object
     */
    private function itemSet(mixed $item, string $key, mixed $value): array|object
    {
        if (is_object($item)) {
            $item->$key = $value;

            return $item;
        }

        $item[$key] = $value;

        return $item;
    }

    private function parseItalianAmount(string $raw): ?float
    {
        $s = trim(str_replace([' ', "\u{00a0}"], '', $raw));

        if ($s === '') {
            return null;
        }

        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        $v = (float) $s;

        return $v > 0 ? $v : null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
