<?php

namespace Espo\Custom\Services;

use DateTimeImmutable;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Stato provvigione derivato dallo stato contratto (Quote.statoContratto).
 *
 * Forecast        ← Inserito, In lavorazione
 * In pagamento    ← Appuntamento fissato, Installato
 * Pagato          ← Chiuso
 * Inesigibile     ← Sospeso, Annullato, Recesso
 *
 * Maturazione invito: pagamento il 15 del mese successivo a installazione o caparra > 15%.
 */
class ProvvigioneStatusSync
{
    public const FORECAST = 'Forecast';

    public const IN_PAGAMENTO = 'In pagamento';

    public const PAGATO = 'Pagato';

    public const INESIGIBILE = 'Inesigibile';

    private const CAPARRA_SOGLIA_PERCENT = 15.0;

    /** @var string[] */
    private const FINANCING_REJECTED_STATES = [
        'Respinto',
        'Annullato',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function resolveStatoFromQuote(?Entity $quote): string
    {
        if (!$quote) {
            return self::FORECAST;
        }

        if ($this->isFinancingRejected($quote)) {
            return self::INESIGIBILE;
        }

        $stato = trim((string) ($quote->get('statoContratto') ?? ''));

        return match ($stato) {
            'Inserito', 'In lavorazione' => self::FORECAST,
            'Appuntamento Fissato', 'Appuntamento fissato', 'Installato' => self::IN_PAGAMENTO,
            'Chiuso' => self::PAGATO,
            'Sospeso', 'Annullato', 'Recesso' => self::INESIGIBILE,
            default => self::FORECAST,
        };
    }

    /**
     * Data pagamento prevista: giorno 15 del mese successivo all'evento di maturazione.
     */
    public function resolveDataPagamento(?Entity $quote): ?string
    {
        $eventDate = $this->resolveMaturityEventDate($quote);

        if ($eventDate === null) {
            return null;
        }

        return $this->paymentDate15NextMonth($eventDate);
    }

    /**
     * Primo giorno del mese di competenza (mese evento maturazione).
     */
    public function resolveMeseCompetenza(?Entity $quote): ?string
    {
        $eventDate = $this->resolveMaturityEventDate($quote);

        if ($eventDate === null) {
            return null;
        }

        return (new DateTimeImmutable($eventDate))->modify('first day of this month')->format('Y-m-d');
    }

    public function syncProvvigioniForQuote(Entity $quote): void
    {
        if (!$quote->getId()) {
            return;
        }

        $targetStato = $this->resolveStatoFromQuote($quote);
        $dataPagamento = $this->resolveDataPagamento($quote);
        $meseCompetenza = $this->resolveMeseCompetenza($quote);

        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quote->getId()])
            ->find();

        foreach ($collection as $provvigione) {
            $provvigione->set('statoProvvigione', $targetStato);

            if ($meseCompetenza !== null) {
                $provvigione->set('dataCompetenza', $meseCompetenza);
            }

            if ($dataPagamento !== null) {
                $provvigione->set('dataLiquidazionePrevista', $dataPagamento);
            }

            $this->entityManager->saveEntity($provvigione, [
                'skipHooks' => true,
                'silent' => true,
                'skipFormula' => true,
            ]);
        }
    }

    public function isEligibleForInvito(Entity $provvigione): bool
    {
        if ($provvigione->get('statoProvvigione') !== self::IN_PAGAMENTO) {
            return false;
        }

        if ($provvigione->get('invitoAFatturareId')) {
            return false;
        }

        return $provvigione->get('dataLiquidazionePrevista') !== null
            && $provvigione->get('dataLiquidazionePrevista') !== '';
    }

    public function matchesInvitoMeseCompetenza(Entity $provvigione, string $meseCompetenza): bool
    {
        $dataPagamento = $provvigione->get('dataLiquidazionePrevista');

        if (!$dataPagamento) {
            return false;
        }

        $paymentMonth = date('Y-m-01', strtotime((string) $dataPagamento));
        $selectedMonth = date('Y-m-01', strtotime($meseCompetenza));

        return $paymentMonth === $selectedMonth;
    }

    public function shouldCountInTotale(string $statoProvvigione): bool
    {
        // Totale contratto: conta sempre, anche Inesigibile.
        return true;
    }

    public function canRecalculate(Entity $provvigione): bool
    {
        $stato = $provvigione->get('statoProvvigione');

        if ($stato === self::PAGATO) {
            return false;
        }

        return in_array($stato, [self::FORECAST, self::IN_PAGAMENTO, self::INESIGIBILE, null, ''], true);
    }

    /**
     * Copia stato contratto e finanziamento da opportunità al contratto (migrazione / creazione).
     */
    public function copyContractFieldsFromOpportunity(Entity $quote, Entity $opportunity): void
    {
        $quote->set([
            'statoContratto' => $opportunity->get('statoContratto') ?: 'In lavorazione',
            'finanziamento' => (bool) $opportunity->get('finanziamento'),
            'statoFinanziamento' => $opportunity->get('statoFinanziamento'),
            'importoCaparra' => $opportunity->get('importoCaparra'),
        ]);

        if (!$quote->get('dataInstallazione') && $opportunity->get('installazione')) {
            $quote->set('dataInstallazione', $opportunity->get('installazione'));
        }
    }

    private function isFinancingRejected(?Entity $quote): bool
    {
        if (!$quote || !(bool) $quote->get('finanziamento')) {
            return false;
        }

        $statoFinanziamento = trim((string) ($quote->get('statoFinanziamento') ?? ''));

        return $statoFinanziamento !== ''
            && in_array($statoFinanziamento, self::FINANCING_REJECTED_STATES, true);
    }

    private function resolveMaturityEventDate(?Entity $quote): ?string
    {
        if (!$quote) {
            return null;
        }

        $installDate = $this->normalizeDate(
            $quote->get('dataInstallazione') ?? $quote->get('dataAttivazione')
        );

        if ($installDate !== null && $this->isInstallatoState($quote)) {
            return $installDate;
        }

        if ($this->hasCaparraOltreSoglia($quote)) {
            return $this->normalizeDate(
                $quote->get('dateOrdered')
                    ?? $quote->get('dateQuoted')
                    ?? $quote->get('createdAt')
            );
        }

        return null;
    }

    private function isInstallatoState(Entity $quote): bool
    {
        $stato = trim((string) ($quote->get('statoContratto') ?? ''));

        return in_array($stato, ['Installato', 'Appuntamento Fissato', 'Appuntamento fissato', 'Chiuso'], true);
    }

    private function hasCaparraOltreSoglia(Entity $quote): bool
    {
        $caparra = $this->floatField($quote, 'importoCaparra');
        $totale = $this->floatField($quote, 'importoContratto')
            ?? $this->floatField($quote, 'amount')
            ?? $this->floatField($quote, 'grandTotalAmount');

        if ($caparra === null || $totale === null || $totale <= 0) {
            return false;
        }

        return (($caparra / $totale) * 100) > self::CAPARRA_SOGLIA_PERCENT;
    }

    private function paymentDate15NextMonth(string $eventDate): string
    {
        $month = (new DateTimeImmutable($eventDate))->modify('first day of next month');

        return $month->format('Y-m-') . '15';
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = substr((string) $value, 0, 10);
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $string);

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function floatField(Entity $entity, string $field): ?float
    {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
