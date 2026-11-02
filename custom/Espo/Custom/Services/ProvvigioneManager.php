<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Creazione e aggiornamento provvigioni tramite regole.
 * Stato: Forecast / In pagamento / Pagato / Inesigibile (da stato contratto).
 */
class ProvvigioneManager
{
    public function __construct(
        private EntityManager $entityManager,
        private RegolaProvvigionaleCalculator $calculator,
        private ProvvigioneAccrual $accrual,
        private QuotePricingCalculator $quotePricingCalculator,
        private ProvvigioneStatusSync $statusSync
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildContextFromEntities(
        ?Entity $category,
        ?Entity $source,
        ?float $imponibileOverride = null,
        ?Entity $opportunity = null
    ): array {
        $imponibile = $imponibileOverride;

        if ($imponibile === null && $source) {
            $imponibile = $this->resolveImponibile($source);
        }

        if ($imponibile === null && $opportunity) {
            $imponibile = $this->resolveImponibile($opportunity);
        }

        $prezzoCodice = null;

        if ($source && $source->getEntityType() === 'Quote') {
            $prezzoCodice = $this->quotePricingCalculator->resolvePrezzoCodiceNetForMinusPlus($source, $opportunity);
        }

        if ($prezzoCodice === null || $prezzoCodice <= 0) {
            $prezzoCodice = $this->resolvePrezzoCodice($source, $opportunity);
        }

        $prezzoCodiceTotale = $this->resolvePrezzoCodice($source, $opportunity);

        if ($prezzoCodiceTotale === null || $prezzoCodiceTotale <= 0) {
            $prezzoCodiceTotale = $prezzoCodice;
        }

        $prezzoListino = $this->resolvePrezzoListino($source, $opportunity);

        $referenceDate = $this->resolveCommercialReferenceDate($source, $opportunity);

        $regime = $this->accrual->resolveRegimeFromCommercial(
            $category,
            $source?->get('fornitorePartnerName') ?? $opportunity?->get('fornitorePartnerName'),
            $source?->get('productBrandName') ?? $opportunity?->get('productBrandName'),
            $referenceDate
        );

        $ecoWindEasy = $this->isArielEcoWindEasy($category, $source, $opportunity);

        $gruppoProvvigione = $category?->get('gruppoProvvigione');

        if ($regime === 'ARIEL 2025') {
            $gruppoProvvigione = $this->resolveArielLegacyGruppo($category, $ecoWindEasy);
        }

        if ($regime === 'ARQUATI 2023' && !$gruppoProvvigione) {
            $name = strtoupper((string) $category?->get('name'));
            $gruppoProvvigione = match (true) {
                str_contains($name, 'PERGOL'), str_contains($name, 'BIOCLIMAT') => 'Pergole',
                str_contains($name, 'VETRAT') => 'Vetrate',
                str_contains($name, 'TENDA'), str_contains($name, 'TENDE') => 'Tende da Sole',
                str_contains($name, 'CLIMA'), str_contains($name, 'ACCESSOR') => 'Clima e altro',
                default => null,
            };
        }

        $plusvalenza = null;

        if ($regime === 'ARIEL 2025') {
            // Allineato al campo Minus/Plus del contratto quando disponibile.
            $minusPlusStored = $source ? $this->floatField($source, 'minusPlus') : null;

            if ($minusPlusStored !== null && $minusPlusStored > 0) {
                $plusvalenza = round($minusPlusStored, 2);
            } elseif (
                !$ecoWindEasy
                && $imponibile !== null
                && $prezzoCodiceTotale !== null
                && $imponibile > $prezzoCodiceTotale
            ) {
                // Documento: «Plus oltre il prezzo codice» — 50% su (imponibile − codice totale).
                $plusvalenza = round($imponibile - $prezzoCodiceTotale, 2);
            }
        } elseif ($imponibile !== null && $prezzoCodice !== null && $imponibile > $prezzoCodice) {
            $plusvalenza = $imponibile - $prezzoCodice;
        }

        $prezzoCodicePerScaletta = $regime === 'ARIEL 2025' ? $prezzoCodiceTotale : $prezzoCodice;

        $margine = $this->resolveMarginePercentuale(
            $source,
            $opportunity,
            $imponibile,
            $prezzoListino,
            $prezzoCodicePerScaletta,
            $regime
        );

        $artel = ArtelCommissionPolicy::applies(
            $source?->get('fornitorePartnerId') ?? $opportunity?->get('fornitorePartnerId'),
            $source?->get('productBrandId') ?? $opportunity?->get('productBrandId')
        );
        $personal = false;
        if ($artel) {
            // Use actual list discount, never the unrelated code-price percentage.
            $margine = ArtelCommissionPolicy::margin($imponibile, $prezzoListino);
            $personal = ArtelCommissionPolicy::isPersonal($source?->get('leadSource'))
                || ArtelCommissionPolicy::isPersonal($opportunity?->get('leadSource'))
                || ($source?->getEntityType() === 'Appuntamento' && ArtelCommissionPolicy::isPersonal($source->get('tipo')));
            $personalOpportunity = $opportunity ?? ($source?->getEntityType() === 'Opportunity' ? $source : null);
            if ($personalOpportunity) $personal = $personal || $this->isReferenzaPersonaleOpportunity($personalOpportunity);
        }

        return [
            'artel' => $artel,
            'artelPersonal' => $personal,
            'quoteId' => $source?->getEntityType() === 'Quote' ? $source->getId() : null,
            'regime' => $regime,
            'fornitorePartnerId' => $source?->get('fornitorePartnerId') ?? $opportunity?->get('fornitorePartnerId'),
            'productBrandId' => $source?->get('productBrandId') ?? $opportunity?->get('productBrandId'),
            'productCategoryId' => $category?->getId() ?? $source?->get('productCategoryId') ?? $opportunity?->get('productCategoryId'),
            'gruppoProvvigione' => $gruppoProvvigione,
            'imponibile' => $imponibile,
            'canoneMensile' => $this->floatField($source, 'canoneMensile') ?? $imponibile,
            'inflowTotale' => $imponibile,
            'plusvalenza' => $plusvalenza,
            'marginePercentuale' => $margine,
            'numeroPod' => $source?->get('numeroPod') ?? $opportunity?->get('numeroPod'),
            'contattoPersonaleArquati' => (bool) ($source?->get('contattoPersonaleArquati')
                ?? $opportunity?->get('contattoPersonaleArquati')),
            'integrazionePncPercentuale' => $this->floatField($source, 'integrazionePncPercentuale')
                ?? $this->floatField($opportunity, 'integrazionePncPercentuale'),
            'ordineIncompletoAriel' => (bool) ($source?->get('ordineIncompletoAriel')
                ?? $opportunity?->get('ordineIncompletoAriel')),
            'arielEcoWindEasy' => $ecoWindEasy,
            'referenceDate' => $referenceDate,
            'contractSignedDate' => $source?->getEntityType() === 'Quote'
                ? $source->get('dateQuoted')
                : null,
        ];
    }

    public function syncPrevistaFromAppuntamento(Entity $appuntamento): ?Entity
    {
        if (!$appuntamento->get('productCategoryId')) {
            return null;
        }

        $category = $this->entityManager->getEntityById(
            'ProductCategory',
            $appuntamento->get('productCategoryId')
        );

        if (!$category) {
            return null;
        }

        // Appointments carry no commercial amount; fixed-fee rules remain supported.
        $context = $this->buildContextFromEntities($category, $appuntamento);
        $result = $this->calculator->calculateBest($context);

        $provvigione = $this->findProvvigioneByAppuntamento($appuntamento->getId())
            ?? $this->entityManager->createEntity('Provvigione');

        $this->applyProvvigioneFromCalculation(
            $provvigione,
            $appuntamento,
            $category,
            $result,
            ProvvigioneStatusSync::FORECAST,
            $context,
            $appuntamento
        );

        $this->saveProvvigioneEntity($provvigione);

        return $provvigione;
    }

    public function createConsolidataForQuote(Entity $opportunity, Entity $quote): ?Entity
    {
        $this->quotePricingCalculator->syncOnBeforeSave($quote);

        $category = $this->resolveProductCategory($quote, $opportunity);

        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity);
        $minusPlus = $this->quotePricingCalculator->resolveMinusPlusForQuote($quote);

        if ($minusPlus !== null) {
            $quote->set('minusPlus', $minusPlus);
        }

        $context = $this->buildContextFromEntities($category, $quote, $imponibile, $opportunity);
        $context['imponibile'] = $imponibile;

        if (!empty($context['artel'])) {
            $result = $this->calculator->calculateBest($context);
            $personalResult = $result && !empty($context['artelPersonal'])
                ? $this->calculator->calculateForTipoRecord($context, 'Referenza Personale') : null;
            if ($personalResult === null) {
                $oldPersonal = $this->findProvvigioneByContrattoAndTipo($quote->getId(), 'Referenza Personale');
                if ($oldPersonal && in_array($oldPersonal->get('regolaProvvigionaleId'), ['artelPersonal3', 'artelPersonalFv2'], true)
                    && !$oldPersonal->get('consolidata') && !$oldPersonal->get('invitoAFatturareId')) {
                    $this->entityManager->removeEntity($oldPersonal);
                }
            }
            if ($result === null) {
                $old = $this->findProvvigioneByContrattoAndTipo($quote->getId(), 'Provvigione Base');
                if ($old && !$old->get('consolidata') && !$old->get('invitoAFatturareId')
                    && str_starts_with((string) $old->get('regolaProvvigionaleId'), 'artel24')) {
                    $this->entityManager->removeEntity($old);
                }
                $this->refreshQuoteTotaleProvvigioni($quote);
                return null;
            }
            $provvigione = $this->saveConsolidataProvvigione($opportunity, $quote, $category,
                $result, $context, 'Provvigione Base');
            if ($personalResult !== null) {
                $this->saveConsolidataProvvigione($opportunity, $quote, $category,
                    $personalResult, $context, 'Referenza Personale');
            }
            $this->statusSync->syncProvvigioniForQuote($quote);
            $this->refreshQuoteTotaleProvvigioni($quote);
            return $provvigione;
        }

        if ($context['regime'] === 'ARIEL 2026') {
            return $this->createConsolidataAriel2026($opportunity, $quote, $category, $context, $imponibile);
        }

        if ($context['regime'] === 'ARIEL 2025') {
            return $this->createConsolidataArielLegacy($opportunity, $quote, $category, $context, $imponibile);
        }

        if (!$category) {
            return null;
        }

        $context['plusvalenza'] = ($minusPlus !== null && $minusPlus > 0)
            ? $minusPlus
            : (($minusPlus !== null && $minusPlus < 0) ? null : ($context['plusvalenza'] ?? null));

        if ($minusPlus !== null && $minusPlus < 0) {
            $context['minusvalenza'] = $minusPlus;
        }

        if ($context['marginePercentuale'] !== null && !$quote->get('margineSuListino')) {
            $quote->set('margineSuListino', $context['marginePercentuale']);
            $this->entityManager->saveEntity($quote, [
                'skipHooks' => true,
                'silent' => true,
            ]);
        }

        $result = $this->calculator->calculateBest($context);


        $provvigione = $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Provvigione Base',
            null
        );

