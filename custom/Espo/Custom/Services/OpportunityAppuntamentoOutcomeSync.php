<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Allinea Appuntamento all'esito dell'Opportunità vinta/installata.
 *
 * Allineato = Held + Chiuso Positivamente.
 * Esito: impostato a Venduto Cartaceo solo se vuoto (non sovrascrive
 * Annullato Azienda, Gestito, ecc.).
 */
class OpportunityAppuntamentoOutcomeSync
{
    public const STAGE_WON = [
        'Closed Won',
        'Chiuso Positivamente',
    ];

    public const STATUS_HELD = 'Held';
    public const SOTTOSTATO_WON = 'Chiuso Positivamente';
    public const ESITO_DEFAULT = 'Venduto Cartaceo';
    public const ESITO_VENDUTO = [
        'Venduto Cartaceo',
        'Venduto Tablet',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function isWonOpportunity(Entity $opportunity): bool
    {
        $stage = trim((string) ($opportunity->get('stage') ?? ''));

        if (in_array($stage, self::STAGE_WON, true)) {
            return true;
        }

        return trim((string) ($opportunity->get('statoContratto') ?? '')) === 'Installato';
    }

    /**
     * Allineato se Svolto + Chiuso Positivamente (esito non obbligatorio).
     */
    public function isAppuntamentoAligned(Entity $appuntamento): bool
    {
        $status = (string) ($appuntamento->get('status') ?? '');
        $sottostato = (string) ($appuntamento->get('sottostato') ?? '');

        return $status === self::STATUS_HELD
            && $sottostato === self::SOTTOSTATO_WON;
    }

    public function resolveAppuntamento(Entity $opportunity): ?Entity
    {
        $appuntamentoId = trim((string) ($opportunity->get('appuntamentoId') ?? ''));

        if ($appuntamentoId !== '') {
            $linked = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

            if ($linked) {
                return $linked;
            }
        }

        $prospectId = trim((string) ($opportunity->get('prospectId') ?? ''));

        if ($prospectId === '') {
            return null;
        }

        $candidates = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where(['prospectId' => $prospectId])
            ->order('dateStart', 'DESC')
            ->limit(0, 20)
            ->find();

        $list = iterator_to_array($candidates);

        if ($list === []) {
            return null;
        }

        if (count($list) === 1) {
            return $list[0];
        }

        $brandId = (string) ($opportunity->get('productBrandId') ?? '');
        $partnerId = (string) ($opportunity->get('fornitorePartnerId') ?? '');
        $oppName = mb_strtolower((string) ($opportunity->get('name') ?? ''));

        $best = null;
        $bestScore = -1;

        foreach ($list as $app) {
            $score = 0;

            if ($brandId !== '' && (string) $app->get('productBrandId') === $brandId) {
                $score += 3;
            }

            if ($partnerId !== '' && (string) $app->get('fornitorePartnerId') === $partnerId) {
                $score += 2;
            }

            $appName = mb_strtolower((string) ($app->get('name') ?? ''));
            if ($oppName !== '' && $appName !== '' && (str_contains($oppName, $appName) || str_contains($appName, $oppName))) {
                $score += 2;
            }

            if (!$this->isAppuntamentoAligned($app)) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $app;
            }
        }

        return $best;
    }

    /**
     * @return array{
     *   updated: bool,
     *   linked: bool,
     *   appuntamentoId: ?string,
     *   changes: array<string, mixed>
     * }
     */
    public function syncFromOpportunity(Entity $opportunity, bool $dryRun = false): array
    {
        $empty = [
            'updated' => false,
            'linked' => false,
            'appuntamentoId' => null,
            'changes' => [],
        ];

        if (!$this->isWonOpportunity($opportunity)) {
            return $empty;
        }

        $appuntamento = $this->resolveAppuntamento($opportunity);

        if (!$appuntamento) {
            return $empty;
        }

        $appuntamentoId = $appuntamento->getId();
        $linked = false;

        if ((string) ($opportunity->get('appuntamentoId') ?? '') !== $appuntamentoId) {
            $linked = true;

            if (!$dryRun) {
                $opportunity->set('appuntamentoId', $appuntamentoId);
                $this->entityManager->saveEntity($opportunity, [
                    'silent' => true,
                    'skipHooks' => true,
                ]);

                // Ricostruisce nome con data appuntamento (evita "- LOMMI...").
                if (class_exists(OpportunityNameBuilder::class)) {
                    (new OpportunityNameBuilder($this->entityManager))
                        ->rebuild($opportunity, false);
                }
            }
        }

        if ($this->isAppuntamentoAligned($appuntamento)) {
            return [
                'updated' => false,
                'linked' => $linked,
                'appuntamentoId' => $appuntamentoId,
                'changes' => [],
            ];
        }

        $before = [
            'status' => $appuntamento->get('status'),
            'sottostato' => $appuntamento->get('sottostato'),
            'esito' => $appuntamento->get('esito'),
        ];

        $changes = [
            'status' => self::STATUS_HELD,
            'sottostato' => self::SOTTOSTATO_WON,
            'color' => '#00aa00',
        ];

        // Esito solo se vuoto: non sovrascrivere Annullato Azienda / Gestito / ecc.
        $esito = trim((string) ($before['esito'] ?? ''));
        if ($esito === '') {
            $changes['esito'] = self::ESITO_DEFAULT;
        }

        if ($dryRun) {
            return [
                'updated' => true,
                'linked' => $linked,
                'appuntamentoId' => $appuntamentoId,
                'changes' => $changes + ['_before' => $before],
            ];
        }

        $appuntamento->set($changes);
        // skipHooks: evita hang Google Calendar sulla bonifica massiva.
        $this->entityManager->saveEntity($appuntamento, [
            'silent' => true,
            'skipHooks' => true,
        ]);

        return [
            'updated' => true,
            'linked' => $linked,
            'appuntamentoId' => $appuntamentoId,
            'changes' => $changes + ['_before' => $before],
        ];
    }
}
