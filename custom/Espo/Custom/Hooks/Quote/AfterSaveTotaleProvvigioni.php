<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Aggiorna totale provvigioni sul contratto dopo salvataggio.
 */
class AfterSaveTotaleProvvigioni implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private ProvvigioneManager $provvigioneManager
    ) {}

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

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        if ($this->isFinancingOnlyChange($entity)) {
            return;
        }

        $this->provvigioneManager->refreshQuoteTotaleProvvigioni($entity);
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
