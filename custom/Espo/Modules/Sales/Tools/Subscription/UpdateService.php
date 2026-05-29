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

namespace Espo\Modules\Sales\Tools\Subscription;

use Espo\Core\Currency\Converter;
use Espo\Core\Field\Currency;
use Espo\Core\Utils\Util as CoreUtil;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\Subscription;
use Espo\Modules\Sales\Entities\SubscriptionPeriod;
use Espo\Modules\Sales\Tools\Quote\BeforeSaveProcessor;
use Espo\Modules\Sales\Tools\Subscription\UpdateAmounts\Data;
use Espo\Modules\Sales\Tools\Subscription\UpdateAmounts\DiffExtractor;
use Espo\ORM\Name\Attribute;
use stdClass;

class UpdateService
{
    public function __construct(
        private PeriodRepository $periodRepository,
        private SubscriptionRepository $subscriptionRepository,
        private CreateInvoiceForPeriod $createInvoiceForPeriod,
        private Converter $converter,
        private BeforeSaveProcessor $beforeSaveProcessor,
        private DiffExtractor $diffExtractor,
    ) {}

    public function calculateAttributes(Subscription $subscription, Data $data): stdClass
    {
        $toInvoice = Currency::create(0.0, $data->currency);
        $toCredit = Currency::create(0.0, $subscription->getAmountCurrency());
        $net = $toInvoice;

        if ($this->isSame($subscription, $data)) {
            return $this->createResult(
                toInvoice: $toInvoice,
                toCredit: $toCredit,
                net: $net,
            );
        }

        $periods = $this->preparePeriods($subscription, $data);

        foreach ($periods as $period) {
            [$toInvoice, $toCredit] = $this->calculateItem(
                period: $period,
                toInvoice: $toInvoice,
                toCredit: $toCredit,
                data: $data,
            );
        }

        $toCreditConverted = $this->converter->convert($toCredit, $toInvoice->getCode());

        $net = $toInvoice->subtract($toCreditConverted);

        return $this->createResult(
            toInvoice: $toInvoice,
            toCredit: $toCredit,
            net: $net,
        );
    }

    public function isSame(Subscription $subscription, Data $data): bool
    {
        $same = true;

        if ($subscription->getAmountCurrency() !== $data->currency) {
            return false;
        }

        if (count($data->items) !== count($subscription->getItems())) {
            return false;
        }

        foreach ($subscription->getItems() as $i => $item) {
            $otherItem = $data->items[$i];

            if (
                $otherItem->getUnitPrice()?->getAmount() !== $item->getUnitPrice()?->getAmount() ||
                $otherItem->getQuantity() !== $item->getQuantity() ||
                $otherItem->getProductId() !== $item->getProductId()
            ) {
                $same = false;
            }
        }

        return $same;
    }

    private function createResult(Currency $toInvoice, Currency $toCredit, Currency $net): stdClass
    {
        return (object) [
            'invoiceGrandTotalAmount' => $toInvoice->getAmount(),
            'invoiceGrandTotalAmountCurrency' => $toInvoice->getCode(),
            'creditNoteGrandTotalAmount' => $toCredit->getAmount(),
            'creditNoteGrandTotalAmountCurrency' => $toCredit->getCode(),
            'netAmount' => $net->getAmount(),
            'netAmountCurrency' => $net->getCode(),
        ];
    }

    /**
     * @return SubscriptionPeriod[]
     */
    public function preparePeriods(Subscription $subscription, Data $data): array
    {
        $collection = $this->periodRepository
            ->findBilledAfterDateForSubscription($subscription->getId(), $data->date);

        $periods = [];

        foreach ($collection as $period) {
            $copy = $this->copyPeriod($period);

            if ($copy->getStartDate()->isLessThan($data->date)) {
                $copy->setStartDate($data->date);
            }

            $periods[] = $copy;
        }

        return $periods;
    }

    private function copyPeriod(SubscriptionPeriod $period): SubscriptionPeriod
    {
        $copy = $this->periodRepository->getNew();

        $copy->setMultiple($period->getValueMap());
        $copy->setMultiple([Attribute::ID => null]);

        return $copy;
    }

    private function prepareUpdatedSubscription(Subscription $subscription, Data $data): Subscription
    {
        $newSubscription = $this->subscriptionRepository->getNew();

        $newSubscription->setMultiple($subscription->getValueMap());

        $newSubscription->setItems([]);
        $newSubscription->setAmountCurrency($data->currency);

        $newSubscription->setMultiple([Attribute::ID => CoreUtil::generateId()]);
        $newSubscription->setItems($data->items);

        return $newSubscription;
    }

    /**
     * @return array{Currency, Currency}
     */
    private function calculateItem(
        SubscriptionPeriod $period,
        Currency $toInvoice,
        Currency $toCredit,
        Data $data,
    ): array {

        [$invoiceToCredit, $invoiceToCharge] = $this->prepareInvoices($period, $data);

        $this->beforeSaveProcessor->calculateItems($invoiceToCredit);
        $this->beforeSaveProcessor->calculateItems($invoiceToCharge);

        if ($invoiceToCharge->getGrandTotalAmount()) {
            $toInvoice = $toInvoice->add($invoiceToCharge->getGrandTotalAmount());
        }

        if ($invoiceToCredit->getGrandTotalAmount()) {
            $toCredit = $toCredit->add($invoiceToCredit->getGrandTotalAmount());
        }

        return [$toInvoice, $toCredit];
    }

    /**
     * @return array{Invoice, Invoice} To-Credit and To-Charge invoices.
     */
    public function prepareInvoices(SubscriptionPeriod $period, Data $data): array
    {
        $credit = $this->createInvoiceForPeriod->prepareInvoice($period);

        $newSubscription = $this->prepareUpdatedSubscription($period->getSubscription(), $data);
        $period->setSubscription($newSubscription);

        $charge = $this->createInvoiceForPeriod->prepareInvoice($period);

        $this->processDiff($credit, $charge);

        return [$credit, $charge];
    }

    private function processDiff(Invoice $credit, Invoice $charge): void
    {
        $diff = $this->diffExtractor->extract($credit, $charge);

        if (!$diff) {
            return;
        }

        if ($diff->isCredit) {
            $charge->setItems([]);
            $credit->setItems($diff->items);
        } else {
            $credit->setItems([]);
            $charge->setItems($diff->items);
        }
    }
}
