<?php

// =====================================================
// VERSIONE: 2.1.1
// DATA: 11-05-2026 07:20
// FILE: custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php
// =====================================================
//
// FIX 2.1.1
// -----------------------------------------------------
// RIPRISTINO VERSIONE STABILE
//
// Problema:
//
// utilizzo:
//
// $GLOBALS['entityManager']
//
// rompeva il caricamento Opportunity
// su EspoCRM 9.3.6.
//
// Causa reale:
//
// file backup duplicati dentro:
//
// custom/Espo/Custom/Hooks/
//
// Fix:
//
// ripristinato constructor injection
// originale stabile.
//
// =====================================================
//
// STORICO VERSIONI
// =====================================================
//
// 2.0.0
// -----------------------------------------------------
// Prima versione hook Opportunity.
//
//
//
// 2.0.1
// -----------------------------------------------------
// Primo fix creazione Opportunity
// da Appuntamento.
//
//
//
// 2.0.2
// -----------------------------------------------------
// FIX STABILE PRODUZIONE
//
// Risolto:
//
// - relazione Appuntamento
// - sync Prospect
// - sync CAP
// - sync Telefono
// - sync WhatsApp
// - naming definitivo
//
// IMPORTANTE:
//
// afterSave necessario perché EspoCRM
// salva la relazione SOLO dopo il save.
//
//
//
// 2.0.3
// -----------------------------------------------------
// Ripristino bottone "Crea Opportunità".
//
//
//
// 2.0.4
// -----------------------------------------------------
// Tentativo sync Lead automatico.
//
//
//
// 2.0.5
// -----------------------------------------------------
// Fix naming Opportunity.
//
// amount = campo ufficiale EspoCRM.
//
//
//
// 2.0.6
// -----------------------------------------------------
// Sync Lead corretto.
//
// importoOpportunit sincronizzato.
//
//
//
// 2.0.7
// -----------------------------------------------------
// FIX DEFINITIVO DATA OPPORTUNITÀ
//
// Problema:
// Opportunity salvava data errata.
//
// Fix:
// uso corretto di:
//
// dateStart
//
//
//
// 2.0.8
// -----------------------------------------------------
// FIX DEFINITIVO LEAD
//
// Problema:
//
// Prospect NON possiede relazione:
//
// leads
//
// quindi il save generava:
//
// Entity does not have a relation 'leads'
//
// Fix:
//
// il Lead viene letto direttamente
// dall'Appuntamento:
//
// leadId
// leadName
//
//
//
// 2.0.9
// -----------------------------------------------------
// FIX DEFINITIVO LOOP INFINITO
//
// Problema:
//
// afterSave eseguiva:
//
// saveEntity()
//
// causando:
//
// afterSave -> saveEntity -> afterSave
//
// loop infinito e timeout.
//
// Fix:
//
// rimosso saveEntity() finale.
//
// =====================================================
//
// OBIETTIVO HOOK
// =====================================================
//
// Questo hook gestisce:
//
// 1) HookVersion
// 2) amountConverted
// 3) Sync Appuntamento
// 4) Data Opportunità
// 5) Prospect
// 6) CAP
// 7) Telefono
// 8) WhatsApp
// 9) Lead automatico
// 10) Importo Opportunità
// 11) Naming definitivo
//
// =====================================================

namespace Espo\Custom\Hooks\Opportunity;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class GlobalLogic
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
    // BEFORE SAVE
    // =====================================================

    public function beforeSave(
        Entity $entity,
        array $options = []
    ): void {

        // =====================================================
        // VERSIONE HOOK
        // =====================================================

        $entity->set(
            'hookVersion',
            '2.1.1'
        );


        // =====================================================
        // IMPORTO CONVERTITO
        // =====================================================

        if ($entity->get('amount')) {

            $entity->set(
                'amountConverted',
                $entity->get('amount')
            );
        }
    }


    // =====================================================
    // AFTER SAVE
    // =====================================================
    //
    // QUI LE RELAZIONI SONO DISPONIBILI
    //
    // =====================================================

    public function afterSave(
        Entity $entity,
        array $options = []
    ): void {


        // =====================================================
        // RECUPERO APPUNTAMENTO RELAZIONATO
        // =====================================================

        $collection = $this
            ->entityManager
            ->getRDBRepository('Opportunity')
            ->getRelation($entity, 'appuntamento')
            ->find();

        $appuntamento = null;

        foreach ($collection as $item) {

            $appuntamento = $item;

            break;
        }


        // =====================================================
        // CONTROLLO APPUNTAMENTO
        // =====================================================

        if (!$appuntamento) {
            return;
        }


        // =====================================================
        // SYNC RELAZIONE APPUNTAMENTO
        // =====================================================

        $entity->set(
            'appuntamentoId',
            $appuntamento->getId()
        );

        $entity->set(
            'appuntamentoName',
            $appuntamento->get('name')
        );


        // =====================================================
        // DATA OPPORTUNITÀ
        // =====================================================

        if ($appuntamento->get('dateStart')) {

            $date = substr(
                $appuntamento->get('dateStart'),
                0,
                10
            );

            $entity->set(
                'dataOpportunit',
                $date
            );
        }


        // =====================================================
        // PROSPECT
        // =====================================================

        if ($appuntamento->get('prospectId')) {

            $entity->set(
                'prospectId',
                $appuntamento->get('prospectId')
            );

            $entity->set(
                'prospectName',
                $appuntamento->get('prospectName')
            );
        }


        // =====================================================
        // CAP
        // =====================================================

        if ($appuntamento->get('cAPId')) {

            $entity->set(
                'cAPId',
                $appuntamento->get('cAPId')
            );

            $entity->set(
                'cAPName',
                $appuntamento->get('cAPName')
            );
        }


        // =====================================================
        // TELEFONO
        // =====================================================

        if ($appuntamento->get('telefono')) {

            $entity->set(
                'telefono',
                $appuntamento->get('telefono')
            );
        }


        // =====================================================
        // WHATSAPP
        // =====================================================

        if ($appuntamento->get('whatsApp')) {

            $entity->set(
                'whatsApp',
                $appuntamento->get('whatsApp')
            );
        }


        // =====================================================
        // LEAD
        // =====================================================

        if ($appuntamento->get('leadId')) {

            $entity->set(
                'leadId',
                $appuntamento->get('leadId')
            );

            $entity->set(
                'leadName',
                $appuntamento->get('leadName')
            );
        }


        // =====================================================
        // IMPORTO OPPORTUNITÀ
        // =====================================================

        if ($entity->get('amount')) {

            $entity->set(
                'importoOpportunit',
                $entity->get('amount')
            );
        }


        // =====================================================
        // NAMING DEFINITIVO
        // =====================================================

        if ($entity->get('prospectName')) {

            $name =

                $entity->get('dataOpportunit')

                . ' - '

                . $entity->get('prospectName')

                . ' - '

                . $entity->get('azienda')

                . ' - '

                . strtoupper(
                    (string) $entity->get('description')
                )

                . ' - € '

                . number_format(
                    (float) $entity->get('amount'),
                    0,
                    ',',
                    '.'
                );


            $entity->set(
                'name',
                $name
            );
        }


        // =====================================================
        // FINE HOOK
        // =====================================================
        //
        // NON usare saveEntity()
        // dentro afterSave.
        //
        // Evita loop infinito.
        //
        // =====================================================
    }
}

