<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Generazione inviti a fatturare da provvigioni in pagamento con maturazione al giorno 15.
 */
class InvitoAFatturareManager
{
    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneStatusSync $statusSync
    ) {}

    /**
     * @return array{invitoId: string, count: int, existing: bool}
     */
    public function generaDaProvvigioni(
        string $consulenteUserId,
        string $meseCompetenza,
        ?string $fornitorePartnerId = null,
        ?string $productBrandId = null,
        ?string $invitoId = null
    ): array {
        $meseStart = date('Y-m-01', strtotime($meseCompetenza));
        $dataPagamento = date('Y-m-15', strtotime($meseStart));

        $where = [
            'statoProvvigione' => ProvvigioneStatusSync::IN_PAGAMENTO,
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
            if (!$this->statusSync->isEligibleForInvito($provvigione)) {
                continue;
            }

            if (!$this->statusSync->matchesInvitoMeseCompetenza($provvigione, $meseStart)) {
                continue;
            }

            $eligible[] = $provvigione;
        }

        if ($eligible === []) {
            throw new \RuntimeException(
                'Nessuna provvigione in pagamento eleggibile per il mese ' . date('m/Y', strtotime($meseStart))
                . ' (pagamento previsto il ' . date('d/m/Y', strtotime($dataPagamento)) . ').'
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

        if ($invitoId) {
            $invito = $this->entityManager->getEntityById('InvitoAFatturare', $invitoId);

            if (!$invito) {
                throw new \RuntimeException('Invito a fatturare non trovato.');
            }

            $isNew = false;
        } elseif ($existing) {
            $invito = $existing;
            $isNew = false;
        } else {
            $invito = $this->entityManager->createEntity('InvitoAFatturare');
            $isNew = true;

            $invito->set([
                'name' => 'INV-' . date('Ym', strtotime($meseStart)) . '-' . substr($consulenteUserId, 0, 6),
                'stato' => 'Bozza',
                'meseCompetenza' => $meseStart,
                'dataScadenzaFatturazione' => $dataPagamento,
                'consulenteId' => $consulenteUserId,
                'fornitorePartnerId' => $fornitorePartnerId,
                'productBrandId' => $productBrandId,
                'assignedUserId' => $consulenteUserId,
            ]);

            $this->entityManager->saveEntity($invito);
        }

        $totale = 0.0;

        foreach ($eligible as $provvigione) {
            $provvigione->set([
                'invitoAFatturareId' => $invito->getId(),
                'invitoAFatturareName' => $invito->get('name'),
            ]);

            $this->entityManager->saveEntity($provvigione, ['silent' => true]);

            $totale += (float) ($provvigione->get('importoConsolidato')
                ?? $provvigione->get('importo')
                ?? 0);
        }

        $invito->set([
            'importoTotaleConsolidato' => $totale,
            'importoTotalePrevisto' => $totale,
            'scostamentoTotale' => 0.0,
            'dataScadenzaFatturazione' => $dataPagamento,
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
