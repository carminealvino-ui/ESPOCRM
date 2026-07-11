<?php

namespace Espo\Custom\Services;

use DateTimeImmutable;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Stato provvigione derivato dallo stato contratto (Opportunity.statoContratto).
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

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function resolveStatoFromOpportunity(?Entity $opportunity): string
    {
        if (!$opportunity) {
            return self::FORECAST;
        }

        $stato = trim((string) ($opportunity->get('statoContratto') ?? ''));

        return match ($stato) {
            'Inserito', 'In lavorazione' => self::FORECAST,
            'Appuntamento fissato', 'Installato' => self::IN_PAGAMENTO,
            'Chiuso' => self::PAGATO,
            'Sospeso', 'Annullato', 'Recesso' => self::INESIGIBILE,
            default => self::FORECAST,
        };
    }

    /**
     * Data pagamento prevista: giorno 15 del mese successivo all'evento di maturazione.
     */
    public function resolveDataPagamento(?Entity $quote, ?Entity $opportunity): ?string
    {
        $eventDate = $this->resolveMaturityEventDate($quote, $opportunity);

        if ($eventDate === null) {
            return null;
        }

        return $this->paymentDate15NextMonth($eventDate);
    }

    /**
     * Primo giorno del mese di competenza (mese evento maturazione).
     */
    public function resolveMeseCompetenza(?Entity $quote, ?Entity $opportunity): ?string
    {
        $eventDate = $this->resolveMaturityEventDate($quote, $opportunity);

        if ($eventDate === null) {
            return null;
        }

        return (new DateTimeImmutable($eventDate))->modify('first day of this month')->format('Y-m-d');
    }

    public function syncProvvigioniForQuote(Entity $quote, ?Entity $opportunity = null): void
    {
        if (!$quote->getId()) {
            return;
        }

        $opportunity ??= $this->resolveOpportunity($quote);
        $targetStato = $this->resolveStatoFromOpportunity($opportunity);
        $dataPagamento = $this->resolveDataPagamento($quote, $opportunity);
        $meseCompetenza = $this->resolveMeseCompetenza($quote, $opportunity);

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
        return !in_array($statoProvvigione, [self::INESIGIBILE], true);
    }

    public function canRecalculate(Entity $provvigione): bool
    {
        $stato = $provvigione->get('statoProvvigione');

        return in_array($stato, [self::FORECAST, self::IN_PAGAMENTO, null, ''], true);
    }

    private function resolveMaturityEventDate(?Entity $quote, ?Entity $opportunity): ?string
    {
        $installDate = $this->normalizeDate(
            $opportunity?->get('installazione')
                ?? $quote?->get('dataInstallazione')
                ?? $quote?->get('dataAttivazione')
        );

        if ($installDate !== null && $this->isInstallatoState($opportunity)) {
            return $installDate;
        }

        if ($opportunity && $this->hasCaparraOltreSoglia($opportunity)) {
            return $this->normalizeDate(
                $opportunity->get('closeDate')
                    ?? $opportunity->get('dataOpportunit')
                    ?? $quote?->get('dateOrdered')
                    ?? $quote?->get('createdAt')
            );
        }

        return null;
    }

    private function isInstallatoState(?Entity $opportunity): bool
    {
        if (!$opportunity) {
            return false;
        }

        $stato = trim((string) ($opportunity->get('statoContratto') ?? ''));

        return in_array($stato, ['Installato', 'Appuntamento fissato', 'Chiuso'], true);
    }

    private function hasCaparraOltreSoglia(Entity $opportunity): bool
    {
        $caparra = $this->floatField($opportunity, 'importoCaparra');
        $totale = $this->floatField($opportunity, 'importoOpportunit')
            ?? $this->floatField($opportunity, 'amount')
            ?? $this->floatField($opportunity, 'importoOffertaIvaEsclusa');

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

    private function resolveOpportunity(Entity $quote): ?Entity
    {
        if (!$quote->get('opportunityId')) {
            return null;
        }

        return $this->entityManager->getEntityById('Opportunity', $quote->get('opportunityId'));
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
