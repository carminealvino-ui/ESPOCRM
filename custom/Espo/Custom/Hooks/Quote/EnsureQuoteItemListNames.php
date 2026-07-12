<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Riga articolo: Voce (name) obbligatoria. Se vuota, copia da productName o Product.
 */
class EnsureQuoteItemListNames implements BeforeSave
{
    public static int $order = 5;

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
            $name = trim((string) ($this->itemValue($item, 'name') ?? ''));

            if ($name !== '') {
                continue;
            }

            $resolved = $this->resolveItemName($item);

            if ($resolved === null) {
                continue;
            }

            $itemList[$index] = $this->itemSet($item, 'name', $resolved);
            $changed = true;
        }

        if ($changed) {
            $entity->set('itemList', $itemList);
        }
    }

    private function resolveItemName(mixed $item): ?string
    {
        $productName = trim((string) ($this->itemValue($item, 'productName') ?? ''));

        if ($productName !== '') {
            return $productName;
        }

        $productId = $this->itemValue($item, 'productId');

        if (!$productId) {
            return null;
        }

        $product = $this->entityManager->getEntityById('Product', $productId);

        if (!$product) {
            return null;
        }

        $name = trim((string) $product->get('name'));

        return $name !== '' ? $name : null;
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
