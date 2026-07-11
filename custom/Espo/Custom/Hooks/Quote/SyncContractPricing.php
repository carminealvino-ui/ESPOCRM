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
            'itemList',
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

        return false;
    }

    private function isFieldReallyChanged(Entity $entity, string $field): bool
    {
        if (!$entity->isAttributeChanged($field)) {
            return false;
        }

        if ($field !== 'itemList') {
            return true;
        }

        return json_encode($entity->get($field)) !== json_encode($entity->getFetched($field));
    }
}
