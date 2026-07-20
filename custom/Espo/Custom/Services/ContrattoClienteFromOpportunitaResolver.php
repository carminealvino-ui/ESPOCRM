<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Risolve Cliente (Account) + Contraente (Contact) da Opportunità.
 * Stessa logica base di CreateContratto per bonifica contratti senza cliente.
 */
class ContrattoClienteFromOpportunitaResolver
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @return array{
     *     accountId: ?string,
     *     accountName: ?string,
     *     billingContactId: ?string,
     *     billingContactName: ?string,
     *     shippingContactId: ?string,
     *     shippingContactName: ?string,
     *     createdAccount: bool,
     *     createdContact: bool
     * }|null
     */
    public function resolveForQuote(Entity $quote, Entity $opportunity, bool $createIfMissing = true): ?array
    {
        $data = $this->resolve($opportunity, $createIfMissing);

        if ($data) {
            return $data;
        }

        if (!$createIfMissing) {
            return null;
        }

        return $this->createFromQuoteFallback($quote, $opportunity);
    }

    public function resolve(Entity $opportunity, bool $createIfMissing = true): ?array
    {
        $lead = $this->loadLead($opportunity);
        $prospect = $this->loadProspect($opportunity, $lead);

        if (!$lead || !$prospect) {
            $fromAppuntamento = $this->loadFromAppuntamento($opportunity);

            if (!$lead && $fromAppuntamento['lead']) {
                $lead = $fromAppuntamento['lead'];
            }

            if (!$prospect && $fromAppuntamento['prospect']) {
                $prospect = $fromAppuntamento['prospect'];
            }
        }

        if (!$lead) {
            $lead = $this->findLeadByDisplayName($this->resolveCustomerLabel($opportunity));
        }

        if (!$prospect) {
            $prospect = $this->findProspectByDisplayName($this->resolveCustomerLabel($opportunity));
        }
        $teamsIds = $opportunity->getLinkMultipleIdList('teams') ?: [];
        $createdAccount = false;

        $accountId = $opportunity->get('accountId');

        if (!$accountId && $lead && $lead->get('createdAccountId')) {
            $accountId = $lead->get('createdAccountId');
        }

        if (!$accountId && $prospect && $prospect->get('clienteId')) {
            $accountId = $prospect->get('clienteId');
        }

        if ($createIfMissing && !$accountId && $lead) {
            $cliente = $this->entityManager->createEntity('Account');
            $cliente->set($this->buildAccountDataFromLead($lead, $opportunity, $teamsIds, $prospect));
            $this->entityManager->saveEntity($cliente, ['silent' => true]);

            $accountId = (string) $cliente->getId();
            $createdAccount = true;

            $lead->set([
                'status' => 'Converted',
                'createdAccountId' => $accountId,
                'convertedAt' => date('Y-m-d H:i:s'),
            ]);
            $this->entityManager->saveEntity($lead, ['silent' => true]);

            if ($lead->get('prospectId')) {
                $prospectForLink = $this->entityManager->getEntityById('Prospect', $lead->get('prospectId'));
                $this->linkProspectToAccount($prospectForLink, $accountId, $cliente->get('name'));
            }
        }

        if ($createIfMissing && !$accountId && $prospect) {
            $clienteId = $prospect->get('clienteId');

            if ($clienteId && $this->entityManager->getEntityById('Account', $clienteId)) {
                $accountId = $clienteId;
            } else {
                $cliente = $this->entityManager->createEntity('Account');
                $cliente->set($this->buildAccountDataFromProspect($prospect, $opportunity, $teamsIds, $lead));
                $this->entityManager->saveEntity($cliente, ['silent' => true]);

                $accountId = (string) $cliente->getId();
                $createdAccount = true;
                $this->linkProspectToAccount($prospect, $accountId, $cliente->get('name'));
            }
        }

        if ($accountId && !$this->entityManager->getEntityById('Account', $accountId)) {
            $fixed = $this->fixInvalidAccountId($accountId, $prospect, $lead, $opportunity, $teamsIds, $createIfMissing);

            if ($fixed) {
                $accountId = $fixed;
                $createdAccount = true;
            } else {
                $accountId = null;
            }
        }

        if (!$accountId) {
            $fromContact = $this->resolveFromOpportunityContact($opportunity, $createIfMissing);

            if ($fromContact) {
                return $fromContact;
            }

            return null;
        }

        $account = $this->entityManager->getEntityById('Account', $accountId);

        if (!$account) {
            return null;
        }

        $accountName = $account->get('name');
        $createdContact = false;

        $referente = (new ReferenteContactService($this->entityManager))->ensureForAccount($accountId, [
            'lead' => $lead,
            'prospect' => $prospect,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ]);

        $billingContactId = $referente['id'] ?? null;
        $billingContactName = $referente['name'] ?? null;

        if ($referente && ($referente['created'] ?? false)) {
            $createdContact = true;
        }

        if (!$billingContactName) {
            $billingContactName = $accountName
                ?: $opportunity->get('leadName')
                ?: $opportunity->get('prospectName');
        }

        return [
            'accountId' => $accountId,
            'accountName' => $accountName,
            'billingContactId' => $billingContactId,
            'billingContactName' => $billingContactName,
            'shippingContactId' => $billingContactId,
            'shippingContactName' => $billingContactName,
            'createdAccount' => $createdAccount,
            'createdContact' => $createdContact,
        ];
    }

    public function syncOpportunity(Entity $opportunity, array $data): void
    {
        if (!$data['accountId']) {
            return;
        }

        $patch = [
            'accountId' => $data['accountId'],
            'accountName' => $data['accountName'],
        ];

        if ($data['billingContactId'] && !$opportunity->get('contactId')) {
            $patch['contactId'] = $data['billingContactId'];
            $patch['contactName'] = $data['billingContactName'];
        }

        $opportunity->set($patch);

        $this->entityManager->saveEntity($opportunity, [
            'skipHooks' => true,
            'silent' => true,
        ]);
    }

    /**
     * @return array{lead: ?Entity, prospect: ?Entity}
     */
    private function loadFromAppuntamento(Entity $opportunity): array
    {
        $appuntamentoId = $opportunity->get('appuntamentoId');

        if (!$appuntamentoId) {
            return ['lead' => null, 'prospect' => null];
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$appuntamento) {
            return ['lead' => null, 'prospect' => null];
        }

        $lead = null;
        $prospect = null;

        if ($appuntamento->get('leadId')) {
            $lead = $this->entityManager->getEntityById('Lead', $appuntamento->get('leadId'));
        } elseif ($appuntamento->get('parentType') === 'Lead' && $appuntamento->get('parentId')) {
            $lead = $this->entityManager->getEntityById('Lead', $appuntamento->get('parentId'));
        }

        if ($appuntamento->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById('Prospect', $appuntamento->get('prospectId'));
        } elseif ($appuntamento->get('parentType') === 'Prospect' && $appuntamento->get('parentId')) {
            $prospect = $this->entityManager->getEntityById('Prospect', $appuntamento->get('parentId'));
        }

        return ['lead' => $lead, 'prospect' => $prospect];
    }

    private function loadLead(Entity $opportunity): ?Entity
    {
        $leadId = $opportunity->get('leadId');

        if ($leadId) {
            return $this->entityManager->getEntityById('Lead', $leadId);
        }

        return null;
    }

    private function loadProspect(Entity $opportunity, ?Entity $lead): ?Entity
    {
        $prospectId = $opportunity->get('prospectId') ?: ($lead ? $lead->get('prospectId') : null);

        if (!$prospectId) {
            return null;
        }

        return $this->entityManager->getEntityById('Prospect', $prospectId);
    }

    private function fixInvalidAccountId(
        string $accountId,
        ?Entity $prospect,
        ?Entity $lead,
        Entity $opportunity,
        array $teamsIds,
        bool $createIfMissing
    ): ?string {
        $prospectAsAccount = $this->entityManager->getEntityById('Prospect', $accountId);

        if (!$prospectAsAccount) {
            return null;
        }

        if ($prospectAsAccount->get('clienteId')
            && $this->entityManager->getEntityById('Account', $prospectAsAccount->get('clienteId'))) {
            return $prospectAsAccount->get('clienteId');
        }

        if (!$createIfMissing) {
            return null;
        }

        $cliente = $this->entityManager->createEntity('Account');
        $cliente->set($this->buildAccountDataFromProspect(
            $prospectAsAccount,
            $opportunity,
            $teamsIds,
            $lead
        ));
        $this->entityManager->saveEntity($cliente, ['silent' => true]);

        $newId = (string) $cliente->getId();
        $this->linkProspectToAccount($prospectAsAccount, $newId, $cliente->get('name'));

        return $newId;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccountDataFromLead(
        Entity $lead,
        Entity $opportunity,
        array $teamsIds,
        ?Entity $prospect = null
    ): array {
        $sync = new LeadProspectSync($this->entityManager);

        if (!$prospect && $lead->get('prospectId')) {
            $prospect = $this->entityManager->getEntityById('Prospect', $lead->get('prospectId'));
        }

        $name = $sync->resolveDisplayName($lead) ?: ($prospect ? $sync->resolveDisplayName($prospect) : null);

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
            'phoneNumber' => $sync->resolvePhoneFromProspect($lead) ?: $lead->get('phoneNumber'),
            'emailAddress' => $lead->get('emailAddress') ?: ($prospect ? $prospect->get('emailAddress') : null),
            'originalLeadId' => $lead->getId(),
            'stato' => 'Nuovo',
            'type' => 'B2C',
            'segmento' => $lead->get('segmento') ?: 'B2C',
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccountDataFromProspect(
        Entity $prospect,
        Entity $opportunity,
        array $teamsIds,
        ?Entity $lead = null
    ): array {
        $sync = new LeadProspectSync($this->entityManager);
        $name = $sync->resolveDisplayName($prospect) ?: ($lead ? $sync->resolveDisplayName($lead) : null);

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
            'stato' => 'Nuovo',
            'type' => 'B2C',
            'segmento' => 'B2C',
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ];

        if ($lead) {
            $data['originalLeadId'] = $lead->getId();

            if (!$data['phoneNumber']) {
                $data['phoneNumber'] = $sync->resolvePhoneFromProspect($lead) ?: $lead->get('phoneNumber');
            }
        }

        return $data;
    }

    private function linkProspectToAccount(?Entity $prospect, string $accountId, ?string $accountName = null): void
    {
        if (!$prospect) {
            return;
        }

        $prospect->set([
            'clienteId' => $accountId,
            'clienteName' => $accountName ?: $prospect->get('clienteName'),
        ]);

        $this->entityManager->saveEntity($prospect, ['silent' => true]);
    }

    private function resolveFromOpportunityContact(Entity $opportunity, bool $createIfMissing): ?array
    {
        $contactId = $opportunity->get('contactId');

        if (!$contactId) {
            return null;
        }

        $contact = $this->entityManager->getEntityById('Contact', $contactId);

        if (!$contact) {
            return null;
        }

        $accountId = $contact->get('accountId');

        if ($accountId && $this->entityManager->getEntityById('Account', $accountId)) {
            return [
                'accountId' => $accountId,
                'accountName' => $contact->get('accountName') ?: $this->entityManager->getEntityById('Account', $accountId)?->get('name'),
                'billingContactId' => $contact->getId(),
                'billingContactName' => $contact->get('name'),
                'shippingContactId' => $contact->getId(),
                'shippingContactName' => $contact->get('name'),
                'createdAccount' => false,
                'createdContact' => false,
            ];
        }

        if (!$createIfMissing) {
            return null;
        }

        $teamsIds = $opportunity->getLinkMultipleIdList('teams') ?: [];
        $cliente = $this->entityManager->createEntity('Account');
        $cliente->set([
            'name' => $contact->get('name') ?: $this->resolveCustomerLabel($opportunity) ?: 'Cliente da referente',
            'phoneNumber' => $contact->get('phoneNumber'),
            'emailAddress' => $contact->get('emailAddress'),
            'billingAddressStreet' => $contact->get('addressStreet'),
            'billingAddressCity' => $contact->get('addressCity'),
            'billingAddressPostalCode' => $contact->get('addressPostalCode'),
            'billingAddressState' => $contact->get('addressState'),
            'shippingAddressStreet' => $contact->get('addressStreet'),
            'shippingAddressCity' => $contact->get('addressCity'),
            'shippingAddressPostalCode' => $contact->get('addressPostalCode'),
            'shippingAddressState' => $contact->get('addressState'),
            'stato' => 'Nuovo',
            'type' => 'B2C',
            'segmento' => 'B2C',
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ]);
        $this->entityManager->saveEntity($cliente, ['silent' => true]);

        $accountId = (string) $cliente->getId();
        $contact->set([
            'accountId' => $accountId,
            'accountName' => $cliente->get('name'),
        ]);
        $this->entityManager->saveEntity($contact, ['silent' => true]);

        return [
            'accountId' => $accountId,
            'accountName' => $cliente->get('name'),
            'billingContactId' => $contact->getId(),
            'billingContactName' => $contact->get('name'),
            'shippingContactId' => $contact->getId(),
            'shippingContactName' => $contact->get('name'),
            'createdAccount' => true,
            'createdContact' => false,
        ];
    }

    private function createFromQuoteFallback(Entity $quote, Entity $opportunity): ?array
    {
        $name = $this->resolveCustomerLabel($opportunity, $quote);

        if (!$name) {
            return null;
        }

        $teamsIds = $opportunity->getLinkMultipleIdList('teams') ?: [];
        $cliente = $this->entityManager->createEntity('Account');
        $cliente->set([
            'name' => $name,
            'billingAddressStreet' => $quote->get('billingAddressStreet'),
            'billingAddressCity' => $quote->get('billingAddressCity'),
            'billingAddressPostalCode' => $quote->get('billingAddressPostalCode'),
            'billingAddressState' => $quote->get('billingAddressState'),
            'billingAddressCountry' => $quote->get('billingAddressCountry'),
            'shippingAddressStreet' => $quote->get('shippingAddressStreet') ?: $quote->get('billingAddressStreet'),
            'shippingAddressCity' => $quote->get('shippingAddressCity') ?: $quote->get('billingAddressCity'),
            'shippingAddressPostalCode' => $quote->get('shippingAddressPostalCode') ?: $quote->get('billingAddressPostalCode'),
            'shippingAddressState' => $quote->get('shippingAddressState') ?: $quote->get('billingAddressState'),
            'shippingAddressCountry' => $quote->get('shippingAddressCountry') ?: $quote->get('billingAddressCountry'),
            'phoneNumber' => $opportunity->get('telefono'),
            'stato' => 'Nuovo',
            'type' => 'B2C',
            'segmento' => 'B2C',
            'teamsIds' => $teamsIds,
            'assignedUserId' => $opportunity->get('assignedUserId') ?: $quote->get('assignedUserId'),
        ]);
        $this->entityManager->saveEntity($cliente, ['silent' => true]);

        $accountId = (string) $cliente->getId();
        $referente = (new ReferenteContactService($this->entityManager))->ensureForAccount($accountId, [
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ]);

        $billingContactId = $referente['id'] ?? null;
        $billingContactName = $referente['name'] ?? $name;

        if (!$billingContactId) {
            $contact = $this->entityManager->createEntity('Contact');
            $contact->set([
                'firstName' => $name,
                'name' => $name,
                'accountId' => $accountId,
                'accountName' => $name,
                'phoneNumber' => $opportunity->get('telefono'),
                'addressStreet' => $quote->get('billingAddressStreet'),
                'addressCity' => $quote->get('billingAddressCity'),
                'addressPostalCode' => $quote->get('billingAddressPostalCode'),
                'addressState' => $quote->get('billingAddressState'),
                'addressCountry' => $quote->get('billingAddressCountry'),
            ]);
            $this->entityManager->saveEntity($contact, ['silent' => true]);
            $billingContactId = (string) $contact->getId();
            $billingContactName = $contact->get('name');
        }

        return [
            'accountId' => $accountId,
            'accountName' => $name,
            'billingContactId' => $billingContactId,
            'billingContactName' => $billingContactName,
            'shippingContactId' => $billingContactId,
            'shippingContactName' => $billingContactName,
            'createdAccount' => true,
            'createdContact' => true,
        ];
    }

    private function resolveCustomerLabel(Entity $opportunity, ?Entity $quote = null): ?string
    {
        foreach ([
            $opportunity->get('prospectName'),
            $opportunity->get('leadName'),
            $opportunity->get('contactName'),
            $this->parseCustomerNameFromRecordName($opportunity->get('name')),
            $quote ? $this->parseCustomerNameFromRecordName($quote->get('name')) : null,
        ] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function parseCustomerNameFromRecordName(mixed $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $parts = array_map('trim', explode(' - ', $name));

        if (count($parts) >= 2 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[0])) {
            return $parts[1] !== '' ? $parts[1] : null;
        }

        return null;
    }

    private function findLeadByDisplayName(?string $name): ?Entity
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $byName = $this->entityManager->getRDBRepository('Lead')
            ->where(['name' => $name])
            ->findOne();

        if ($byName) {
            return $byName;
        }

        return $this->entityManager->getRDBRepository('Lead')
            ->where(['name*' => '%' . $name . '%'])
            ->order('modifiedAt', 'DESC')
            ->findOne();
    }

    private function findProspectByDisplayName(?string $name): ?Entity
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $byName = $this->entityManager->getRDBRepository('Prospect')
            ->where(['name' => $name])
            ->findOne();

        if ($byName) {
            return $byName;
        }

        return $this->entityManager->getRDBRepository('Prospect')
            ->where(['name*' => '%' . $name . '%'])
            ->order('modifiedAt', 'DESC')
            ->findOne();
    }
}
