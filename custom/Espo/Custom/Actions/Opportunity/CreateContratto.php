<?php

namespace Espo\Custom\Actions\Opportunity;

use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Crea contratto (Quote) da Opportunity chiusa positivamente.
 *
 * VERSIONE: 2.2.0
 * - Risolve Cliente (account) da Account, Prospect.cliente o Lead
 * - Evita ID Prospect nel campo account
 * - Copia indirizzi, contatti e data contratto
 * - IVA, totali, CAP, installatore, contatto contraente da prospect/lead
 */
class CreateContratto
{
    private const CLOSED_WON_STAGES = [
        'Closed Won',
        'Chiuso Positivamente',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function run(Entity $opportunity): object
    {
        if (!$opportunity) {
            throw new \RuntimeException('Opportunita non trovata.');
        }

        $this->assertClosedWon($opportunity);

        $existing = $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['opportunityId' => $opportunity->getId()])
            ->findOne();

        if ($existing) {
            return (object) [
                'quoteId' => $existing->getId(),
                'quoteName' => $existing->get('name'),
                'existing' => true,
            ];
        }

        $teamsIds = $opportunity->getLinkMultipleIdList('teams');
        $lead = $this->resolveLead($opportunity);
        $accountId = $this->resolveAccountId($opportunity, $lead, $teamsIds);

        $quote = $this->buildQuote($opportunity, $accountId, $teamsIds, $lead);

        $this->entityManager->saveEntity($quote);

        $provvigione = null;

        try {
            $provvigione = $this->provvigioneManager->createConsolidataForQuote($opportunity, $quote);
        } catch (\Throwable $e) {
            // Fase 1: il contratto deve esistere anche se le provvigioni non sono configurate.
        }

        return (object) [
            'quoteId' => $quote->getId(),
            'quoteName' => $quote->get('name'),
            'existing' => false,
            'provvigioneId' => $provvigione?->getId(),
        ];
    }

    private function assertClosedWon(Entity $opportunity): void
    {
        $stage = $opportunity->get('stage');

        if (in_array($stage, self::CLOSED_WON_STAGES, true)) {
            return;
        }

        $probability = $opportunity->get('probability');

        if ($probability === 100 || $probability === '100') {
            return;
        }

        throw new \RuntimeException(
            'Il contratto si crea solo su opportunita concluse positivamente.'
        );
    }

    private function resolveLead(Entity $opportunity): ?Entity
    {
        $leadId = $opportunity->get('leadId');

        if (!$leadId) {
            return null;
        }

        return $this->entityManager->getEntityById('Lead', $leadId);
    }

    private function resolveAccountId(Entity $opportunity, ?Entity $lead, array $teamsIds): ?string
    {
        $rawAccountId = $opportunity->get('accountId');

        if ($rawAccountId && $this->isValidAccountId((string) $rawAccountId)) {
            return (string) $rawAccountId;
        }

        $prospectId = $opportunity->get('prospectId');

        if ($prospectId) {
            $prospect = $this->entityManager->getEntityById('Prospect', (string) $prospectId);

            if ($prospect) {
                $clienteId = $prospect->get('clienteId');

                if ($clienteId && $this->isValidAccountId((string) $clienteId)) {
                    return (string) $clienteId;
                }

                return $this->createAccountFromProspect($prospect, $opportunity, $teamsIds);
            }
        }

        if ($lead) {
            $createdAccountId = $lead->get('createdAccountId');

            if ($createdAccountId && $this->isValidAccountId((string) $createdAccountId)) {
                return (string) $createdAccountId;
            }

            return $this->createAccountFromLead($lead, $opportunity, $teamsIds);
        }

        return null;
    }

    private function isValidAccountId(string $id): bool
    {
        return $this->entityManager->getEntityById('Account', $id) !== null;
    }

