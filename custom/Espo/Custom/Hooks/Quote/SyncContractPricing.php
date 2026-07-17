<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Contratto: prezzi riga IVA inclusa se flag attivo; importo/minus-plus da importoContratto.
 * Ricalcolo completo solo se cambiano realmente campi importi/articoli.
 */
class SyncContractPricing implements BeforeSave
{
    public static int $order = 999;

    /** @var string[] */
    private const FULL_PRICING_TRIGGER_FIELDS = [
        'itemList',
        'amount',
        'taxAmount',
        'grandTotalAmount',
        'totalPrezzoCodice',
        'prezzoCodiceIvaEsclusa',
        'minusPlus',
        'importoContratto',
        'isTaxInclusive',
        'numeroContratto',
        'number',
        'dataAttivazione',
        'dataInstallazione',
        'dateQuoted',
        'productCategoryId',
        'prezzoListinoIvaEsclusa',
        'margineSuListino',
        'contattoPersonaleArquati',
        'shippingCost',
        'taxRate',
        'aliquotaIVA',
        'priceBookId',
        'taxId',
    ];

    public function __construct(
        private QuotePricingCalculator $pricingCalculator
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if (!$this->shouldRunFullPricingSync($entity)) {
            if ($entity->isAttributeChanged('tassoZero')) {
                try {
                    $this->pricingCalculator->syncFinancingFieldsOnBeforeSave($entity);
                } catch (\Throwable $e) {
                    error_log('SyncContractPricing financing [' . ($entity->getId() ?? 'new') . ']: ' . $e->getMessage());
                }
            }

            return;
        }

        try {
            $this->pricingCalculator->syncOnBeforeSave($entity);
        } catch (\Throwable $e) {
            error_log('SyncContractPricing [' . ($entity->getId() ?? 'new') . ']: ' . $e->getMessage());
        }
    }

    private function shouldRunFullPricingSync(Entity $entity): bool
    {
        if ($entity->isNew()) {
            return true;
        }

        $hookVersion = (string) ($entity->get('hookVersion') ?? '');

        if ($hookVersion !== '' && str_contains($hookVersion, 'CreateContratto')) {
            return $this->hasCreateContrattoPricingChange($entity);
        }

        foreach (self::FULL_PRICING_TRIGGER_FIELDS as $field) {
            if ($this->isFieldReallyChanged($entity, $field)) {
                return true;
            }
        }

        return false;
    }

    private function hasCreateContrattoPricingChange(Entity $entity): bool
    {
        /** @var string[] */
        $strictTriggers = [
            'amount',
            'taxAmount',
            'importoContratto',
            'isTaxInclusive',
            'taxRate',
            'taxId',
            'priceBookId',
            'shippingCost',
        ];

        foreach ($strictTriggers as $field) {
            if ($this->isFieldReallyChanged($entity, $field)) {
                return true;
            }
        }

        return $this->isItemListSemanticallyChanged($entity);
    }

    private function isItemListSemanticallyChanged(Entity $entity): bool
    {
        if (!$entity->isAttributeChanged('itemList')) {
            return false;
        }

        $current = $this->normalizeItemList($entity->get('itemList'));
        $fetched = $this->normalizeItemList($entity->getFetched('itemList'));

        return json_encode($current) !== json_encode($fetched);
    }

    /**
     * @return list<array<string, float|int|string>>
     */
    private function normalizeItemList(mixed $itemList): array
    {
        if (!is_array($itemList)) {
            return [];
        }

        $numericKeys = [
            'quantity',
            'unitPrice',
            'listPrice',
            'amount',
            'taxAmount',
            'prezzoCodice',
            'discount',
            'order',
        ];
        $normalized = [];

        foreach ($itemList as $item) {
            $row = [];

            foreach ([
                'id',
                'productId',
                'name',
                'quantity',
                'unitPrice',
                'listPrice',
                'amount',
                'taxAmount',
                'prezzoCodice',
                'discount',
                'order',
            ] as $key) {
                $value = $this->itemValue($item, $key);

                if ($value === null || $value === '') {
                    continue;
                }

                if (in_array($key, $numericKeys, true)) {
                    $row[$key] = round((float) $value, 2);
                } else {
                    $row[$key] = (string) $value;
                }
            }

            ksort($row);
            $normalized[] = $row;
        }

        usort(
            $normalized,
            static fn (array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0))
        );

        return $normalized;
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

    private function isFieldReallyChanged(Entity $entity, string $field): bool
    {
        if (!$entity->isAttributeChanged($field)) {
            return false;
        }

        if ($field !== 'itemList') {
            return true;
        }

        return $this->isItemListSemanticallyChanged($entity);
    }
}
