<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;

/**
 * Coerenza tra:
 * - status (Stato lavorazione)
 * - statoContratto
 * - statoFinanziamento
 *
 * Target enum:
 * status: Bozza | In Gestione | Appuntamento fissato | Installato | Invalido
 * statoContratto: Inserito | In lavorazione | Sospeso | Annullato | Recesso | Chiuso
 * statoFinanziamento: In valutazione | In attesa OTP | Approvato | In rivalutazione
 *                     | In attesa di documentazione | Respinto | Annullato
 *
 * Condizioni chiave:
 * - status Installato ⇔ statoContratto Chiuso
 * - Installato/Chiuso + finanziamento ⇒ Approvato
 * - Recesso ⇒ status Invalido + fin. Annullato + dataInstallazione null
 * - Respinto ⇒ status Invalido + statoContratto Annullato (fin. resta Respinto)
 * - Annullato (senza Respinto) ⇒ status Invalido + fin. Annullato
 * - Invalido/Annullato/Recesso ⇒ dataInstallazione null
 */
class ContrattoStatiRules
{
    /** @var array<string, string> */
    private const STATUS_ALIASES = [
        'Draft' => 'Bozza',
        'Presented' => 'In Gestione',
        'In lavorazione' => 'In Gestione',
        'Approved' => 'In Gestione',
        'In Pagamento' => 'In Gestione',
        'Recesso' => 'Invalido',
        'Finanziamento Rifiutato' => 'Invalido',
        'Canceled' => 'Invalido',
        'Annullato' => 'Invalido',
    ];

    /** @var array<string, string> */
    private const STATO_CONTRATTO_ALIASES = [
        'Appuntamento Fissato' => 'In lavorazione',
        'Appuntamento fissato' => 'In lavorazione',
        // Legacy: Installato era su statoContratto → ora è Chiuso
        'Installato' => 'Chiuso',
    ];

    /** @var array<string, string> */
    private const FINANZIAMENTO_ALIASES = [
        'In attesa di OTP' => 'In attesa OTP',
        'In attesa documentazione' => 'In attesa di documentazione',
        'In Attesa Documentazione' => 'In attesa di documentazione',
        'In lavorazione' => 'In valutazione',
    ];

    public function apply(Entity $entity): void
    {
        $entityType = $entity->getEntityType();

        if ($entityType !== 'Quote' && $entityType !== 'Opportunity') {
            return;
        }

        if ($entityType === 'Quote') {
            $this->normalizeStatus($entity);
        }

        $this->normalizeStatoContratto($entity);
        $this->normalizeStatoFinanziamento($entity);
        $this->applyCrossRules($entity);
    }

    private function normalizeStatus(Entity $entity): void
    {
        $current = trim((string) ($entity->get('status') ?? ''));

        if ($current === '') {
            $entity->set('status', 'Bozza');

            return;
        }

        if (isset(self::STATUS_ALIASES[$current])) {
            $entity->set('status', self::STATUS_ALIASES[$current]);
        }
    }

    private function normalizeStatoContratto(Entity $entity): void
    {
        $current = trim((string) ($entity->get('statoContratto') ?? ''));

        if ($current === '') {
            $entity->set('statoContratto', 'Inserito');

            return;
        }

        if (isset(self::STATO_CONTRATTO_ALIASES[$current])) {
            $entity->set('statoContratto', self::STATO_CONTRATTO_ALIASES[$current]);
        }
    }

    private function normalizeStatoFinanziamento(Entity $entity): void
    {
        $current = trim((string) ($entity->get('statoFinanziamento') ?? ''));

        if ($current === '' || !isset(self::FINANZIAMENTO_ALIASES[$current])) {
            return;
        }

        $entity->set(
            'statoFinanziamento',
            self::FINANZIAMENTO_ALIASES[$current]
        );
    }

    private function applyCrossRules(Entity $entity): void
    {
        $statoContratto = trim((string) ($entity->get('statoContratto') ?? ''));
        $statoFinanziamento = trim((string) ($entity->get('statoFinanziamento') ?? ''));
        $finanziamento = (bool) $entity->get('finanziamento');
        $isQuote = $entity->getEntityType() === 'Quote';
        $status = $isQuote ? trim((string) ($entity->get('status') ?? '')) : '';

        // Recesso ⇒ finanziamento Annullato + stato lavorazione Invalido
        if ($statoContratto === 'Recesso') {
            if ($statoFinanziamento !== 'Annullato') {
                $entity->set('statoFinanziamento', 'Annullato');
            }

            if ($isQuote && $status !== 'Invalido') {
                $entity->set('status', 'Invalido');
            }

            if ($isQuote) {
                $this->clearDataInstallazione($entity);
            }

            return;
        }

        // Finanziamento respinto (KO banca): non forzare Annullato sul finanziamento.
        if ($statoFinanziamento === 'Respinto') {
            if ($statoContratto !== 'Annullato') {
                $entity->set('statoContratto', 'Annullato');
            }

            if ($isQuote && $status !== 'Invalido') {
                $entity->set('status', 'Invalido');
            }

            if (!$finanziamento) {
                $entity->set('finanziamento', true);
            }

            if ($isQuote) {
                $this->clearDataInstallazione($entity);
            }

            return;
        }

        // Annullato ⇒ stato lavorazione Invalido; se c'era finanziamento ⇒ Annullato
        // (Respinto già gestito sopra e non va sovrascritto).
        if ($statoContratto === 'Annullato') {
            if ($isQuote && $status !== 'Invalido') {
                $entity->set('status', 'Invalido');
            }

            if (($finanziamento || $statoFinanziamento !== '')
                && $statoFinanziamento !== 'Annullato'
            ) {
                $entity->set('statoFinanziamento', 'Annullato');
            }

            if ($isQuote) {
                $this->clearDataInstallazione($entity);
            }

            return;
        }

        // Installato ⇔ Chiuso
        if ($isQuote && $status === 'Installato' && $statoContratto !== 'Chiuso') {
            $entity->set('statoContratto', 'Chiuso');
            $statoContratto = 'Chiuso';
        }

        if ($isQuote && $statoContratto === 'Chiuso' && $status !== 'Installato' && $status !== 'Invalido') {
            $entity->set('status', 'Installato');
            $status = 'Installato';
        }

        // Contratto Invalido ⇒ niente data installazione
        if ($isQuote && $status === 'Invalido') {
            $this->clearDataInstallazione($entity);

            return;
        }

        // Chiuso / Installato + finanziamento ⇒ Approvato
        if ($statoContratto === 'Chiuso' || ($isQuote && $status === 'Installato')) {
            $hasFinancing = $finanziamento || $statoFinanziamento !== '';

            if ($hasFinancing) {
                if (!$finanziamento) {
                    $entity->set('finanziamento', true);
                }

                if ($statoFinanziamento !== 'Approvato') {
                    $entity->set('statoFinanziamento', 'Approvato');
                }
            }
        }
    }

    private function clearDataInstallazione(Entity $entity): void
    {
        $value = $entity->get('dataInstallazione');

        if ($value === null || $value === '') {
            return;
        }

        $entity->set('dataInstallazione', null);
    }
}
