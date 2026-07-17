<?php

namespace Espo\Custom\Hooks\Disponibilita;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Hook Disponibilita: nome, date calendario, colore da ProductBrand.
 * Logica allineata alla versione produzione stabile (isAllDay + fascia nel nome).
 */
class SetName implements BeforeSave
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        $data = $entity->get('datadisponibilita');
        $inizio = $entity->get('orarioInizio');
        $fine = $entity->get('orarioFine');

        if (empty($data)) {
            $data = $entity->get('dateStartDate');

            if (empty($data) && !empty($entity->get('dateStart'))) {
                $data = substr((string) $entity->get('dateStart'), 0, 10);
            }

            if (!empty($data)) {
                $entity->set('datadisponibilita', $data);
            }
        }

        if (empty($data)) {
            return;
        }

        $entity->set('isAllDay', true);
        $entity->set('dateStartDate', $data);
        $entity->set('dateEndDate', $data);
        $entity->set('dateStart', $data . ' 00:00:00');
        $entity->set('dateEnd', $data . ' 23:59:59');

        $oraInizio = '';
        $oraFine = '';

        if (!empty($inizio)) {
            $dtStart = new \DateTime((string) $inizio, new \DateTimeZone('UTC'));
            $dtStart->setTimezone(new \DateTimeZone('Europe/Rome'));
            $oraInizio = $dtStart->format('H:i');
        }

        if (!empty($fine)) {
            $dtEnd = new \DateTime((string) $fine, new \DateTimeZone('UTC'));
            $dtEnd->setTimezone(new \DateTimeZone('Europe/Rome'));
            $oraFine = $dtEnd->format('H:i');
        }

        $assignedUsersIds = $entity->getLinkMultipleIdList('assignedUsers');

        if ($assignedUsersIds !== []) {
            $entity->set('assignedUserId', $assignedUsersIds[0]);
        }

        $brand = $this->resolveProductBrand($entity);
        $brandName = $brand ? (string) $brand->get('name') : '';

        if ($brandName !== '' && $entity->hasAttribute('azienda')) {
            $entity->set('azienda', $brandName);
        }

        $nome = '';

        if ($brandName !== '') {
            $nome .= $brandName . ' | ';
        } elseif ($entity->get('azienda')) {
            $nome .= $entity->get('azienda') . ' | ';
        }

        if ($oraInizio && $oraFine) {
            $nome .= $oraInizio . ' - ' . $oraFine;
        }

        $entity->set('name', $nome);

        $colore = $brand ? trim((string) ($brand->get('color') ?: '')) : '';

        if ($colore !== '') {
            $entity->set('color', $colore);
        }
    }

    private function resolveProductBrand(Entity $entity): ?Entity
    {
        $brandId = $entity->get('productBrandId');

        if ($brandId) {
            return $this->entityManager->getEntityById('ProductBrand', $brandId);
        }

        $azienda = trim((string) ($entity->get('azienda') ?: ''));

        if ($azienda === '') {
            return null;
        }

        $brand = $this->entityManager
            ->getRDBRepository('ProductBrand')
            ->where(['name' => $azienda])
            ->findOne();

        if (!$brand) {
            return null;
        }

        $entity->set('productBrandId', $brand->getId());
        $entity->set('productBrandName', $brand->get('name'));

        return $brand;
    }
}
