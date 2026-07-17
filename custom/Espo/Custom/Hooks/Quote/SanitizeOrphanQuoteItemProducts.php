<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Riga articolo con productId puntato a un Product cancellato: pulisce il link
 * prima del salvataggio (evita "Product X does not exist").
 */
class SanitizeOrphanQuoteItemProducts implements BeforeSave
{
    public static int $order = 1;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        $itemList = $entity->get('itemList');

        if (!is_array($itemList) || $itemList === []) {
            return;
        }

        $changed = false;

        foreach ($itemList as $index => $item) {
            $productId = $this->itemValue($item, 'productId');

            if (!$productId) {
                continue;
            }

            $product = $this->entityManager->getEntityById('Product', $productId);

            if ($product) {
                continue;
            }

            $itemList[$index] = $this->clearProductLink($item);
            $changed = true;

            error_log(sprintf(
                'SanitizeOrphanQuoteItemProducts [%s]: rimosso productId orfano %s da riga articolo',
                $entity->getId() ?? 'new',
                $productId
            ));
        }

        if ($changed) {
            $entity->set('itemList', $itemList);
        }
    }

    private function clearProductLink(mixed $item): array|object
    {
        foreach (['productId', 'productName'] as $key) {
            $item = $this->itemSet($item, $key, null);
        }

        return $item;
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
}
