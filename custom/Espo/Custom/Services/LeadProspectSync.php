<?php

namespace Espo\Custom\Services;


use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Sincronizza Lead da Prospect (appuntamento Held e repair massivo).
 */
class LeadProspectSync
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findProspectForLead(Entity $lead): ?Entity
    {
        $prospectId = $lead->get('prospectId');

        if ($prospectId) {
            $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);

            if ($prospect) {
                return $prospect;
            }
        }

        return $this->entityManager
            ->getRDBRepository('Prospect')
            ->where(['leadId' => $lead->getId()])
            ->findOne();
    }

    public function resolvePhoneFromProspect(Entity $prospect): ?string
    {
        if ($prospect->get('phoneNumber')) {
            return $prospect->get('phoneNumber');
        }

        if ($prospect->get('telefono')) {
            return $prospect->get('telefono');
        }

        $fromWa = $this->extractPhoneFromWhatsAppUrl($prospect->get('whatsApp'));

        if ($fromWa) {
            return $fromWa;
        }

        return $this->extractPhoneFromWhatsAppUrl($prospect->get('whatsApp39'));
    }

    public function extractPhoneFromWhatsAppUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('/wa\.me\/(\d+)/', $url, $m)) {
            return $m[1];
        }

        if (preg_match('/phone=(\d+)/', $url, $m)) {
            return $m[1];
        }

        if (preg_match('/(\d{8,15})/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function resolveDisplayName(Entity $entity): ?string
    {
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

        if ($entity->getEntityType() === 'Prospect') {
            $rs = $entity->get('ragioneSociale');

            if ($rs) {
                return trim((string) $rs);
            }
        }

        if ($entity->getEntityType() === 'Lead') {
            $ref = $entity->get('referenteAziendale');

            if ($ref) {
                return trim((string) $ref);
            }
        }

        return null;
    }

    public function findExistingLeadByProspect(Entity $prospect): ?Entity
    {
        $phone = $this->resolvePhoneFromProspect($prospect);

        if ($phone) {
            $existing = $this->entityManager
                ->getRDBRepository('Lead')
                ->where(['phoneNumber' => $phone])
                ->findOne();

            if ($existing) {
                return $existing;
            }
        }

        $name = $this->resolveDisplayName($prospect);

        if ($name) {
            $existing = $this->entityManager
                ->getRDBRepository('Lead')
                ->where(['name' => $name])
                ->findOne();

            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    public function linkLeadAndProspect(Entity $lead, Entity $prospect): void
    {
        if (!$lead->get('prospectId')) {
            $lead->set('prospectId', $prospect->getId());
        }

        if (!$prospect->get('leadId')) {
            $prospect->set('leadId', $lead->getId());
            $this->entityManager->saveEntity($prospect, ['silent' => true]);
        }
    }

    /**
     * @param bool $onlyEmpty non sovrascrive campi già valorizzati (tranne description da prospect se presente)
     */
    public function syncLeadFromProspect(
        Entity $lead,
        Entity $prospect,
        bool $onlyEmpty = true
    ): bool {
        $changed = false;

        if ($prospect->get('description')) {
            if (!$onlyEmpty || !$lead->get('description')) {
                $lead->set('description', $prospect->get('description'));
                $changed = true;
            }
        }

        $scalarFields = [
            'firstName',
            'lastName',
            'name',
            'addressStreet',
            'addressCity',
            'addressPostalCode',
            'addressState',
            'addressCountry',
            'cAPId',
            'cAPName',
            'azienda',
            'fornitorePartnerId',
            'fornitorePartnerName',
            'productBrandId',
            'productBrandName',
            'productCategoryId',
            'productCategoryName',
            'partitaIVA',
            'insegna',
            'referenteAziendale',
            'website',
        ];

        foreach ($scalarFields as $field) {
            $value = $prospect->get($field);

            if ($value === null || $value === '') {
                continue;
            }

            if ($onlyEmpty && $lead->get($field)) {
                continue;
            }

            $lead->set($field, $value);
            $changed = true;
        }

        $phone = $this->resolvePhoneFromProspect($prospect);

        if ($phone && (!$onlyEmpty || !$lead->get('phoneNumber'))) {
            $this->applyPhoneToLead($lead, $phone);
            $changed = true;
        }

        $email = $prospect->get('emailAddress');

        if ($email && (!$onlyEmpty || !$lead->get('emailAddress'))) {
            $this->applyEmailToLead($lead, $email);
            $changed = true;
        }

        foreach (['whatsApp', 'whatsApp39'] as $waField) {
            $wa = $prospect->get($waField);

            if ($wa && (!$onlyEmpty || !$lead->get($waField))) {
                $lead->set($waField, $wa);
                $changed = true;
            }
        }

        $displayName = $this->resolveDisplayName($prospect);

        if ($displayName && (!$onlyEmpty || !$lead->get('name'))) {
            $lead->set('name', $displayName);
            $changed = true;
        }

        $this->resolveBrandPartnerFromAzienda($lead, $prospect->get('azienda'));

        $this->linkLeadAndProspect($lead, $prospect);

        return $changed;
    }

    public function applyPhoneToLead(Entity $lead, string $phone): void
    {
        $lead->set('phoneNumber', $phone);
        $lead->set('phoneNumberData', [
            (object) [
                'phoneNumber' => $phone,
                'type' => 'Mobile',
                'primary' => true,
                'optOut' => false,
            ],
        ]);
    }

    public function applyEmailToLead(Entity $lead, string $email): void
    {
        $lead->set('emailAddress', $email);
        $lead->set('emailAddressData', [
            (object) [
                'emailAddress' => $email,
                'primary' => true,
                'optOut' => false,
            ],
        ]);
    }

    public function resolveBrandPartnerFromAzienda(Entity $entity, ?string $azienda): void
    {
        if ($entity->get('productBrandId') || !$azienda) {
            return;
        }

        $brand = $this->entityManager
            ->getRDBRepository('ProductBrand')
            ->where(['name' => $azienda])
            ->findOne();

        if (!$brand) {
            return;
        }

        $entity->set('productBrandId', $brand->getId());
        $entity->set('productBrandName', $brand->get('name'));

        if ($brand->get('fornitorePartnerId')) {
            $entity->set('fornitorePartnerId', $brand->get('fornitorePartnerId'));
            $entity->set('fornitorePartnerName', $brand->get('fornitorePartnerName'));
        }
    }

    /**
     * Aggiorna Account creato in conversione Lead (campi vuoti).
     */
    public function enrichAccountFromLead(
        Entity $account,
        Entity $lead,
        bool $onlyEmpty = true
    ): bool {
        $name = $this->resolveDisplayName($lead);

        $data = [
            'name' => $name ?: 'Cliente da lead',
            'billingAddressStreet' => $lead->get('addressStreet'),
            'billingAddressCity' => $lead->get('addressCity'),
            'billingAddressPostalCode' => $lead->get('addressPostalCode'),
            'billingAddressState' => $lead->get('addressState'),
            'shippingAddressStreet' => $lead->get('addressStreet'),
            'shippingAddressCity' => $lead->get('addressCity'),
            'shippingAddressPostalCode' => $lead->get('addressPostalCode'),
            'shippingAddressState' => $lead->get('addressState'),
            'phoneNumber' => $lead->get('phoneNumber')
                ?: $this->extractPhoneFromWhatsAppUrl($lead->get('whatsApp')),
            'emailAddress' => $lead->get('emailAddress'),
            'website' => $lead->get('website'),
            'description' => $lead->get('description'),
            'whatsApp' => $lead->get('whatsApp'),
            'partitaIVA' => $lead->get('partitaIVA'),
            'originalLeadId' => $lead->getId(),
            'segmento' => $lead->get('segmento') ?: 'B2C',
            'type' => 'B2C',
        ];

        $patch = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($onlyEmpty && $account->get($key)) {
                continue;
            }

            $patch[$key] = $value;
        }

        if (empty($patch)) {
            return false;
        }

        $account->set($patch);

        return true;
    }

    /**
     * Repair massivo: Lead da Prospect + Account da Lead convertiti.
     *
     * @return array{processed:int, updated:int, accountsUpdated:int, skipped:int, errors:array}
     */
    public function repairAllLeads(bool $onlyEmpty = true, ?int $limit = null): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'accountsUpdated' => 0,
            'contactsEnsured' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $query = $this->entityManager
            ->getRDBRepository('Lead')
            ->order('createdAt', 'DESC');

        if ($limit) {
            $query->limit(0, $limit);
        }

        foreach ($query->find() as $lead) {
            $stats['processed']++;

            try {
                $prospect = $this->findProspectForLead($lead);

                if (!$prospect) {
                    $stats['skipped']++;
                    continue;
                }

                $leadChanged = $this->syncLeadFromProspect($lead, $prospect, $onlyEmpty);

                if ($leadChanged) {
                    $this->entityManager->saveEntity($lead);
                    $stats['updated']++;
                }

                $accountId = $lead->get('createdAccountId');

                if ($accountId) {
                    $account = $this->entityManager->getEntityById('Account', $accountId);

                    if ($account) {
                        if ($this->enrichAccountFromLead($account, $lead, $onlyEmpty)) {
                            $this->entityManager->saveEntity($account);
                            $stats['accountsUpdated']++;
                        }

                        $this->linkProspectToAccount(
                            $prospect,
                            $accountId,
                            $account->get('name')
                        );

                        $referente = (new ReferenteContactService($this->entityManager))
                            ->ensureForAccount($accountId, [
                                'lead' => $lead,
                                'prospect' => $prospect,
                            ]);

                        if ($referente) {
                            $stats['contactsEnsured']++;
                        }
                    } elseif (!$leadChanged) {
                        $stats['skipped']++;
                    }
                } elseif (!$leadChanged) {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = $lead->getId() . ': ' . $e->getMessage();
            }
        }

        return $stats;
    }

    private function linkProspectToAccount(
        Entity $prospect,
        string $accountId,
        ?string $accountName = null
    ): void {
        $prospect->set([
            'clienteId' => $accountId,
            'clienteName' => $accountName ?: $prospect->get('clienteName'),
        ]);
        $this->entityManager->saveEntity($prospect, ['silent' => true]);
    }
}
