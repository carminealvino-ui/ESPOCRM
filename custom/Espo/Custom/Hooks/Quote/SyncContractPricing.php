<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Contratto: prezzi riga IVA inclusa se flag attivo; importo/minus-plus da importoContratto.
 * Salto ricalcolo completo se cambiano solo campi finanziamento (pannello Finanziamento).
 */
class SyncContractPricing implements BeforeSave
{
    public static int $order = 999;

    /** @var string[] */
    private const FINANCING_FIELDS = [
        'finanziamento',
        'statoFinanziamento',
        'importoCaparra',
        'importoSaldo',
        'importoFinanziato',
        'rataPrestito',
        'nrRate',
        'tassoZero',
    ];

    /** @var string[] */
    private const PRICING_FIELDS = [
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
        'statoContratto',
    ];

    public function __construct(
        private QuotePricingCalculator $pricingCalculator
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if ($this->isFinancingOnlyChange($entity)) {
            if ($entity->isAttributeChanged('tassoZero')) {
                $this->pricingCalculator->syncFinancingFieldsOnBeforeSave($entity);
            }

            return;
        }

        $this->pricingCalculator->syncOnBeforeSave($entity);
    }

    private function isFinancingOnlyChange(Entity $entity): bool
    {
        if ($entity->isNew()) {
            return false;
        }

        $changedFinancing = false;

        foreach (self::FINANCING_FIELDS as $field) {
            if ($entity->isAttributeChanged($field)) {
                $changedFinancing = true;
                break;
            }
        }

        if (!$changedFinancing) {
            return false;
        }

        foreach (self::PRICING_FIELDS as $field) {
            if ($entity->isAttributeChanged($field)) {
                return false;
            }
        }

        return true;
    }
}
