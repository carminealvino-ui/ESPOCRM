<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ricalcola tutte le provvigioni consolidate quando cambiano importi articoli.
 */
class ProvvigioneConsolidata implements AfterSave
{
    public static int $order = 15;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if (!$entity->getId()) {
            return;
        }

        if (!$this->shouldRecalculate($entity)) {
            return;
        }

        $quote = $this->entityManager->getEntityById('Quote', $entity->getId());

        if (!$quote || !$quote->get('opportunityId')) {
            return;
        }

        try {
            $this->provvigioneManager->recalculateAllForQuote($quote);
        } catch (\Throwable $e) {
            error_log('ProvvigioneConsolidata [' . $entity->getId() . ']: ' . $e->getMessage());
        }
    }

    private function shouldRecalculate(Entity $entity): bool
    {
        if ($entity->isNew()) {
            return (bool) $entity->get('opportunityId');
        }

        $watch = [
            'itemList',
            'amount',
            'taxAmount',
            'grandTotalAmount',
            'totalPrezzoCodice',
            'prezzoCodiceIvaEsclusa',
            'prezzoCodiceIvaInclusa',
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
            'priceBookId',
            'finanziamento',
            'statoFinanziamento',
            'statoContratto',
        ];

        foreach ($watch as $field) {
            if ($field === 'itemList') {
                if ($this->isItemListReallyChanged($entity) || $this->hasItemListPrezzoCodiceChanged($entity)) {
                    return true;
                }

                continue;
            }

            if ($entity->isAttributeChanged($field)) {
                return true;
            }
        }

        return false;
    }

    private function isItemListReallyChanged(Entity $entity): bool
    {
        if (!$entity->isAttributeChanged('itemList')) {
            return false;
        }

        return json_encode($entity->get('itemList')) !== json_encode($entity->getFetched('itemList'));
    }

    private function hasItemListPrezzoCodiceChanged(Entity $entity): bool
    {
        if (!$entity->isAttributeChanged('itemList')) {
            return false;
        }

        $current = $this->extractItemListPrezzoCodice($entity->get('itemList'));
        $fetched = $this->extractItemListPrezzoCodice($entity->getFetched('itemList'));

        return json_encode($current) !== json_encode($fetched);
    }

    /**
     * @return list<array{productId: string, prezzoCodice: float|null}>
     */
    private function extractItemListPrezzoCodice(mixed $itemList): array
    {
        if (!is_array($itemList)) {
            return [];
        }

        $rows = [];

        foreach ($itemList as $item) {
            $productId = (string) ($this->itemValue($item, 'productId') ?? '');
            $prezzoCodice = $this->itemValue($item, 'prezzoCodice');

            $rows[] = [
                'productId' => $productId,
                'prezzoCodice' => $prezzoCodice === null || $prezzoCodice === ''
                    ? null
                    : round((float) $prezzoCodice, 2),
            ];
        }

        return $rows;
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
}
