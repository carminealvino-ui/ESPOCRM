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

    /** @var string[] */
    private const PRICING_TRIGGER_FIELDS = [
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
        'finanziamento',
        'statoFinanziamento',
    ];

    public function __construct(
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        if (!$this->shouldRefreshTotale($entity)) {
            return;
        }

        try {
            $this->provvigioneManager->refreshQuoteTotaleProvvigioni($entity);
        } catch (\Throwable $e) {
            error_log('AfterSaveTotaleProvvigioni [' . $entity->getId() . ']: ' . $e->getMessage());
        }
    }

    private function shouldRefreshTotale(Entity $entity): bool
    {
        if ($entity->isNew()) {
            return true;
        }

        foreach (self::PRICING_TRIGGER_FIELDS as $field) {
            if ($entity->isAttributeChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
