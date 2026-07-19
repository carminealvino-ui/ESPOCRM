<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Crea o riusa Contact (referente) collegato al Cliente (Account).
 */
class ReferenteContactService
{
    private EntityManager $entityManager;

    private LeadProspectSync $leadSync;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->leadSync = new LeadProspectSync($entityManager);
    }

    /**
     * @param array{lead?:Entity|null, prospect?:Entity|null, assignedUserId?:string|null} $context
     * @return array{
     *     billingContactId:?string,
     *     billingContactName:?string,
     *     shippingContactId:?string,
     *     shippingContactName:?string,
     *     leadContact:?array{id:string,name:string,created:bool},
     *     prospectContact:?array{id:string,name:string,created:bool}
     * }
     */
    public function ensureLeadAndProspectForAccount(string $accountId, array $context = []): array
    {
        $lead = $context['lead'] ?? null;
        $prospect = $context['prospect'] ?? null;
        $assignedUserId = $context['assignedUserId'] ?? null;

        if (!$prospect && $lead) {
            $prospect = $this->leadSync->findProspectForLead($lead);
        }

        // Contraente / referente Lead
        $leadContact = $this->ensureFromPerson($accountId, $lead, $assignedUserId, true);
        // Referente Prospect (es. contatto installazione)
        $prospectContact = $this->ensureFromPerson($accountId, $prospect, $assignedUserId, false);

        $billing = $leadContact ?: $prospectContact;
        $shipping = $prospectContact ?: $leadContact;

        return [
            'billingContactId' => $billing['id'] ?? null,
            'billingContactName' => $billing['name'] ?? null,
            'shippingContactId' => $shipping['id'] ?? null,
            'shippingContactName' => $shipping['name'] ?? null,
            'leadContact' => $leadContact,
            'prospectContact' => $prospectContact,
        ];
    }

    /**
     * @param array{lead?:Entity|null, prospect?:Entity|null, assignedUserId?:string|null} $context
     * @return array{id:string,name:string,created:bool}|null
     */
    public function ensureForAccount(string $accountId, array $context = []): ?array
    {
        $pair = $this->ensureLeadAndProspectForAccount($accountId, $context);

        if (!empty($pair['leadContact'])) {
            return $pair['leadContact'];
        }

        return $pair['prospectContact'];
    }

    /**
     * Crea/riusa Contact da una sola persona (Lead oppure Prospect), senza mescolare i due.
     *
     * @return array{id:string,name:string,created:bool}|null
     */
    public function ensureFromPerson(
        string $accountId,
        ?Entity $person,
        ?string $assignedUserId,
        bool $linkLead
    ): ?array {
        if (!$person) {
            return null;
        }

        $isLead = $person->getEntityType() === 'Lead';
        $lead = $isLead ? $person : null;
        $prospect = $isLead ? null : $person;

        $payload = $this->buildContactPayload($accountId, $lead, $prospect, $assignedUserId);

        if (!$payload['name']) {
            return null;
        }

        $existing = $this->findExistingContact($accountId, $payload, $lead);

        if ($existing) {
            $this->patchContactIfNeeded($existing, $payload);

            if ($linkLead && $lead) {
                $this->linkLeadToContact($lead, $existing);
            }

            return [
                'id' => $existing->getId(),
                'name' => $existing->get('name'),
                'created' => false,
            ];
        }

        $contact = $this->entityManager->createEntity('Contact');
        $contact->set($payload);
        $this->entityManager->saveEntity($contact);

        if ($linkLead && $lead) {
            $lead->set([
                'createdContactId' => $contact->getId(),
                'createdContactName' => $contact->get('name'),
            ]);
            $this->entityManager->saveEntity($lead, ['silent' => true]);
        }

        return [
            'id' => $contact->getId(),
            'name' => $contact->get('name'),
            'created' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactPayload(
        string $accountId,
        ?Entity $lead,
        ?Entity $prospect,
        ?string $assignedUserId
    ): array {
        $name = null;
        $firstName = null;
        $lastName = null;
        $phone = null;
        $email = null;
        $description = null;
        $addressStreet = null;
        $addressCity = null;
        $addressPostalCode = null;
        $addressState = null;

        if ($lead) {
            $name = $this->leadSync->resolveDisplayName($lead);
            $firstName = $lead->get('firstName');
            $lastName = $lead->get('lastName');
            $phone = $this->leadSync->resolvePhoneFromProspect($lead)
                ?: ($lead->get('phoneNumber') ?: $lead->get('telefono'));
            $email = $lead->get('emailAddress');
            $description = $lead->get('description')
                ?: $lead->get('descrizioneOpportunitGenerata');
            $addressStreet = $lead->get('addressStreet');
            $addressCity = $lead->get('addressCity');
            $addressPostalCode = $lead->get('addressPostalCode');
            $addressState = $lead->get('addressState');
        }

        if ($prospect) {
            if (!$name) {
                $name = $this->leadSync->resolveDisplayName($prospect);
            }
            $firstName = $firstName ?: $prospect->get('firstName');
            $lastName = $lastName ?: $prospect->get('lastName');
            $phone = $phone ?: $this->leadSync->resolvePhoneFromProspect($prospect);
            $email = $email ?: $prospect->get('emailAddress');
            $description = $description ?: $prospect->get('description');
            $addressStreet = $addressStreet ?: $prospect->get('addressStreet');
            $addressCity = $addressCity ?: $prospect->get('addressCity');
            $addressPostalCode = $addressPostalCode ?: $prospect->get('addressPostalCode');
            $addressState = $addressState ?: $prospect->get('addressState');
        }

        if (!$phone && $lead) {
            $phone = $this->leadSync->extractPhoneFromWhatsAppUrl($lead->get('whatsApp'));
        }

        if (!$phone && $prospect) {
            $phone = $this->leadSync->extractPhoneFromWhatsAppUrl($prospect->get('whatsApp'));
        }

        $data = [
            'name' => $name,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'accountId' => $accountId,
            'assignedUserId' => $assignedUserId,
        ];

        if ($phone) {
            $data['phoneNumber'] = $phone;
            $data['phoneNumberData'] = [
                (object) [
                    'phoneNumber' => $phone,
                    'type' => 'Mobile',
                    'primary' => true,
                    'optOut' => false,
                ],
            ];
        }

        if ($email) {
            $data['emailAddress'] = $email;
            $data['emailAddressData'] = [
                (object) [
                    'emailAddress' => $email,
                    'primary' => true,
                    'optOut' => false,
                ],
            ];
        }

        if ($description) {
            $data['description'] = $description;
        }

        if ($addressStreet) {
            $data['addressStreet'] = $addressStreet;
        }

        if ($addressCity) {
            $data['addressCity'] = $addressCity;
        }

        if ($addressPostalCode) {
            $data['addressPostalCode'] = $addressPostalCode;
        }

        if ($addressState) {
            $data['addressState'] = $addressState;
        }

        return $data;
    }

    /**
     * Trova un Contact già esistente per la stessa persona.
     * Non riusa un altro referente solo perché condivide telefono/email
     * (caso tipico: Lead e Prospect sullo stesso Cliente).
     *
     * @param array<string, mixed> $payload
     */
    private function findExistingContact(
        string $accountId,
        array $payload,
        ?Entity $lead
    ): ?Entity {
        $expectedName = $payload['name'] ?? null;

        if ($lead && $lead->get('createdContactId')) {
            $fromLead = $this->entityManager->getEntityById(
                'Contact',
                $lead->get('createdContactId')
            );

            // Evita di riusare il Contact del Prospect se createdContactId è sbagliato.
            if ($fromLead && $this->namesMatch($fromLead->get('name'), $expectedName)) {
                return $fromLead;
            }
        }

        if ($expectedName) {
            $onAccount = $this->entityManager
                ->getRDBRepository('Contact')
                ->where(['accountId' => $accountId])
                ->find();

            foreach ($onAccount as $candidate) {
                if ($this->namesMatch($candidate->get('name'), $expectedName)) {
                    return $candidate;
                }
            }

            $byNameGlobal = $this->entityManager
                ->getRDBRepository('Contact')
                ->where(['name' => $expectedName])
                ->findOne();

            if ($byNameGlobal && !$byNameGlobal->get('accountId')) {
                $byNameGlobal->set('accountId', $accountId);
                $this->entityManager->saveEntity($byNameGlobal, ['silent' => true]);

                return $byNameGlobal;
            }
        }

        return null;
    }

    private function namesMatch(?string $a, ?string $b): bool
    {
        $na = $this->normalizePersonName($a);
        $nb = $this->normalizePersonName($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        return $na === $nb;
    }

    private function normalizePersonName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtoupper($name, 'UTF-8');
    }


    private function linkLeadToContact(?Entity $lead, Entity $contact): void
    {
        if (!$lead) {
            return;
        }

        $lead->set([
            'createdContactId' => $contact->getId(),
            'createdContactName' => $contact->get('name'),
        ]);
        $this->entityManager->saveEntity($lead, ['silent' => true]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patchContactIfNeeded(Entity $contact, array $payload): void
    {
        $patch = [];

        foreach ([
            'firstName',
            'lastName',
            'phoneNumber',
            'emailAddress',
            'assignedUserId',
            'description',
            'addressStreet',
            'addressCity',
            'addressPostalCode',
            'addressState',
        ] as $field) {
            if (empty($payload[$field])) {
                continue;
            }

            if (!$contact->get($field)) {
                $patch[$field] = $payload[$field];
            }
        }

        if (!$contact->get('accountId') && !empty($payload['accountId'])) {
            $patch['accountId'] = $payload['accountId'];
        }

        if (!empty($patch)) {
            if (!empty($patch['phoneNumber'])) {
                $patch['phoneNumberData'] = $payload['phoneNumberData'] ?? null;
            }

            if (!empty($patch['emailAddress'])) {
                $patch['emailAddressData'] = $payload['emailAddressData'] ?? null;
            }

            $contact->set($patch);
            $this->entityManager->saveEntity($contact, ['silent' => true]);
        }
    }
}