    private function createAccountFromProspect(Entity $prospect, Entity $opportunity, array $teamsIds): string
    {
        $cliente = $this->entityManager->createEntity('Account');

        $name = $prospect->get('name');

        if (!$name) {
            $name = trim(
                ($prospect->get('firstName') ?? '') . ' ' . ($prospect->get('lastName') ?? '')
            );
        }

        $cliente->set([
            'name' => $name ?: 'Cliente da prospect',
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

        $prospect->set([
            'clienteId' => $cliente->getId(),
            'clienteName' => $cliente->get('name'),
        ]);

        $this->entityManager->saveEntity($prospect);

        return $cliente->getId();
    }

    private function createAccountFromLead(Entity $lead, Entity $opportunity, array $teamsIds): string
    {
        $cliente = $this->entityManager->createEntity('Account');

        $cliente->set([
            'name' => $lead->get('name'),
            'billingAddressStreet' => $lead->get('addressStreet'),
            'billingAddressCity' => $lead->get('addressCity'),
            'billingAddressPostalCode' => $lead->get('addressPostalCode'),
            'billingAddressState' => $lead->get('addressState'),
            'phoneNumber' => $lead->get('phoneNumber'),
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId'),
            'type' => 'B2C',
            'segmento' => 'B2C',
        ]);

        $this->entityManager->saveEntity($cliente);

        $lead->set([
            'status' => 'Converted',
            'createdAccountId' => $cliente->getId(),
            'convertedAt' => date('Y-m-d H:i:s'),
        ]);

        $this->entityManager->saveEntity($lead);

        return $cliente->getId();
    }

    private function buildQuote(
        Entity $opportunity,
        ?string $accountId,
        array $teamsIds,
        ?Entity $lead
    ): Entity {
        $amount = $opportunity->get('amount') ?: $opportunity->get('importoOpportunita');

        $dataInstallazione = $opportunity->get('installazione');
        $dataAttivazione = $opportunity->get('dataAttivazione');
        $prospect = null;

        if ($opportunity->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById(
                'Prospect',
                (string) $opportunity->get('prospectId')
            );
        }

        $appuntamento = null;

        if ($opportunity->get('appuntamentoId')) {
            $appuntamento = $this->entityManager->getEntityById(
                'Appuntamento',
                $opportunity->get('appuntamentoId')
            );

            if ($appuntamento) {
                $dataAttivazione = $dataAttivazione ?: $appuntamento->get('dataAttivazione');
                $dataInstallazione = $dataInstallazione ?: $appuntamento->get('dataInstallazione');
            }
        }

        $prezzoCodice = $opportunity->get('prezzoCodiceIvaEsclusa');
        $minusPlus = null;

        if ($amount && $prezzoCodice && (float) $amount > (float) $prezzoCodice) {
            $minusPlus = (float) $amount - (float) $prezzoCodice;
        }

        $quote = $this->entityManager->createEntity('Quote');

        $quoteName = $opportunity->get('name') ?: $opportunity->get('description');
        $dateQuoted = $this->resolveDateQuoted($opportunity, $appuntamento);

        $quote->set([
            'name' => $quoteName,
            'status' => 'Draft',
            'opportunityId' => $opportunity->getId(),
            'opportunityName' => $opportunity->get('name'),
            'amount' => $amount,
            'amountCurrency' => $opportunity->get('amountCurrency'),
            'importoContratto' => $amount,
            'importoContrattoCurrency' => $opportunity->get('amountCurrency')
                ?: $opportunity->get('importoOpportunitaCurrency'),
            'minusPlus' => $minusPlus,
            'totalPrezzoCodice' => $prezzoCodice,
            'prezzoCodice' => $prezzoCodice,
            'dateQuoted' => $dateQuoted,
            'description' => $opportunity->get('description'),
            'taxRate' => $opportunity->get('iVA') ?? $opportunity->get('taxRate'),
            'taxCodeId' => $opportunity->get('taxCodeId'),
            'isTaxInclusive' => true,
            'priceBookId' => $opportunity->get('priceBookId'),
            'itemList' => [],
            'assignedUserId' => $opportunity->get('assignedUserId'),
            'teamsIds' => $teamsIds,
            'fornitorePartnerId' => $opportunity->get('fornitorePartnerId'),
            'fornitorePartnerName' => $opportunity->get('fornitorePartnerName'),
            'productBrandId' => $opportunity->get('productBrandId'),
            'productBrandName' => $opportunity->get('productBrandName'),
            'productCategoryId' => $opportunity->get('productCategoryId'),
            'productCategoryName' => $opportunity->get('productCategoryName'),
            'dataInstallazione' => $dataInstallazione,
            'dataAttivazione' => $dataAttivazione,
            'dataCompetenza' => $dataAttivazione
                ? date('Y-m-01', strtotime($dataAttivazione))
                : date('Y-m-01', strtotime($dateQuoted)),
        ]);

        if ($accountId) {
            $this->applyAccountToQuote($quote, $accountId, $lead, $prospect, $opportunity);
        } else {
            $this->applyAddressesFromSource($quote, $opportunity, $lead, $prospect);
        }

        $this->ensureBillingContact($quote, $accountId, $lead, $prospect);
        $this->applyOpportunityExtras($quote, $opportunity);
        $this->applyTaxAndTotals($quote, $opportunity);

        return $quote;
    }

    private function resolveDateQuoted(Entity $opportunity, ?Entity $appuntamento): string
    {
        foreach (['closeDate', 'dateClosed', 'dataOpportunit'] as $field) {
            $value = $opportunity->get($field);

            if ($value) {
                return substr((string) $value, 0, 10);
            }
        }

        if ($appuntamento && $appuntamento->get('dateStart')) {
            return substr((string) $appuntamento->get('dateStart'), 0, 10);
        }

        return date('Y-m-d');
    }


    private function ensureBillingContact(
        Entity $quote,
        ?string $accountId,
        ?Entity $lead,
        ?Entity $prospect
    ): void {
        if ($quote->get('billingContactId')) {
            return;
        }

        $source = $prospect ?? $lead;

        if (!$source) {
            return;
        }

        $firstName = $source->get('firstName');
        $lastName = $source->get('lastName');
        $name = $source->get('name');

        if (!$name && ($firstName || $lastName)) {
            $name = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
        }

        if (!$name) {
            return;
        }

        $contact = $this->entityManager->createEntity('Contact');

        $contact->set([
            'firstName' => $firstName ?: $name,
            'lastName' => $lastName,
            'name' => $name,
            'addressStreet' => $source->get('addressStreet'),
            'addressCity' => $source->get('addressCity'),
            'addressPostalCode' => $source->get('addressPostalCode'),
            'addressState' => $source->get('addressState'),
            'phoneNumber' => $source->get('phoneNumber'),
            'accountId' => $accountId,
            'assignedUserId' => $quote->get('assignedUserId'),
        ]);

        $this->entityManager->saveEntity($contact);

        $quote->set([
            'billingContactId' => $contact->getId(),
            'billingContactName' => $contact->get('name'),
            'shippingContactId' => $contact->getId(),
            'shippingContactName' => $contact->get('name'),
        ]);
    }

    private function applyOpportunityExtras(Entity $quote, Entity $opportunity): void
    {
        if ($opportunity->get('cAPId')) {
            $quote->set([
                'cAPId' => $opportunity->get('cAPId'),
                'cAPName' => $opportunity->get('cAPName'),
            ]);
        }

        $installatore = trim((string) ($opportunity->get('installatore') ?? ''));

        if ($installatore === '') {
            return;
        }

        $provider = $this->entityManager
            ->getRDBRepository('ShippingProvider')
            ->where(['name' => $installatore])
            ->findOne();

        if ($provider) {
            $quote->set([
                'shippingProviderId' => $provider->getId(),
                'shippingProviderName' => $provider->get('name'),
            ]);
        }
    }

    private function applyTaxAndTotals(Entity $quote, Entity $opportunity): void
    {
        $amount = (float) ($quote->get('amount') ?? 0);
        $taxRate = $this->normalizeTaxRate(
            $opportunity->get('iVA') ?? $quote->get('taxRate') ?? 0.1
        );

        $taxAmount = 0.0;

        if ($amount > 0) {
            if ($quote->get('isTaxInclusive')) {
                $net = $amount / (1 + $taxRate);
                $taxAmount = $amount - $net;
            } else {
                $taxAmount = $amount * $taxRate;
            }
        }

        $quote->set([
            'taxRate' => $taxRate,
            'aliquotaIVA' => round($taxRate * 100, 2),
            'taxAmount' => $taxAmount,
            'taxAmountCurrency' => $quote->get('amountCurrency'),
            'grandTotalAmount' => $amount,
            'grandTotalAmountCurrency' => $quote->get('amountCurrency'),
        ]);
    }

    private function normalizeTaxRate(mixed $value): float
    {
        $rate = (float) $value;

        if ($rate <= 0) {
            return 0.1;
        }

        if ($rate > 1) {
            return $rate / 100;
        }

        return $rate;
    }

    private function applyAccountToQuote(
        Entity $quote,
        string $accountId,
        ?Entity $lead,
        ?Entity $prospect,
        Entity $opportunity
    ): void {
        $account = $this->entityManager->getEntityById('Account', $accountId);

        if (!$account) {
            return;
        }

        $quote->set([
            'accountId' => $accountId,
            'accountName' => $account->get('name'),
            'billingAddressStreet' => $account->get('billingAddressStreet'),
            'billingAddressCity' => $account->get('billingAddressCity'),
            'billingAddressPostalCode' => $account->get('billingAddressPostalCode'),
            'billingAddressState' => $account->get('billingAddressState'),
            'shippingAddressStreet' => $account->get('shippingAddressStreet')
                ?: $account->get('billingAddressStreet'),
            'shippingAddressCity' => $account->get('shippingAddressCity')
                ?: $account->get('billingAddressCity'),
            'shippingAddressPostalCode' => $account->get('shippingAddressPostalCode')
                ?: $account->get('billingAddressPostalCode'),
            'shippingAddressState' => $account->get('shippingAddressState')
                ?: $account->get('billingAddressState'),
        ]);

        $billingContactId = $account->get('billingContactId');

        if ($billingContactId) {
            $contact = $this->entityManager->getEntityById('Contact', $billingContactId);

            if ($contact) {
                $quote->set([
                    'billingContactId' => $billingContactId,
                    'billingContactName' => $contact->get('name'),
                    'shippingContactId' => $billingContactId,
                    'shippingContactName' => $contact->get('name'),
                ]);

                return;
            }
        }

        if ($lead && $lead->get('createdContactId')) {
            $contact = $this->entityManager->getEntityById(
                'Contact',
                (string) $lead->get('createdContactId')
            );

            if ($contact) {
                $quote->set([
                    'billingContactId' => $contact->getId(),
                    'billingContactName' => $contact->get('name'),
                    'shippingContactId' => $contact->getId(),
                    'shippingContactName' => $contact->get('name'),
                ]);
            }

            return;
        }

        $this->applyAddressesFromSource($quote, $opportunity, $lead, $prospect);
    }

    private function applyAddressesFromSource(
        Entity $quote,
        Entity $opportunity,
        ?Entity $lead,
        ?Entity $prospect
    ): void {
        $source = $prospect ?? $lead;

        if (!$source) {
            $quote->set([
                'billingAddressStreet' => $opportunity->get('billingAddressStreet'),
                'billingAddressCity' => $opportunity->get('billingAddressCity'),
                'billingAddressPostalCode' => $opportunity->get('billingAddressPostalCode'),
                'billingAddressState' => $opportunity->get('billingAddressState'),
            ]);

            return;
        }

        $quote->set([
            'billingAddressStreet' => $source->get('addressStreet'),
            'billingAddressCity' => $source->get('addressCity'),
            'billingAddressPostalCode' => $source->get('addressPostalCode'),
            'billingAddressState' => $source->get('addressState'),
            'shippingAddressStreet' => $source->get('addressStreet'),
            'shippingAddressCity' => $source->get('addressCity'),
            'shippingAddressPostalCode' => $source->get('addressPostalCode'),
            'shippingAddressState' => $source->get('addressState'),
        ]);
    }
}
