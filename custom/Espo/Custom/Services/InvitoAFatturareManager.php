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
        ?string $productBrandId = null,
        ?string $invitoId = null
    ): array {
        $meseStart = $this->normalizeMeseStart($meseCompetenza);
        $eligible = $this->findEleggibiliProvvigioni(
            $consulenteUserId,
            $meseStart,
            $fornitorePartnerId,
            $productBrandId,
            $invitoId,
            onlyUnlinked: true
        );

        if ($eligible === []) {
            throw new \RuntimeException(
                'Nessuna provvigione consolidata eleggibile per il periodo selezionato.'
            );
        }

        $invito = $this->resolveOrCreateInvito(
            $consulenteUserId,
            $meseStart,
            $fornitorePartnerId,
            $productBrandId,
            $invitoId
        );

        $linkedIds = $this->getLinkedProvvigioneIds($invito->getId());
        $newIds = array_map(static fn (Entity $p) => $p->getId(), $eligible);
        $ids = array_values(array_unique(array_merge($linkedIds, $newIds)));

        $this->collegaProvvigioni($invito->getId(), $ids);

        return [
            'invitoId' => $invito->getId(),
            'count' => count($newIds),
            'existing' => count($linkedIds) > 0,
        ];
    }

    /**
     * Griglia eleggibili raggruppata per tipo provvigione e venditore.
     *
     * @return array{groups: list<array<string, mixed>>, totaleSelezionabile: float, linkedCount: int}
     */
    public function getProvvigioniEleggibili(
        string $consulenteUserId,
        string $meseCompetenza,
        ?string $fornitorePartnerId = null,
        ?string $productBrandId = null,
        ?string $invitoId = null
    ): array {
        $meseStart = $this->normalizeMeseStart($meseCompetenza);
        $linkedIds = $invitoId ? $this->getLinkedProvvigioneIds($invitoId) : [];
        $provvigioni = $this->findEleggibiliProvvigioni(
            $consulenteUserId,
            $meseStart,
            $fornitorePartnerId,
            $productBrandId,
            $invitoId,
            onlyUnlinked: false
        );

        $byTipo = [];

        foreach ($provvigioni as $provvigione) {
            $tipo = (string) ($provvigione->get('tipo') ?: 'Provvigione Base');
            $venditoreId = (string) ($provvigione->get('assignedUserId') ?: $consulenteUserId);
            $venditoreName = (string) ($provvigione->get('assignedUserName') ?: '—');
            $row = $this->buildProvvigioneRow($provvigione, in_array($provvigione->getId(), $linkedIds, true));

            if (!isset($byTipo[$tipo])) {
                $byTipo[$tipo] = [
                    'tipo' => $tipo,
                    'totaleProvvInPag' => 0.0,
                    'venditori' => [],
                ];
            }

            if (!isset($byTipo[$tipo]['venditori'][$venditoreId])) {
                $byTipo[$tipo]['venditori'][$venditoreId] = [
                    'venditoreId' => $venditoreId,
                    'venditoreName' => $venditoreName,
                    'totaleProvvInPag' => 0.0,
                    'rows' => [],
                ];
            }

            $byTipo[$tipo]['venditori'][$venditoreId]['rows'][] = $row;
            $byTipo[$tipo]['venditori'][$venditoreId]['totaleProvvInPag'] += $row['provvInPag'];
            $byTipo[$tipo]['totaleProvvInPag'] += $row['provvInPag'];
        }

        $groups = [];

        foreach ($byTipo as $group) {
            $group['venditori'] = array_values($group['venditori']);
            $groups[] = $group;
        }

        usort($groups, static fn (array $a, array $b) => strcmp($a['tipo'], $b['tipo']));

        $totaleSelezionabile = 0.0;

        foreach ($groups as $group) {
            $totaleSelezionabile += $group['totaleProvvInPag'];
        }

        return [
            'groups' => $groups,
            'totaleSelezionabile' => round($totaleSelezionabile, 2),
            'linkedCount' => count($linkedIds),
        ];
    }

    /**
     * Imposta l'elenco esatto delle provvigioni sull'invito (Bozza).
     *
     * @param list<string> $provvigioneIds
     * @return array{count: int, importoTotaleConsolidato: float}
     */
    public function collegaProvvigioni(string $invitoId, array $provvigioneIds): array
    {
        $invito = $this->entityManager->getEntityById('InvitoAFatturare', $invitoId);

        if (!$invito) {
            throw new \RuntimeException('Invito a fatturare non trovato.');
        }

        if ($invito->get('stato') !== 'Bozza') {
            throw new \RuntimeException('La selezione è modificabile solo in stato Bozza.');
        }

        $provvigioneIds = array_values(array_unique(array_filter($provvigioneIds)));
        $consulenteId = (string) $invito->get('consulenteId');
        $meseStart = (string) $invito->get('meseCompetenza');
        $fornitorePartnerId = $invito->get('fornitorePartnerId');
        $productBrandId = $invito->get('productBrandId');

        $eleggibili = $this->findEleggibiliProvvigioni(
            $consulenteId,
            $meseStart,
            $fornitorePartnerId,
            $productBrandId,
            $invitoId,
            onlyUnlinked: false
        );

        $eleggibiliById = [];

        foreach ($eleggibili as $provvigione) {
            $eleggibiliById[$provvigione->getId()] = $provvigione;
        }

        foreach ($provvigioneIds as $id) {
            if (!isset($eleggibiliById[$id])) {
                throw new \RuntimeException('Provvigione non eleggibile: ' . $id);
            }
        }

        $currentlyLinked = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['invitoAFatturareId' => $invitoId])
            ->find();

        foreach ($currentlyLinked as $provvigione) {
            if (!in_array($provvigione->getId(), $provvigioneIds, true)) {
                $provvigione->set([
                    'invitoAFatturareId' => null,
                    'invitoAFatturareName' => null,
                ]);

                if ($provvigione->get('statoProvvigione') === 'InInvito') {
                    $provvigione->set('statoProvvigione', 'Consolidata');
                }

                $this->entityManager->saveEntity($provvigione, ['silent' => true]);
            }
        }

        foreach ($provvigioneIds as $id) {
            $provvigione = $eleggibiliById[$id];

            $provvigione->set([
                'invitoAFatturareId' => $invitoId,
                'invitoAFatturareName' => $invito->get('name'),
            ]);

            $this->entityManager->saveEntity($provvigione, ['silent' => true]);
        }

        $this->entityManager->saveEntity($invito);

        return [
            'count' => count($provvigioneIds),
            'importoTotaleConsolidato' => (float) $invito->get('importoTotaleConsolidato'),
        ];
    }

    public function emettiInvito(Entity $invito): void
    {
        if ($invito->get('stato') !== 'Bozza') {
            throw new \RuntimeException('Solo un invito in Bozza può essere emesso.');
        }

        $invito->set([
            'stato' => 'Emesso',
            'dataInvito' => date('Y-m-d'),
        ]);

        $this->entityManager->saveEntity($invito);
    }

    private function normalizeMeseStart(string $meseCompetenza): string
    {
        return date('Y-m-01', strtotime($meseCompetenza));
    }

    /**
     * @return list<Entity>
     */
    private function findEleggibiliProvvigioni(
        string $consulenteUserId,
        string $meseStart,
        ?string $fornitorePartnerId,
        ?string $productBrandId,
        ?string $invitoId,
        bool $onlyUnlinked
    ): array {
        $meseEnd = date('Y-m-t', strtotime($meseStart));

        $where = [
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
            $stato = $provvigione->get('statoProvvigione');
            $linkedInvitoId = $provvigione->get('invitoAFatturareId');

            if ($onlyUnlinked) {
                if ($stato !== 'Consolidata' || $linkedInvitoId) {
                    continue;
                }
            } elseif ($stato === 'Consolidata') {
                if ($linkedInvitoId && $linkedInvitoId !== $invitoId) {
                    continue;
                }
            } elseif ($stato === 'InInvito') {
                if ($linkedInvitoId !== $invitoId) {
                    continue;
                }
            } else {
                continue;
            }

            if (!$this->isInMeseCompetenza($provvigione, $meseStart, $meseEnd)) {
                continue;
            }

            $eligible[] = $provvigione;
        }

        return $eligible;
    }

    private function isInMeseCompetenza(Entity $provvigione, string $meseStart, string $meseEnd): bool
    {
        $liquidazione = $provvigione->get('dataLiquidazionePrevista');
        $competenza = $provvigione->get('dataCompetenza');
        $refDate = $liquidazione ?: $competenza;

        if (!$refDate) {
            return true;
        }

        return $refDate >= $meseStart && $refDate <= $meseEnd;
    }

    /**
     * @return list<string>
     */
    private function getLinkedProvvigioneIds(string $invitoId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['invitoAFatturareId' => $invitoId])
            ->find();

        $ids = [];

        foreach ($collection as $provvigione) {
            $ids[] = $provvigione->getId();
        }

        return $ids;
    }

    private function resolveOrCreateInvito(
        string $consulenteUserId,
        string $meseStart,
        ?string $fornitorePartnerId,
        ?string $productBrandId,
        ?string $invitoId
    ): Entity {
        if ($invitoId) {
            $invito = $this->entityManager->getEntityById('InvitoAFatturare', $invitoId);

            if (!$invito) {
                throw new \RuntimeException('Invito a fatturare non trovato.');
            }

            return $invito;
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
            return $existing;
        }

        $invito = $this->entityManager->createEntity('InvitoAFatturare');

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

        return $invito;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProvvigioneRow(Entity $provvigione, bool $selected): array
    {
        $quote = null;
        $contrattoId = $provvigione->get('contrattoId');

        if ($contrattoId) {
            $quote = $this->entityManager->getEntityById('Quote', $contrattoId);
        }

        $importoConsolidato = (float) ($provvigione->get('importoConsolidato')
            ?? $provvigione->get('importo')
            ?? 0);

        $provvGiaPag = $provvigione->get('statoProvvigione') === 'Fatturata'
            ? $importoConsolidato
            : 0.0;

        $dataVendita = $quote?->get('dateOrdered')
            ?? $quote?->get('dataInstallazione')
            ?? $provvigione->get('dataCompetenza');

        $prezzoVendita = $quote?->get('importoContratto') ?? $quote?->get('amount');
        $imponibile = $quote?->get('prezzoListinoIvaEsclusa');
        $aliquota = $quote?->get('aliquotaIVA') ?? $quote?->get('taxRate');

        return [
            'id' => $provvigione->getId(),
            'selected' => $selected,
            'tipo' => $provvigione->get('tipo'),
            'dataVendita' => $dataVendita,
            'cliente' => $provvigione->get('clienteName') ?? $quote?->get('accountName'),
            'contrattoId' => $contrattoId,
            'contrattoName' => $provvigione->get('contrattoName') ?? $quote?->get('name'),
            'statoContratto' => $quote?->get('statoContratto'),
            'prezzoVendita' => $prezzoVendita !== null ? (float) $prezzoVendita : null,
            'aliquota' => $aliquota,
            'imponibile' => $imponibile !== null ? (float) $imponibile : null,
            'minusPlus' => $quote?->get('minusPlus') !== null ? (float) $quote->get('minusPlus') : null,
            'percProv' => $provvigione->get('tassoProvvigioni'),
            'provvGiaPag' => round($provvGiaPag, 2),
            'provvInPag' => round($importoConsolidato, 2),
            'articoliPortale' => $provvigione->get('contrattoName') ?? $quote?->get('name'),
            'statoProvvigione' => $provvigione->get('statoProvvigione'),
        ];
    }
}
