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

namespace Espo\Modules\Sales\Entities;

use Espo\Core\Field\Address;
use Espo\Core\Field\Currency;
use Espo\Core\Field\Date;
use Espo\Core\Field\Link;
use Espo\Core\Field\LinkMultiple;
use Espo\Core\FieldProcessing\Loader\Params as FieldLoaderParams;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\Sales\Classes\FieldLoaders\Invoice\AmountDue as AmountDueLoader;
use Espo\Modules\Sales\Tools\Invoice\InvoiceOrderItem;
use Espo\Modules\Sales\Tools\Invoice\ShippingCostBreakdownUtil;
use Espo\Modules\Sales\Tools\PaymentTerms\PaymentTermsHavingOrder;
use Espo\Modules\Sales\Tools\PaymentTerms\PaymentTermsHavingOrderTrait;
use Espo\Modules\Sales\Tools\Quote\RoundingHavingOrderTrait;
use Espo\Modules\Sales\Tools\Quote\ShippingCostBreakdownItem;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntity;
use Espo\Modules\Sales\Tools\Sales\HavingCurrencyRateEntityTrait;
use Espo\Modules\Sales\Tools\Sales\HavingDiscountOrder;
use Espo\Modules\Sales\Tools\Sales\HavingDiscountOrderTrait;
use Espo\Modules\Sales\Tools\Sales\HavingLocalAmountsTrait;
use Espo\Modules\Sales\Tools\Sales\HavingTaxInclusiveOrder;
use Espo\Modules\Sales\Tools\Sales\HavingTaxInclusiveOrderTrait;
use Espo\Modules\Sales\Tools\Sales\IssuableOrder;
use Espo\Modules\Sales\Tools\Sales\IssuableOrderTrait;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderItem;
use Espo\Modules\Sales\Tools\Sales\OrderUtil;
use Espo\Modules\Sales\Tools\Tax\RoundingHavingOrder;
use Espo\Modules\Sales\Tools\Tax\TaxableOrder;
use Espo\Modules\Sales\Tools\Tax\TaxableOrderTrait;
use Espo\ORM\EntityCollection;

/**
 * @extends OrderEntity<InvoiceOrderItem>
 */
