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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Modules\Sales\Entities\InventoryNumber;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\ReceiptOrder;
use Espo\Modules\Sales\Hooks\ReceiptOrder\SerialNumberCheck;
use Espo\ORM\EntityManager;

class ReceivedInventoryCheck
{
    public function __construct(
        private EntityManager $entityManager,
        private ValidationHelper $validationHelper,
        private SerialNumberCheck $serialNumberCheck
    ) {}

    /**
     * @throws BadRequest
     */
    public function validate(ReceiptOrder $order): void
    {
        $this->validateNotEmpty($order);
        $this->validateNumbers($order);
        $this->validateQuantity($order);
        $this->validateSerialNumbers($order);
    }

    /**
     * @throws BadRequest
     */
    private function validateQuantity(ReceiptOrder $order): void
    {
        $map1 = [];
        $map2 = [];

        $productMap = $this->getProductMap($order);

        foreach ($order->getReceivedItems() as $item) {
            $productId = $item->getProductId();

            if (!$productId) {
                continue;
            }

            $product = $productMap[$productId] ?? null;

            if (!$product) {
                continue;
            }

            if ($product->getInventoryNumberType() !== InventoryNumber::TYPE_SERIAL) {
                continue;
            }

            if ($item->getQuantity() === null) {
                continue;
            }

            if ($item->getQuantity() !== 1.0) {
                throw $this->createSerialNumberNotOne();
            }
        }

        foreach ($order->getItems() as $item) {
            $productId = $item->getProductId();

            if (
                !$productId ||
                !$item->getInventoryNumberType() ||
                !$item->getQuantityReceived()
            ) {
                continue;
            }

            $product = $productMap[$productId] ?? null;

            if (!$product) {
                continue;
            }

            if (!$product->getInventoryNumberType()) {
                continue;
            }

            $map1[$productId] ??= 0.0;
            $map1[$productId] += $item->getQuantityReceived();
        }

        foreach ($order->getReceivedItems() as $item) {
            $productId = $item->getProductId();

            if (
                !$productId ||
                !$item->getQuantity()
            ) {
                continue;
            }

            $map2[$productId] ??= 0.0;
            $map2[$productId] += $item->getQuantity();
        }

        if (count($map1) !== count($map2)) {
            throw $this->createMismatchError();
        }

        foreach ($map1 as $productId => $value1) {
            $value2 = $map2[$productId] ?? 0.0;

            if ($value1 !== $value2) {
                throw $this->createMismatchError();
            }
        }
    }

    private function createMismatchError(): BadRequest
    {
        return BadRequest::createWithBody(
            'Received quantity mismatch.',
            Body::create()
                ->withMessageTranslation('receivedQuantityMismatch', ReceiptOrder::ENTITY_TYPE)
                ->encode()
        );
    }

    /**
     * @param ReceiptOrder $order
     * @return array<string, Product>
     */
    private function getProductMap(ReceiptOrder $order): array
    {
        /** @var iterable<Product> $products */
        $products = $this->entityManager
            ->getRDBRepositoryByClass(Product::class)
            ->where(['id' => $order->getInventoryProductIds()])
            ->find();

        $map = [];

        foreach ($products as $product) {
            $map[$product->getId()] = $product;
        }

        return $map;
    }

    /**
     * @param ReceiptOrder $order
     * @return array<string, InventoryNumber>
     */
    private function getInventoryNumberMap(ReceiptOrder $order): array
    {
        /** @var iterable<InventoryNumber> $numbers */
        $numbers = $this->entityManager
            ->getRDBRepositoryByClass(InventoryNumber::class)
            ->where(['id' => $order->getInventoryNumberIds()])
            ->find();

        $map = [];

        foreach ($numbers as $number) {
            $map[$number->getId()] = $number;
        }

        return $map;
    }

    /**
     * @throws BadRequest
     */
    private function validateNotEmpty(ReceiptOrder $order): void
    {
        foreach ($order->getReceivedItems() as $item) {
            if (!$item->getInventoryNumberId()) {
                throw BadRequest::createWithBody(
                    'Received inventory number is not specified.',
                    Body::create()
                        ->withMessageTranslation('receivedInventoryNumberIsEmpty', ReceiptOrder::ENTITY_TYPE)
                        ->encode()
                );
            }
        }
    }

    /**
     * @throws BadRequest
     */
    private function validateNumbers(ReceiptOrder $order): void
    {
        $productMap = $this->getProductMap($order);
        $numberMap = $this->getInventoryNumberMap($order);

        foreach ($order->getReceivedItems() as $item) {
            $productId = $item->getProductId();
            $numberId = $item->getInventoryNumberId();

            if (!$productId) {
                throw new BadRequest('No product ID in received item.');
            }

            if (!$numberId) {
                throw new BadRequest('No inventory number ID in received item.');
            }

            $product = $productMap[$productId] ?? null;
            $number = $numberMap[$numberId] ?? null;

            if (!$product) {
                throw new BadRequest('Not existing product ID in received item.');
            }

            if (!$number) {
                throw new BadRequest('Not existing number ID in received item.');
            }

            if ($number->getProduct()->getId() !== $productId) {
                throw BadRequest::createWithBody(
                    'Inventory number does not correspond the product.',
                    Body::create()
                        ->withMessageTranslation('receivedInventoryNumberProductMismatch', ReceiptOrder::ENTITY_TYPE)
                        ->encode()
                );
            }
        }
    }

    private function createSerialNumberNotOne(): BadRequest
    {
        return BadRequest::createWithBody(
            'Quantity of a received item with a serial number should be one.',
            Body::create()
                ->withMessageTranslation('receivedSerialNumberNotOne', ReceiptOrder::ENTITY_TYPE)
                ->encode()
        );
    }

    /**
     * @throws BadRequest
     */
    private function validateSerialNumbers(ReceiptOrder $order): void
    {
        if (!$this->validationHelper->toValidateSerialNumbers($order)) {
            return;
        }

        $numbers = $this->serialNumberCheck->findInStock($order);

        if ($numbers === []) {
            return;
        }

        $names = array_map(fn ($item) => $item->getName(), $numbers);

        throw BadRequest::createWithBody(
            'Serial number is already in stock.',
            Body::create()
                ->withMessageTranslation('serialNumberAlreadyInStock', ReceiptOrder::ENTITY_TYPE, [
                    'numbers' => implode("\n", $names),
                ])
                ->encode()
        );
    }
}