        if ($context['regime'] === 'ARQUATI 2023' && $result !== null) {
            $rulePnc = $this->entityManager->getEntityById('RegolaProvvigionale', 'arqPnc1');
            if ($rulePnc && $rulePnc->get('attiva') && $this->calculator->isWithinValidity($rulePnc, $context)) {
                $amountPnc = $this->calculator->calculateRule($rulePnc, $context);
                $this->saveConsolidataProvvigione($opportunity, $quote, $category,
                    ['importo' => $amountPnc, 'regola' => $rulePnc], $context, 'Patto di non concorrenza (PNC)');
            }
        }
        $this->syncIntegrazioneContattiPersonali($quote, $opportunity, $category, $context, $imponibile);
        if ($context['regime'] !== 'ARQUATI 2023') {
        $this->ensureWeekendBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);
        $this->ensureTaxiBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);
        }

        $this->statusSync->syncProvvigioniForQuote($quote);
        $this->refreshQuoteTotaleProvvigioni($quote);

        return $provvigione;
    }

    public function resolveTotaleProvvigioniForQuoteId(string $quoteId): ?float
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $totale = 0.0;
        $counted = 0;

        foreach ($collection as $provvigione) {
            $importo = $provvigione->get('importoConsolidato') ?? $provvigione->get('importo');

            if ($importo === null || $importo === '') {
                continue;
            }

            $totale += (float) $importo;
            $counted++;
        }

        return $counted > 0 ? round($totale, 2) : null;
    }

    public function refreshQuoteTotaleProvvigioni(Entity $quote): void
    {
        if (!$quote->getId()) {
            return;
        }

        $totale = $this->resolveTotaleProvvigioniForQuoteId($quote->getId());

        $quote->set('totaleProvvigioni', $totale);

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
    }

    /**
     * Ricalcola tutte le provvigioni consolidate del contratto tramite regole.
     *
     * @return array{created: int, updated: int, purged: int}
     */
    public function recalculateAllForQuote(Entity $quote, bool $forceRecalculate = false): array
    {
        $opportunity = $this->resolveOpportunityForQuote($quote, null);

        if (!$opportunity) {
            return ['created' => 0, 'updated' => 0, 'purged' => 0];
        }

        $purged = $forceRecalculate
            ? $this->purgeAllAutoProvvigioniForQuote($quote->getId())
            : $this->purgeRecalculableProvvigioniForQuote($quote->getId());
        $this->syncQuotePricingFields($quote, $opportunity);

        $quoteId = $quote->getId();
        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return ['created' => 0, 'updated' => 0, 'purged' => $purged];
        }

        $this->createConsolidataForQuote($opportunity, $quote);
        $this->refreshQuoteTotaleProvvigioni($quote);

        $count = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quote->getId()])
            ->count();

        return [
            'created' => $count,
            'updated' => 0,
            'purged' => $purged,
        ];
    }

    private function saveProvvigioneEntity(Entity $provvigione): void
    {
        if ($provvigione->get('consolidata')) return;
        CommissionConsolidation::apply($this->entityManager, $provvigione);
        $this->entityManager->saveEntity($provvigione, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
    }

    private function purgeRecalculableProvvigioniForQuote(string $quoteId): int
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $count = 0;

        foreach ($collection as $provvigione) {
            if ($provvigione->get('consolidata') || $provvigione->get('invitoAFatturareId')) continue;
            if (!$this->statusSync->canRecalculate($provvigione)) {
                continue;
            }

            $this->entityManager->removeEntity($provvigione);
            $count++;
        }

        return $count;
    }

    /**
     * Rimuove tutte le righe provvigionali automatiche (bonifica massiva).
     *
     * @return list<string>
     */
    private const AUTO_PROVVIGIONE_TIPI = [
        'Bonus Estate 2026',
        'Bonus Ordine Completo',
        'Provvigione Base',
        'Plus Provvigionale',
        'Minus Provvigionale',
        'Bonus (Sabato-Domenica)',
        'Bonus Taxi',
        'Referenza Personale',
        'Patto di non concorrenza (PNC)',
    ];

    private function purgeAllAutoProvvigioniForQuote(string $quoteId): int
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $count = 0;

        foreach ($collection as $provvigione) {
            if ($provvigione->get('consolidata') || $provvigione->get('invitoAFatturareId')) continue;
            $tipo = (string) ($provvigione->get('tipo') ?? '');
            $hasRule = (bool) $provvigione->get('regolaProvvigionaleId');

            if (!$hasRule && !in_array($tipo, self::AUTO_PROVVIGIONE_TIPI, true)) {
                continue;
            }

            $this->entityManager->removeEntity($provvigione);
            $count++;
        }

        return $count;
    }

    private function purgeProvvigioniForQuote(string $quoteId): int
    {
        $collection = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quoteId])
            ->find();

        $count = 0;

        foreach ($collection as $provvigione) {
            if ($provvigione->get('consolidata') || $provvigione->get('invitoAFatturareId')) continue;
            $this->entityManager->removeEntity($provvigione);
            $count++;
        }

        return $count;
    }

    private function syncQuotePricingFields(Entity $quote, Entity $opportunity): void
    {
        $this->quotePricingCalculator->syncOnBeforeSave($quote);

        $imponibile = $this->quotePricingCalculator->resolveImponibileNetto($quote)
            ?? $this->resolveQuoteImponibile($quote, $opportunity);
        $minusPlus = $this->quotePricingCalculator->resolveMinusPlusForQuote($quote);
        $prezzoListino = $this->floatField($quote, 'prezzoListinoIvaEsclusa')
            ?? $this->floatField($opportunity, 'prezzoListinoIvaEsclusa');

        if ($minusPlus !== null) {
            $quote->set('minusPlus', $minusPlus);
        }

        if ($imponibile !== null && $prezzoListino !== null && $prezzoListino > 0) {
            $quote->set(
                'margineSuListino',
                round((($imponibile - $prezzoListino) / $prezzoListino) * 100, 2)
            );
        }

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
    }

    private function resolveOpportunityForQuote(Entity $quote, ?Entity $provvigione): ?Entity
    {
        $opportunityId = $quote->get('opportunityId') ?: $provvigione?->get('opportunitaId');

        if (!$opportunityId) {
            return null;
        }

        return $this->entityManager->getEntityById('Opportunity', $opportunityId);
    }

    /**
     * Ariel legacy (scalette minus fino al 25/01/2026 incluso):
     * % su imponibile per fascia sconto su prezzo codice + plus 50% oltre codice.
     *
     * @param array<string, mixed> $context
     */
    private function createConsolidataArielLegacy(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context,
        ?float $imponibile
    ): ?Entity {
        if ($imponibile === null || $imponibile <= 0) {
            return null;
        }

        $minusPlus = $this->resolveMinusPlusValue($quote, $opportunity, $imponibile);

        $quote->set([
            'minusPlus' => $minusPlus,
            'prezzoCodiceIvaEsclusa' => $quote->get('prezzoCodiceIvaEsclusa')
                ?: $opportunity->get('prezzoCodiceIvaEsclusa'),
            'prezzoListinoIvaEsclusa' => $quote->get('prezzoListinoIvaEsclusa')
                ?: $opportunity->get('prezzoListinoIvaEsclusa'),
        ]);

        if ($context['marginePercentuale'] !== null) {
            $quote->set('margineSuListino', $context['marginePercentuale']);
        }

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
        ]);

        $context = $this->refreshArielLegacyContext($quote, $opportunity, $context, $imponibile, $minusPlus);

        $result = $this->calculator->calculateForTipoRecord($context, 'Provvigione Base')
            ?? $this->buildArielLegacyBaseResult($context, $imponibile);

        $base = $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Provvigione Base',
            null
        );

        $this->ensureArielLegacyPlusProvvigione($opportunity, $quote, $category, $context);
        $this->ensureWeekendBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);
        $this->ensureTaxiBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);
        $this->statusSync->syncProvvigioniForQuote($quote);
        $this->refreshQuoteTotaleProvvigioni($quote);

        return $base;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function ensureArielLegacyPlusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context
    ): void {
        if (!empty($context['arielEcoWindEasy'])) {
            return;
        }

        if ($context['plusvalenza'] === null || $context['plusvalenza'] <= 0) {
            return;
        }

        $plusResult = $this->resultFromRuleId('arlLegacyPlus50', $context)
            ?? $this->calculator->calculateForTipoRecord($context, 'Plus Provvigionale')
            ?? $this->buildArielLegacyPlusResult($context);

        if ($plusResult === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $plusResult,
            $context,
            'Plus Provvigionale',
            null
        );
    }

    /**
     * GDL / Ariel 2026: provvigione base + referenza personale (additiva) + plus + bonus weekend.
     * Art. 3.4: minusvalenza nel contatore Quote.minusPlus, senza provvigione negativa.
     *
     * @param array<string, mixed> $context
     */
    private function createConsolidataAriel2026(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context,
        ?float $imponibile
    ): ?Entity {
        if ($imponibile === null || $imponibile <= 0) {
            return null;
        }

        $minusPlus = $this->resolveMinusPlusValue($quote, $opportunity, $imponibile);

        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity) ?? $imponibile;
        $context['imponibile'] = $imponibile;

        $quote->set([
            'minusPlus' => $minusPlus,
            'prezzoCodiceIvaEsclusa' => $quote->get('prezzoCodiceIvaEsclusa')
                ?: $opportunity->get('prezzoCodiceIvaEsclusa'),
            'prezzoListinoIvaEsclusa' => $quote->get('prezzoListinoIvaEsclusa')
                ?: $opportunity->get('prezzoListinoIvaEsclusa'),
        ]);

        $this->entityManager->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
        ]);

        $context['plusvalenza'] = ($minusPlus !== null && $minusPlus > 0) ? $minusPlus : null;

        $baseResult = $this->resultFromRuleId('arielBase10', $context);

        $base = $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $baseResult,
            $context,
            'Provvigione Base',
            null
        );

        $this->syncArielCompleteOrderBonus($opportunity, $quote, $category, $context);

        if ($this->isReferenzaPersonaleOpportunity($opportunity)) {
            $refResult = $this->resultFromRuleId('referenzaPersonale', $context);

            if ($refResult !== null) {
                $this->saveConsolidataProvvigione(
                    $opportunity,
                    $quote,
                    $category,
                    $refResult,
                    $context,
                    'Referenza Personale',
        'Patto di non concorrenza (PNC)',
                    null
                );
            }
        }

        if ($context['plusvalenza'] !== null && $context['plusvalenza'] > 0) {
            $this->ensureArielPlusProvvigione($opportunity, $quote, $category, $context);

        }

        $this->ensureWeekendBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);
        $this->ensureTaxiBonusProvvigione($opportunity, $quote, $category, $context, $imponibile);

        $this->statusSync->syncProvvigioniForQuote($quote);
        $this->refreshQuoteTotaleProvvigioni($quote);

        return $base;
    }

    /**
     * @param array<string, mixed> $context
     */
    /** Update only the completeness bonus, preserving the base and other commissions. */
    public function syncArielOrderCompleteness(Entity $quote): void
    {
        $opportunity = $this->resolveOpportunityForQuote($quote, null);
        if (!$opportunity) return;
        $category = $this->resolveProductCategory($quote, $opportunity);
        $imponibile = $this->resolveQuoteImponibile($quote, $opportunity);
        $context = $this->buildContextFromEntities($category, $quote, $imponibile, $opportunity);
        if ($context['regime'] !== 'ARIEL 2026') return;
        $this->syncArielCompleteOrderBonus($opportunity, $quote, $category, $context);
        $this->refreshQuoteTotaleProvvigioni($quote);
    }

    private function syncArielCompleteOrderBonus(
        Entity $opportunity, Entity $quote, ?Entity $category, array $context
    ): void {
        $existing = $this->findProvvigioneByContrattoAndTipo($quote->getId(), 'Bonus Ordine Completo');
        if (!empty($context['ordineIncompletoAriel'])) {
            if ($existing && !$existing->get('consolidata') && !$existing->get('invitoAFatturareId')) $this->entityManager->removeEntity($existing, ['skipHooks' => true, 'silent' => true]);
            return;
        }
        $result = $this->resultFromRuleId('arielBase105', $context);
        if (!$result) return;
        $this->saveConsolidataProvvigione(
            $opportunity, $quote, $category, $result, $context, 'Bonus Ordine Completo', null
        );
    }

    private function ensureArielPlusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context
    ): void {
        if ($context['plusvalenza'] === null || $context['plusvalenza'] <= 0) {
            return;
        }

        $result = $this->resultFromRuleId('arielPlus35', $context);

        if ($result === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Plus Provvigionale',
            null
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function ensureWeekendBonusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context,
        ?float $imponibile
    ): void {
        // AMG / ARTEL does not provide a Saturday-Sunday commission bonus.
        foreach (['fornitorePartner' => 'FornitorePartner', 'productBrand' => 'ProductBrand'] as $link => $type) {
            $source = $quote->get($link . 'Id') ? $quote : $opportunity;
            $id = $source->get($link . 'Id');
            $name = $id
                ? $this->entityManager->getEntityById($type, $id)?->get('name')
                : $source->get($link . 'Name');
            if (preg_match('/\b(?:AMG|ARTEL)\b/i', (string) $name)) {
                return;
            }
        }

        if ($imponibile === null || $imponibile <= 0) {
            return;
        }

        if (!$this->isWeekendContractDate($quote, $opportunity)) {
            return;
        }

        $this->ensureSummerBonus($opportunity, $quote, $category, $context);

        $result = $this->resultFromRuleId('bonusWeekendSd', $context)
            ?? $this->calculator->calculateForTipoRecord($context, 'Bonus (Sabato-Domenica)');

        if ($result === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Bonus (Sabato-Domenica)',
            null
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function ensureSummerBonus(Entity $opportunity, Entity $quote, ?Entity $category, array $context): void
    {
        $date = $quote->get('dateQuoted');
        if ($quote->get('fornitorePartnerId') !== '698efa2f79bb6c0ee'
            || !$date || $date < '2026-07-01' || $date > '2026-08-31'
            || !$this->isWeekendContractDate($quote, $opportunity)) return;
        $result = $this->resultFromRuleId('bonusEstate2026', $context);
        if ($result === null) return;
        $this->saveConsolidataProvvigione($opportunity, $quote, $category, $result,
            $context, 'Bonus Estate 2026');
    }

    private function ensureTaxiBonusProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        array $context,
        ?float $imponibile
    ): void {
        if ($imponibile === null || $imponibile <= 0) {
            return;
        }

        if (!$this->isTaxiOpportunity($opportunity)) {
            return;
        }

        $result = $this->resultFromRuleId('bonusTaxi2', $context)
            ?? $this->calculator->calculateForTipoRecord($context, 'Bonus Taxi')
            ?? $this->buildTaxiBonusResult($context, $imponibile);

        if ($result === null) {
            return;
        }

        $this->saveConsolidataProvvigione(
            $opportunity,
            $quote,
            $category,
            $result,
            $context,
            'Bonus Taxi',
            null
        );
    }

    private function isTaxiOpportunity(Entity $opportunity): bool
    {
        $appuntamentoId = $opportunity->get('appuntamentoId');

        if (!$appuntamentoId) {
            return false;
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        return $appuntamento && (bool) $appuntamento->get('taxi');
    }

    /**
     * Fallback se la regola bonusTaxi2 non è ancora in DB (seed non eseguito).
     *
     * @param array<string, mixed> $context
     * @return array{importo: float, regola: Entity}|null
     */
    private function buildTaxiBonusResult(array $context, float $imponibile): ?array
    {
        if ($imponibile <= 0 || $this->entityManager->getEntityById('RegolaProvvigionale', 'bonusTaxi2')) {
            return null;
        }

        $rule = $this->entityManager->getNewEntity('RegolaProvvigionale');
        $rule->set([
            'id' => 'bonusTaxi2',
            'name' => 'Bonus Taxi',
            'attiva' => true,
            'tipoCalcolo' => 'PercentualeImponibile',
            'tipoProvvigioneRecord' => 'Bonus Taxi',
            'percentuale' => 2.0,
        ]);

        return [
            'importo' => round($imponibile * 0.02, 2),
            'regola' => $rule,
        ];
    }

    private function isWeekendContractDate(Entity $quote, ?Entity $opportunity): bool
    {
        $date = $this->resolveWeekendReferenceDate($quote, $opportunity);

        if ($date === null) {
            return false;
        }

        $day = (int) (new \DateTimeImmutable($date))->format('N');

        return $day >= 6;
    }

    private function resolveWeekendReferenceDate(Entity $quote, ?Entity $opportunity): ?string
    {
        $candidates = [
            $quote->get('dateQuoted'),
            $this->parseDateFromContractLabel($quote->get('name')),
            $opportunity?->get('closeDate'),
            $opportunity?->get('dataOpportunit'),
            $quote->get('createdAt'),
        ];

        if ($opportunity?->get('appuntamentoId')) {
            $appuntamento = $this->entityManager->getEntityById(
                'Appuntamento',
                $opportunity->get('appuntamentoId')
            );

            if ($appuntamento) {
                $candidates[] = $appuntamento->get('dateStart');
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeDateValue($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function parseDateFromContractLabel(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $name, $m)) {
            return $m[1];
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $name, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        if (strlen($string) >= 10) {
            $string = substr($string, 0, 10);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $string);

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function isReferenzaPersonaleOpportunity(Entity $opportunity): bool
    {
        $appuntamentoId = $opportunity->get('appuntamentoId');

        if (!$appuntamentoId) {
            return false;
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$appuntamento) {
            return false;
        }

        $tipo = $appuntamento->get('tipo');

        if (is_array($tipo)) {
            return in_array('Referenza Personale', $tipo, true);
        }

        if (is_string($tipo) && $tipo !== '') {
            return str_contains($tipo, 'Referenza Personale');
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{importo: float, regola: Entity}|null
     */
    private function resultFromRuleId(string $ruleId, array $context, bool $allowNegative = false): ?array
    {
        $rule = $this->entityManager->getEntityById('RegolaProvvigionale', $ruleId);

        if (!$rule || !$rule->get('attiva')) {
            return null;
        }

        if (!$this->calculator->isWithinValidity($rule, $context)) {
            $tipoRecord = $rule->get('tipoProvvigioneRecord');
            return $tipoRecord
                ? $this->calculator->calculateForTipoRecord($context, $tipoRecord)
                : null;
        }

        $importo = $this->calculator->calculateRule($rule, $context);

        if ($importo === null) {
            return null;
        }

        if ($allowNegative) {
            if ($importo >= 0) {
                return null;
            }
        } elseif ($importo <= 0) {
            return null;
        }

        return [
            'importo' => $importo,
            'regola' => $rule,
        ];
    }

    /**
     * @param array{importo: float, regola: Entity}|null $result
     * @param array<string, mixed> $context
     */
    private function saveConsolidataProvvigione(
        Entity $opportunity,
        Entity $quote,
        ?Entity $category,
        ?array $result,
        array $context,
        string $tipo,
        ?string $nameSuffix = null
    ): ?Entity {
        $provvigione = $this->findProvvigioneByContrattoAndTipo($quote->getId(), $tipo)
            ?? $this->entityManager->createEntity('Provvigione');

        if ($provvigione->get('consolidata') || $provvigione->get('invitoAFatturareId')) return $provvigione;

        $stato = $this->statusSync->resolveStatoFromQuote($quote);

        $this->applyProvvigioneFromCalculation(
            $provvigione,
            $opportunity,
            $category,
            $result,
            $stato,
            $context,
            $quote
        );

        $clienteId = $quote->get('accountId') ?: $opportunity->get('accountId');
        $clienteName = $quote->get('accountName') ?: $opportunity->get('accountName');

        $provvigione->set([
            'tipo' => $tipo,
            'contrattoId' => $quote->getId(),
            'contrattoName' => $quote->get('name'),
            'opportunitaId' => $opportunity->getId(),
            'opportunitaName' => $opportunity->get('name'),
            'clienteId' => $clienteId,
            'clienteName' => $clienteName,
        ]);

        $importo = $provvigione->get('importoConsolidato') ?? $provvigione->get('importo');

        if ($importo !== null && $importo !== '') {
            $importoRounded = round((float) $importo, 2);
            $provvigione->set([
                'importo' => $importoRounded,
                'importoConsolidato' => $importoRounded,
                'name' => $this->buildProvvigioneDisplayName($quote, $tipo, $importoRounded),
            ]);
        }

        $this->saveProvvigioneEntity($provvigione);

        return $provvigione;
    }

    private function resolveProductCategory(Entity $quote, Entity $opportunity): ?Entity
    {
        $categoryId = $quote->get('productCategoryId') ?: $opportunity->get('productCategoryId');

        if (!$categoryId) {
            return null;
        }

        return $this->entityManager->getEntityById('ProductCategory', $categoryId);
    }

    /**
     * @param array<string, mixed>|null $context
     * @param array{importo: float, regola: Entity}|null $result
     */
    private function applyProvvigioneFromCalculation(
        Entity $provvigione,
        Entity $parent,
        ?Entity $category,
        ?array $result,
        string $stato,
        ?array $context,
        Entity $dateSource
    ): void {
        $regime = $context['regime'] ?? $this->accrual->resolveRegimeFromCommercial(
            $category,
            $parent->get('fornitorePartnerName'),
            $parent->get('productBrandName'),
            $context['referenceDate'] ?? $this->resolveCommercialReferenceDate($dateSource, null)
        );
        $importo = $result['importo'] ?? null;
        $rule = $result['regola'] ?? null;

        $dataAttivazione = $dateSource->get('dataAttivazione');
        $dataInstallazione = $dateSource->get('dataInstallazione') ?? $dateSource->get('installazione');
        $eventDate = $this->accrual->resolveEventDate($dataAttivazione, $dataInstallazione);

        $giorni = $rule?->get('giorniLiquidazione') ?? $this->accrual->getLiquidationDays($regime);
        $tipoRecord = $rule?->get('tipoProvvigioneRecord') ?? 'Provvigione Base';

        $provvigione->set([
            'statoProvvigione' => $stato,
            'regimeProvvigione' => $regime,
            'tipo' => $tipoRecord,
            'productCategoryId' => $category?->getId(),
            'productCategoryName' => $category?->get('name') ?? $parent->get('productCategoryName'),
            'fornitorePartnerId' => $parent->get('fornitorePartnerId'),
            'fornitorePartnerName' => $parent->get('fornitorePartnerName'),
            'productBrandId' => $parent->get('productBrandId'),
            'productBrandName' => $parent->get('productBrandName'),
            'dataInstallazione' => $dataInstallazione,
            'dataAttivazione' => $dataAttivazione,
            'dataCompetenza' => $this->accrual->resolveCompetenceMonthStart($eventDate),
            'giorniLiquidazioneDaAttivazione' => $giorni,
            'assignedUserId' => $parent->get('assignedUserId'),
            'assignedUserName' => $parent->get('assignedUserName'),
        ]);

        if ($rule) {
            $baseCalcolo = $this->resolveBaseCalcolo($rule, $context);

            $provvigione->set([
                'regolaProvvigionaleId' => $rule->getId(),
                'regolaProvvigionaleName' => $rule->get('name'),
                'tassoProvvigioni' => $this->resolveDisplayTasso($rule),
                'baseCalcolo' => $baseCalcolo['baseCalcolo'],
                'importoBaseCalcolo' => $baseCalcolo['importoBaseCalcolo'],
            ]);
        } elseif ($context !== null) {
            $fallbackBase = $this->resolveFallbackBaseCalcolo($tipoRecord, $context);
            $provvigione->set([
                'baseCalcolo' => $fallbackBase['baseCalcolo'],
                'importoBaseCalcolo' => $fallbackBase['importoBaseCalcolo'],
            ]);

            if ($importo !== null && ($context['regime'] ?? '') === 'ARIEL 2025') {
                if ($tipoRecord === 'Provvigione Base') {
                    $percent = $this->resolveArielLegacyBasePercent($context);

                    if ($percent !== null) {
                        $provvigione->set('tassoProvvigioni', $percent);
                    }
                } elseif ($tipoRecord === 'Plus Provvigionale') {
                    $provvigione->set('tassoProvvigioni', 50);
                }
            }
        }

        if ($giorni > 0 && $eventDate && $stato !== ProvvigioneStatusSync::FORECAST) {
            $quote = $dateSource->getEntityType() === 'Quote' ? $dateSource : null;
            $dataPagamento = $quote ? $this->statusSync->resolveDataPagamento($quote) : null;

            if ($dataPagamento !== null) {
                $provvigione->set('dataLiquidazionePrevista', $dataPagamento);
            } else {
                $provvigione->set(
                    'dataLiquidazionePrevista',
                    $this->accrual->calculateLiquidationDate($regime, $dataAttivazione, $dataInstallazione)
                        ?? (new \DateTimeImmutable($eventDate))->modify('last day of this month')
                            ->modify('+' . $giorni . ' days')->format('Y-m-d')
                );
            }
        }

        if ($stato === ProvvigioneStatusSync::FORECAST) {
            $provvigione->set([
                'importoPrevisto' => $importo,
                'importo' => $importo,
                'importoConsolidato' => null,
            ]);
        } else {
            $importoRounded = $importo !== null ? round((float) $importo, 2) : null;

            $provvigione->set([
                'importoConsolidato' => $importoRounded,
                'importo' => $importoRounded,
            ]);
        }

        if (
            $dateSource->getEntityType() === 'Quote'
            && $importo !== null
            && (float) $importo !== 0.0
        ) {
            $provvigione->set(
                'name',
                $this->buildProvvigioneDisplayName($dateSource, $tipoRecord, (float) $importo)
            );
        }
    }

    private function findProvvigioneByAppuntamento(string $appuntamentoId): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['appuntamentoId' => $appuntamentoId])
            ->findOne();
    }

    private function findProvvigioneByContrattoAndTipo(string $contrattoId, string $tipo): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where([
                'contrattoId' => $contrattoId,
                'tipo' => $tipo,
            ])
            ->findOne();
    }

    private function findProvvigione(
        string $stato,
        ?string $appuntamentoId = null,
        ?string $opportunitaId = null,
        ?string $contrattoId = null,
        ?string $tipo = null
    ): ?Entity {
        $where = ['statoProvvigione' => $stato];

        if ($appuntamentoId) {
            $where['appuntamentoId'] = $appuntamentoId;
        }

        if ($opportunitaId) {
            $where['opportunitaId'] = $opportunitaId;
        }

        if ($contrattoId) {
            $where['contrattoId'] = $contrattoId;
        }

        if ($tipo) {
            $where['tipo'] = $tipo;
        }

        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where($where)
            ->findOne();
    }

    /**
     * Integrazione +5% contatti personali (ARQUATI PNC).
     *
     * @param array<string, mixed> $context
     */
    private function syncIntegrazioneContattiPersonali(
        Entity $quote,
        Entity $opportunity,
        Entity $category,
        array $context,
        ?float $imponibile
    ): void {
        if (($context['regime'] ?? '') !== 'ARQUATI 2023') {
            return;
        }

        if (!$context['contattoPersonaleArquati'] || $imponibile === null || $imponibile <= 0) {
            return;
        }

        $rule = $this->entityManager->getEntityById('RegolaProvvigionale', 'arqCpP5');

        if (!$rule || !$rule->get('attiva') || !$this->calculator->isWithinValidity($rule, $context)) {
            return;
        }

        $importo = round($imponibile * 5 / 100, 2);

        $plus = $this->findProvvigioneByContrattoAndTipo($quote->getId(), 'Plus Provvigionale')
            ?? $this->entityManager->createEntity('Provvigione');

        if ($plus->get('consolidata') || $plus->get('invitoAFatturareId')) return;
        $stato = $this->statusSync->resolveStatoFromQuote($quote);

        $this->applyProvvigioneFromCalculation(
            $plus,
            $opportunity,
            $category,
            ['importo' => $importo, 'regola' => $rule],
            $stato,
            $context,
            $quote
        );

        $plus->set([
            'tipo' => 'Plus Provvigionale',
            'contrattoId' => $quote->getId(),
            'contrattoName' => $quote->get('name'),
            'opportunitaId' => $opportunity->getId(),
            'opportunitaName' => $opportunity->get('name'),
            'clienteId' => $quote->get('accountId'),
            'clienteName' => $quote->get('accountName'),
            'name' => $this->buildProvvigioneDisplayName($quote, 'Integrazione contatto personale', $importo),
        ]);

        $this->saveProvvigioneEntity($plus);
    }

    private function resolvePrezzoListino(?Entity $source, ?Entity $opportunity): ?float
    {
        return $this->floatField($source, 'prezzoListinoIvaEsclusa')
            ?? $this->floatField($opportunity, 'prezzoListinoIvaEsclusa');
    }

    private function resolvePrezzoCodice(?Entity $source, ?Entity $opportunity): ?float
    {
        return $this->floatField($source, 'prezzoCodiceIvaEsclusa')
            ?? $this->floatField($source, 'totalPrezzoCodice')
            ?? $this->floatField($source, 'prezzoCodice')
            ?? $this->floatField($opportunity, 'prezzoCodiceIvaEsclusa');
    }

    private function resolveMarginePercentuale(
        ?Entity $source,
        ?Entity $opportunity,
        ?float $imponibile,
        ?float $prezzoListino,
        ?float $prezzoCodice = null,
        ?string $regime = null
    ): ?float {
        if ($regime === 'ARQUATI 2023') {
            // PNC May 2023: percentage above/below CODE, with both amounts net of VAT.
            if ($imponibile === null || $prezzoCodice === null || $prezzoCodice <= 0) {
                return null;
            }
            return round((($imponibile - $prezzoCodice) / $prezzoCodice) * 100, 2);
        }

        if ($regime === 'ARIEL 2025') {
            if ($imponibile === null || $prezzoCodice === null || $prezzoCodice <= 0) {
                return null;
            }

            // Sconto % su prezzo codice. Vendita sopra codice → fascia 0% (plus separato).
            $sconto = round((($imponibile - $prezzoCodice) / $prezzoCodice) * 100, 2);

            return min($sconto, 0.0);
        }

        $stored = $this->floatField($source, 'margineSuListino')
            ?? $this->floatField($opportunity, 'suPrezzoCodice');

        if ($stored !== null) {
            return $stored;
        }

        if ($imponibile === null || $prezzoListino === null || $prezzoListino <= 0) {
            return null;
        }

        return round((($imponibile - $prezzoListino) / $prezzoListino) * 100, 2);
    }

    private function resolveCommercialReferenceDate(?Entity $source, ?Entity $opportunity): ?string
    {
        $candidates = [
            $source?->get('dateQuoted'),
            $source?->get('dataInstallazione'),
            $source?->get('dataAttivazione'),
            $opportunity?->get('closeDate'),
            $opportunity?->get('dateStart'),
            $opportunity?->get('dataInstallazione'),
            $opportunity?->get('dataAttivazione'),
            $source?->get('createdAt'),
            $opportunity?->get('createdAt'),
        ];

        foreach ($candidates as $date) {
            if (is_string($date) && $date !== '') {
                return $date;
            }
        }

        $name = (string) ($source?->get('name') ?? $opportunity?->get('name') ?? '');

        if (preg_match('/\b(20\d{2})-(\d{2})-(\d{2})\b/', $name, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        if (preg_match('/\b(\d{2})[.\-\/](\d{2})[.\-\/](20\d{2})\b/', $name, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return null;
    }

    private function isArielEcoWindEasy(?Entity $category, ?Entity $source, ?Entity $opportunity): bool
    {
        $labels = [
            $category?->get('name'),
            $source?->get('name'),
            $opportunity?->get('name'),
            $source?->get('description'),
            $opportunity?->get('description'),
        ];

        foreach ($labels as $label) {
            if ($label && stripos((string) $label, 'ECO WIND EASY') !== false) {
                return true;
            }
        }

        return false;
    }

    private function resolveArielLegacyGruppo(?Entity $category, bool $ecoWindEasy): string
    {
        if ($ecoWindEasy) {
            return 'Ariel Eco Wind Easy';
        }

        $name = strtoupper(trim((string) $category?->get('name')));

        return match (true) {
            str_contains($name, 'CLIMAT') => 'Ariel Climatizzatori',
            str_contains($name, 'CALDA') => 'Ariel Caldaie',
            str_contains($name, 'STUF') => 'Ariel Stufe',
            str_contains($name, 'BIOMASSA') => 'Ariel Stufe',
            str_contains($name, 'PELLET') => 'Ariel Stufe',
            default => 'Ariel Climatizzatori',
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function refreshArielLegacyContext(
        Entity $quote,
        Entity $opportunity,
        array $context,
        ?float $imponibile,
        ?float $minusPlus
    ): array {
        $context['imponibile'] = $imponibile;

        if ($minusPlus !== null && $minusPlus > 0) {
            $context['plusvalenza'] = round($minusPlus, 2);
        } elseif ($minusPlus !== null && $minusPlus <= 0) {
            $context['plusvalenza'] = null;
        }

        $prezzoCodiceTotale = $this->resolvePrezzoCodice($quote, $opportunity);

        if ($prezzoCodiceTotale !== null && $prezzoCodiceTotale > 0 && $imponibile !== null) {
            $context['marginePercentuale'] = $this->resolveMarginePercentuale(
                $quote,
                $opportunity,
                $imponibile,
                null,
                $prezzoCodiceTotale,
                'ARIEL 2025'
            );
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{importo: float, regola: Entity}|null
     */
    private function buildArielLegacyBaseResult(array $context, ?float $imponibile): ?array
    {
        if ($imponibile === null || $imponibile <= 0) {
            return null;
        }

        $ruleId = $this->resolveArielLegacyBaseRuleId($context);

        if ($ruleId) {
            $fromRule = $this->resultFromRuleId($ruleId, $context);

            if ($fromRule !== null) {
                return $fromRule;
            }
        }

        if ($ruleId && $this->entityManager->getEntityById('RegolaProvvigionale', $ruleId)) {
            return null; // An existing rule was excluded; do not bypass its validity.
        }

        $percent = $this->resolveArielLegacyBasePercent($context);

        if ($percent === null || $percent <= 0) {
            return null;
        }

        $rule = $ruleId ? $this->entityManager->getEntityById('RegolaProvvigionale', $ruleId) : null;

        return [
            'importo' => round($imponibile * $percent / 100, 2),
            'regola' => ($rule && $rule->get('attiva')) ? $rule : null,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{importo: float, regola: Entity}|null
     */
    private function buildArielLegacyPlusResult(array $context): ?array
    {
        $plusvalenza = $context['plusvalenza'] ?? null;

        if ($plusvalenza === null || (float) $plusvalenza <= 0) {
            return null;
        }

        $fromRule = $this->resultFromRuleId('arlLegacyPlus50', $context);

        if ($fromRule !== null) {
            return $fromRule;
        }

        if ($this->entityManager->getEntityById('RegolaProvvigionale', 'arlLegacyPlus50')) {
            return null;
        }

        return [
            'importo' => round((float) $plusvalenza * 0.5, 2),
            'regola' => null,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveArielLegacyBaseRuleId(array $context): ?string
    {
        if (!empty($context['arielEcoWindEasy'])) {
            return 'arlEcoWind5';
        }

        $gruppo = (string) ($context['gruppoProvvigione'] ?? 'Ariel Climatizzatori');
        $margine = $context['marginePercentuale'] ?? null;

        if ($margine === null) {
            return match ($gruppo) {
                'Ariel Caldaie', 'Ariel Stufe' => 'arlCalM290',
                'Ariel Eco Wind Easy' => 'arlEcoWind5',
                default => 'arlCliM029',
            };
        }

        if ($gruppo === 'Ariel Caldaie' || $gruppo === 'Ariel Stufe') {
            if ($margine >= -2.9) {
                return 'arlCalM290';
            }

            if ($margine >= -10) {
                return 'arlCalM1000';
            }

            return 'arlCalMDeep';
        }

        if ($gruppo === 'Ariel Climatizzatori') {
            if ($margine >= -2.99) {
                return 'arlCliM029';
            }

            if ($margine >= -4.99) {
                return 'arlCliM499';
            }

            if ($margine >= -6.99) {
                return 'arlCliM699';
            }

            if ($margine >= -9.99) {
                return 'arlCliM999';
            }

            if ($margine >= -12.99) {
                return 'arlCliM1299';
            }

            // User-approved historical minimum: 3% also beyond the last
            // published minus band (instruction confirmed on 2026-10-09).
            return 'arlCliM1499';
        }

        return 'arlCliM029';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveArielLegacyBasePercent(array $context): ?float
    {
        $ruleId = $this->resolveArielLegacyBaseRuleId($context);

        if (!$ruleId) {
            return null;
        }

        $rule = $this->entityManager->getEntityById('RegolaProvvigionale', $ruleId);

        if (!$rule || !$rule->get('attiva')) {
            return match ($ruleId) {
                'arlEcoWind5' => 5.0,
                'arlCalM290', 'arlStuM290' => 13.0,
                'arlCalM1000', 'arlStuM1000' => 7.0,
                'arlCalMDeep', 'arlStuMDeep' => 5.0,
                'arlCliM499' => 13.0,
                'arlCliM699' => 12.0,
                'arlCliM999' => 10.0,
                'arlCliM1299' => 6.0,
                'arlCliM1499' => 3.0,
                default => 15.0,
            };
        }

        return $this->floatField($rule, 'percentuale');
    }

    private function resolveMinusPlusValue(
        Entity $quote,
        Entity $opportunity,
        ?float $imponibile
    ): ?float {
        $fromCalculator = $this->quotePricingCalculator->resolveMinusPlusForQuote($quote);

        if ($fromCalculator !== null) {
            return $fromCalculator;
        }

        $stored = $this->floatField($quote, 'minusPlus') ?? $this->floatField($opportunity, 'minusPlus');

        if ($stored !== null) {
            return round($stored, 2);
        }

        $prezzoCodice = $this->quotePricingCalculator->resolvePrezzoCodiceNetForMinusPlus($quote, $opportunity)
            ?? $this->resolvePrezzoCodice($quote, $opportunity);
        $imponibileNet = $this->quotePricingCalculator->resolveImponibileNetto($quote) ?? $imponibile;

        if ($imponibileNet === null || $prezzoCodice === null) {
            return null;
        }

        return round($imponibileNet - $prezzoCodice, 2);
    }

    private function resolveQuoteImponibile(Entity $quote, ?Entity $opportunity = null): ?float
    {
        $net = $this->quotePricingCalculator->resolveImponibileNetto($quote);

        if ($net !== null && $net > 0) {
            return $net;
        }

        if ($opportunity) {
            return $this->resolveImponibile($opportunity);
        }

        return null;
    }

    private function resolveImponibile(Entity $entity): ?float
    {
        if ($entity->getEntityType() === 'Appuntamento') {
            return null;
        }

        if ($entity->getEntityType() === 'Quote') {
            return $this->quotePricingCalculator->resolveImponibileNetto($entity);
        }

        return $this->floatField($entity, 'amount')
            ?? $this->floatField($entity, 'importoContratto')
            ?? $this->floatField($entity, 'importoOpportunita');
    }

    private function floatField(Entity $entity, string $field): ?float
    {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{baseCalcolo: string, importoBaseCalcolo: float|null}
     */
    private function resolveFallbackBaseCalcolo(string $tipoRecord, array $context): array
    {
        if (in_array($tipoRecord, ['Minus Provvigionale', 'Plus Provvigionale'], true)) {
            return [
                'baseCalcolo' => $tipoRecord === 'Minus Provvigionale' ? 'Minusvalenza' : 'Plusvalenza',
                'importoBaseCalcolo' => isset($context['plusvalenza'])
                    ? round((float) $context['plusvalenza'], 2)
                    : null,
            ];
        }

        return [
            'baseCalcolo' => 'ImponibileContratto',
            'importoBaseCalcolo' => isset($context['imponibile'])
                ? round((float) $context['imponibile'], 2)
                : null,
        ];
    }

    /**
     * @param array<string, mixed>|null $context
     * @return array{baseCalcolo: string, importoBaseCalcolo: float|null}
     */
    private function resolveBaseCalcolo(?Entity $rule, ?array $context): array
    {
        $tipo = $rule?->get('tipoCalcolo') ?? 'PercentualeImponibile';

        return match ($tipo) {
            'PercentualePlusvalenza', 'PercentualeMinusvalenza' => [
                'baseCalcolo' => isset($context['plusvalenza']) && (float) $context['plusvalenza'] < 0
                    ? 'Minusvalenza'
                    : 'Plusvalenza',
                'importoBaseCalcolo' => isset($context['plusvalenza'])
                    ? round((float) $context['plusvalenza'], 2)
                    : null,
            ],
            'PercentualeMargine' => [
                'baseCalcolo' => 'MargineSuListino',
                'importoBaseCalcolo' => isset($context['imponibile'])
                    ? round((float) $context['imponibile'], 2)
                    : null,
            ],
            'CoefficienteCanone' => [
                'baseCalcolo' => 'CanoneMensile',
                'importoBaseCalcolo' => isset($context['canoneMensile'])
                    ? round((float) $context['canoneMensile'], 2)
                    : null,
            ],
            'GettoneFisso' => [
                'baseCalcolo' => 'GettoneFisso',
                'importoBaseCalcolo' => $this->floatField($rule, 'gettoneImporto'),
            ],
            'ImportoFissoPod' => [
                'baseCalcolo' => 'NumeroPod',
                'importoBaseCalcolo' => isset($context['numeroPod'])
                    ? (float) (int) $context['numeroPod']
                    : null,
            ],
            default => [
                'baseCalcolo' => 'ImponibileContratto',
                'importoBaseCalcolo' => isset($context['imponibile'])
                    ? round((float) $context['imponibile'], 2)
                    : null,
            ],
        };
    }

    private function resolveDisplayTasso(?Entity $rule): ?float
    {
        if (!$rule) {
            return null;
        }

        $percent = $this->floatField($rule, 'percentuale');
        $add = $this->floatField($rule, 'percentualeAddizionale');

        if ($rule->get('tipoCalcolo') === 'PercentualeImponibileAddizionale' && $percent !== null && $add !== null) {
            return round($percent + $add, 2);
        }

        return $percent ?? $this->floatField($rule, 'coefficiente');
    }

    private function buildProvvigioneDisplayName(Entity $quote, string $tipo, float $importo): string
    {
        // data contratto - nr. contratto - cliente - tipo - importo consolidato
        $dataContratto = $this->resolveContractDate($quote);
        $codice = $this->resolveQuoteCodice($quote);
        $cliente = strtoupper(trim((string) ($quote->get('accountName') ?? 'Cliente')));

        return sprintf(
            '%s - %s - %s - %s - €. %s',
            $dataContratto ?? 'DATA-NON-DEFINITA',
            $codice,
            $cliente !== '' ? $cliente : 'CLIENTE',
            strtoupper($tipo),
            number_format($importo, 2, '.', '')
        );
    }

    private function resolveContractDate(Entity $quote): ?string
    {
        $date = $quote->get('dateQuoted');

        if (is_string($date) && $date !== '') {
            return $this->normalizeDate($date);
        }

        $opportunity = $quote->get('opportunityId')
            ? $this->entityManager->getEntityById('Opportunity', $quote->get('opportunityId'))
            : null;

        foreach ([
            $opportunity?->get('closeDate'),
            $quote->get('dataInstallazione'),
            $quote->get('dataAttivazione'),
            $quote->get('createdAt'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $this->normalizeDate($candidate);
            }
        }

        return null;
    }

    private function normalizeDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $timestamp = strtotime((string) $date);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function resolveQuoteCodice(Entity $quote): string
    {
        foreach (['numeroContratto', 'numberA', 'number'] as $field) {
            $value = trim((string) ($quote->get($field) ?? ''));

            if ($value === '') {
                continue;
            }

            return $value;
        }

        return $quote->getId();
    }
}
