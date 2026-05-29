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

namespace Espo\Modules\Sales\Tools\SubscriptionTemplate;

use Espo\Core\Currency\ConfigDataProvider;
use Espo\Core\Currency\Converter;
use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionTemplate;
use Espo\Modules\Sales\Tools\Price\PriceProvider;
use Espo\Modules\Sales\Tools\Price\Sales\Data;
use Espo\Modules\Sales\Tools\Quote\RoundingUtil;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Subscription\IntervalUnit;
use Espo\Modules\Sales\Tools\Subscription\Util;
use Espo\Modules\Sales\Tools\SubscriptionTemplate\Prepare\PrepareData;
use Espo\ORM\EntityManager;
use RuntimeException;
use stdClass;

class PrepareService
{
    public function __construct(
        private EntityManager $entityManager,
        private PriceProvider $priceProvider,
        private ConfigDataProvider $configDataProvider,
        private Converter $converter,
        private RoundingUtil $roundingUtil,
    ) {}

    public function prepareAttributes(SubscriptionTemplate $template, PrepareData $data): stdClass
    {
        $newItems = $this->prepareItems($template, $data);

        // @todo Revise trial.

        $attributes = [
            Subscription::ATTR_TEMPLATE_ID => $template->getId(),
            'templateName' => $template->getName(),
            'billingPlanId' => $data->billingPlan->getId(),
            'billingPlanName' => $data->billingPlan->getName(),
            'startDate' => $data->startDate->toString(),
            Subscription::FIELD_END_DATE => $this->getEndDate($template, $data)?->toString() ?? null,
            OrderEntity::ATTR_ITEM_LIST => array_map(fn (SubscriptionTemplateOrderItem $it) => $it->toRaw(), $newItems),
            OrderEntity::ATTR_AMOUNT_CURRENCY => $this->getCurrency($data),
            Subscription::FIELD_STATUS => Subscription::STATUS_PAUSED,
            Subscription::FIELD_HAS_TRIAL => $template->hasTrial(),
        ];

        return (object) $attributes;
    }

    private function getEndDate(SubscriptionTemplate $template, PrepareData $data): ?Date
    {
        $trialDays = $this->getTrialDays($template);

        $date = $data->startDate->addDays($trialDays);

        $unit = $template->getTermUnit();
        $number = $template->getTermLength();

        if (!$unit || $number === null) {
            return null;
        }

        if ($unit === IntervalUnit::Year) {
            $unit = IntervalUnit::Month;
            $number *= 12;
        }

        if ($unit === IntervalUnit::Month) {
            return Util::addMonths($date, $number);
        }

        if ($unit === IntervalUnit::Week) {
            return $date->addDays($number * 7);
        }

        if ($unit === IntervalUnit::Day) {
            return $date->addDays($number);
        }

        /** @phpstan-ignore-next-line */
        throw new RuntimeException();
    }

    /**
     * @return SubscriptionTemplateOrderItem[]
     */
    private function prepareItems(SubscriptionTemplate $template, PrepareData $data): array
    {
        $newItems = [];

        foreach ($template->getItems() as $item) {
            $newItems[] = $this->prepareItem($item, $data);
        }

        return $newItems;
    }

    private function prepareItem(SubscriptionTemplateOrderItem $item, PrepareData $data): SubscriptionTemplateOrderItem
    {
        $quantity = $item->getQuantity() ?? 1.0;

        if (!$item->isFixedQuantity()) {
            $quantity *= $data->quantity;
        }

        if (!$item->allowFractionalQuantity()) {
            $quantity = round($quantity);
        }

        $productId = $item->getProductId() ?? throw new RuntimeException("No product.");

        $product = $this->entityManager->getRDBRepositoryByClass(Product::class)->getById($productId) ??
            throw new RuntimeException("Product $productId not found.");

        $pair = $this->priceProvider->get(
            $product,
            $quantity,
            data: new Data(
                interval: $data->billingPlan->getInterval()
            ),
        );

        if ($pair->getUnit()) {
            $currency = $this->getCurrency($data);

            $unitPrice = $this->convertPriceItem($pair->getUnit(), $currency);

            $amount = $unitPrice->multiply($quantity);

            $amount = $this->roundingUtil->round($amount);

            $item = $item
                ->withUnitPrice($unitPrice)
                ->withAmount($amount);
        }

        return $item
            ->withQuantity($quantity);
    }

    private function convertPriceItem(Currency $price, string $currency): Currency
    {
        if ($price->getCode() === $currency) {
            return $price;
        }

        $price = $this->converter->convert($price, $currency);

        return $this->roundingUtil->round($price);
    }

    private function getCurrency(PrepareData $data): string
    {
        return $data->currency ?? $this->configDataProvider->getDefaultCurrency();
    }

    private function getTrialDays(SubscriptionTemplate $template): ?int
    {
        return $template->hasTrial() ?
            $template->getTrialPeriodDays() : 0;
    }
}
