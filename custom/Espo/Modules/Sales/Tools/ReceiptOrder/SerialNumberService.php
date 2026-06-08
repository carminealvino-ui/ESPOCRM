<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 11af5a568c1a72dce4e164257d1a0207
 ************************************************************************************/

namespace Espo\Modules\Sales\Tools\ReceiptOrder;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\Date;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Entities\ReceiptOrderItem;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use RuntimeException;

class SerialNumberService
{
    private const DEFAULT_IMPORT_LIMIT = 300;

    public function __construct(
        private Acl $acl,
        private EntityManager $entityManager,
        private Config $config,
        private Metadata $metadata,
        private DateTime $dateTime
    ) {}

    /**
     * @param string[] $items
     * @return void
     * @throws NotFound
     * @throws Forbidden
     * @throws Conflict
     */
    public function receiveSerialNumbers(string $id, string $productId, array $items): void
    {
        $items = array_map(fn ($item) => trim($item), $items);

        $order = $this->fetchOrder($id);
        $product = $this->fetchProduct($productId);

        $this->checkStatus($order);
        $this->checkMaxSize($items);
        $this->checkDoesNotExist($items, $productId);

        $this->entityManager
            ->getTransactionManager()
            ->run(fn () => $this->process($order, $product, $items));
    }

    private function getImportLimit(): int
    {
        return $this->config->get('receiptOrderSerialNumberImportMaxSize') ?? self::DEFAULT_IMPORT_LIMIT;
    }

    /**
     * @param string[] $items
     * @throws Conflict
     */
    private function checkDoesNotExist(array $items, string $productId): void
    {
        /** @var iterable<InventoryNumber> $collection */
        $collection = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->select('name')
            ->where([
                'name' => $items,
                'productId' => $productId,
            ])
            ->find();

        $existing = [];

        foreach ($collection as $entity) {
            $existing[] = $entity->getName();
        }

        $existing = array_slice($existing, 0, 20);

        if ($existing === []) {
            return;
        }

        throw Conflict::createWithBody(
            'Serial numbers already exist.',
            Body::create()
                ->withMessageTranslation('serialNumbersExists', ReceiptOrder::ENTITY_TYPE, [
                    'numbers' => implode("\n", $existing),
                ])
                ->encode()
        );
    }

    /**
     * @param string[] $items
     * @return void
     * @throws Forbidden
     */
    public function checkMaxSize(array $items): void
    {
        if (count($items) > $this->getImportLimit()) {
            throw Forbidden::createWithBody(
                'Serial number import exceeded.',
                Body::create()
                    ->withMessageTranslation('serialNumberImportLimitExceeded', ReceiptOrder::ENTITY_TYPE, [
                        'maxSize' => (string) $this->getImportLimit(),
                        'count' => (string) count($items),
                    ])
                    ->encode()
            );
        }
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function fetchOrder(string $id): ReceiptOrder
    {
        $order = $this->entityManager
            ->getRDBRepositoryByClass(ReceiptOrder::class)
            ->getById($id);

        if (!$order) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityEdit($order)) {
            throw new Forbidden("No edit access to receipt order.");
        }

        $order->loadItemListField();
        $order->loadReceivedItemListField();

        return $order;
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function fetchProduct(string $id): Product
    {
        $product = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->getById($id);

        if (!$product) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($product)) {
            throw new Forbidden("No access to product.");
        }

        return $product;
    }

    /**
     * @throws Forbidden
     */
    public function checkStatus(ReceiptOrder $order): void
    {
        if (
            in_array($order->getStatus(), array_merge(
                $this->metadata->get('scopes.ReceiptOrder.doneStatusList') ?? [],
                $this->metadata->get('scopes.ReceiptOrder.canceledStatusList') ?? [],
            ))
        ) {
            throw new Forbidden("Order has status incompatible with import.");
        }
    }

    /**
     * @param string[] $items
     * @throws Forbidden
     * @throws NotFound
     */
    private function process(ReceiptOrder $order, Product $product, array $items): void
    {
        $orderItem = $this->fetchOrderItem($order, $product);

        $quantityReceived = $orderItem->getQuantityReceived() ?? 0.0;
        $newItems = [];

        foreach ($items as $name) {
            $number = $this->createNumber($name, $product);

            $newItems[] = new OrderItem(
                name: $product->getName(),
                productId: $product->getId(),
                inventoryNumberId: $number->getId(),
                quantity: 1.0,
            );

            $quantityReceived ++;
        }

        $this->addNewNumbers($order, $newItems);
        $this->updateQuantityReceived($order, $orderItem, $quantityReceived);

        $this->entityManager->saveEntity($order);
    }

    public function createNumber(string $name, Product $product): InventoryNumber
    {
        $number = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->getNew();

        $number
            ->setName($name)
            ->setProductId($product->getId())
            ->setType(InventoryNumber::TYPE_SERIAL);

        if ($product->getExpirationDays() !== null) {
            $expirationDate = Date::createToday($this->dateTime->getTimezone())
                ->addDays($product->getExpirationDays());

            $number->setExpirationDate($expirationDate);
        }

        $this->entityManager->saveEntity($number);

        return $number;
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function fetchOrderItem(ReceiptOrder $order, Product $product): ReceiptOrderItem
    {
        $orderItem = array_values(
            array_filter(
                $order->getItems(),
                fn ($item) => $item->getProductId() === $product->getId()
            )
        )[0] ?? null;

        if (!$orderItem) {
            throw new Forbidden("Product is not in order.");
        }

        $this->entityManager
            ->getRDBRepositoryByClass(ReceiptOrderItem::class)
            ->forUpdate()
            ->sth()
            ->select(Attribute::ID)
            ->where([Attribute::ID=> $orderItem->getId()])
            ->findOne();

        $orderItem = $this->entityManager
            ->getRDBRepositoryByClass(ReceiptOrderItem::class)
            ->where([Attribute::ID => $orderItem->getId()])
            ->findOne();

        if (!$orderItem) {
            throw new NotFound("Order item not found.");
        }

        return $orderItem;
    }

    /**
     * @param OrderItem[] $newItems
     */
    private function addNewNumbers(ReceiptOrder $order, array $newItems): void
    {
        $items = array_merge($order->getReceivedItems(), $newItems);

        $order->setReceivedItems($items);
    }

    private function updateQuantityReceived(
        ReceiptOrder $order,
        ReceiptOrderItem $orderItem,
        float $quantityReceived
    ): void {

        $items = $order->getItems();

        $index = null;

        foreach ($items as $i => $it) {
            if ($it->getId() === $orderItem->getId()) {
                $index = $i;
            }
        }

        if ($index === null) {
            throw new RuntimeException("Item not found.");
        }

        $items[$index] = $items[$index]->withQuantityReceived($quantityReceived);

        $order->setItems($items);
    }
}
