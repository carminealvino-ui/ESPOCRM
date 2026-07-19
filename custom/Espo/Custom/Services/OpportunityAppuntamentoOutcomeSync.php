<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Allinea Appuntamento all'esito dell'Opportunità vinta/installata.
 *
 * Opportunità Closed Won / Installato →
 *   Appuntamento: Held + Chiuso Positivamente + Venduto Cartaceo
 *   (mantiene Venduto Tablet se già presente).
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

    public function isAppuntamentoAligned(Entity $appuntamento): bool
    {
        $status = (string) ($appuntamento->get('status') ?? '');
        $sottostato = (string) ($appuntamento->get('sottostato') ?? '');
        $esito = (string) ($appuntamento->get('esito') ?? '');

        return $status === self::STATUS_HELD
            && $sottostato === self::SOTTOSTATO_WON
            && in_array($esito, self::ESITO_VENDUTO, true);
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

            // Preferisci quelli ancora non allineati (sono il target della bonifica).
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

        $esito = (string) ($appuntamento->get('esito') ?? '');
        if (!in_array($esito, self::ESITO_VENDUTO, true)) {
            $esito = self::ESITO_DEFAULT;
        }

        $before = [
            'status' => $appuntamento->get('status'),
            'sottostato' => $appuntamento->get('sottostato'),
            'esito' => $appuntamento->get('esito'),
        ];

        $changes = [
            'status' => self::STATUS_HELD,
            'sottostato' => self::SOTTOSTATO_WON,
            'esito' => $esito,
        ];

        // Evita save inutile se già uguale (difesa).
        if (
            $before['status'] === $changes['status']
            && $before['sottostato'] === $changes['sottostato']
            && $before['esito'] === $changes['esito']
        ) {
            return [
                'updated' => false,
                'linked' => $linked,
                'appuntamentoId' => $appuntamentoId,
                'changes' => [],
            ];
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
        $this->entityManager->saveEntity($appuntamento);

        return [
            'updated' => true,
            'linked' => $linked,
            'appuntamentoId' => $appuntamentoId,
            'changes' => $changes + ['_before' => $before],
        ];
    }
}
