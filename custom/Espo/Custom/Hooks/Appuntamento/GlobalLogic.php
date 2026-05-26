<?php
// ========================================
// VERSIONE: 1.6.5
// DATA: 2026-05-22
// AUTORE: CARMINE ALVINO + CHATGPT
// FILE:
// custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php
// ========================================
//
// STORICO VERSIONI
// ========================================
//
// 1.5.x
// ----------------------------------------
// ✔ Sync Prospect → Appuntamento
// ✔ CAP automatico
// ✔ Location automatica
// ✔ Description automatica
// ✔ Colori automatici
// ✔ Creazione Lead automatica
//
// 1.5.9
// ----------------------------------------
// ✔ Fix sincronizzazione indirizzo
// ✔ Fix location dinamica
//
// 1.6.0
// ----------------------------------------
// ✔ Relazione reale con Lead
// ✔ Nome Lead corretto
// ✔ Fix indirizzo Appuntamento
// ✔ Fix mappe Google
// ✔ Fix CAP
// ✔ Fix colori calendario
// ✔ Fix sottostato Chiuso Positivamente
// ✔ Fix assegnazione ADMIN
//
// FIX 07-05-2026 16:36
// ----------------------------------------
// ✔ Mapping stato Lead automatico
// ✔ Assegnazione utente Lead automatica
// ✔ Sync assegnatario Appuntamento → Lead
//
// 1.6.1
// ----------------------------------------
// ✔ FIX PRODUZIONE SYNC PROSPECT → LEAD
// ✔ Su Lead esistente compila campi vuoti da Prospect
// ✔ Indirizzo, CAP, telefono, azienda
// ✔ NON sovrascrive dati manuali gia' presenti
//
// ROLLBACK:
// backup/hooks_cleanup/
// backup-appuntamento-globallogic-1.6.0-prospect-lead-stabile.php
//
// 1.6.2
// ----------------------------------------
// ✔ REGOLA PRODUZIONE LEAD AUTOMATICO
// ✔ Qualsiasi Appuntamento con status = Held genera/aggiorna Lead
// ✔ Il sottostato NON blocca la creazione Lead
// ✔ Il sottostato decide solo Lead.status
//
// MAPPATURA LEAD.status:
// - sottostato Pending -> In Process
// - sottostato Non Interessato -> Dead
// - sottostato Chiuso Positivamente -> Assegnato
// - altri sottostati Held -> In Process
//
// ROLLBACK:
// backup/hooks_cleanup/
// backup-appuntamento-globallogic-1.6.1-held-lead-stabile.php
//
// 1.6.3
// ----------------------------------------
// ✔ FIX PRODUZIONE DESCRIZIONE LEAD
// ✔ Lead.description deve arrivare da Prospect.description
// ✔ Non usa la description automatica dell'Appuntamento
// ✔ Campo UI: Descrizione Attività
//
// ROLLBACK:
// backup/hooks_cleanup/
// backup-appuntamento-globallogic-1.6.2-description-prospect-stabile.php
//
// 1.6.4
// ----------------------------------------
// ✔ FIX PRODUZIONE FORNITORE / BRAND
// ✔ Sync fornitorePartner e productBrand
//    Prospect/Lead -> Appuntamento
// ✔ Sync fornitorePartner e productBrand
//    Prospect -> Lead
// ✔ NON sovrascrive dati manuali gia' presenti sul Lead
//
// ROLLBACK:
// backup/hooks_cleanup/
// backup-appuntamento-globallogic-1.6.3-brand-partner-sync-stabile.php
//
// 1.6.5
// ----------------------------------------
// ✔ FIX PRODUZIONE PRODUCT BRAND
// ✔ Se productBrand manca, viene risolto dal vecchio campo azienda
// ✔ Usa ProductBrand.name = azienda
// ✔ Compila anche fornitorePartner dal brand
//
// ROLLBACK:
// backup/hooks_cleanup/
// backup-appuntamento-globallogic-1.6.4-brand-fallback-stabile.php
//
// 1.7.0 (25-05-2026)
// -----------------------------------------------------
// - Sync Lead completo da Prospect (email, WhatsApp, telefono da wa.me)
// - LeadProspectSync + repair massivo Lead/action/repairFromProspect
//
// MAPPATURA:
//
// Appuntamento.sottostato
// → Lead.status
//
// Pending
// → In Process
//
// Non Interessato
// → Dead
//
// Chiuso Positivamente
// → Assegnato
//
// ========================================

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\ORM\EntityManager;
use Espo\Custom\Services\LeadProspectSync;
use Espo\Custom\Services\LineaProdottoCategorySync;
use Espo\ORM\Entity;

class GlobalLogic
{
    private EntityManager $entityManager;

    private static bool $processing = false;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        if (self::$processing) {
            return;
        }

        self::$processing = true;

