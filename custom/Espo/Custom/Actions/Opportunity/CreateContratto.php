<?php

// =====================================================
// VERSIONE: 1.6.0
// DATA: 12-05-2026 22:05
// FILE:
// custom/Espo/Custom/Actions/Opportunity/CreateContratto.php
// =====================================================
//
// STORICO FIX
// -----------------------------------------------------
//
// 1.0.0
// - Prima creazione contratto
//
// 1.1.0
// - Redirect automatico
//
// 1.2.0
// - Blocco duplicati
//
// 1.3.0
// - Creazione automatica Cliente
//
// 1.3.1
// - Update Lead convertito
//
// 1.3.2
// - Copia indirizzi e telefono
//
// 1.3.3
// - Copia assignedUser
//
// 1.3.4
// - Copia teams
//
// 1.3.5
// - type=B2C
// - segmento=B2C
//
// 1.4.0
// - Tentativo refactor API style
//
// 1.4.1
// - Introduzione Request/Response
//
// 1.4.2
// - Fix namespace
//
// 1.4.3
// - Fix class loading
//
// 1.5.0
// -----------------------------------------------------
// FIX DEFINITIVO COMPATIBILITA CONTROLLER
//
// Il controller custom passa direttamente:
//
// Espo\Modules\Crm\Entities\Opportunity
//
// e NON:
//
// Espo\Core\Api\Request
//
// Quindi:
//
// run($opportunity)
//
// 1.6.0
// -----------------------------------------------------
// FIX COMPLETO DATI CONTRATTO
//
// 1.7.0 (base: custom.zip 12-05-2026 + correzioni branch)
// -----------------------------------------------------
// - importoOpportunit (nome campo corretto)
// - Cliente da Prospect.cliente / creazione Account
// - fornitorePartner, productBrand, productCategory, hookVersion
// - installatoreId -> shippingProvider
// - accountName + contatti da prospect/lead
//
// AGGIUNTO:
//
// - amount
// - dateQuoted
// - itemList
// - taxRate
// - taxCodeId
// - priceBookId
// - isTaxInclusive
//
// OBIETTIVO:
//
// Recuperare automaticamente:
//
// - totale contratto
// - subtotali
// - iva
// - articoli
// - intestazione corretta
// - data contratto
//
// =====================================================

namespace Espo\Custom\Actions\Opportunity;

use Espo\ORM\EntityManager;

class CreateContratto
{
    protected EntityManager $entityManager;

    // =====================================================
    // COSTRUTTORE
    // =====================================================

