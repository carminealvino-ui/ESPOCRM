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
    public function resolve(Entity $opportunity, bool $createIfMissing = true): ?array
    {
        $lead = $this->loadLead($opportunity);
        $prospect = $this->loadProspect($opportunity, $lead);
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

    private function loadLead(Entity $opportunity): ?Entity
    {
        $leadId = $opportunity->get('leadId');

        if (!$leadId) {
            return null;
        }

        return $this->entityManager->getEntityById('Lead', $leadId);
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
}
