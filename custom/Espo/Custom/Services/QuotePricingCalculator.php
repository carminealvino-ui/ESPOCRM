<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Passaggio 1 contratto: totali prezzo codice, minus/plus (plusvalenza), margine % su listino.
 *
 * Regola: minusPlus = imponibile IVA esclusa − prezzo codice totale IVA esclusa.
 * B2C: {@see importoContratto} è sempre IVA inclusa → si scorpora per amount e minus/plus.
 * B2B: importoContratto è IVA esclusa.
 */
class QuotePricingCalculator
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function syncOnBeforeSave(Entity $quote): void
    {
        $this->syncItemListPrezzoCodice($quote);
        $this->syncTotalsAndDerivedFields($quote);
    }

    private function syncItemListPrezzoCodice(Entity $quote): void
    {
        $itemList = $quote->get('itemList');

        if (!is_array($itemList) || $itemList === []) {
            return;
        }

        foreach ($itemList as $index => $item) {
            $productId = $this->itemValue($item, 'productId');

            if (!$productId) {
                continue;
            }

            $linePrezzoCodice = $this->floatOrNull($this->itemValue($item, 'prezzoCodice'));

            if ($linePrezzoCodice !== null && $linePrezzoCodice > 0) {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if (!$product) {
                continue;
            }

            $prezzoCodice = $this->floatOrNull($product->get('prezzoCodice'))
                ?? $this->floatOrNull($product->get('listPrice'))
                ?? $this->floatOrNull($product->get('unitPrice'));

            if ($prezzoCodice === null) {
                continue;
            }

            $itemList[$index] = $this->itemSet($item, 'prezzoCodice', $prezzoCodice);
        }

        $quote->set('itemList', $itemList);
    }

    private function syncTotalsAndDerivedFields(Entity $quote): void
    {
        $totalPrezzoCodice = $this->sumPrezzoCodiceFromItems($quote);
        $totalListino = $this->sumListinoFromItems($quote);

        if ($totalPrezzoCodice <= 0) {
            $totalPrezzoCodice = $this->floatOrNull($quote->get('totalPrezzoCodice')) ?? 0.0;
        }

        if ($totalPrezzoCodice <= 0) {
            $totalPrezzoCodice = $this->floatOrNull($quote->get('prezzoCodiceIvaEsclusa'))
                ?? $this->floatOrNull($quote->get('prezzoCodice'))
                ?? 0.0;
        }

        $quote->set('totalPrezzoCodice', round($totalPrezzoCodice, 2));

        if ($totalPrezzoCodice > 0) {
            $quote->set('prezzoCodiceIvaEsclusa', round($totalPrezzoCodice, 2));
        }

        $this->syncAmountAndTaxFromImportoContratto($quote);

        $imponibile = $this->resolveImponibileNetto($quote);

        if ($imponibile === null) {
            return;
        }

        if ($totalPrezzoCodice > 0) {
            $quote->set('minusPlus', round($imponibile - $totalPrezzoCodice, 2));
        }

        $prezzoListino = $this->floatOrNull($quote->get('prezzoListinoIvaEsclusa'));

        if ($prezzoListino === null && $totalListino > 0) {
            $prezzoListino = $totalListino;
            $quote->set('prezzoListinoIvaEsclusa', round($totalListino, 2));
        }

        if ($prezzoListino !== null && $prezzoListino > 0) {
            $quote->set(
                'margineSuListino',
                round((($imponibile - $prezzoListino) / $prezzoListino) * 100, 2)
            );
        } elseif ($totalPrezzoCodice > 0) {
            $quote->set(
                'margineSuListino',
                round((($imponibile - $totalPrezzoCodice) / $totalPrezzoCodice) * 100, 2)
            );
        }

        if (!$quote->get('importoContratto') && $quote->get('grandTotalAmount')) {
            $quote->set('importoContratto', $quote->get('grandTotalAmount'));
        }
    }

    /**
     * Allinea amount (netto), IVA e totale da importoContratto.
     * B2C: importoContratto = lordo IVA inclusa; B2B: importoContratto = imponibile netto.
     */
    private function syncAmountAndTaxFromImportoContratto(Entity $quote): void
    {
        $importoContratto = $this->floatOrNull($quote->get('importoContratto'));

        if ($importoContratto === null || $importoContratto <= 0) {
            return;
        }

        $aliquota = $this->resolveAliquotaIva($quote);
        $split = $this->splitImportoContratto($importoContratto, $aliquota, $this->isB2cContract($quote));

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

    /**
     * Imponibile netto per minus/plus e provvigioni.
     */
    public function resolveImponibileNetto(Entity $quote): ?float
    {
        $importoContratto = $this->floatOrNull($quote->get('importoContratto'));

        if ($importoContratto !== null && $importoContratto > 0) {
            $aliquota = $this->resolveAliquotaIva($quote);

            return $this->splitImportoContratto(
                $importoContratto,
                $aliquota,
                $this->isB2cContract($quote)
            )['net'];
        }

        $amount = $this->floatOrNull($quote->get('amount'));

        if ($amount !== null && $amount > 0) {
            return $amount;
        }

        $taxAmount = $this->floatOrNull($quote->get('taxAmount')) ?? 0.0;
        $grandTotal = $this->floatOrNull($quote->get('grandTotalAmount'));

        if ($grandTotal !== null && $grandTotal > 0 && $taxAmount > 0) {
            return round($grandTotal - $taxAmount, 2);
        }

        return null;
    }

    public function isB2cContract(Entity $quote): bool
    {
        $accountId = $quote->get('accountId');

        if (!$accountId) {
            return true;
        }

        $account = $this->entityManager->getEntityById('Account', $accountId);

        if (!$account) {
            return true;
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

    private function resolveAliquotaIva(Entity $quote): float
    {
        $aliquota = $this->floatOrNull($quote->get('aliquotaIVA'));

        if ($aliquota !== null && $aliquota > 0) {
            return $aliquota;
        }

        $taxRate = $this->floatOrNull($quote->get('taxRate'));

        if ($taxRate !== null && $taxRate > 0) {
            return $taxRate < 1 ? $taxRate * 100 : $taxRate;
        }

        return 10.0;
    }

    private function sumPrezzoCodiceFromItems(Entity $quote): float
    {
        $itemList = $quote->get('itemList');

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

    private function sumListinoFromItems(Entity $quote): float
    {
        $itemList = $quote->get('itemList');

        if (!is_array($itemList)) {
            return 0.0;
        }

        $sum = 0.0;
        $isTaxInclusive = (bool) $quote->get('isTaxInclusive');

        foreach ($itemList as $item) {
            $listPrice = $this->floatOrNull($this->itemValue($item, 'listPrice')) ?? 0.0;
            $qty = (float) ($this->itemValue($item, 'quantity') ?? 1);

            if ($listPrice <= 0 || $qty <= 0) {
                continue;
            }

            if ($isTaxInclusive) {
                $aliquota = $this->floatOrNull($quote->get('aliquotaIVA')) ?? 10.0;
                $listPrice = round($listPrice / (1 + $aliquota / 100), 2);
            }

            $sum += $listPrice * $qty;
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

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
