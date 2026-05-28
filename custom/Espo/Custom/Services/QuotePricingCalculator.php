<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Contratto / Opportunity: prezzo codice da prodotto, Minus/Plus GDL (B2C).
 *
 * COMBO 9+9: codice 4.000 netto, importo venduto 4.500 IVI → imponibile 4.090,91 → minusPlus ≈ 91.
 * GDL: minusPlus = imponibile netto − prezzo codice IVA escl. (non lordo − codice IVI).
 */
class QuotePricingCalculator
{
    private const DEFAULT_ALIQUOTA_IVA = 10.0;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function syncOnBeforeSave(Entity $quote): void
    {
        $this->ensureImportoContrattoOnQuote($quote);
        $this->syncItemListPrezzoCodice($quote);
        $this->syncItemListListinoFromProducts($quote);
        $this->syncItemListAmountFromImportoContratto($quote);
        $this->syncTotalsAndDerivedFields($quote, true);
    }

    /**
     * Importo venduto B2C: campo importoContratto, opportunità o nome contratto (es. «€. 4.500»).
     */
    public function resolveImportoContrattoForQuote(Entity $quote): ?float
    {
        $importo = $this->floatOrNull($quote->get('importoContratto'));

        if ($importo !== null && $importo > 0) {
            return $importo;
        }

        if ($quote->get('opportunityId')) {
            $opportunity = $this->entityManager->getEntityById(
                'Opportunity',
                $quote->get('opportunityId')
            );

            if ($opportunity) {
                foreach (['importoOpportunit', 'importoContratto', 'amount'] as $field) {
                    $val = $this->floatOrNull($opportunity->get($field));

                    if ($val !== null && $val > 0) {
                        return $val;
                    }
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

    private function ensureImportoContrattoOnQuote(Entity $quote): void
    {
        $resolved = $this->resolveImportoContrattoForQuote($quote);

        if ($resolved !== null && $resolved > 0) {
            $quote->set('importoContratto', $resolved);
        }
    }

    private function syncItemListAmountFromImportoContratto(Entity $quote): void
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
        $changed = false;

        foreach ($itemList as $index => $item) {
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($qty <= 0) {
                $qty = 1.0;
            }

            $lineNet = round($split['net'] / $qty, 2);
            $lineTax = round($split['tax'] / $qty, 2);
            $lineGross = round($split['gross'] / $qty, 2);

            if ($taxInclusive) {
                $itemList[$index] = $this->itemSet($item, 'unitPrice', $lineGross);
                $itemList[$index] = $this->itemSet($itemList[$index], 'amount', $lineGross);
                $itemList[$index] = $this->itemSet($itemList[$index], 'taxAmount', $lineTax);
            } else {
                $itemList[$index] = $this->itemSet($item, 'unitPrice', $lineNet);
                $itemList[$index] = $this->itemSet($itemList[$index], 'amount', $lineNet);
                $itemList[$index] = $this->itemSet($itemList[$index], 'taxAmount', $lineTax);
                $itemList[$index] = $this->itemSet($itemList[$index], 'listPrice', $lineNet);
            }

            $changed = true;
        }

        if ($changed) {
            $quote->set('itemList', $itemList);
        }

        $quote->set([
            'amount' => $split['net'],
            'taxAmount' => $split['tax'],
            'grandTotalAmount' => $split['gross'],
            'aliquotaIVA' => $aliquota,
            'importoContratto' => $importo,
        ]);

        if ($this->floatOrNull($quote->get('taxRate')) === null && $aliquota > 0) {
            $quote->set('taxRate', round($aliquota / 100, 4));
        }
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

            $codiceNet = $this->resolveProductPrezzoCodiceNet($product, $aliquota);
            $codiceIvi = $this->resolveProductPrezzoCodiceIvaInclusa($product, $aliquota);

            if ($codiceNet === null && $codiceIvi === null) {
                continue;
            }

            $lineCodice = $this->floatOrNull($this->itemValue($item, 'prezzoCodice'));
            $targetCodice = $taxInclusive && $codiceIvi !== null && $codiceIvi > 0
                ? $codiceIvi
                : $codiceNet;

            if ($targetCodice === null) {
                continue;
            }

            if ($lineCodice !== null && abs($lineCodice - $targetCodice) < 0.02) {
                continue;
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
        $totalPrezzoCodice = $this->sumPrezzoCodiceFromItems($entity);

        if ($totalPrezzoCodice <= 0) {
            $totalPrezzoCodice = $this->floatOrNull($entity->get('totalPrezzoCodice')) ?? 0.0;
        }

        if ($totalPrezzoCodice <= 0) {
            $totalPrezzoCodice = $this->floatOrNull($entity->get('prezzoCodiceIvaEsclusa')) ?? 0.0;
        }

        if ($totalPrezzoCodice <= 0) {
            $totalPrezzoCodice = $this->sumPrezzoCodiceNetFromProductsOnItems($entity);
        }

        $codiceNetFromProducts = $this->sumPrezzoCodiceNetFromProductsOnItems($entity);

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
        $this->syncListinoFromProducts($entity);

        if ($isQuote) {
            $this->syncAmountAndTaxFromImportoContratto($entity);
        }

        $minusPlus = $this->resolveMinusPlus($entity);

        if ($minusPlus !== null) {
            $entity->set('minusPlus', $minusPlus);
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
            $entity->set('importoContratto', $entity->get('grandTotalAmount'));
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

    public function resolveProductPrezzoCodiceNet(Entity $product, float $aliquotaPercent): ?float
    {
        $net = $this->floatOrNull($product->get('prezzoCodice'));

        if ($net !== null && $net > 0) {
            return $net;
        }

        $ivi = $this->floatOrNull($product->get('prezzoCodiceIvaInclusa'));

        if ($ivi !== null && $ivi > 0) {
            return round($ivi / (1 + $aliquotaPercent / 100), 2);
        }

        return null;
    }

    public function resolveProductPrezzoCodiceIvaInclusa(Entity $product, float $aliquotaPercent): ?float
    {
        $ivi = $this->floatOrNull($product->get('prezzoCodiceIvaInclusa'));

        if ($ivi !== null && $ivi > 0) {
            return $ivi;
        }

        $net = $this->floatOrNull($product->get('prezzoCodice'));

        if ($net !== null && $net > 0) {
            return round($net * (1 + $aliquotaPercent / 100), 2);
        }

        return null;
    }

    private function syncAmountAndTaxFromImportoContratto(Entity $quote): void
    {
        $importoContratto = $this->floatOrNull($quote->get('importoContratto'));

        if ($importoContratto === null || $importoContratto <= 0) {
            return;
        }

        $aliquota = $this->resolveAliquotaIva($quote);
        $split = $this->splitImportoContratto(
            $importoContratto,
            $aliquota,
            $this->isQuotePricesTaxInclusive($quote)
        );

        $quote->set([
            'amount' => $split['net'],
            'taxAmount' => $split['tax'],
            'grandTotalAmount' => $split['gross'],
            'aliquotaIVA' => $aliquota,
        ]);

        if ($this->floatOrNull($quote->get('taxRate')) === null) {
            $quote->set('taxRate', round($aliquota / 100, 4));
        }
    }

    public function resolveImponibileNetto(Entity $entity): ?float
    {
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
        $opportunity = null;

        if ($entity->getEntityType() === 'Quote' && $entity->get('opportunityId')) {
            $opportunity = $this->entityManager->getEntityById(
                'Opportunity',
                $entity->get('opportunityId')
            );
        }

        return $this->resolveMinusPlusFromValues(
            $this->resolveImportoVenditaLordo($entity),
            $this->resolvePrezzoCodiceIvaInclusa($entity, $opportunity),
            $this->floatOrNull($entity->get('prezzoCodiceIvaEsclusa'))
                ?? $this->floatOrNull($opportunity?->get('prezzoCodiceIvaEsclusa')),
            $this->resolveAliquotaIva($entity),
            $this->isB2cContract($entity)
        );
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

    private function syncItemListListinoFromProducts(Entity $quote): void
    {
        if (!$this->isQuotePricesTaxInclusive($quote)) {
            return;
        }

        $itemList = $quote->get('itemList');

        if (!is_array($itemList) || $itemList === []) {
            return;
        }

        $aliquota = $this->resolveAliquotaIva($quote);
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

            $listIvi = $this->resolveProductListinoIvaInclusa($product, $aliquota);

            if ($listIvi === null || $listIvi <= 0) {
                continue;
            }

            $lineList = $this->floatOrNull($this->itemValue($item, 'listPrice'));

            if ($lineList !== null && abs($lineList - $listIvi) < 0.02) {
                continue;
            }

            $itemList[$index] = $this->itemSet($item, 'listPrice', $listIvi);
            $changed = true;
        }

        if ($changed) {
            $quote->set('itemList', $itemList);
        }
    }

    public function resolveProductListinoIvaInclusa(Entity $product, float $aliquotaPercent): ?float
    {
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
                    $ivi = $this->resolveProductPrezzoCodiceIvaInclusa($product, $aliquota);

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

            $net = $this->resolveProductPrezzoCodiceNet($product, $aliquota);
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($net !== null && $net > 0 && $qty > 0) {
                $sum += $net * $qty;
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

            $ivi = $this->floatOrNull($product->get('prezzoListinoIvaInclusa'));
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