    public function __construct(
        EntityManager $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    // =====================================================
    // RUN
    // =====================================================

    public function run($opportunity)
    {

        // =====================================================
        // VALIDAZIONE
        // =====================================================

        if (!$opportunity) {

            throw new \Exception(
                'Opportunità non trovata'
            );
        }

        // =====================================================
        // BLOCCO DUPLICATI
        // =====================================================

        $existing = $this->entityManager
            ->getRDBRepository('Quote')
            ->where([
                'opportunityId' =>
                    $opportunity->getId()
            ])
            ->findOne();

        if ($existing) {

            return (object) [

                'quoteId' =>
                    $existing->getId(),

                'quoteName' =>
                    $existing->get('name'),

                'existing' => true
            ];
        }

        // =====================================================
        // TEAM
        // =====================================================

        $teamsIds =
            $opportunity->getLinkMultipleIdList(
                'teams'
            );

        // =====================================================
        // CLIENTE
        // =====================================================

        $accountId =
            $opportunity->get(
                'accountId'
            );

        // =====================================================
        // LEAD
        // =====================================================

        $leadId =
            $opportunity->get(
                'leadId'
            );

        $lead = null;

        if ($leadId) {

            $lead = $this->entityManager
                ->getEntityById(
                    'Lead',
                    $leadId
                );
        }

        // =====================================================
        // CREAZIONE CLIENTE
        // =====================================================

        if (
            !$accountId &&
            $lead
        ) {

            $cliente = $this->entityManager
                ->createEntity(
                    'Account'
                );

            $cliente->set([

                // =============================================
                // NOME CLIENTE
                // =============================================

                'name' =>
                    $lead->get('name'),

                // =============================================
                // INDIRIZZO FATTURAZIONE
                // =============================================

                'billingAddressStreet' =>
                    $lead->get(
                        'addressStreet'
                    ),

                'billingAddressCity' =>
                    $lead->get(
                        'addressCity'
                    ),

                'billingAddressPostalCode' =>
                    $lead->get(
                        'addressPostalCode'
                    ),

                'billingAddressState' =>
                    $lead->get(
                        'addressState'
                    ),

                // =============================================
                // TELEFONO
                // =============================================

                'phoneNumber' =>
                    $lead->get(
                        'phoneNumber'
                    ),

                // =============================================
                // TEAM
                // =============================================

                'teamsIds' =>
                    $teamsIds,

                // =============================================
                // ASSEGNATO
                // =============================================

                'assignedUserId' =>
                    $opportunity->get(
                        'assignedUserId'
                    ),

                // =============================================
                // CUSTOM
                // =============================================

                'type' =>
                    'B2C',

                'segmento' =>
                    'B2C'
            ]);

            // =====================================================
            // SAVE CLIENTE
            // =====================================================

            $this->entityManager
                ->saveEntity(
                    $cliente
                );

            $accountId =
                $cliente->getId();

            // =====================================================
            // UPDATE LEAD
            // =====================================================

            $lead->set([

                'status' =>
                    'Converted',

                'createdAccountId' =>
                    $accountId,

                'convertedAt' =>
                    date(
                        'Y-m-d H:i:s'
                    )
            ]);

            $this->entityManager
                ->saveEntity(
                    $lead
                );
        }


        // =====================================================
        // PROSPECT -> CLIENTE (1.7.0)
        // =====================================================

        if (!$accountId && $opportunity->get('prospectId')) {

            $prospect = $this->entityManager->getEntityById(
                'Prospect',
                $opportunity->get('prospectId')
            );

            if ($prospect) {

                $clienteId = $prospect->get('clienteId');

                if ($clienteId && $this->entityManager->getEntityById('Account', $clienteId)) {
                    $accountId = $clienteId;
                } else {

                    $cliente = $this->entityManager->createEntity('Account');

                    $nome = $prospect->get('name');

                    if (!$nome) {
                        $nome = trim(
                            ($prospect->get('firstName') ?? '') . ' ' . ($prospect->get('lastName') ?? '')
                        );
                    }

                    $cliente->set([
                        'name' => $nome ?: 'Cliente da prospect',
                        'billingAddressStreet' => $prospect->get('addressStreet'),
                        'billingAddressCity' => $prospect->get('addressCity'),
                        'billingAddressPostalCode' => $prospect->get('addressPostalCode'),
                        'billingAddressState' => $prospect->get('addressState'),
                        'phoneNumber' => $prospect->get('phoneNumber'),
                        'teamsIds' => $teamsIds,
                        'assignedUserId' => $opportunity->get('assignedUserId'),
                        'type' => 'B2C',
                        'segmento' => 'B2C',
                    ]);

                    $this->entityManager->saveEntity($cliente);

                    $accountId = $cliente->getId();

                    $prospect->set([
                        'clienteId' => $accountId,
                        'clienteName' => $cliente->get('name'),
                    ]);

                    $this->entityManager->saveEntity($prospect);
                }
            }
        }

        // Validazione accountId (evita ID prospect nel campo Cliente)
        if ($accountId && !$this->entityManager->getEntityById('Account', $accountId)) {
            $accountId = null;
        }

        // =====================================================
        // NOME CONTRATTO
        // =====================================================

        $quoteName =
            'CONTRATTO_' .
            date('Ymd_His');

        // =====================================================
        // IMPORTO
        // =====================================================

        $amount =
            $opportunity->get(
                'amount'
            );

        if (!$amount) {

            $amount =
                $opportunity->get(
                    'importoOpportunit'
                );
        }

        // =====================================================
        // ITEM LIST
        // =====================================================

        $itemList =
            $opportunity->get(
                'itemList'
            );

        // =====================================================
        // PRICE BOOK
        // =====================================================

        $priceBookId =
            $opportunity->get(
                'priceBookId'
            );

        // =====================================================
        // TAX CODE
        // =====================================================

        $taxCodeId =
            $opportunity->get(
                'taxCodeId'
            );

        // =====================================================
        // TAX RATE
        // =====================================================

        $taxRate =
            $opportunity->get(
                'taxRate'
            );

        // =====================================================
        // DESCRIZIONE
        // =====================================================

        $description =
            $opportunity->get(
                'description'
            );

        // =====================================================
        // DATA CONTRATTO
        // =====================================================

        $dateQuoted =
            $opportunity->get('closeDate')
            ?: $opportunity->get('dateClosed')
            ?: $opportunity->get('dataOpportunit');

        // =====================================================
        // INDIRIZZI
        // =====================================================

        $billingAddressStreet =
            $opportunity->get(
                'billingAddressStreet'
            );

        $billingAddressCity =
            $opportunity->get(
                'billingAddressCity'
            );

        $billingAddressPostalCode =
            $opportunity->get(
                'billingAddressPostalCode'
            );

        $billingAddressState =
            $opportunity->get(
                'billingAddressState'
            );


        // =====================================================
        // PARTNER / BRAND / CATEGORIA / HOOK (1.7.0)
        // =====================================================

        $fornitorePartnerId = $opportunity->get('fornitorePartnerId');
        $fornitorePartnerName = $opportunity->get('fornitorePartnerName');
        $productBrandId = $opportunity->get('productBrandId');
        $productBrandName = $opportunity->get('productBrandName');
        $productCategoryId = $opportunity->get('productCategoryId');
        $productCategoryName = $opportunity->get('productCategoryName');

        if (!$productCategoryId && $opportunity->get('lineaProdotto')) {
            $cat = $this->entityManager
                ->getRDBRepository('ProductCategory')
                ->where(['name' => $opportunity->get('lineaProdotto')])
                ->findOne();
            if ($cat) {
                $productCategoryId = $cat->getId();
                $productCategoryName = $cat->get('name');
            }
        }

        $hookVersion = $opportunity->get('hookVersion') ?: 'CreateContratto-1.7.0';
        $installatoreId = $opportunity->get('installatoreId');


        // =====================================================
        // CREAZIONE CONTRATTO
        // =====================================================

        $quote = $this->entityManager
            ->createEntity(
                'Quote'
            );

        $quote->set([

            // =================================================
            // BASE
            // =================================================

            'name' =>
                $quoteName,

            'opportunityId' =>
                $opportunity->getId(),

            'accountId' =>
                $accountId,

            'opportunityName' =>
                $opportunity->get('name'),

            'status' =>
                'Draft',

            'hookVersion' =>
                $hookVersion,

            'fornitorePartnerId' =>
                $fornitorePartnerId,

            'fornitorePartnerName' =>
                $fornitorePartnerName,

            'productBrandId' =>
                $productBrandId,

            'productBrandName' =>
                $productBrandName,

            'productCategoryId' =>
                $productCategoryId,

            'productCategoryName' =>
                $productCategoryName,

            'shippingProviderId' =>
                $installatoreId,

            'shippingProviderName' =>
                $opportunity->get('installatoreName'),

            // =================================================
            // IMPORTI
            // =================================================

            'amount' =>
                $amount,

            'importoContratto' =>
                $amount,

            // =================================================
            // DATA
            // =================================================

            'dateQuoted' =>
                $dateQuoted,

            // =================================================
            // DESCRIZIONE
            // =================================================

            'description' =>
                $description,

            // =================================================
            // IVA
            // =================================================

            'taxRate' =>
                $taxRate,

            'taxCodeId' =>
                $taxCodeId,

            'isTaxInclusive' => true,

            // =================================================
            // LISTINO
            // =================================================

            'priceBookId' =>
                $priceBookId,

            // =================================================
            // ARTICOLI
            // =================================================

            'itemList' =>
                $itemList,

            // =================================================
            // INDIRIZZO FATTURAZIONE
            // =================================================

            'billingAddressStreet' =>
                $billingAddressStreet,

            'billingAddressCity' =>
                $billingAddressCity,

            'billingAddressPostalCode' =>
                $billingAddressPostalCode,

            'billingAddressState' =>
                $billingAddressState,

            // =================================================
            // INDIRIZZO INSTALLAZIONE
            // =================================================

            'shippingAddressStreet' =>
                $billingAddressStreet,

            'shippingAddressCity' =>
                $billingAddressCity,

            'shippingAddressPostalCode' =>
                $billingAddressPostalCode,

            'shippingAddressState' =>
                $billingAddressState,

            // =================================================
            // USER
            // =================================================

            'assignedUserId' =>
                $opportunity->get(
                    'assignedUserId'
                ),

            // =================================================
            // TEAM
            // =================================================

            'teamsIds' =>
                $teamsIds
        ]);

        // =====================================================
        // SAVE CONTRATTO
        // =====================================================

        // =====================================================
        // CLIENTE NOME + CONTATTO (1.7.0)
        // =====================================================

        if ($accountId) {
            $account = $this->entityManager->getEntityById('Account', $accountId);
            if ($account) {
                $quote->set([
                    'accountId' => $accountId,
                    'accountName' => $account->get('name'),
                ]);
            }
        }

        if (!$quote->get('billingContactId') && $opportunity->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById('Prospect', $opportunity->get('prospectId'));
            if ($prospect) {
                $nome = $prospect->get('name') ?: trim(($prospect->get('firstName') ?? '') . ' ' . ($prospect->get('lastName') ?? ''));
                if ($nome) {
                    $contact = $this->entityManager->createEntity('Contact');
                    $contact->set([
                        'name' => $nome,
                        'firstName' => $prospect->get('firstName'),
                        'lastName' => $prospect->get('lastName'),
                        'accountId' => $accountId,
                        'assignedUserId' => $opportunity->get('assignedUserId'),
                    ]);
                    $this->entityManager->saveEntity($contact);
                    $quote->set([
                        'billingContactId' => $contact->getId(),
                        'billingContactName' => $contact->get('name'),
                        'shippingContactId' => $contact->getId(),
                        'shippingContactName' => $contact->get('name'),
                    ]);
                }
            }
        }

        $this->entityManager->saveEntity($quote);



        // =====================================================
        // RESPONSE
        // =====================================================

        return (object) [

            'quoteId' =>
                $quote->getId(),

            'quoteName' =>
                $quote->get('name'),

            'existing' => false
        ];
    }
}
