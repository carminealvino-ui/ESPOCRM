<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;

/**
 * Coerenza Appuntamento: status ↔ sottostato ↔ esito.
 *
 * Stato:
 *   Planned | Held | Not Held | Ingestibile
 * Sottostato Held: Pending | Gestito | Non Interessato | Chiuso Positivamente
 * Sottostato Not Held: Annullato | Non Gestito | Non Ricevuto | Rifissato
 * Sottostato Ingestibile: Infattibilità Tecnica | Solo Informazioni | Prodotto non Conforme | Fuori Target
 * Esito Ingestibile: motivi commerciali/tecnici (indipendente dal sottostato)
 *
 * Gestito e Rifissato esclusi dal monitoraggio KPI (regola business).
 */
class AppuntamentoStatiRules
{
    /** @var array<string, array{status: string, sottostato: string}> */
    public const ESITO_MAP = [
        'In Trattativa' => ['status' => 'Held', 'sottostato' => 'Pending'],
        'Marito/Moglie Assente' => ['status' => 'Held', 'sottostato' => 'Pending'],
        'Ripasso per info' => ['status' => 'Held', 'sottostato' => 'Gestito'],
        'Appuntamento non in agenda' => ['status' => 'Held', 'sottostato' => 'Gestito'],
        'Prezzo Elevato' => ['status' => 'Held', 'sottostato' => 'Non Interessato'],
        'Modalità di pagamento' => ['status' => 'Held', 'sottostato' => 'Non Interessato'],
        'No Caparra' => ['status' => 'Held', 'sottostato' => 'Non Interessato'],
        'Venduto Tablet' => ['status' => 'Held', 'sottostato' => 'Chiuso Positivamente'],
        'Venduto Cartaceo' => ['status' => 'Held', 'sottostato' => 'Chiuso Positivamente'],
        'Annullato dal Potenziale' => ['status' => 'Not Held', 'sottostato' => 'Annullato'],
        'Annullato Azienda' => ['status' => 'Not Held', 'sottostato' => 'Annullato'],
        'Annullato Call Center' => ['status' => 'Not Held', 'sottostato' => 'Annullato'],
        'Annullato dal Consulente' => ['status' => 'Not Held', 'sottostato' => 'Non Gestito'],
        'Cliente Assente' => ['status' => 'Not Held', 'sottostato' => 'Non Ricevuto'],
        'Rimandato dal Potenziale' => ['status' => 'Not Held', 'sottostato' => 'Rifissato'],
        'Rimandato da cliente' => ['status' => 'Not Held', 'sottostato' => 'Rifissato'],
        'Rimandato da consulente' => ['status' => 'Not Held', 'sottostato' => 'Rifissato'],
        'Solo Preventivo' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Non Finanziabile' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Non detraibile per età' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Non detraibile per prodotto' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Non detraibile per esposizione' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Permessi' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Casa Popolare/Affitto' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'In ristrutturazione/In costruzione/Cantiere' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Cambio Telo' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Copertura Auto' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Tenda a Capanno' => ['status' => 'Ingestibile', 'sottostato' => ''],
        'Infattibilità Tecnica' => ['status' => 'Ingestibile', 'sottostato' => 'Infattibilità Tecnica'],
        'Prodotto non Conforme' => ['status' => 'Ingestibile', 'sottostato' => 'Prodotto non Conforme'],
    ];

    /** @var array<string, string> */
    public const SOTTOSTATO_TO_STATUS = [
        'Pending' => 'Held',
        'Gestito' => 'Held',
        'Non Interessato' => 'Held',
        'Chiuso Positivamente' => 'Held',
        'Annullato' => 'Not Held',
        'Non Gestito' => 'Not Held',
        'Non Ricevuto' => 'Not Held',
        'Rifissato' => 'Not Held',
        'Infattibilità Tecnica' => 'Ingestibile',
        'Solo Informazioni' => 'Ingestibile',
        'Prodotto non Conforme' => 'Ingestibile',
        'Fuori Target' => 'Ingestibile',
    ];

    /** @var array<string, list<string>> */
    public const ALLOWED_SOTTOSTATO = [
        'Held' => ['Pending', 'Gestito', 'Non Interessato', 'Chiuso Positivamente'],
        'Not Held' => ['Annullato', 'Non Gestito', 'Non Ricevuto', 'Rifissato'],
        'Ingestibile' => [
            'Infattibilità Tecnica',
            'Solo Informazioni',
            'Prodotto non Conforme',
            'Fuori Target',
        ],
        'Planned' => [],
    ];

    /** @var list<string> */
    public const MONITORING_EXCLUDED_SOTTOSTATI = [
        'Gestito',
        'Rifissato',
    ];

    public function apply(Entity $entity): void
    {
        if ($entity->getEntityType() !== 'Appuntamento') {
            return;
        }

        $status = trim((string) ($entity->get('status') ?? ''));
        $sottostato = trim((string) ($entity->get('sottostato') ?? ''));
        $esito = trim((string) ($entity->get('esito') ?? ''));

        if ($status === '' || $status === 'Planned') {
            $entity->set('status', $status === '' ? 'Planned' : $status);
            $entity->set('sottostato', null);
            $entity->set('esito', null);

            return;
        }

        // Esito guida status (+ sottostato se mappato; per Ingestibile non azzera il sottostato)
        if ($esito !== '' && isset(self::ESITO_MAP[$esito])) {
            $mapped = self::ESITO_MAP[$esito];
            $entity->set('status', $mapped['status']);
            $status = $mapped['status'];

            if ($mapped['sottostato'] !== '') {
                $entity->set('sottostato', $mapped['sottostato']);
                $sottostato = $mapped['sottostato'];
            }
        }

        // Sottostato guida status
        if ($sottostato !== '' && isset(self::SOTTOSTATO_TO_STATUS[$sottostato])) {
            $entity->set('status', self::SOTTOSTATO_TO_STATUS[$sottostato]);
            $status = self::SOTTOSTATO_TO_STATUS[$sottostato];
        }

        // Sottostato non ammesso per lo status → svuota solo sottostato (non l'esito su Ingestibile)
        $allowed = self::ALLOWED_SOTTOSTATO[$status] ?? [];

        if ($sottostato !== '' && !in_array($sottostato, $allowed, true)) {
            $entity->set('sottostato', null);

            if ($status !== 'Ingestibile') {
                $entity->set('esito', null);
            }
        }
    }

    public static function isExcludedFromMonitoring(?string $sottostato): bool
    {
        return in_array(
            trim((string) $sottostato),
            self::MONITORING_EXCLUDED_SOTTOSTATI,
            true
        );
    }
}
