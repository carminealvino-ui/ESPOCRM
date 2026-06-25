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

namespace Espo\Modules\Sales\Tools\Invoice\EInvoice;

use Einvoicing\AllowanceOrCharge;
use Einvoicing\Delivery;
use Einvoicing\Identifier;
use Einvoicing\Invoice as EInvoice;
use Einvoicing\InvoiceLine;
use Einvoicing\InvoiceReference;
use Einvoicing\Party;
use Einvoicing\Presets\AbstractPreset;
use Einvoicing\Presets\CiusAtGov;
use Einvoicing\Presets\CiusAtNat;
use Einvoicing\Presets\CiusEsFace;
use Einvoicing\Presets\CiusIt;
use Einvoicing\Presets\CiusRo;
use Einvoicing\Presets\Nlcius;
use Einvoicing\Presets\Peppol;
use Espo\Core\Currency\CalculatorUtil;
use Espo\Core\Field\Address;
use Espo\Core\Field\Date;
use Espo\Core\Name\Field;
use Espo\Entities\AddressCountry;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Entities\InvoiceItem;
use Espo\Modules\Sales\Entities\Product;
use Espo\Modules\Sales\Entities\TaxCode;
use Espo\Modules\Sales\Entities\TaxLineItem;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnknownFormat;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnsupportedTaxCombination;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Presets\XRechnung;
use Espo\Modules\Sales\Tools\Invoice\InvoiceOrderItem;
use Espo\Modules\Sales\Tools\Quote\RoundingUtil;
use Espo\Modules\Sales\Tools\Sales\ConfigDataProvider;
use Espo\Modules\Sales\Tools\Tax\TaxCodeType;
use Espo\ORM\EntityManager;
use DateTime;
use Exception;
use RuntimeException;

class Helper
{
    /**
     * @var array<string, class-string<AbstractPreset>>
     */
    private array $formatPresetMap = [
        'Peppol' => Peppol::class,
        'CiusAtGov' => CiusAtGov::class,
        'CiusAtNat' => CiusAtNat::class,
        'CiusEsFace' => CiusEsFace::class,
        'CiusIt' => CiusIt::class,
        'CiusRo' => CiusRo::class,
        'Nlcius' => Nlcius::class,
        'XRechnung' => XRechnung::class,
    ];

    public function __construct(
        private EntityManager $entityManager,
        private ConfigProvider $eInvoiceConfig,
        private ConfigDataProvider $configDataProvider,
        private RoundingUtil $roundingUtil,
        private EuTaxMappingProcessor $taxMappingProcessor,
    ) {}

    /**
     * @throws UnknownFormat
     */
    public function prepareNew(string $format): EInvoice
    {
        $preset = $this->formatPresetMap[$format] ?? null;

        if ($preset === null) {
            throw new UnknownFormat();
        }

        return new EInvoice($preset);
    }

    /**
     * @throws UnsupportedTaxCombination
     */
    public function addLines(Invoice|CreditNote $invoice, EInvoice $eInvoice): void
    {
        foreach ($invoice->getItems() as $item) {
            $line = new InvoiceLine();

            $line
                ->setName($item->getName())
                ->setNote($item->getDescription())
                ->setPrice($item->getUnitPrice()?->getAmount() ?? 0.0)
                ->setQuantity($item->getQuantity() ?? 0.0);

            if ($item->getPeriodStartDate() && $item->getPeriodEndDate()) {
                $line->setPeriodStartDate($this->convertDate($item->getPeriodStartDate()));
                $line->setPeriodEndDate($this->convertDate($item->getPeriodEndDate()));
            }

            $this->setLineTax($invoice, $item, $line);

            $eInvoice->addLine($line);
        }
    }

    public function addShippingItems(Invoice|CreditNote $invoice, EInvoice $eInvoice): void
    {
        if (!$invoice->getShippingCost()) {
            return;
        }

        if (!$this->configDataProvider->isTaxCodesEnabled()) {
            $this->addShippingItemsNoTaxCodes($invoice, $eInvoice);

            return;
        }

        foreach ($this->getTaxLineShippingItems($invoice) as $taxLineItem) {
            $rate = $taxLineItem->getTaxCode()->getRate();

            $change = (new AllowanceOrCharge())
                ->setReasonCode('SAA');

            if ($taxLineItem->getTaxCode()->getType() === TaxCodeType::Percentage) {
                $change->setAmount($taxLineItem->getBaseAmount()->getAmount());

                // @todo
                $category = $rate ? 'S' : 'Z';

                $change
                    ->setVatCategory($category)
                    ->setVatRate((float) $rate);
            } else {
                $amount = $taxLineItem->getAmount()->add($taxLineItem->getBaseAmount());

                $change
                    ->setAmount($amount->getAmount());
            }

            $eInvoice->addCharge($change);
        }
    }

