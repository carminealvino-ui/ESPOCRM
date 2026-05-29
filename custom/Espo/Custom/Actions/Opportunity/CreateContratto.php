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
//
// 1.7.1
// -----------------------------------------------------
// - Nome contratto solo via formula Quote (no name in PHP)
// - accountName / billingContactName prima del primo save
// - Fix accountId = id Prospect errato
//
// 1.7.2
// -----------------------------------------------------
// - Nome formula: priorità Lead (nome + contatto billingContact)
// - Risoluzione Lead anche da createdAccountId / leadName opportunità
//
// 1.8.0
// -----------------------------------------------------
// - Creazione/aggiornamento Cliente completo da Lead/Prospect/Opportunità
// - Fix accountId = Prospect senza clienteId
// - Sync accountId su opportunità
//
// 1.8.1
// -----------------------------------------------------
// - Referente (Contact) find-or-create su ogni Cliente (ReferenteContactService)
//
// 1.9.0
// -----------------------------------------------------
// - Indirizzi da Account/Lead/Prospect (non solo opportunità)
// - Categoria/tax con fallback; secondo save per formula nome; NO righe articoli
//
// 1.9.1
// -----------------------------------------------------
// - Non inserire voci (itemList) sul contratto alla creazione
//
// 1.10.0
// -----------------------------------------------------
// - Nome contratto impostato in PHP (formula salta se hookVersion CreateContratto)
// - Dopo save: nome corretto + itemList svuotato (no righe articoli)
//
// 1.11.0
// -----------------------------------------------------
// - Nome: data - cliente - descrizione - importo contratto
//
// 1.12.0
// -----------------------------------------------------
// - hookVersion quote sempre CreateContratto-* (non copiare 2.1.5 da opportunità)
// - Nome impostato in PHP prima e dopo save (formula non sovrascrive)
// - billingContactName da accountName se referente assente
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
use Espo\Custom\Services\ReferenteContactService;

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

            $cliente = $this->entityManager->createEntity('Account');

            $cliente->set(
                $this->buildAccountDataFromLead(
                    $lead,
                    $opportunity,
                    $teamsIds
                )
            );

            $this->entityManager->saveEntity($cliente);

            $accountId = $cliente->getId();

            $lead->set([
                'status' => 'Converted',
                'createdAccountId' => $accountId,
                'convertedAt' => date('Y-m-d H:i:s'),
            ]);

            $this->entityManager->saveEntity($lead);

            if ($lead->get('prospectId')) {
                $prospectForLink = $this->entityManager->getEntityById(
                    'Prospect',
                    $lead->get('prospectId')
                );
                $this->linkProspectToAccount(
                    $prospectForLink,
                    $accountId,
                    $cliente->get('name')
                );
            }
        }


        // =====================================================
        // PROSPECT -> CLIENTE (1.7.0)
        // =====================================================

        $prospect = null;

        if ($opportunity->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById(
                'Prospect',
                $opportunity->get('prospectId')
            );
        }

        if (!$accountId && $prospect) {

            $clienteId = $prospect->get('clienteId');

            if ($clienteId && $this->entityManager->getEntityById('Account', $clienteId)) {
                $accountId = $clienteId;
            } else {

                $cliente = $this->entityManager->createEntity('Account');

                $cliente->set(
                    $this->buildAccountDataFromProspect(
                        $prospect,
                        $opportunity,
                        $teamsIds,
                        $lead
                    )
                );

                $this->entityManager->saveEntity($cliente);

                $accountId = $cliente->getId();

                $this->linkProspectToAccount(
                    $prospect,
                    $accountId,
                    $cliente->get('name')
                );
            }
        }

        // Validazione accountId (evita ID prospect nel campo Cliente)
        if ($accountId && !$this->entityManager->getEntityById('Account', $accountId)) {
            $prospectAsAccount = $this->entityManager->getEntityById('Prospect', $accountId);

            if ($prospectAsAccount) {
                if ($prospectAsAccount->get('clienteId')
                    && $this->entityManager->getEntityById('Account', $prospectAsAccount->get('clienteId'))) {
                    $accountId = $prospectAsAccount->get('clienteId');
                } else {
                    if (!$prospect) {
                        $prospect = $prospectAsAccount;
                    }

                    $cliente = $this->entityManager->createEntity('Account');
                    $cliente->set(
                        $this->buildAccountDataFromProspect(
                            $prospectAsAccount,
                            $opportunity,
                            $teamsIds,
                            $lead
                        )
                    );
                    $this->entityManager->saveEntity($cliente);
                    $accountId = $cliente->getId();
                    $this->linkProspectToAccount(
                        $prospectAsAccount,
                        $accountId,
                        $cliente->get('name')
                    );
                }
            } else {
                $accountId = null;
            }
        }

        // =====================================================
        // ARRICCHISCI CLIENTE ESISTENTE (campi vuoti)
        // =====================================================

        if ($accountId) {
            $account = $this->entityManager->getEntityById('Account', $accountId);

            if ($account) {
                $patch = [];

                if (!$lead && $opportunity->get('leadId')) {
                    $lead = $this->entityManager->getEntityById(
                        'Lead',
                        $opportunity->get('leadId')
                    );
                }

                if ($lead) {
                    $patch = array_merge(
                        $patch,
                        $this->buildAccountDataFromLead($lead, $opportunity, $teamsIds)
                    );
                }

                if (!$prospect && $opportunity->get('prospectId')) {
                    $prospect = $this->entityManager->getEntityById(
                        'Prospect',
                        $opportunity->get('prospectId')
                    );
                }

                if ($prospect) {
                    $patch = array_merge(
                        $patch,
                        $this->buildAccountDataFromProspect(
                            $prospect,
                            $opportunity,
                            $teamsIds,
                            $lead
                        )
                    );
                }

                $patch = $this->mergeOnlyEmptyFields($account, $patch);

                if (!empty($patch)) {
                    $account->set($patch);
                    $this->entityManager->saveEntity($account);
                }

                if ($prospect) {
                    $this->linkProspectToAccount(
                        $prospect,
                        $accountId,
                        $account->get('name')
                    );
                }

            }
        }

        // =====================================================
        // ETICHETTE PER FORMULA NOME (prima del save) — priorità Lead
        // =====================================================

        if (!$lead && $accountId) {
            $lead = $this->entityManager
                ->getRDBRepository('Lead')
                ->where(['createdAccountId' => $accountId])
                ->order('createdAt', 'DESC')
                ->findOne();
        }

        $accountName = null;
        $billingContactId = null;
        $billingContactName = null;
        $shippingContactId = null;
        $shippingContactName = null;

        if ($accountId) {
            $account = $this->entityManager->getEntityById('Account', $accountId);
            if ($account) {
                $accountName = $account->get('name');
            }

            $referente = $this->ensureReferenteForCliente(
                $accountId,
                $lead,
                $prospect,
                $opportunity
            );

            $billingContactId = $referente['billingContactId'];
            $billingContactName = $referente['billingContactName'];
            $shippingContactId = $referente['shippingContactId'];
            $shippingContactName = $referente['shippingContactName'];
        }

        if (!$billingContactName && $accountName) {
            $billingContactName = $accountName;
        }

        if (!$billingContactName && $opportunity->get('leadName')) {
            $billingContactName = $opportunity->get('leadName');
        }

        if (!$shippingContactName) {
            $shippingContactName = $billingContactName;
        }

        // =====================================================
        // IMPORTO
        // =====================================================

        $amount = $this->resolveOpportunityAmount($opportunity);

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

        $taxCodeId = $this->resolveTaxCodeId($opportunity, $lead, $prospect);

        // =====================================================
        // TAX RATE
        // =====================================================

        $taxRate =
            $opportunity->get(
                'taxRate'
            );

        if ($taxRate === null || $taxRate === '') {
            $iva = $opportunity->get('iVA') ?? $opportunity->get('iVAListino');
            if ($iva !== null && $iva !== '') {
                $taxRate = ((float) $iva) / 100;
            }
        }

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

        $accountForQuote = $accountId
            ? $this->entityManager->getEntityById('Account', $accountId)
            : null;

        $addressData = $this->resolveQuoteAddressData(
            $opportunity,
            $accountForQuote,
            $lead,
            $prospect
        );

        // =====================================================
        // PARTNER / BRAND / CATEGORIA / HOOK (1.7.0)
        // =====================================================

        $partnerData = $this->resolvePartnerBrandCategory(
            $opportunity,
            $lead,
            $prospect
        );

        $fornitorePartnerId = $partnerData['fornitorePartnerId'];
        $fornitorePartnerName = $partnerData['fornitorePartnerName'];
        $productBrandId = $partnerData['productBrandId'];
        $productBrandName = $partnerData['productBrandName'];
        $productCategoryId = $partnerData['productCategoryId'];
        $productCategoryName = $partnerData['productCategoryName'];

        // Non usare hookVersion opportunità (es. 2.1.5): la formula Quote lo interpreta e ricalcola il nome.
        $hookVersion = 'CreateContratto-1.12.0';
        $installatoreId = $opportunity->get('installatoreId');

        $contractDisplayName = $this->buildContractDisplayName(
            $dateQuoted,
            $billingContactName ?: $accountName,
            $description,
            $amount
        );


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

            'opportunityId' =>
                $opportunity->getId(),

            'accountId' =>
                $accountId,

            'accountName' =>
                $accountName,

            'billingContactId' =>
                $billingContactId,

            'billingContactName' =>
                $billingContactName,

            'shippingContactId' =>
                $shippingContactId,

            'shippingContactName' =>
                $shippingContactName,

            'opportunityName' =>
                $opportunity->get('name'),

            'status' =>
                'Draft',

            'name' =>
                $contractDisplayName,

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

            // Nessuna riga articoli alla creazione (evita copia da opportunità/core)
            'itemList' =>
                [],

            // =================================================
            // INDIRIZZI (Account / Lead / Prospect)
            // =================================================

            'billingAddressStreet' =>
                $addressData['billingAddressStreet'],

            'billingAddressCity' =>
                $addressData['billingAddressCity'],

            'billingAddressPostalCode' =>
                $addressData['billingAddressPostalCode'],

            'billingAddressState' =>
                $addressData['billingAddressState'],

            'billingAddressCountry' =>
                $addressData['billingAddressCountry'],

            'shippingAddressStreet' =>
                $addressData['shippingAddressStreet'],

            'shippingAddressCity' =>
                $addressData['shippingAddressCity'],

            'shippingAddressPostalCode' =>
                $addressData['shippingAddressPostalCode'],

            'shippingAddressState' =>
                $addressData['shippingAddressState'],

            'shippingAddressCountry' =>
                $addressData['shippingAddressCountry'],

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

        $this->entityManager->saveEntity($quote);

        $this->refreshQuoteAfterCreate(
            $quote,
            $accountName,
            $billingContactName,
            $amount,
            $dateQuoted,
            $description
        );

        $this->finalizeOpportunityAfterContratto(
            $opportunity,
            $accountId,
            $billingContactId,
            $billingContactName
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


    // =====================================================
    // REFERENTE (Contact) — find or create
    // =====================================================

    private function ensureReferenteForCliente(
        string $accountId,
        $lead,
        $prospect,
        $opportunity
    ): array {
        $service = new ReferenteContactService($this->entityManager);

        $referente = $service->ensureForAccount($accountId, [
            'lead' => $lead,
            'prospect' => $prospect,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ]);

        if (!$referente) {
            return [
                'billingContactId' => null,
                'billingContactName' => null,
                'shippingContactId' => null,
                'shippingContactName' => null,
            ];
        }

        return [
            'billingContactId' => $referente['id'],
            'billingContactName' => $referente['name'],
            'shippingContactId' => $referente['id'],
            'shippingContactName' => $referente['name'],
        ];
    }

    // =====================================================
    // HELPER: NOME PERSONA / LEAD / PROSPECT
    // =====================================================

    private function resolveDisplayName($entity): ?string
    {
        if (!$entity) {
            return null;
        }

        $name = trim((string) $entity->get('name'));

        if ($name !== '') {
            return $name;
        }

        $name = trim(
            ($entity->get('firstName') ?? '') . ' ' . ($entity->get('lastName') ?? '')
        );

        if ($name !== '') {
            return $name;
        }

        if ($entity->getEntityType() === 'Lead') {
            $ref = $entity->get('referenteAziendale');
            if ($ref) {
                return trim((string) $ref);
            }
        }

        if ($entity->getEntityType() === 'Prospect') {
            $ref = $entity->get('ragioneSociale');
            if ($ref) {
                return trim((string) $ref);
            }
        }

        return null;
    }

    private function getLeadPhoneNumber($lead): ?string
    {
        if (!$lead) {
            return null;
        }

        if ($lead->get('phoneNumber')) {
            return $lead->get('phoneNumber');
        }

        if ($lead->get('telefono')) {
            return $lead->get('telefono');
        }

        $prospectId = $lead->get('prospectId');

        if ($prospectId) {
            $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);
            if ($prospect && $prospect->get('phoneNumber')) {
                return $prospect->get('phoneNumber');
            }
        }

        return null;
    }

    private function getLeadEmailAddress($lead): ?string
    {
        if (!$lead) {
            return null;
        }

        if ($lead->get('emailAddress')) {
            return $lead->get('emailAddress');
        }

        $prospectId = $lead->get('prospectId');

        if ($prospectId) {
            $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);
            if ($prospect && $prospect->get('emailAddress')) {
                return $prospect->get('emailAddress');
            }
        }

        return null;
    }

    private function buildAccountDataFromLead($lead, $opportunity, array $teamsIds): array
    {
        $prospect = null;
        $prospectId = $lead->get('prospectId');

        if ($prospectId) {
            $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);
        }

        $name = $this->resolveDisplayName($lead);

        if (!$name && $prospect) {
            $name = $this->resolveDisplayName($prospect);
        }

        $billingStreet = $lead->get('addressStreet') ?: ($prospect ? $prospect->get('addressStreet') : null);
        $billingCity = $lead->get('addressCity') ?: ($prospect ? $prospect->get('addressCity') : null);
        $billingPostal = $lead->get('addressPostalCode') ?: ($prospect ? $prospect->get('addressPostalCode') : null);
        $billingState = $lead->get('addressState') ?: ($prospect ? $prospect->get('addressState') : null);

        return [
            'name' => $name ?: 'Cliente da lead',
            'billingAddressStreet' => $billingStreet,
            'billingAddressCity' => $billingCity,
            'billingAddressPostalCode' => $billingPostal,
            'billingAddressState' => $billingState,
            'shippingAddressStreet' => $billingStreet,
            'shippingAddressCity' => $billingCity,
            'shippingAddressPostalCode' => $billingPostal,
            'shippingAddressState' => $billingState,
            'phoneNumber' => $this->getLeadPhoneNumber($lead),
            'emailAddress' => $this->getLeadEmailAddress($lead),
            'website' => $lead->get('website'),
            'description' => $lead->get('description')
                ?: $lead->get('descrizioneOpportunitGenerata'),
            'whatsApp' => $lead->get('whatsApp') ?: ($prospect ? $prospect->get('whatsApp') : null),
            'partitaIVA' => $lead->get('partitaIVA') ?: ($prospect ? $prospect->get('partitaIVA') : null),
            'originalLeadId' => $lead->getId(),
            'stato' => 'Nuovo',
            'type' => 'B2C',
            'segmento' => $lead->get('segmento') ?: 'B2C',
            'b2B' => $lead->get('b2B'),
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ];
    }

    private function buildAccountDataFromProspect($prospect, $opportunity, array $teamsIds, $lead = null): array
    {
        $name = $this->resolveDisplayName($prospect);

        if (!$name && $lead) {
            $name = $this->resolveDisplayName($lead);
        }

        $data = [
            'name' => $name ?: 'Cliente da prospect',
            'billingAddressStreet' => $prospect->get('addressStreet'),
            'billingAddressCity' => $prospect->get('addressCity'),
            'billingAddressPostalCode' => $prospect->get('addressPostalCode'),
            'billingAddressState' => $prospect->get('addressState'),
            'shippingAddressStreet' => $prospect->get('addressStreet'),
            'shippingAddressCity' => $prospect->get('addressCity'),
            'shippingAddressPostalCode' => $prospect->get('addressPostalCode'),
            'shippingAddressState' => $prospect->get('addressState'),
            'phoneNumber' => $prospect->get('phoneNumber'),
            'emailAddress' => $prospect->get('emailAddress'),
            'website' => $prospect->get('website'),
            'description' => $prospect->get('description'),
            'whatsApp' => $prospect->get('whatsApp'),
            'partitaIVA' => $prospect->get('partitaIVA'),
            'stato' => 'Nuovo',
            'type' => 'B2C',
            'segmento' => 'B2C',
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ];

        if ($lead) {
            $data['originalLeadId'] = $lead->getId();
            if (!$data['phoneNumber']) {
                $data['phoneNumber'] = $this->getLeadPhoneNumber($lead);
            }
            if (!$data['emailAddress']) {
                $data['emailAddress'] = $this->getLeadEmailAddress($lead);
            }
            if (!$data['description']) {
                $data['description'] = $lead->get('description')
                    ?: $lead->get('descrizioneOpportunitGenerata');
            }
        }

        return $data;
    }

    private function mergeOnlyEmptyFields($account, array $data): array
    {
        $patch = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $current = $account->get($key);

            if ($current === null || $current === '' || $current === []) {
                $patch[$key] = $value;
            }
        }

        return $patch;
    }

    private function finalizeOpportunityAfterContratto(
        $opportunity,
        ?string $accountId,
        ?string $contactId,
        ?string $contactName
    ): void {
        if (!$accountId) {
            return;
        }

        $account = $this->entityManager->getEntityById('Account', $accountId);

        if (!$account) {
            return;
        }

        $patch = [
            'accountId' => $accountId,
            'accountName' => $account->get('name'),
        ];

        if ($contactId) {
            $patch['contactId'] = $contactId;
            $patch['contactName'] = $contactName;
        }

        $opportunity->set($patch);

        $this->entityManager->saveEntity($opportunity, [
            'skipHooks' => true,
        ]);
    }

    private function linkProspectToAccount($prospect, string $accountId, ?string $accountName = null): void
    {
        if (!$prospect) {
            return;
        }

        $prospect->set([
            'clienteId' => $accountId,
            'clienteName' => $accountName ?: $prospect->get('clienteName'),
        ]);
        $this->entityManager->saveEntity($prospect);
    }

    private function resolveOpportunityAmount($opportunity)
    {
        $amount = $opportunity->get('amount');

        if (!$amount) {
            $amount = $opportunity->get('importoOpportunit');
        }

        return $amount;
    }

    private function resolveTaxCodeId($opportunity, $lead, $prospect): ?string
    {
        $taxCodeId = $opportunity->get('taxCodeId');

        if ($taxCodeId) {
            return $taxCodeId;
        }

        if ($lead && $lead->get('taxCodeId')) {
            return $lead->get('taxCodeId');
        }

        if ($prospect && $prospect->get('taxCodeId')) {
            return $prospect->get('taxCodeId');
        }

        $iva = $opportunity->get('iVA') ?? $opportunity->get('iVAListino');

        if ($iva === null || $iva === '') {
            return null;
        }

        $rate = ((float) $iva) / 100;

        $tax = $this->entityManager
            ->getRDBRepository('Tax')
            ->where(['rate' => $rate])
            ->findOne();

        if ($tax) {
            return $tax->getId();
        }

        $tax = $this->entityManager
            ->getRDBRepository('Tax')
            ->where(['rate' => (float) $iva])
            ->findOne();

        return $tax ? $tax->getId() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePartnerBrandCategory($opportunity, $lead, $prospect): array
    {
        $data = [
            'fornitorePartnerId' => $opportunity->get('fornitorePartnerId'),
            'fornitorePartnerName' => $opportunity->get('fornitorePartnerName'),
            'productBrandId' => $opportunity->get('productBrandId'),
            'productBrandName' => $opportunity->get('productBrandName'),
            'productCategoryId' => $opportunity->get('productCategoryId'),
            'productCategoryName' => $opportunity->get('productCategoryName'),
        ];

        foreach (['fornitorePartnerId', 'productBrandId', 'productCategoryId'] as $field) {
            if ($data[$field]) {
                continue;
            }

            if ($lead && $lead->get($field)) {
                $data[$field] = $lead->get($field);
                $nameField = str_replace('Id', 'Name', $field);
                $data[$nameField] = $lead->get($nameField);
            } elseif ($prospect && $prospect->get($field)) {
                $data[$field] = $prospect->get($field);
                $nameField = str_replace('Id', 'Name', $field);
                $data[$nameField] = $prospect->get($nameField);
            }
        }

        if (!$data['productCategoryId'] && $opportunity->get('lineaProdotto')) {
            $cat = $this->entityManager
                ->getRDBRepository('ProductCategory')
                ->where(['name' => $opportunity->get('lineaProdotto')])
                ->findOne();

            if ($cat) {
                $data['productCategoryId'] = $cat->getId();
                $data['productCategoryName'] = $cat->get('name');
            }
        }

        return $data;
    }

    /**
     * @return array<string, ?string>
     */
    private function resolveQuoteAddressData($opportunity, $account, $lead, $prospect): array
    {
        $billingFields = [
            'billingAddressStreet',
            'billingAddressCity',
            'billingAddressPostalCode',
            'billingAddressState',
            'billingAddressCountry',
        ];

        $leadMap = [
            'billingAddressStreet' => 'addressStreet',
            'billingAddressCity' => 'addressCity',
            'billingAddressPostalCode' => 'addressPostalCode',
            'billingAddressState' => 'addressState',
            'billingAddressCountry' => 'addressCountry',
        ];

        $out = [];

        foreach ($billingFields as $field) {
            $value = $opportunity->get($field);

            if (!$value && $account) {
                $value = $account->get($field);
            }

            if (!$value && $lead) {
                $value = $lead->get($leadMap[$field] ?? $field);
            }

            if (!$value && $prospect) {
                $value = $prospect->get($leadMap[$field] ?? $field);
            }

            $out[$field] = $value ?: null;
        }

        $shippingFields = [
            'shippingAddressStreet',
            'shippingAddressCity',
            'shippingAddressPostalCode',
            'shippingAddressState',
            'shippingAddressCountry',
        ];

        foreach ($shippingFields as $field) {
            $billingKey = str_replace('shipping', 'billing', $field);
            $value = $opportunity->get($field);

            if (!$value && $account) {
                $value = $account->get($field);
            }

            if (!$value) {
                $value = $out[$billingKey] ?? null;
            }

            $out[$field] = $value ?: null;
        }

        return $out;
    }

    private function refreshQuoteAfterCreate(
        $quote,
        ?string $accountName,
        ?string $billingContactName,
        $amount,
        $dateQuoted,
        ?string $description
    ): void {
        $quoteId = $quote->getId();

        if (!$quoteId) {
            return;
        }

        $fresh = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$fresh) {
            return;
        }

        $clienteLabel = $billingContactName ?: $accountName;

        if (!$clienteLabel) {
            $clienteLabel = $fresh->get('billingContactName')
                ?: $fresh->get('accountName');
        }

        if (!$clienteLabel && $fresh->get('accountId')) {
            $account = $this->entityManager->getEntityById(
                'Account',
                $fresh->get('accountId')
            );

            if ($account) {
                $clienteLabel = $account->get('name');
            }
        }

        $importo = $amount ?: $fresh->get('importoContratto') ?: $fresh->get('amount');

        $descrizione = trim((string) ($description ?: $fresh->get('description')));

        if ($descrizione === '' && $fresh->get('opportunityId')) {
            $opp = $this->entityManager->getEntityById(
                'Opportunity',
                $fresh->get('opportunityId')
            );

            if ($opp) {
                $descrizione = trim((string) $opp->get('description'));
            }
        }

        $patch = [
            'name' => $this->buildContractDisplayName(
                $dateQuoted ?: $fresh->get('dateQuoted'),
                $clienteLabel,
                $descrizione,
                $importo
            ),
            'itemList' => [],
            'hookVersion' => 'CreateContratto-1.12.0',
        ];

        foreach ([
            'accountName',
            'billingContactName',
            'shippingContactName',
            'importoContratto',
            'amount',
            'billingAddressStreet',
            'billingAddressCity',
            'billingAddressPostalCode',
            'billingAddressState',
            'shippingAddressStreet',
            'shippingAddressCity',
            'shippingAddressPostalCode',
            'shippingAddressState',
            'productCategoryId',
            'productCategoryName',
        ] as $field) {
            $current = $fresh->get($field);
            $previous = $quote->get($field);

            if (
                ($current === null || $current === '' || $current === [])
                && $previous !== null
                && $previous !== ''
                && $previous !== []
            ) {
                $patch[$field] = $previous;
            }
        }

        $fresh->set($patch);

        $this->entityManager->saveEntity($fresh, [
            'skipHooks' => true,
        ]);
    }

    private function buildContractDisplayName(
        ?string $dateQuoted,
        ?string $clienteLabel,
        ?string $description,
        $amount
    ): string {
        $cliente = trim((string) $clienteLabel);
        $descrizione = trim((string) $description);
        $importoStr = '';

        if ($amount !== null && $amount !== '') {
            $importoStr = number_format((float) $amount, 0, ',', '.');
        }

        return ($dateQuoted ?: '')
            . ' - '
            . $cliente
            . ' - '
            . $descrizione
            . ' - €. '
            . $importoStr;
    }

}
