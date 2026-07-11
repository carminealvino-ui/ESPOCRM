<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Contratto: prezzi riga IVA inclusa se flag attivo; importo/minus-plus da importoContratto.
 * Ricalcolo completo solo se cambiano campi che impattano importi/articoli (whitelist).
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
                $this->pricingCalculator->syncFinancingFieldsOnBeforeSave($entity);
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

        foreach (self::FULL_PRICING_TRIGGER_FIELDS as $field) {
            if ($entity->isAttributeChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
