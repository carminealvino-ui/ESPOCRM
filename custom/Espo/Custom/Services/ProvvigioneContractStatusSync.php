<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Mappa stato contratto (Opportunity.statoContratto, Quote.status, finanziamento) → statoProvvigione.
 */
class ProvvigioneContractStatusSync
{
    private const STATO_PREVISTA = 'Prevista';

    private const STATO_CONSOLIDATA = 'Consolidata';

    private const STATO_IN_INVITO = 'InInvito';

    /** @var array<string, string> */
    private const STATUS_TO_STATO = [
        // Forecast
        'presented' => self::STATO_PREVISTA,
        'presentato' => self::STATO_PREVISTA,
        'in lavorazione' => self::STATO_PREVISTA,
        'bozza' => self::STATO_PREVISTA,
        'draft' => self::STATO_PREVISTA,
        'inserito' => self::STATO_PREVISTA,
        'appuntamento fissato' => self::STATO_PREVISTA,
        'sospeso' => self::STATO_PREVISTA,
        'in rivalutazione' => self::STATO_PREVISTA,
        'in attesa documentazione' => self::STATO_PREVISTA,
        // Consolidata
        'approvato' => self::STATO_CONSOLIDATA,
        'approved' => self::STATO_CONSOLIDATA,
        'chiuso' => self::STATO_CONSOLIDATA,
        // In invito a fatturare
        'installato' => self::STATO_IN_INVITO,
        'installed' => self::STATO_IN_INVITO,
    ];

    /** @var list<string> */
    private const PURGE_STATUSES = [
        'recesso',
        'finanziamento rifiutato',
        'finanziamento respinto',
        'annullato',
        'invalido',
        'respinto',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function resolveStatoFromQuote(Entity $quote): ?string
    {
        $opportunity = $this->resolveOpportunity($quote);

        if ($this->shouldPurgeQuote($quote, $opportunity)) {
            return null;
        }

        return $this->resolveMappedStato($quote, $opportunity);
    }

    public function shouldPurgeQuote(Entity $quote, ?Entity $opportunity = null): bool
    {
        $opportunity ??= $this->resolveOpportunity($quote);

        foreach ($this->iterateNormalizedStatuses($quote, $opportunity) as $status) {
            if ($this->shouldPurgeStatus($status)) {
                return true;
            }
        }

        return false;
    }

    public function resolveStatoForNewProvvigione(Entity $quote): string
    {
        return $this->resolveStatoFromQuote($quote) ?? self::STATO_CONSOLIDATA;
    }

    /**
     * @return list<string>
     */
    public function describeQuoteStatuses(Entity $quote): array
    {
        $opportunity = $this->resolveOpportunity($quote);
        $labels = [];

        foreach ($this->statusFieldSources($quote, $opportunity) as [$entity, $field]) {
            $value = $entity->get($field);

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $labels[] = $entity->getEntityType() . '.' . $field . '=' . $value;
        }

        return $labels;
    }

    private function resolveMappedStato(Entity $quote, ?Entity $opportunity): ?string
    {
        foreach ($this->priorityStatusFieldSources($quote, $opportunity) as [$entity, $field]) {
            $raw = $entity->get($field);

            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }

            $status = mb_strtolower(trim((string) $raw));

            if (isset(self::STATUS_TO_STATO[$status])) {
                return self::STATUS_TO_STATO[$status];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function iterateNormalizedStatuses(Entity $quote, ?Entity $opportunity): array
    {
        $values = [];

        foreach ($this->statusFieldSources($quote, $opportunity) as [$entity, $field]) {
            $raw = $entity->get($field);

            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }

            $values[] = mb_strtolower(trim((string) $raw));
        }

        return $values;
    }

    /**
     * Fonti in ordine di priorità per la mappatura stato provvigione.
     *
     * @return list<array{0: Entity, 1: string}>
     */
    private function priorityStatusFieldSources(Entity $quote, ?Entity $opportunity): array
    {
        $sources = [];

        if ($opportunity) {
            $sources[] = [$opportunity, 'statoContratto'];
        }

        $sources[] = [$quote, 'status'];

        if ($opportunity) {
            $sources[] = [$opportunity, 'statoFinanziamento'];
        }

        $sources[] = [$quote, 'statoFinanziamento'];
        $sources[] = [$quote, 'statoContratto'];

        if ($opportunity) {
            $sources[] = [$opportunity, 'status'];
        }

        return $sources;
    }

    /**
     * Tutte le fonti (per purge e diagnostica).
     *
     * @return list<array{0: Entity, 1: string}>
     */
    private function statusFieldSources(Entity $quote, ?Entity $opportunity): array
    {
        $sources = [
            [$quote, 'status'],
            [$quote, 'statoContratto'],
            [$quote, 'statoFinanziamento'],
        ];

        if ($opportunity) {
            $sources[] = [$opportunity, 'statoContratto'];
            $sources[] = [$opportunity, 'statoFinanziamento'];
            $sources[] = [$opportunity, 'status'];
        }

        return $sources;
    }

    private function resolveOpportunity(Entity $quote): ?Entity
    {
        if (!$quote->get('opportunityId')) {
            return null;
        }

        return $this->entityManager->getEntityById('Opportunity', $quote->get('opportunityId'));
    }

    private function shouldPurgeStatus(string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, self::PURGE_STATUSES, true);
    }
}
