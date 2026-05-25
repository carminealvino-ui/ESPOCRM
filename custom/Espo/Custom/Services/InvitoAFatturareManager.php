<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Generazione inviti a fatturare da provvigioni consolidate eleggibili.
 */
class InvitoAFatturareManager
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{invitoId: string, count: int, existing: bool}
     */
    public function generaDaProvvigioni(
        string $consulenteUserId,
        string $meseCompetenza,
        ?string $fornitorePartnerId = null,
        ?string $productBrandId = null
    ): array {
        $meseStart = date('Y-m-01', strtotime($meseCompetenza));
        $meseEnd = date('Y-m-t', strtotime($meseStart));

        $where = [
            'statoProvvigione' => 'Consolidata',
            'invitoAFatturareId' => null,
            'assignedUserId' => $consulenteUserId,
        ];

        if ($fornitorePartnerId) {
            $where['fornitorePartnerId'] = $fornitorePartnerId;
        }

        if ($productBrandId) {
            $where['productBrandId'] = $productBrandId;
        }

        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where($where)
            ->find();

        $eligible = [];

        foreach ($collection as $provvigione) {
            $competenza = $provvigione->get('dataCompetenza');
            $liquidazione = $provvigione->get('dataLiquidazionePrevista');

            $refDate = $liquidazione ?: $competenza;

            if (!$refDate) {
                $eligible[] = $provvigione;
                continue;
            }

            if ($refDate >= $meseStart && $refDate <= $meseEnd) {
                $eligible[] = $provvigione;
            }
        }

        if ($eligible === []) {
            throw new \RuntimeException(
                'Nessuna provvigione consolidata eleggibile per il periodo selezionato.'
            );
        }

        $whereInvito = [
            'consulenteId' => $consulenteUserId,
            'meseCompetenza' => $meseStart,
            'stato' => 'Bozza',
        ];

        if ($fornitorePartnerId) {
            $whereInvito['fornitorePartnerId'] = $fornitorePartnerId;
        }

        if ($productBrandId) {
            $whereInvito['productBrandId'] = $productBrandId;
        }

        $existing = $this->entityManager
            ->getRDBRepository('InvitoAFatturare')
            ->where($whereInvito)
            ->findOne();

        if ($existing) {
            $invito = $existing;
            $isNew = false;
        } else {
            $invito = $this->entityManager->createEntity('InvitoAFatturare');
            $isNew = true;

            $invito->set([
                'name' => 'INV-' . date('Ym', strtotime($meseStart)) . '-' . substr($consulenteUserId, 0, 6),
                'stato' => 'Bozza',
                'meseCompetenza' => $meseStart,
                'consulenteId' => $consulenteUserId,
                'fornitorePartnerId' => $fornitorePartnerId,
                'productBrandId' => $productBrandId,
                'assignedUserId' => $consulenteUserId,
            ]);

            $this->entityManager->saveEntity($invito);
        }

        $consolidato = 0.0;
        $previsto = 0.0;

        foreach ($eligible as $provvigione) {
            $provvigione->set([
                'invitoAFatturareId' => $invito->getId(),
                'invitoAFatturareName' => $invito->get('name'),
            ]);

            $this->entityManager->saveEntity($provvigione, ['silent' => true]);

            $consolidato += (float) ($provvigione->get('importoConsolidato')
                ?? $provvigione->get('importo')
                ?? 0);
            $previsto += (float) ($provvigione->get('importoPrevisto') ?? 0);
        }

        $invito->set([
            'importoTotaleConsolidato' => $consolidato,
            'importoTotalePrevisto' => $previsto,
            'scostamentoTotale' => $consolidato - $previsto,
        ]);

        $this->entityManager->saveEntity($invito);

        return [
            'invitoId' => $invito->getId(),
            'count' => count($eligible),
            'existing' => !$isNew,
        ];
    }

    public function emettiInvito(Entity $invito): void
    {
        $invito->set([
            'stato' => 'Emesso',
            'dataInvito' => date('Y-m-d'),
        ]);

        $this->entityManager->saveEntity($invito);
    }
}