class Invoice extends OrderEntity
    implements TaxableOrder, RoundingHavingOrder, IssuableOrder, HavingDiscountOrder, HavingTaxInclusiveOrder,
        HavingCurrencyRateEntity, PaymentTermsHavingOrder
{
    use TaxableOrderTrait;
    use RoundingHavingOrderTrait;
    use IssuableOrderTrait;
    use HavingDiscountOrderTrait;
    use HavingTaxInclusiveOrderTrait;
    use HavingCurrencyRateEntityTrait;
    use HavingLocalAmountsTrait;
    use PaymentTermsHavingOrderTrait;

    public const ENTITY_TYPE = 'Invoice';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PAID = 'Paid';
    public const STATUS_ISSUED = 'Issued';
    public const STATUS_CANCELED = 'Canceled';

    public const TYPE_INVOICE = 'Invoice';
    public const TYPE_DEBIT_NOTE = 'Debit Note';

    public const FIELD_STATUS = 'status';
    public const FIELD_TYPE = 'type';
    public const FIELD_DATE_DUE = 'dateDue';
    public const FIELD_AMOUNT_DUE = 'amountDue';
    public const FIELD_DATE_INVOICED = 'dateInvoiced';
    public const FIELD_PRECEDING_INVOICE = 'precedingInvoice';
    public const FIELD_PAYMENT_METHOD = 'paymentMethod';
    public const FIELD_OVERDUE_DAYS = 'overdueDays';
    public const FIELD_NUMBER_DEBIT_NOTE_A = 'numberDebitNoteA';

    public const LINK_SUBSCRIPTION_PERIODS = 'subscriptionPeriods';
    public const LINK_SUBSCRIPTION_UPDATES = 'subscriptionUpdates';

    public function getType(): string
    {
        return $this->get(self::FIELD_TYPE);
    }

    public function setType(string $type): self
    {
        return $this->set(self::FIELD_TYPE, $type);
    }

    public function isDateInvoicedChanged(): bool
    {
        return $this->isAttributeChanged(self::FIELD_DATE_INVOICED);
    }

    public function getDateInvoiced(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject(self::FIELD_DATE_INVOICED);
    }

    public function isDateDueChanged(): bool
    {
        return $this->isAttributeChanged(self::FIELD_DATE_DUE);
    }

    public function getDateDue(): ?Date
    {
        /** @var ?Date */
        return $this->getValueObject('dateDue');
    }

    public function setDateDue(?Date $date): self
    {
        $this->setValueObject('dateDue', $date);

        return $this;
    }

    public function setDateInvoiced(?Date $date): self
    {
        $this->setValueObject(self::FIELD_DATE_INVOICED, $date);

        return $this;
    }

    public function getAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject('amount');
    }

    public function getBillingAddress(): Address
    {
        /** @var Address */
        return $this->getValueObject('billingAddress');
    }

    public function getShippingAddress(): Address
    {
        /** @var Address */
        return $this->getValueObject('shippingAddress');
    }

    public function getBuyerReference(): ?string
    {
        return $this->get('buyerReference');
    }

    public function setBuyerReference(?string $reference): self
    {
        return $this->set('buyerReference', $reference);
    }

    public function getPurchaseOrderReference(): ?string
    {
        return $this->get('purchaseOrderReference');
    }

    public function setPurchaseOrderReference(?string $reference): self
    {
        return $this->set('purchaseOrderReference', $reference);
    }

    public function getNote(): ?string
    {
        return $this->get('note');
    }

    public function getGrandTotalAmount(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject('grandTotalAmount');
    }

    public function getOpportunity(): ?Opportunity
    {
        /** @var ?Opportunity */
        return $this->relations->getOne('opportunity');
    }

    public function getQuote(): ?Quote
    {
        /** @var ?Quote */
        return $this->relations->getOne('quote');
    }

    public function getSalesOrder(): ?SalesOrder
    {
        /** @var ?SalesOrder */
        return $this->relations->getOne('salesOrder');
    }

    public function getBillingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne('billingContact');
    }

    public function setBillingContact(?Contact $contact): self
    {
        return $this->setRelatedLinkOrEntity('billingContact', $contact);
    }

    public function getShippingContact(): ?Contact
    {
        /** @var ?Contact */
        return $this->relations->getOne('shippingContact');
    }

    public function setTax(?Tax $tax): self
    {
        return $this->setRelatedLinkOrEntity('tax', $tax);
    }

    public function getTax(): ?Tax
    {
        /** @var ?Tax */
        return $this->relations->getOne('tax');
    }

    public function getShippingProvider(): ?ShippingProvider
    {
        /** @var ?ShippingProvider */
        return $this->relations->getOne('shippingProvider');
    }

    public function getPriceBook(): ?PriceBook
    {
        /** @var ?PriceBook */
        return $this->relations->getOne('priceBook');
    }

    public function setPriceBook(?PriceBook $priceBook): self
    {
        return $this->setRelatedLinkOrEntity('priceBook', $priceBook);
    }

    public function getShippingCost(): ?Currency
    {
        /** @var ?Currency */
        return $this->getValueObject(OrderEntity::FIELD_SHIPPING_COST);
    }

    public function getPaymentMethods(): LinkMultiple
    {
        /** @var LinkMultiple */
        return $this->getValueObject('paymentMethods');
    }

    /**
     * @return EntityCollection<PaymentMethod>
     */
    public function getPaymentMethodCollection(): EntityCollection
    {
        /** @var EntityCollection<PaymentMethod> */
        return $this->relations->getMany('paymentMethods');
    }

    public function setPaymentMethods(LinkMultiple $methods): self
    {
        $this->setValueObject('paymentMethods', $methods);

        return $this;
    }

    /**
     * @return ShippingCostBreakdownItem[]
     */
    public function getShippingCostBreakdown(): array
    {
        return ShippingCostBreakdownUtil::breakdown($this);
    }

    public function getAccountEntity(): ?Account
    {
        /** @var ?Account */
        return $this->relations->getOne('account');
    }

    /**
     * @return InvoiceOrderItem[]
     */
    public function getItems(): array
    {
        return array_map(function (OrderItem $item) {
            return InvoiceOrderItem::fromRaw($item->toRaw());
        }, parent::getItems());
    }

    public function setBillingAddress(?Address $address): self
    {
        return $this->setValueObject('billingAddress', $address);
    }

    public function getAmountDue(): ?Currency
    {
        if ($this->isNew()) {
            return $this->getGrandTotalAmount();
        }

        $loader = new AmountDueLoader($this->entityManager);

        $loader->process($this, FieldLoaderParams::create());

        $raw = $this->get(self::FIELD_AMOUNT_DUE);
        $rawCurrency = $this->get(self::FIELD_AMOUNT_DUE . 'Currency');

        return $raw && $rawCurrency ?
            Currency::create($raw, $rawCurrency) : null;
    }

    public function setNote(?string $note): static
    {
        return $this->set('note', $note);
    }

    public function getPaymentMethod(): ?Link
    {
        if (!$this->has(self::FIELD_PAYMENT_METHOD . 'Id')) {
            $this->loadPaymentMethod();
        }

        /** @var ?Link */
        return $this->getValueObject(self::FIELD_PAYMENT_METHOD);
    }

    private function loadPaymentMethod(): void
    {
        // @todo Use setInContainerNotWritten when v9.1 is min supported.

        $map = OrderUtil::obtainPrimaryPaymentMethodAttributes($this);

        foreach ($map as $k => $v) {
            $this->set($k, $v);
            $this->setFetched($k, $v);
        }
    }

    public function setShippingCost(?Currency $cost): self
    {
        return $this->setValueObject(OrderEntity::FIELD_SHIPPING_COST, $cost);
    }

    public function setPrecedingInvoice(Link|Invoice|null $invoice): self
    {
        return $this->setRelatedLinkOrEntity(self::FIELD_PRECEDING_INVOICE, $invoice);
    }

    public function getPrecedingInvoice(): ?Invoice
    {
        /** @var ?Invoice */
        return $this->relations->getOne(self::FIELD_PRECEDING_INVOICE);
    }

    public function isPaymentTermsToCalculate(): bool
    {
        return $this->isNew() ||
            $this->isPaymentTermsProfileChanged() ||
            $this->isItemListChanged() ||
            $this->isCurrencyRateChanged() ||
            $this->isAttributeChanged(self::FIELD_DATE_INVOICED) ||
            $this->isAttributeChanged(self::FIELD_DATE_DUE);
    }
}