        try {

            // ========================================
            // VERSIONE HOOK
            // ========================================

            $entity->set(
                'hookVersion',
                '1.7.1'
            );

            // ========================================
            // RECUPERO STATUS
            // ========================================

            $status = $entity->get('status');

            $sottostato = $entity->get('sottostato');

            // ========================================
            // RECUPERO PROSPECT
            // ========================================

            $prospect = null;

            if (

                $entity->get('parentType') === 'Prospect' &&
                $entity->get('parentId')

            ) {

                $prospect = $this->entityManager->getEntity(
                    'Prospect',
                    $entity->get('parentId')
                );
            }

            if (

                !$prospect &&
                $entity->get('prospectId')

            ) {

                $prospect = $this->entityManager->getEntity(
                    'Prospect',
                    $entity->get('prospectId')
                );
            }

            // ========================================
            // RECUPERO LEAD
            // ========================================

            $lead = null;

            if (

                $entity->get('parentType') === 'Lead' &&
                $entity->get('parentId')

            ) {

                $lead = $this->entityManager->getEntity(
                    'Lead',
                    $entity->get('parentId')
                );
            }

            // ========================================
            // SORGENTE DATI
            // PRIORITÀ:
            // 1. LEAD
            // 2. PROSPECT
            // ========================================

            $source = $lead ?: $prospect;

            // ========================================
            // FIX RELAZIONE PROSPECT
            // ========================================

            if ($prospect) {

                $entity->set(
                    'prospectId',
                    $prospect->getId()
                );

                $entity->set(
                    'prospectName',
                    $prospect->get('name')
                );
            }

            // ========================================
            // FIX RELAZIONE LEAD
            // ========================================

            if ($lead) {

                $entity->set(
                    'parentType',
                    'Lead'
                );

                $entity->set(
                    'parentId',
                    $lead->getId()
                );

                // ========================================
                // FIX NOME LEAD
                // ========================================

                $leadName = $lead->get('name');

                if (!$leadName) {

                    $firstName = $lead->get('firstName');
                    $lastName = $lead->get('lastName');

                    $leadName = trim(
                        $firstName . ' ' . $lastName
                    );
                }

                if (!$leadName) {
                    $leadName = $lead->get('phoneNumber');
                }

                $entity->set(
                    'parentName',
                    $leadName
                );
            }

            // ========================================
            // SYNC FORNITORE / BRAND (1.6.4)
            // ========================================

            if ($source) {

                $this->syncBrandPartnerFromSource(
                    $entity,
                    $source
                );

                $this->resolveBrandPartnerFromAzienda(
                    $entity,
                    $source->get('azienda')
                );
            }

            $this->normalizeProductCascade($entity);


            // ========================================
            // FIX CAP
            // ========================================

            if (

                $source &&
                $source->get('cAPId')

            ) {

                $entity->set(
                    'cAPId',
                    $source->get('cAPId')
                );
            }

            // ========================================
            // FIX INDIRIZZO APPUNTAMENTO
            // ========================================

            if ($source) {

                $entity->set(
                    'indirizzoStreet',
                    $source->get('addressStreet')
                );

                $entity->set(
                    'indirizzoCity',
                    $source->get('addressCity')
                );

                $entity->set(
                    'indirizzoPostalCode',
                    $source->get('addressPostalCode')
                );

                $entity->set(
                    'indirizzoState',
                    $source->get('addressState')
                );

                $entity->set(
                    'indirizzoCountry',
                    $source->get('addressCountry')
                );
            }

            // ========================================
            // FIX LOCATION
            // ========================================

            if ($source) {

                $location = [];

                if ($source->get('addressStreet')) {
                    $location[] = $source->get('addressStreet');
                }

                if ($source->get('addressPostalCode')) {
                    $location[] = $source->get('addressPostalCode');
                }

                if ($source->get('addressCity')) {
                    $location[] = $source->get('addressCity');
                }

                if ($source->get('addressState')) {
                    $location[] = $source->get('addressState');
                }

                if ($source->get('addressCountry')) {
                    $location[] = $source->get('addressCountry');
                }

                $entity->set(
                    'location',
                    implode(', ', $location)
                );
            }

            // ========================================
            // DESCRIPTION AUTOMATICA
            // ========================================

            if (

                !$entity->get('description') &&
                $source

            ) {

                $description = [];

                $clienteNome = $source->get('name');

                if (!$clienteNome) {

                    $clienteNome = trim(
                        ($source->get('firstName') ?: '') .
                        ' ' .
                        ($source->get('lastName') ?: '')
                    );
                }

                if ($clienteNome) {
                    $description[] = 'Cliente: ' . $clienteNome;
                }

                if ($source->get('phoneNumber')) {
                    $description[] = 'Telefono: ' . $source->get('phoneNumber');
                }

                if ($entity->get('noteCallCenter')) {
                    $description[] = 'Note Call Center: ' . $entity->get('noteCallCenter');
                }

                $entity->set(
                    'description',
                    implode("\n", $description)
                );
            }

            // ========================================
            // COLORI CALENDARIO
            // ========================================

            if (

                $status === 'Held' &&
                $sottostato === 'Chiuso Positivamente'

            ) {

                $entity->set(
                    'color',
                    '#00aa00'
                );

            } elseif ($status === 'Held') {

                $entity->set(
                    'color',
                    '#006400'
                );

            } elseif ($status === 'Planned') {

                $entity->set(
                    'color',
                    '#0000ff'
                );

            } elseif (

                $status === 'Not Held' ||
                $status === 'Ingestibile'

            ) {

                $entity->set(
                    'color',
                    '#cc0000'
                );
            }

            // ========================================
            // CREAZIONE LEAD AUTOMATICA (1.6.2)
            // ========================================
            //
            // REGOLA PRODUZIONE:
            // qualsiasi Appuntamento con status = Held genera
            // o aggiorna Lead. Il sottostato serve solo per
            // mappare Lead.status e NON blocca la creazione.
            //
            // ========================================

            if (

                $status === 'Held' &&
                $prospect

            ) {

                $leadSync = new LeadProspectSync($this->entityManager);

                $existingLead = $leadSync->findExistingLeadByProspect($prospect);

                $lead = $existingLead;

                $isNewLead = !$lead;

                // ========================================
                // MAPPING STATO LEAD (1.6.2)
                // ========================================

                $leadStatus = 'In Process';

                if ($sottostato === 'Pending') {

                    $leadStatus = 'In Process';

                } elseif ($sottostato === 'Non Interessato') {

                    $leadStatus = 'Dead';

                } elseif ($sottostato === 'Chiuso Positivamente') {

                    $leadStatus = 'Assegnato';
                }

                // ========================================
                // RECUPERO UTENTE ASSEGNATO
                // ========================================

                $assignedUsersIds = $entity->get('assignedUsersIds') ?: [];

                $leadAssignedUserId = null;

                if (!empty($assignedUsersIds)) {

                    $leadAssignedUserId = $assignedUsersIds[0];
                }

                // ========================================
                // CREA LEAD (scheletro) + SYNC COMPLETO 1.7.0
                // ========================================

                if (!$lead) {

                    $lead = $this->entityManager->createEntity('Lead');

                    $lead->set([
                        'status' => $leadStatus,
                        'assignedUserId' => $leadAssignedUserId,
                    ]);

                    $this->entityManager->saveEntity($lead);
                }

                if ($lead) {

                    $leadSync->syncLeadFromProspect(
                        $lead,
                        $prospect,
                        !$isNewLead
                    );

                    $lead->set('status', $leadStatus);

                    if ($leadAssignedUserId) {
                        $lead->set('assignedUserId', $leadAssignedUserId);
                    }

                    $this->entityManager->saveEntity($lead);
                }

                // ========================================
                // RELAZIONE APPUNTAMENTO → LEAD
                // ========================================

                $entity->set(
                    'parentType',
                    'Lead'
                );

                $entity->set(
                    'parentId',
                    $lead->getId()
                );

                // ========================================
                // FIX NOME LEAD
                // ========================================

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
                    'parentName',
                    $leadName
                );
            }

