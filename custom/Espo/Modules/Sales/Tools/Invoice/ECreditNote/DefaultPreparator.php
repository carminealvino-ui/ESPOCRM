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

namespace Espo\Modules\Sales\Tools\Invoice\ECreditNote;

use DateTime;
use Einvoicing\Invoice as EInvoice;
use Espo\Core\Field\Date;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Sales\Entities\CreditNote;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnknownFormat;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Exceptions\UnsupportedTaxCombination;
use Espo\Modules\Sales\Tools\Invoice\EInvoice\Helper;
use Espo\ORM\EntityManager;
use Exception;
use RuntimeException;

class DefaultPreparator implements Preparator
{
    public function __construct(
        private EntityManager $entityManager,
        private Helper $helper,
    ) {}

    /**
     * @throws UnknownFormat
     * @throws UnsupportedTaxCombination
     */
    public function prepare(CreditNote $creditNote, string $format): EInvoice
    {
        $eInvoice = $this->helper->prepareNew($format);

        $eInvoice->setType(EInvoice::TYPE_CREDIT_NOTE);

        if ($creditNote->getNumber()) {
            $eInvoice->setNumber($creditNote->getNumber());
        }

        if ($creditNote->getDateDue()) {
            $eInvoice->setDueDate($this->convertDate($creditNote->getDateDue()));
        }

        if ($creditNote->getDateIssued()) {
            $eInvoice->setIssueDate($this->convertDate($creditNote->getDateIssued()));
        }

        if ($creditNote->getAmount()) {
            $eInvoice->setCurrency($creditNote->getAmount()->getCode());
        }

        if ($creditNote->getBuyerReference()) {
            $eInvoice->setBuyerReference($creditNote->getBuyerReference());
        }

        if ($creditNote->getPurchaseOrderReference()) {
            $eInvoice->setPurchaseOrderReference($creditNote->getPurchaseOrderReference());
        }

        if ($creditNote->getNote()) {
            $eInvoice->addNote($creditNote->getNote());
        }

        if ($creditNote->getInvoice()?->getNumber()) {
            $this->helper->addInvoiceReference($eInvoice, $creditNote->getInvoice());
        }

        $eInvoice->setSeller($this->helper->prepareSeller());

        $account = $this->getAccount($creditNote);

        if ($account) {
            $eInvoice->setBuyer($this->helper->prepareBuyer($account, $creditNote));
        }

        $this->helper->addLines($creditNote, $eInvoice);
        $this->helper->addShippingItems($creditNote, $eInvoice);
        $this->helper->addRounding($creditNote, $eInvoice);

        if ($creditNote->getRoundingAmount()) {
            $eInvoice->setRoundingAmount($creditNote->getRoundingAmount()->getAmount());
        }

        return $eInvoice;
    }

    private function convertDate(Date $date): DateTime
    {
        try {
            return new DateTime($date->toString());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function getAccount(CreditNote $creditNote): ?Account
    {
        $account = null;

        if ($creditNote->getAccount()) {
            $account = $this->entityManager
                ->getRDBRepositoryByClass(Account::class)
                ->getById($creditNote->getAccount()->getId());
        }

        return $account;
    }
}
