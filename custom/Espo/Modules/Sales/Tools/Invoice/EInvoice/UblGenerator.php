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

use Einvoicing\Exceptions\ValidationException;
use Einvoicing\Invoice as EInvoice;
use Einvoicing\Writers\UblWriter;
use Espo\Core\FileStorage\Manager;
use Espo\Entities\Attachment;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Entities\Invoice;
use Espo\Modules\Sales\Tools\Invoice\ECreditNote\Preparator as CreditNotePreparator;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\RuleValidationFailure;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnknownFormat;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnsupportedTaxCombination;
use Espo\ORM\EntityManager;

class UblGenerator
{
    public function __construct(
        private EntityManager $entityManager,
        private Manager $fileStorageManager,
        private Preparator $preparator,
        private CreditNotePreparator $creditNotePreparator,
    ) {}

    /**
     * @throws RuleValidationFailure
     * @throws UnknownFormat
     * @throws UnsupportedTaxCombination
     */
    public function generateAttachment(Invoice|CreditNote $invoice, string $format): Attachment
    {
        $contents = $this->generate($invoice, $format);

        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();

        $attachment
            ->setRole(Attachment::ROLE_EXPORT_FILE)
            ->setName($this->getFileName($invoice, $format))
            ->setSize(strlen($contents))
            ->setType('application/xml');

        $this->entityManager->saveEntity($attachment);

        $this->fileStorageManager->putContents($attachment, $contents);

        return $attachment;
    }

    /**
     * @throws RuleValidationFailure
     * @throws UnknownFormat
     * @throws UnsupportedTaxCombination
     */
    public function validate(Invoice|CreditNote $invoice, string $format): void
    {
        $eInvoice = $this->prepareEInvoice($invoice, $format);

        try {
            $eInvoice->validate();
        } catch (ValidationException $e) {
            throw RuleValidationFailure::create($e->getMessage(), $e->getBusinessRuleId());
        }
    }

    private function getFileName(Invoice|CreditNote $invoice, string $format): string
    {
        $name = str_replace("\"", "\\\"", $invoice->getNumber() ?? $invoice->getId());
        $name = $name . '.' . strtolower($format);

        return $name . '.ubl.xml';
    }

    /**
     * @throws UnknownFormat
     * @throws UnsupportedTaxCombination
     */
    private function prepareEInvoice(Invoice|CreditNote $invoice, string $format): EInvoice
    {
        if ($invoice instanceof CreditNote) {
            return $this->creditNotePreparator->prepare($invoice, $format);
        }

        return $this->preparator->prepare($invoice, $format);
    }

    /**
     * @throws RuleValidationFailure
     * @throws UnknownFormat
     * @throws UnsupportedTaxCombination
     */
    private function generate(Invoice|CreditNote $invoice, string $format): string
    {
        $eInvoice = $this->prepareEInvoice($invoice, $format);

        try {
            $eInvoice->validate();
        } catch (ValidationException $e) {
            throw RuleValidationFailure::create($e->getMessage(), $e->getBusinessRuleId());
        }

        return (new UblWriter())->export($eInvoice);
    }
}
