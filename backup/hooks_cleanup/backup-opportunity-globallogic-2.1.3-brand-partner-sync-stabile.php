<?php

// =====================================================
// VERSIONE: 2.1.3
// DATA: 2026-05-22
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
// FIX 2.1.2
// -----------------------------------------------------
// FIX PRODUZIONE SYNC APPUNTAMENTO -> OPPORTUNITY
//
// Problema:
//
// la logica di sync era eseguita in afterSave(), ma il
// metadata attuale registra questo hook solo in beforeSave.
// Inoltre i set() eseguiti in afterSave senza saveEntity()
// non garantiscono persistenza dei dati.
//
// Fix:
//
// la sync viene richiamata anche da beforeSave, cosi i dati
// vengono salvati nello stesso ciclo CREATE / UPDATE.
//
// Rollback:
//
// ripristinare il file stabile da:
// backup/hooks_cleanup/
// backup-opportunity-globallogic-2.1.1-sync-appuntamento-stabile.php
//
// =====================================================
//
// FIX 2.1.3
// -----------------------------------------------------
// FIX PRODUZIONE MAPPING LEAD / CONTATTI
//
// Problema:
//
// se Appuntamento e' collegato a Lead tramite parent
// (parentType = Lead, parentId valorizzato), Opportunity
// non riceveva leadId perche' il codice leggeva solo leadId.
// Inoltre telefono e WhatsApp non avevano fallback stabile da
// Prospect / Lead.
//
// Fix:
//
// - fallback Lead da parentType/parentId
// - telefono da Appuntamento, Prospect o Lead
// - WhatsApp da Prospect
// - nessuna creazione Lead
// - nessun duplicato
//
// Rollback:
//
// ripristinare il file stabile da:
// backup/hooks_cleanup/
// backup-opportunity-globallogic-2.1.2-lead-mapping-stabile.php
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
            '2.1.3'
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


        // =====================================================
        // SYNC APPUNTAMENTO IN BEFORE SAVE (2.1.2)
        // =====================================================
        //
        // Il metodo afterSave storico contiene la logica stabile.
        // Da 2.1.2 viene richiamato anche qui per rendere
        // persistenti i set() nello stesso salvataggio.
        //
        // =====================================================

        if (
            $entity->get('appuntamentoId') ||
            !$entity->isNew()
        ) {

            $this->afterSave(
                $entity,
                $options
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

        $appuntamento = null;

        if ($entity->get('appuntamentoId')) {

            $appuntamento = $this->entityManager->getEntity(
                'Appuntamento',
                $entity->get('appuntamentoId')
            );
        }

        if (!$appuntamento) {

            $collection = $this
                ->entityManager
                ->getRDBRepository('Opportunity')
                ->getRelation($entity, 'appuntamento')
                ->find();

            foreach ($collection as $item) {

                $appuntamento = $item;

                break;
            }
        }


        // =====================================================
        // CONTROLLO APPUNTAMENTO
        // =====================================================

        if (!$appuntamento) {
            return;
        }


        // =====================================================
        // RECUPERO PROSPECT / LEAD (2.1.3)
        // =====================================================

        $prospect = null;

        if ($appuntamento->get('prospectId')) {

            $prospect = $this->entityManager->getEntity(
                'Prospect',
                $appuntamento->get('prospectId')
            );
        }

        $lead = null;

        if ($appuntamento->get('leadId')) {

            $lead = $this->entityManager->getEntity(
                'Lead',
                $appuntamento->get('leadId')
            );

        } elseif (
            $appuntamento->get('parentType') === 'Lead' &&
            $appuntamento->get('parentId')
        ) {

            $lead = $this->entityManager->getEntity(
                'Lead',
                $appuntamento->get('parentId')
            );
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
        // TELEFONO (2.1.3)
        // =====================================================

        $telefono = $appuntamento->get('telefono');

        if (!$telefono && $prospect) {

            $telefono =
                $prospect->get('phoneNumber')
                ?: $prospect->get('telefono');
        }

        if (!$telefono && $lead) {
            $telefono = $lead->get('phoneNumber');
        }

        if ($telefono) {

            $entity->set(
                'telefono',
                $telefono
            );
        }


        // =====================================================
        // WHATSAPP (2.1.3)
        // =====================================================

        $whatsApp = null;

        if ($prospect) {

            $whatsApp =
                $prospect->get('whatsApp')
                ?: $prospect->get('whatsApp39');
        }

        if ($whatsApp) {

            $entity->set(
                'whatsApp',
                $whatsApp
            );
        }


        // =====================================================
        // LEAD (2.1.3)
        // =====================================================

        if ($lead) {

            $leadName = $lead->get('name');

            if (!$leadName) {

                $leadName = trim(
                    ($lead->get('firstName') ?: '') .
                    ' ' .
                    ($lead->get('lastName') ?: '')
                );
            }

            if (!$leadName) {
                $leadName = $lead->get('phoneNumber');
            }

            $entity->set(
                'leadId',
                $lead->getId()
            );

            $entity->set(
                'leadName',
                $leadName
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

