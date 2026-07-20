<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Ripara fornitorePartner su Opportunity quando:
 * - l'ID punta a un FornitorePartner mancante/cancellato (lista mostra l'ID grezzo)
 * - il nome denormalizzato è vuoto o coincide con l'ID
 * - l'ID è in realtà un ProductBrand (assegnazione errata)
 *
 * Risolve da productBrand → fornitorePartner, altrimenti da azienda / brand name.
 */
class OpportunityFornitorePartnerRepair
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{needs: bool, reason: ?string}
     */
    public function diagnose(Entity $opportunity): array
    {
        $partnerId = trim((string) $opportunity->get('fornitorePartnerId'));
        $partnerName = trim((string) $opportunity->get('fornitorePartnerName'));

        if ($partnerId === '') {
            if ($this->resolvePartner($opportunity) !== null) {
                return ['needs' => true, 'reason' => 'missing-partner-resolvable'];
            }

            return ['needs' => false, 'reason' => null];
        }

        if ($partnerName === '' || $partnerName === $partnerId) {
            return ['needs' => true, 'reason' => 'empty-or-id-as-name'];
        }

        $partner = $this->entityManager->getEntityById('FornitorePartner', $partnerId);

        if (!$partner) {
            return ['needs' => true, 'reason' => 'orphan-id'];
        }

        $liveName = trim((string) $partner->get('name'));

        if ($liveName !== '' && $liveName !== $partnerName) {
            return ['needs' => true, 'reason' => 'stale-name'];
        }

        return ['needs' => false, 'reason' => null];
    }

    /**
     * Applica la riparazione sull'entity in memoria (niente save).
     * Usabile da hook beforeSave.
     *
     * @return array{changed: bool, beforeId: ?string, beforeName: ?string, afterId: ?string, afterName: ?string, reason: ?string}
     */
    public function apply(Entity $opportunity): array
    {
        $beforeId = $opportunity->get('fornitorePartnerId');
        $beforeName = $opportunity->get('fornitorePartnerName');
        $diag = $this->diagnose($opportunity);

        if (!$diag['needs']) {
            return [
                'changed' => false,
                'beforeId' => $beforeId,
                'beforeName' => $beforeName,
                'afterId' => $beforeId,
                'afterName' => $beforeName,
                'reason' => null,
            ];
        }

        $resolved = $this->resolvePartner($opportunity);

        if ($resolved === null) {
            if ($beforeId) {
                $opportunity->set('fornitorePartnerId', null);
                $opportunity->set('fornitorePartnerName', null);

                return [
                    'changed' => true,
                    'beforeId' => $beforeId,
                    'beforeName' => $beforeName,
                    'afterId' => null,
                    'afterName' => null,
                    'reason' => 'cleared-orphan',
                ];
            }

            return [
                'changed' => false,
                'beforeId' => $beforeId,
                'beforeName' => $beforeName,
                'afterId' => $beforeId,
                'afterName' => $beforeName,
                'reason' => 'unresolvable',
            ];
        }

        $afterId = $resolved['id'];
        $afterName = $resolved['name'];

        if ((string) $beforeId === (string) $afterId && (string) $beforeName === (string) $afterName) {
            return [
                'changed' => false,
                'beforeId' => $beforeId,
                'beforeName' => $beforeName,
                'afterId' => $afterId,
                'afterName' => $afterName,
                'reason' => null,
            ];
        }

        $opportunity->set('fornitorePartnerId', $afterId);
        $opportunity->set('fornitorePartnerName', $afterName);

        if (!$opportunity->get('productBrandId') && !empty($resolved['brandId'])) {
            $opportunity->set('productBrandId', $resolved['brandId']);
            $opportunity->set('productBrandName', $resolved['brandName'] ?? null);
        }

        return [
            'changed' => true,
            'beforeId' => $beforeId,
            'beforeName' => $beforeName,
            'afterId' => $afterId,
            'afterName' => $afterName,
            'reason' => $diag['reason'],
        ];
    }

    /**
     * @return array{changed: bool, beforeId: ?string, beforeName: ?string, afterId: ?string, afterName: ?string, reason: ?string}
     */
    public function repair(Entity $opportunity, bool $dryRun = false): array
    {
        $result = $this->apply($opportunity);

        if ($result['changed'] && !$dryRun) {
            $this->entityManager->saveEntity($opportunity, [
                'skipHooks' => true,
                'silent' => true,
            ]);
        }

        return $result;
    }

    /**
     * @return array{id: string, name: string, brandId?: string, brandName?: string}|null
     */
    public function resolvePartner(Entity $opportunity): ?array
    {
        $brand = null;

        if ($opportunity->get('productBrandId')) {
            $brand = $this->entityManager->getEntityById(
                'ProductBrand',
                (string) $opportunity->get('productBrandId')
            );
        }

        if (!$brand) {
            $brandLabel = trim((string) (
                $opportunity->get('productBrandName')
                ?: $opportunity->get('azienda')
                ?: ''
            ));

            if ($brandLabel !== '') {
                $brand = $this->entityManager
                    ->getRDBRepository('ProductBrand')
                    ->where(['name' => $brandLabel])
                    ->findOne();
            }
        }

        if (!$brand) {
            $brand = $this->guessBrandFromName((string) $opportunity->get('name'));
        }

        // Caso: fornitorePartnerId è in realtà un ProductBrand.
        $currentId = trim((string) $opportunity->get('fornitorePartnerId'));

        if ($currentId !== '') {
            $asBrand = $this->entityManager->getEntityById('ProductBrand', $currentId);

            if ($asBrand) {
                $brand = $brand ?: $asBrand;
            }

            $currentPartner = $this->entityManager->getEntityById('FornitorePartner', $currentId);

            if ($currentPartner && trim((string) $currentPartner->get('name')) !== '') {
                // Partner valido: basta sincronizzare il nome (e brand se vuoto).
                $out = [
                    'id' => $currentPartner->getId(),
                    'name' => trim((string) $currentPartner->get('name')),
                ];

                if ($brand) {
                    $out['brandId'] = $brand->getId();
                    $out['brandName'] = $brand->get('name');

                    // Se il brand ha un partner diverso e quello corrente è orfano/stale, preferisci il brand.
                    if (
                        $brand->get('fornitorePartnerId')
                        && (string) $brand->get('fornitorePartnerId') !== (string) $currentPartner->getId()
                    ) {
                        $fromBrand = $this->partnerFromBrand($brand);

                        if ($fromBrand) {
                            return $fromBrand;
                        }
                    }
                }

                return $out;
            }
        }

        if ($brand) {
            $fromBrand = $this->partnerFromBrand($brand);

            if ($fromBrand) {
                return $fromBrand;
            }

            // Brand senza partner collegato: FornitorePartner con stesso nome del brand (es. GFB).
            $byName = $this->entityManager
                ->getRDBRepository('FornitorePartner')
                ->where(['name' => $brand->get('name')])
                ->findOne();

            if ($byName) {
                return [
                    'id' => $byName->getId(),
                    'name' => (string) $byName->get('name'),
                    'brandId' => $brand->getId(),
                    'brandName' => (string) $brand->get('name'),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{id: string, name: string, brandId: string, brandName: string}|null
     */
    private function partnerFromBrand(Entity $brand): ?array
    {
        $partnerId = trim((string) $brand->get('fornitorePartnerId'));

        if ($partnerId === '') {
            return null;
        }

        $partner = $this->entityManager->getEntityById('FornitorePartner', $partnerId);

        if (!$partner) {
            return null;
        }

        $name = trim((string) (
            $partner->get('name')
            ?: $brand->get('fornitorePartnerName')
            ?: ''
        ));

        if ($name === '') {
            return null;
        }

        return [
            'id' => $partner->getId(),
            'name' => $name,
            'brandId' => $brand->getId(),
            'brandName' => (string) $brand->get('name'),
        ];
    }

    private function guessBrandFromName(string $opportunityName): ?Entity
    {
        $parts = array_values(array_filter(array_map('trim', explode(' - ', $opportunityName))));

        // Formato tipico: DATA - CLIENTE - BRAND - DESCRIZIONE - € …
        if (count($parts) < 3) {
            return null;
        }

        $candidates = [];

        foreach (array_slice($parts, 2) as $part) {
            if ($part === '' || str_starts_with($part, '€')) {
                continue;
            }

            $candidates[] = $part;
        }

        foreach ($candidates as $candidate) {
            $brand = $this->entityManager
                ->getRDBRepository('ProductBrand')
                ->where(['name' => $candidate])
                ->findOne();

            if ($brand) {
                return $brand;
            }
        }

        // Match parziale tipico: "GFB" in un segmento.
        $brands = $this->entityManager->getRDBRepository('ProductBrand')->find();

        foreach ($brands as $brand) {
            $brandName = trim((string) $brand->get('name'));

            if ($brandName === '') {
                continue;
            }

            foreach ($candidates as $candidate) {
                if (strcasecmp($candidate, $brandName) === 0) {
                    return $brand;
                }
            }
        }

        return null;
    }
}
