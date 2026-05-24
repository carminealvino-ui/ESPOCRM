<?php

// =====================================================
// VERSIONE: 1.7.0
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
// ...
// 1.7.0
// -----------------------------------------------------
// CONTRATTO SENZA ARTICOLI DA OPPORTUNITA
//
// Opportunity chiusa = solo testata (importi, partner, categoria).
// Quote/Contratto nasce con itemList vuoto; le righe si aggiungono
// sul contratto con regole provvigionali per voce.
//
// 1.6.0
// -----------------------------------------------------
// FIX COMPLETO DATI CONTRATTO
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
                    'importoOpportunita'
                );
        }

        // =====================================================
        // ITEM LIST (vuoto: le voci si creano sul contratto)
        // =====================================================

        $itemList = [];

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
            $opportunity->get(
                'dateClosed'
            );

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
        // CREAZIONE CONTRATTO
        // =====================================================


        $fornitorePartnerId = $opportunity->get('fornitorePartnerId');
        $fornitorePartnerName = $opportunity->get('fornitorePartnerName');
        $productBrandId = $opportunity->get('productBrandId');
        $productBrandName = $opportunity->get('productBrandName');
        $productCategoryId = $opportunity->get('productCategoryId');
        $productCategoryName = $opportunity->get('productCategoryName');

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

            // =================================================
            // CONTESTO COMMERCIALE (da opportunita, non per riga)
            // =================================================

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

            // =================================================
            // TEAM
            // =================================================

            'teamsIds' =>
                $teamsIds
        ]);

        // =====================================================
        // SAVE CONTRATTO
        // =====================================================

        $this->entityManager
            ->saveEntity(
                $quote
            );

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
