<?php

// =====================================================
// VERSIONE: 2.2.8
// DATA: 2026-06-11
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
// 2.1.4
// -----------------------------------------------------
// FIX PRODUZIONE FORNITORE / BRAND
//
// Obiettivo:
//
// sincronizzare su Opportunity:
// - fornitorePartner
// - productBrand
//
// Fonte primaria:
// Appuntamento collegato.
//
// Fallback:
// Lead o Prospect collegati.
//
// Rollback:
//
// ripristinare il file stabile da:
// backup/hooks_cleanup/
// backup-opportunity-globallogic-2.1.3-brand-partner-sync-stabile.php
//
// 2.1.5
// -----------------------------------------------------
// FIX PRODUZIONE PRODUCT BRAND
//
// Se productBrand manca, viene risolto dal vecchio campo azienda
// tramite ProductBrand.name = azienda.
//
// Rollback:
//
// ripristinare il file stabile da:
// backup/hooks_cleanup/
// backup-opportunity-globallogic-2.1.4-brand-fallback-stabile.php
//
// FIX 2.2.3
// -----------------------------------------------------
// OPPORTUNITA IN LISTA APPUNTAMENTO
//
// Problema:
//
// Opportunity con lead_id valorizzato ma appuntamento_id NULL
// compaiono in lista Lead (hasMany via lead) ma non in lista
// Appuntamento (hasMany via appuntamento).
//
// Fix:
//
// - resolveAppuntamentoFromLead() in afterSave
// - beforeSave richiama sync anche con leadId
//
// Rollback:
// backup/hooks_cleanup/backup-opportunity-globallogic-2.2.2-category-cascade-stabile.php
//
// FIX 2.2.6
// -----------------------------------------------------
// LISTINO PREZZI (Price Book) in vigore per data opportunità + brand
//
// - OpportunityPriceBookResolver in beforeSave
// - Solo se priceBookId vuoto o cambiano data/brand (non se utente cambia listino)
//
// FIX 2.2.5
// -----------------------------------------------------
// RIporto campi = solo suggerimento (modificabile dopo)
//
// Problema:
//
// beforeSave richiamava afterSave su ogni UPDATE (!isNew) e
// syncBrandPartnerFromSource sovrascriveva sempre fornitore/brand/categoria.
//
// Fix:
//
// - sync da Appuntamento/Lead solo su CREATE o cambio appuntamentoId/leadId
// - syncBrandPartnerFromSource imposta solo campi Opportunity ancora vuoti
// - normalizeProductCascade solo in fase import (non su ogni modifica)
// - telefono/prospect/lead: non sovrascrivere valori gia presenti
//
// =====================================================
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
use Espo\Custom\Services\LineaProdottoCategorySync;
use Espo\Custom\Services\OpportunityPriceBookResolver;
use Espo\Custom\Services\ReferenteContactService;

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

        if (!empty($options['skipHooks'])) {
            return;
        }

        // =====================================================
        // VERSIONE HOOK
        // =====================================================

        $entity->set(
            'hookVersion',
            '2.2.8'
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

        $importFromSource = $entity->isNew()
            || $entity->isAttributeChanged('appuntamentoId')
            || $entity->isAttributeChanged('leadId');

        $needsSync = $importFromSource
            || $entity->get('appuntamentoId')
            || $entity->get('leadId');

        if ($needsSync) {
            $this->runOpportunitySync(
                $entity,
                $options,
                $importFromSource
            );
        }

        $this->applyPriceBookFromEffectiveDate($entity);
    }

    private function applyPriceBookFromEffectiveDate(Entity $entity): void
    {
        if (!$entity->hasAttribute('priceBookId')) {
            return;
        }

        if ($entity->isAttributeChanged('priceBookId')) {
            return;
        }

        $shouldResolve = $entity->isNew()
            || !$entity->get('priceBookId')
            || $entity->isAttributeChanged('dataOpportunit')
            || $entity->isAttributeChanged('productBrandId')
            || $entity->isAttributeChanged('productBrandName')
            || $entity->isAttributeChanged('azienda');

        if (!$shouldResolve) {
            return;
        }

        $priceBook = (new OpportunityPriceBookResolver($this->entityManager))
            ->resolveForOpportunity($entity);

        if (!$priceBook) {
            return;
        }

        $entity->set('priceBookId', $priceBook->getId());

        if ($entity->hasAttribute('priceBookName')) {
            $entity->set('priceBookName', $priceBook->get('name'));
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
        $this->runOpportunitySync(
            $entity,
            $options,
            true
        );
    }

    private function runOpportunitySync(
        Entity $entity,
        array $options,
        bool $importFromSource
    ): void {

        if (!empty($options['skipHooks'])) {
            return;
        }

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

        if (!$appuntamento) {
            $appuntamento = $this->resolveAppuntamentoFromLead($entity);
        }


        // =====================================================
        // CONTROLLO APPUNTAMENTO
        // =====================================================

        if (!$appuntamento) {
            $this->linkLeadFromProspectIfMissing($entity);
            $this->syncAccountAndContactFromLead($entity);
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
        // SYNC FORNITORE / BRAND (2.1.4) — solo suggerimento (2.2.5)
        // =====================================================

        if ($importFromSource) {

            $this->syncBrandPartnerFromSource(
                $entity,
                $appuntamento
            );

            if (
                (!$entity->get('fornitorePartnerId') || !$entity->get('productBrandId') || !$entity->get('productCategoryId')) &&
                $lead
            ) {

                $this->syncBrandPartnerFromSource(
                    $entity,
                    $lead
                );
            }

            if (
                (!$entity->get('fornitorePartnerId') || !$entity->get('productBrandId') || !$entity->get('productCategoryId')) &&
                $prospect
            ) {

                $this->syncBrandPartnerFromSource(
                    $entity,
                    $prospect
                );
            }

            if (!$entity->get('productBrandId')) {

                $this->resolveBrandPartnerFromAzienda(
                    $entity,
                    $appuntamento->get('azienda') ?: $entity->get('azienda')
                );
            }

            $this->normalizeProductCascade($entity);
        }

        $this->syncLeadSourceFromAppuntamento($entity, $appuntamento, $prospect);


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

            $this->setEntityFieldIfEmpty(
                $entity,
                'dataOpportunit',
                $date
            );
        }


        // =====================================================
        // PROSPECT
        // =====================================================

        if ($appuntamento->get('prospectId')) {

            $this->setEntityFieldIfEmpty(
                $entity,
                'prospectId',
                $appuntamento->get('prospectId')
            );

            $this->setEntityFieldIfEmpty(
                $entity,
                'prospectName',
                $appuntamento->get('prospectName')
            );
        }


        // =====================================================
        // CAP
        // =====================================================

        if ($appuntamento->get('cAPId')) {

            $this->setEntityFieldIfEmpty(
                $entity,
                'cAPId',
                $appuntamento->get('cAPId')
            );

            $this->setEntityFieldIfEmpty(
                $entity,
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

            $this->setEntityFieldIfEmpty(
                $entity,
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

            $this->setEntityFieldIfEmpty(
                $entity,
                'whatsApp',
                $whatsApp
            );
        }


        // =====================================================
        // LEAD (2.1.3)
        // =====================================================

        if ($lead && !$entity->get('leadId')) {

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

            if (!$entity->get('leadId')) {
                $entity->set(
                    'leadId',
                    $lead->getId()
                );

                $entity->set(
                    'leadName',
                    $leadName
                );
            }

            $this->syncLeadFieldsFromOpportunity($entity, $lead);
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

            $brandLabel = trim((string) (
                $entity->get('productBrandName')
                ?: $entity->get('azienda')
            ));

            $name =

                $entity->get('dataOpportunit')

                . ' - '

                . $entity->get('prospectName')

                . ' - '

                . $brandLabel

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

        $this->syncAccountAndContactFromLead($entity);


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


    // =====================================================
    // FALLBACK BRAND DA AZIENDA (2.1.5)
    // =====================================================

    private function resolveBrandPartnerFromAzienda(
        Entity $entity,
        ?string $azienda
    ): void {

        if ($entity->get('productBrandId')) {
            return;
        }

        if (!$azienda) {
            return;
        }

        $brand = $this->entityManager
            ->getRepository('ProductBrand')
            ->where([
                'name' => $azienda
            ])
            ->findOne();

        if (!$brand) {
            return;
        }

        $entity->set(
            'productBrandId',
            $brand->getId()
        );

        $entity->set(
            'productBrandName',
            $brand->get('name')
        );

        if (
            $brand->get('fornitorePartnerId') &&
            !$entity->get('fornitorePartnerId')
        ) {

            $entity->set(
                'fornitorePartnerId',
                $brand->get('fornitorePartnerId')
            );

            $entity->set(
                'fornitorePartnerName',
                $brand->get('fornitorePartnerName')
            );
        }
    }


    // =====================================================
    // SYNC FORNITORE / BRAND (2.1.4) — solo campi vuoti (2.2.5)
    // =====================================================

    private function syncBrandPartnerFromSource(
        Entity $entity,
        Entity $source
    ): void {

        $fieldList = [
            'fornitorePartnerId',
            'fornitorePartnerName',
            'productBrandId',
            'productBrandName',
            'productCategoryId',
            'productCategoryName',
            'lineaProdotto',
        ];

        foreach ($fieldList as $field) {

            if (!$source->get($field)) {
                continue;
            }

            if (!$entity->hasAttribute($field)) {
                continue;
            }

            $this->setEntityFieldIfEmpty(
                $entity,
                $field,
                $source->get($field)
            );
        }
    }

    private function syncLeadSourceFromAppuntamento(
        Entity $entity,
        Entity $appuntamento,
        ?Entity $prospect = null
    ): void {

        if (!$entity->hasAttribute('leadSource')) {
            return;
        }

        if ($entity->get('leadSource')) {
            return;
        }

        $leadSource = $this->resolveLeadSourceFromAppuntamento($appuntamento, $prospect);

        if (!$leadSource) {
            return;
        }

        $this->setEntityFieldIfEmpty(
            $entity,
            'leadSource',
            $leadSource
        );
    }

    private function resolveLeadSourceFromAppuntamento(
        Entity $appuntamento,
        ?Entity $prospect = null
    ): ?string {
        $metadata = $this->entityManager
            ->getMetadata()
            ->get(['entityDefs', 'Opportunity', 'fields', 'leadSource', 'options']) ?? [];

        $candidates = [];

        if ($prospect && $prospect->get('origine')) {
            $candidates[] = $prospect->get('origine');
        }

        $map = [
            'TELCALL' => ['Call', 'Call Center', 'TELCALL'],
            'Appuntamento Call Center' => ['Call', 'Call Center', 'TELCALL'],
            'Appuntamento da Gestione Lead' => ['Generazione Lead', 'Lead', 'Existing Customer'],
            'Appuntamento da Gestione CB' => ['Partner', 'Existing Customer', 'Assegnazione CB'],
            'Referenza Personale' => ['Referenza Personale', 'Partner', 'Existing Customer'],
            'Generazione Lead' => ['Generazione Lead', 'Lead', 'Existing Customer'],
            'Extractor' => ['Extractor', 'Other'],
            'Assegnazione CB' => ['Assegnazione CB', 'Partner', 'Vodafone Assegnazione CB'],
            'Call Center' => ['Call', 'Call Center'],
        ];

        $callCenter = $appuntamento->get('callCenter');

        if ($callCenter && isset($map[$callCenter])) {
            $candidates = array_merge($candidates, $map[$callCenter]);
        }

        $tipo = $appuntamento->get('tipo');

        if ($tipo) {
            $types = is_array($tipo) ? $tipo : [$tipo];

            foreach ($types as $t) {
                if (isset($map[$t])) {
                    $candidates = array_merge($candidates, $map[$t]);
                }
            }
        }

        if ($callCenter) {
            $candidates[] = $callCenter;
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $metadata, true)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            $candidateLower = strtolower((string) $candidate);

            foreach ($metadata as $option) {
                $optionLower = strtolower((string) $option);

                if (
                    $optionLower === $candidateLower
                    || str_contains($optionLower, $candidateLower)
                    || str_contains($candidateLower, $optionLower)
                ) {
                    return $option;
                }
            }
        }

        return null;
    }

    private function setEntityFieldIfEmpty(
        Entity $entity,
        string $field,
        mixed $value
    ): void {

        if ($value === null || $value === '') {
            return;
        }

        if (!$entity->hasAttribute($field)) {
            return;
        }

        $current = $entity->get($field);

        if ($current !== null && $current !== '') {
            return;
        }

        $entity->set(
            $field,
            $value
        );
    }

    // =====================================================
    // CASCADE PARTNER / BRAND / CATEGORIA (2.2.0)
    // =====================================================

    private function normalizeProductCascade(Entity $entity): void
    {
        (new LineaProdottoCategorySync($this->entityManager))
            ->alignOnEntity($entity);

        if ($entity->get('productBrandId') && !$entity->get('fornitorePartnerId')) {
            $brand = $this->entityManager->getEntityById(
                'ProductBrand',
                $entity->get('productBrandId')
            );

            if ($brand && $brand->get('fornitorePartnerId')) {
                $entity->set(
                    'fornitorePartnerId',
                    $brand->get('fornitorePartnerId')
                );

                $entity->set(
                    'fornitorePartnerName',
                    $brand->get('fornitorePartnerName')
                );
            }
        }

        if (!$entity->get('productCategoryId')) {
            return;
        }

        $category = $this->entityManager->getEntityById(
            'ProductCategory',
            $entity->get('productCategoryId')
        );

        if (!$category) {
            return;
        }


        if (!$category->get('productBrandId')) {
            return;
        }

        if (
            $entity->get('productBrandId') &&
            $entity->get('productBrandId') !== $category->get('productBrandId')
        ) {
            $entity->set('productCategoryId', null);
            $entity->set('productCategoryName', null);

            return;
        }

        if (!$entity->get('productBrandId')) {
            $entity->set(
                'productBrandId',
                $category->get('productBrandId')
            );

            $entity->set(
                'productBrandName',
                $category->get('productBrandName')
            );
        }
    }

    // =====================================================
    // LEAD DA PROSPECT (opportunità senza appuntamento)
    // =====================================================


    private function syncAccountAndContactFromLead(Entity $entity): void
    {
        if (!$entity->get('leadId')) {
            return;
        }

        $lead = $this->entityManager->getEntityById('Lead', $entity->get('leadId'));

        if (!$lead) {
            return;
        }

        $accountId = $lead->get('createdAccountId');

        if (!$accountId) {
            $prospectId = $entity->get('prospectId') ?: $lead->get('prospectId');

            if ($prospectId) {
                $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);

                if ($prospect && $prospect->get('clienteId')) {
                    $accountId = $prospect->get('clienteId');
                }
            }
        }

        if ($accountId && $this->entityManager->getEntityById('Account', $accountId)) {
            if (!$entity->get('accountId')) {
                $account = $this->entityManager->getEntityById('Account', $accountId);
                $entity->set('accountId', $accountId);
                $entity->set('accountName', $account->get('name'));
            }
        }

        if (!$entity->get('accountId')) {
            return;
        }

        $prospect = null;

        if ($entity->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById('Prospect', $entity->get('prospectId'));
        }

        $referente = (new ReferenteContactService($this->entityManager))
            ->ensureForAccount($entity->get('accountId'), [
                'lead' => $lead,
                'prospect' => $prospect,
                'assignedUserId' => $entity->get('assignedUserId'),
            ]);

        if ($referente && !$entity->get('contactId')) {
            $entity->set('contactId', $referente['id']);
            $entity->set('contactName', $referente['name']);
        }

        if ($entity->isAttributeChanged('accountId') || $entity->isAttributeChanged('contactId')) {
            $this->entityManager->saveEntity($entity, ['silent' => true]);
        }
    }

    // =====================================================
    // APPUNTAMENTO DA LEAD (2.2.3)
    // =====================================================

    private function resolveAppuntamentoFromLead(Entity $entity): ?Entity
    {
        $leadId = $entity->get('leadId');

        if (!$leadId) {
            return null;
        }

        $leadWhere = [
            'OR' => [
                ['leadId' => $leadId],
                [
                    'parentType' => 'Lead',
                    'parentId' => $leadId,
                ],
            ],
        ];

        $prospectId = $entity->get('prospectId');

        if ($prospectId) {
            $byProspect = $this->entityManager
                ->getRDBRepository('Appuntamento')
                ->where(array_merge($leadWhere, ['prospectId' => $prospectId]))
                ->order('dateStart', 'DESC')
                ->findOne();

            if ($byProspect) {
                return $byProspect;
            }
        }

        return $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where($leadWhere)
            ->order('dateStart', 'DESC')
            ->findOne();
    }

    private function linkLeadFromProspectIfMissing(Entity $entity): void
    {
        if ($entity->get('leadId')) {
            return;
        }

        $prospectId = $entity->get('prospectId');

        if (!$prospectId) {
            return;
        }

        $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);

        if (!$prospect || !$prospect->get('leadId')) {
            return;
        }

        $lead = $this->entityManager->getEntityById('Lead', $prospect->get('leadId'));

        if (!$lead) {
            return;
        }

        $leadName = $lead->get('name');

        if (!$leadName) {
            $leadName = trim(($lead->get('firstName') ?: '') . ' ' . ($lead->get('lastName') ?: ''));
        }

        $entity->set('leadId', $lead->getId());
        $entity->set('leadName', $leadName);

        $this->entityManager->saveEntity($entity, ['silent' => true]);
    }

    private function syncLeadFieldsFromOpportunity(Entity $opportunity, Entity $lead): void
    {
        $sync = new \Espo\Custom\Services\LeadProspectSync($this->entityManager);

        $prospect = null;

        if ($opportunity->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById(
                'Prospect',
                $opportunity->get('prospectId')
            );
        }

        if (!$prospect) {
            $prospect = $sync->findProspectForLead($lead);
        }

        if ($prospect) {
            $sync->syncLeadFromProspect($lead, $prospect, true);
        }

        $scalarFromOpp = [
            'fornitorePartnerId',
            'fornitorePartnerName',
            'productBrandId',
            'productBrandName',
            'productCategoryId',
            'productCategoryName',
        ];

        foreach ($scalarFromOpp as $field) {
            $value = $opportunity->get($field);

            if ($value && !$lead->get($field)) {
                $lead->set($field, $value);
            }
        }

        $this->entityManager->saveEntity($lead, ['silent' => true]);
    }

}