            // ========================================
            // FIX ASSEGNAZIONE ADMIN
            // ========================================

            if (

                $status === 'Not Held' ||
                $status === 'Ingestibile'

            ) {

                // ========================================
                // RESET UTENTI ASSEGNATI
                // ========================================

                $entity->set(
                    'assignedUsersIds',
                    []
                );

                // ========================================
                // ASSEGNA SOLO ADMIN
                // USER ID = 1
                // ========================================

                $entity->set(
                    'assignedUsersIds',
                    ['1']
                );
            }

        } finally {

            self::$processing = false;
        }
    }

    // ========================================
    // SYNC PROSPECT -> LEAD (1.6.1)
    // ========================================

    private function syncLeadFromProspect(
        Entity $lead,
        Entity $prospect
    ): void {
        (new LeadProspectSync($this->entityManager))
            ->syncLeadFromProspect($lead, $prospect, true);
    }

    // ========================================
    // SYNC FORNITORE / BRAND (1.6.4)
    // ========================================

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

            $entity->set(
                $field,
                $source->get($field)
            );
        }
    }

    // ========================================
    // FALLBACK BRAND DA AZIENDA (1.6.5)
    // ========================================

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

        if ($brand->get('fornitorePartnerId')) {

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

    // ========================================
    // SET CAMPO SOLO SE VUOTO (1.6.1)
    // ========================================

    private function setLeadFieldIfEmpty(
        Entity $lead,
        string $field,
        $value
    ): void {

        if ($value === null || $value === '') {
            return;
        }

        if ($lead->get($field)) {
            return;
        }

        $lead->set(
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

}
