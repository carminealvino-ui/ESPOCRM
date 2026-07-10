<?php

namespace Espo\Custom\Services;

/**
 * Mappa stato contratto (Quote.status) → statoProvvigione.
 *
 * Presentato / In lavorazione → Prevista (forecast)
 * Approvato → Consolidata
 * Installato → InInvito
 * Recesso / Finanziamento rifiutato / Annullato → nessuna provvigione (purge)
 */
class ProvvigioneContractStatusSync
{
    private const STATO_PREVISTA = 'Prevista';

    private const STATO_CONSOLIDATA = 'Consolidata';

    private const STATO_IN_INVITO = 'InInvito';

    /** @var array<string, string> */
    private const STATUS_TO_STATO = [
        'presented' => self::STATO_PREVISTA,
        'presentato' => self::STATO_PREVISTA,
        'in lavorazione' => self::STATO_PREVISTA,
        'bozza' => self::STATO_PREVISTA,
        'draft' => self::STATO_PREVISTA,
        'approvato' => self::STATO_CONSOLIDATA,
        'approved' => self::STATO_CONSOLIDATA,
        'installato' => self::STATO_IN_INVITO,
    ];

    /** @var list<string> */
    private const PURGE_STATUSES = [
        'recesso',
        'finanziamento rifiutato',
        'finanziamento respinto',
        'annullato',
        'invalido',
    ];

    public function resolveStatoFromQuote(Entity $quote): ?string
    {
        $status = $this->resolveContractStatusValue($quote);

        if ($status === null) {
            return null;
        }

        if ($this->shouldPurgeStatus($status)) {
            return null;
        }

        return self::STATUS_TO_STATO[$status] ?? null;
    }

    public function shouldPurgeQuote(Entity $quote): bool
    {
        $status = $this->resolveContractStatusValue($quote);

        return $status !== null && $this->shouldPurgeStatus($status);
    }

    public function resolveStatoForNewProvvigione(Entity $quote): string
    {
        return $this->resolveStatoFromQuote($quote) ?? self::STATO_CONSOLIDATA;
    }

    private function resolveContractStatusValue(Entity $quote): ?string
    {
        foreach (['status', 'statoContratto'] as $field) {
            $value = $quote->get($field);

            if ($value !== null && trim((string) $value) !== '') {
                return mb_strtolower(trim((string) $value));
            }
        }

        return null;
    }

    private function shouldPurgeStatus(string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, self::PURGE_STATUSES, true);
    }
}
