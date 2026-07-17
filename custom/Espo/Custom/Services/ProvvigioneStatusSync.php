<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Allinea stato Provvigione in base a Quote.statoContratto.
 */
class ProvvigioneStatusSync
{
    public const FORECAST = 'Forecast';
    public const IN_PAGAMENTO = 'In pagamento';
    public const PAGATO = 'Pagato';
    public const INESIGIBILE = 'Inesigibile';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function resolveStatoFromQuote(?Entity $quote): string
    {
        if (!$quote) {
            return self::FORECAST;
        }

        $stato = trim((string) ($quote->get('statoContratto') ?? ''));

        return match ($stato) {
            'Inserito' => self::FORECAST,
            'In pagamento' => self::IN_PAGAMENTO,
            'Chiuso' => self::PAGATO,
            'Sospeso', 'Annullato', 'Recesso' => self::INESIGIBILE,
            default => self::FORECAST,
        };
    }

    public function syncProvvigioniForQuote(Entity $quote): void
    {
        $quoteId = $quote->getId();

        if (!$quoteId) {
            return;
        }

        $targetStato = $this->resolveStatoFromQuote($quote);

        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        foreach ($collection as $provvigione) {
            if ($provvigione->get('statoProvvigione') === $targetStato) {
                continue;
            }

            $provvigione->set('statoProvvigione', $targetStato);
            $this->entityManager->saveEntity($provvigione, [
                'skipHooks' => true,
                'silent' => true,
                'skipFormula' => true,
            ]);
        }
    }
}