    public function prepareSeller(): Party
    {
        $seller = new Party();

        $seller
            ->setName($this->eInvoiceConfig->getSellerCompanyName())
            ->setVatNumber($this->eInvoiceConfig->getSellerVatNumber());

        if ($this->eInvoiceConfig->getSellerElectronicAddressIdentifier()) {
            $seller->setElectronicAddress(
                new Identifier(
                    $this->eInvoiceConfig->getSellerElectronicAddressIdentifier(),
                    $this->eInvoiceConfig->getSellerElectronicAddressScheme()
                )
            );
        }

        if ($this->eInvoiceConfig->getSellerTaxRegistrationIdentifier()) {
            $seller->setTaxRegistrationId(
                new Identifier(
                    $this->eInvoiceConfig->getSellerTaxRegistrationIdentifier(),
                    $this->eInvoiceConfig->getSellerTaxRegistrationScheme()
                )
            );
        }

        $address = $this->eInvoiceConfig->getSellerAddress();

        $seller
            ->setCity($address->getCity())
            ->setPostalCode($address->getPostalCode())
            ->setSubdivision($address->getState());

        if ($address->getStreet()) {
            $lines = explode("\n", $address->getStreet());
            $lines = array_map(fn ($it) => trim($it), $lines);

            $seller->setAddress($lines);
        }

        if ($address->getCountry()) {
            $seller->setCountry($this->getCountryCode($address->getCountry()));
        }

        $seller
            ->setContactEmail($this->eInvoiceConfig->getSellerContactEmailAddress())
            ->setContactPhone($this->eInvoiceConfig->getSellerContactPhoneNumber())
            ->setContactName($this->eInvoiceConfig->getSellerContactName());

        return $seller;
    }

    public function prepareBuyer(Account $account, Invoice|CreditNote $invoice): Party
    {
        $buyer = new Party();

        $buyer->setName($account->getName());

        if ($invoice instanceof CreditNote) {
            if ($invoice->getInvoice()) {
                $this->addAddress($buyer, $invoice->getInvoice()->getBillingAddress());
            } else {
                $this->addAddress($buyer, $account->getBillingAddress());
            }
        } else {
            $this->addAddress($buyer, $invoice->getBillingAddress());
        }

        $identifier = $account->get('electronicAddressIdentifier');
        $scheme = $account->get('electronicAddressScheme');
        $vatNumber = $account->get('taxNumber');

        if ($identifier) {
            $identifier = new Identifier($identifier, $scheme);

            $buyer->setElectronicAddress($identifier);
        }

        if ($vatNumber) {
            $buyer->setVatNumber($vatNumber);
        }

        return $buyer;
    }

    public function prepareDelivery(Invoice $invoice): Delivery
    {
        $delivery = new Delivery();

        $this->addAddress($delivery, $invoice->getShippingAddress());

        return $delivery;
    }

