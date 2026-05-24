<?php

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class ProvvigioneForecast
{
    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneAccrual $accrual
    ) {}

    public function syncFromAppuntamento(Entity $appuntamento): void
    {
        if (!$appuntamento->get('productCategoryId')) {
            return;
        }

        $category = $this->entityManager->getEntityById(
            'ProductCategory',
            $appuntamento->get('productCategoryId')
        );

        if (!$category) {
            return;
        }

        $regime = $this->accrual->resolveRegimeFromCategory($category);
        $imponibile = $appuntamento->get('importoImponibilePrevisto');

        if ($imponibile === null || $imponibile === '') {
            $imponibile = $appuntamento->get('importoTrattativa');
        }

        $importoPrevisto = $this->accrual->estimateForecastAmount(
            $regime,
            $imponibile !== null && $imponibile !== '' ? (float) $imponibile : null
        );

        $dataAttivazione = $appuntamento->get('dataAttivazione');
        $dataInstallazione = $appuntamento->get('dataInstallazione');
        $eventDate = $this->accrual->resolveEventDate($dataAttivazione, $dataInstallazione);

        $provvigione = $this->findPrevistaForAppuntamento($appuntamento->getId());

        if (!$provvigione) {
            $provvigione = $this->entityManager->createEntity('Provvigione');
        }

        $name = 'PREV-' . $appuntamento->get('name');

        $provvigione->set([
            'name' => $name,
            'statoProvvigione' => 'Prevista',
            'regimeProvvigione' => $regime,
            'tipo' => 'Provvigione Base',
            'appuntamentoId' => $appuntamento->getId(),
            'appuntamentoName' => $appuntamento->get('name'),
            'productCategoryId' => $category->getId(),
            'productCategoryName' => $category->get('name'),
            'fornitorePartnerId' => $appuntamento->get('fornitorePartnerId'),
            'fornitorePartnerName' => $appuntamento->get('fornitorePartnerName'),
            'productBrandId' => $appuntamento->get('productBrandId'),
            'productBrandName' => $appuntamento->get('productBrandName'),
            'dataInstallazione' => $dataInstallazione,
            'dataAttivazione' => $dataAttivazione,
            'dataCompetenza' => $this->accrual->resolveCompetenceMonthStart($eventDate),
            'dataLiquidazionePrevista' => $this->accrual->calculateLiquidationDate(
                $regime,
                $dataAttivazione,
                $dataInstallazione
            ),
            'giorniLiquidazioneDaAttivazione' => $this->accrual->getLiquidationDays($regime),
            'importoPrevisto' => $importoPrevisto,
            'importoConsolidato' => null,
            'importo' => $importoPrevisto,
            'assignedUserId' => $appuntamento->get('assignedUserId'),
            'assignedUserName' => $appuntamento->get('assignedUserName'),
        ]);

        $this->entityManager->saveEntity($provvigione, ['silent' => true]);
    }

    private function findPrevistaForAppuntamento(string $appuntamentoId): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where([
                'appuntamentoId' => $appuntamentoId,
                'statoProvvigione' => 'Prevista',
            ])
            ->findOne();
    }
}
