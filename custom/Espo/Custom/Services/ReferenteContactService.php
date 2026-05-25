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
     * @return array{id:string,name:string,created:bool}|null
     */
    public function ensureForAccount(string $accountId, array $context = []): ?array
    {
        $lead = $context['lead'] ?? null;
        $prospect = $context['prospect'] ?? null;
        $assignedUserId = $context['assignedUserId'] ?? null;

        if (!$prospect && $lead) {
            $prospect = $this->leadSync->findProspectForLead($lead);
        }

        $payload = $this->buildContactPayload($accountId, $lead, $prospect, $assignedUserId);

        if (!$payload['name']) {
            return null;
        }

        $existing = $this->findExistingContact($accountId, $payload, $lead);

        if ($existing) {
            $this->patchContactIfNeeded($existing, $payload);

            return [
                'id' => $existing->getId(),
                'name' => $existing->get('name'),
                'created' => false,
            ];
        }

        $contact = $this->entityManager->createEntity('Contact');
        $contact->set($payload);
        $this->entityManager->saveEntity($contact);

        if ($lead) {
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

        if ($lead) {
            $name = $this->leadSync->resolveDisplayName($lead);
            $firstName = $lead->get('firstName');
            $lastName = $lead->get('lastName');
            $phone = $this->leadSync->resolvePhoneFromProspect($lead)
                ?: ($lead->get('phoneNumber') ?: $lead->get('telefono'));
            $email = $lead->get('emailAddress');
        }

        if ($prospect) {
            if (!$name) {
                $name = $this->leadSync->resolveDisplayName($prospect);
            }
            $firstName = $firstName ?: $prospect->get('firstName');
            $lastName = $lastName ?: $prospect->get('lastName');
            $phone = $phone ?: $this->leadSync->resolvePhoneFromProspect($prospect);
            $email = $email ?: $prospect->get('emailAddress');
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

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findExistingContact(
        string $accountId,
        array $payload,
        ?Entity $lead
    ): ?Entity {
        if ($lead && $lead->get('createdContactId')) {
            $fromLead = $this->entityManager->getEntityById(
                'Contact',
                $lead->get('createdContactId')
            );

            if ($fromLead) {
                return $fromLead;
            }
        }

        $name = $payload['name'] ?? null;

        if ($name) {
            $byName = $this->entityManager
                ->getRDBRepository('Contact')
                ->where([
                    'accountId' => $accountId,
                    'name' => $name,
                ])
                ->findOne();

            if ($byName) {
                return $byName;
            }
        }

        $phone = $payload['phoneNumber'] ?? null;

        if ($phone) {
            $byPhone = $this->entityManager
                ->getRDBRepository('Contact')
                ->where([
                    'accountId' => $accountId,
                    'phoneNumber' => $phone,
                ])
                ->findOne();

            if ($byPhone) {
                return $byPhone;
            }
        }

        $email = $payload['emailAddress'] ?? null;

        if ($email) {
            $byEmail = $this->entityManager
                ->getRDBRepository('Contact')
                ->where([
                    'accountId' => $accountId,
                    'emailAddress' => $email,
                ])
                ->findOne();

            if ($byEmail) {
                return $byEmail;
            }
        }

        if ($name) {
            $byNameGlobal = $this->entityManager
                ->getRDBRepository('Contact')
                ->where(['name' => $name])
                ->order(['createdAt' => 'DESC'])
                ->findOne();

            if ($byNameGlobal && !$byNameGlobal->get('accountId')) {
                $byNameGlobal->set('accountId', $accountId);
                $this->entityManager->saveEntity($byNameGlobal, ['silent' => true]);

                return $byNameGlobal;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patchContactIfNeeded(Entity $contact, array $payload): void
    {
        $patch = [];

        foreach (['firstName', 'lastName', 'phoneNumber', 'emailAddress', 'assignedUserId'] as $field) {
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