    private function convertDate(Date $date): DateTime
    {
        try {
            return new DateTime($date->toString());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function getCountryCode(string $country): ?string
    {
        $entity = $this->entityManager
            ->getRDBRepositoryByClass(AddressCountry::class)
            ->where([Field::NAME => $country])
            ->findOne();

        return $entity?->getCode();
    }

    private function addAddress(Party|Delivery $party, Address $address): void
    {
        if ($address->getStreet()) {
            $lines = explode("\n", $address->getStreet());
            $lines = array_map(fn($it) => trim($it), $lines);

            $party->setAddress($lines);
        }

        if ($address->getCity()) {
            $party->setCity($address->getCity());
        }

        if ($address->getPostalCode()) {
            $party->setPostalCode($address->getPostalCode());
        }

        if ($address->getState()) {
            $party->setSubdivision($address->getState());
        }

        if ($address->getCountry()) {
            $countryCode = $this->getCountryCode($address->getCountry());

            if ($countryCode) {
                $party->setCountry($countryCode);
            }
        }
    }

    /**
     * @throws UnsupportedTaxCombination
     */
    private function setLineTax(CreditNote|Invoice $invoice, InvoiceOrderItem $item, InvoiceLine $line): void
    {
        if (!$this->configDataProvider->isTaxCodesEnabled()) {
            $this->setItemTaxNoTaxCodes($item, $line);

            return;
        }

        $code = $invoice->getAmountCurrency() ?? 'USD';
        $quantity = (string) ($item->getQuantity() ?? 1);

        $price = $item->getUnitPriceNet() ?? '0';

        $taxLineItems = $this->getTaxLineItems($invoice, $item);

        foreach ($taxLineItems as $i => $taxLineItem) {
            if ($i === count($taxLineItems) - 1) {
                $this->setLineTaxLast($taxLineItem, $line, $item->getProductId());

                break;
            }

            // @todo Check.
            // @todo Test quantity > 1.

            $taxAmount = $taxLineItem->getAmount()->getAmountAsString();

            if (CalculatorUtil::compare($quantity, '0') !== 0) {
                $unitTaxAmount = CalculatorUtil::divide($taxAmount, $quantity);

                $unitTaxAmount = $this->roundingUtil->roundAmount($unitTaxAmount, $code);
            } else {
                $unitTaxAmount = $taxAmount;
            }

            $price = CalculatorUtil::add($price, $unitTaxAmount);
        }

        $line->setPrice((float) $price);
    }

    private function setItemTaxNoTaxCodes(InvoiceOrderItem $item, InvoiceLine $line): void
    {
        $vatRate = $item->get(InvoiceItem::FIELD_TAX_RATE) ?? 0.0;

        $line->setVatRate($vatRate);

        if ($vatRate === 0.0) {
            $line->setVatCategory('Z');
        }
    }

    /**
     * @return TaxLineItem[]
     */
    private function getTaxLineItems(CreditNote|Invoice $invoice, InvoiceOrderItem $item): array
    {
        $taxLineItems = iterator_to_array($invoice->getTaxLineItemCollection());

        $taxLineItems = array_filter($taxLineItems, function (TaxLineItem $it) use ($item) {
            return $it->getItemLink()?->getId() === $item->getId();
        });

        return array_values($taxLineItems);
    }

    /**
     * @return TaxLineItem[]
     */
    private function getTaxLineShippingItems(CreditNote|Invoice $invoice): array
    {
        $taxLineItems = iterator_to_array($invoice->getTaxLineItemCollection());

        $taxLineItems = array_filter($taxLineItems, function (TaxLineItem $it) {
            return $it->getComponent() === TaxLineItem::COMPONENT_SHIPPING;
        });

        return array_values($taxLineItems);
    }

    private function addShippingItemsNoTaxCodes(CreditNote|Invoice $invoice, EInvoice $eInvoice): void
    {
        foreach ($invoice->getShippingCostBreakdown() as $item) {
            $vatRate = $item->getTaxRate();

            $change = (new AllowanceOrCharge())
                ->setReasonCode('SAA')
                ->setAmount($item->getAmount()->getAmount())
                ->setVatRate($vatRate);

            if ($vatRate === 0.0) {
                $change->setVatCategory('Z');
            }

            $eInvoice->addCharge($change);
        }
    }

    public function addRounding(CreditNote|Invoice $invoice, EInvoice $eInvoice): void
    {
        if ($invoice->getRoundingAmount()) {
            $eInvoice->setRoundingAmount($invoice->getRoundingAmount()->getAmount());
        }

        $total = $invoice->getGrandTotalAmount()?->getAmount() ?? 0.0;

        $diff = $total - $eInvoice->getTotals()->payableAmount;

        $rounding = $eInvoice->getTotals()->roundingAmount + $diff;

        $eInvoice->setRoundingAmount($rounding);
    }

    public function addInvoiceReference(EInvoice $eInvoice, Invoice $precedingInvoice): void
    {
        $invoiceReference = $this->prepareInvoiceReference($precedingInvoice);

        $eInvoice->addPrecedingInvoiceReference($invoiceReference);
    }

    private function prepareInvoiceReference(Invoice $precedingInvoice): InvoiceReference
    {
        if (!$precedingInvoice->getNumber()) {
            throw new RuntimeException("No number in referenced invoice.");
        }

        $reference = new InvoiceReference($precedingInvoice->getNumber());

        if ($precedingInvoice->getDateInvoiced()) {
            $date = $this->convertDate($precedingInvoice->getDateInvoiced());

            $reference->setIssueDate($date);
        }

        return $reference;
    }

    /**
     * @throws UnsupportedTaxCombination
     */
    private function setLineTaxLast(TaxLineItem $taxLineItem, InvoiceLine $line, ?string $productId): void
    {
        $taxCode = $taxLineItem->getTaxCode();

        if ($taxCode->getType() !== TaxCodeType::Percentage) {
            throw new UnsupportedTaxCombination("The last tax must be a percentage.");
        }

        $rate = (float) ($taxLineItem->getRate() ?? '0');

        $product = null;

        if ($productId) {
            $product = $this->entityManager->getRDBRepositoryByClass(Product::class)->getById($productId);
        }

        [$category, $exemptionCode, $reason] = $this->getTaxData($taxCode, $rate, $product);

        $line
            ->setVatRate($rate)
            ->setVatCategory($category)
            ->setVatExemptionReasonCode($exemptionCode)
            ->setVatExemptionReason($reason);
    }

    private function getExemptionCode(?string $category): ?string
    {
        if (!$category || $category === 'S') {
            return null;
        }

        if ($category === 'K') {
            return 'VATEX-EU-IC';
        }

        if ($category === 'AE') {
            return 'VATEX-EU-AE';
        }

        if ($category === 'G') {
            return 'VATEX-EU-G';
        }

        return null;
    }

    /**
     * @return array{string, ?string, ?string}
     */
    private function getTaxData(TaxCode $taxCode, float $rate, ?Product $product): array
    {
        $mappingEntry = $this->taxMappingProcessor->get($taxCode, $product);

        if (!$mappingEntry) {
            $category = 'S';

            if (!$rate) {
                $category = 'Z';
            }

            return [$category, $this->getExemptionCode($category), null];
        }

        $category = $mappingEntry->getCategoryCode();
        $reasonCode = $mappingEntry->getExemptionCode();
        $reason = $mappingEntry->getExemptionReason();

        if (!$reasonCode) {
            $reasonCode = $this->getExemptionCode($category);
        }

        return [$category, $reasonCode, $reason];
    }
}
