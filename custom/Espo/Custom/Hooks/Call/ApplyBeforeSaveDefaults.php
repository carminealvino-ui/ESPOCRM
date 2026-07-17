<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

class ApplyBeforeSaveDefaults implements BeforeSave
{
    public static int $order = 3;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        $entity->set('dateEnd', null);

        $this->syncProspectId($entity);
        $this->syncTelefono($entity);
        $this->syncWhatsAppNumero($entity);
        $this->syncAutoName($entity);
    }

    private function syncProspectId(Entity $entity): void
    {
        if ($entity->get('prospectId')) {
            return;
        }

        $parentName = trim((string) ($entity->get('parentName') ?? ''));

        if ($parentName === '') {
            return;
        }

        $prospect = $this->entityManager
            ->getRDBRepository('Prospect')
            ->where(['name' => $parentName])
            ->findOne();

        if ($prospect) {
            $entity->set('prospectId', $prospect->getId());
        }
    }

    private function syncTelefono(Entity $entity): void
    {
        $telefono = trim((string) ($entity->get('telefono') ?? ''));

        if ($telefono !== '') {
            return;
        }

        $prospectId = $entity->get('prospectId');

        if (!$prospectId) {
            return;
        }

        $prospect = $this->entityManager->getEntityById('Prospect', $prospectId);

        if (!$prospect) {
            return;
        }

        $phone = trim((string) ($prospect->get('phoneNumber') ?? ''));

        if ($phone !== '') {
            $entity->set('telefono', $phone);
        }
    }

    private function syncWhatsAppNumero(Entity $entity): void
    {
        $telefono = trim((string) ($entity->get('telefono') ?? ''));

        if ($telefono === '') {
            return;
        }

        $entity->set('whatsAppNumero', 'https://wa.me/+39' . $telefono);
    }

    private function syncAutoName(Entity $entity): void
    {
        if ($this->shouldSkipAutoName($entity)) {
            return;
        }

        $dateStart = $entity->get('dateStart');
        $parentName = trim((string) ($entity->get('parentName') ?? ''));
        $tipologia = trim((string) ($entity->get('tipologia') ?? ''));
        $telefono = trim((string) ($entity->get('telefono') ?? ''));

        if (!$dateStart || $parentName === '' || $tipologia === '') {
            return;
        }

        $formatted = $this->formatDateStart($dateStart);

        $entity->set(
            'name',
            mb_strtoupper(
                $formatted . ' - ' . $tipologia . ' - ' . $parentName . ' - ' . $telefono,
                'UTF-8'
            )
        );
    }

    private function shouldSkipAutoName(Entity $entity): bool
    {
        $nota = (string) ($entity->get('nota') ?? '');
        $tipologia = trim((string) ($entity->get('tipologia') ?? ''));
        $nameUpper = mb_strtoupper(trim((string) ($entity->get('name') ?? '')), 'UTF-8');

        if ($nota !== '' && (
            str_contains($nota, 'Auto-Pending-Appuntamento:')
            || str_contains($nota, 'Auto-Richiamo-Appuntamento:')
            || str_contains($nota, 'Auto-Rinvio-Call:')
            || str_contains($nota, 'Auto-Richiamo-Call:')
        )) {
            return true;
        }

        if ($tipologia === 'Richiamo su Opportunità Generata') {
            return true;
        }

        return str_contains($nameUpper, 'RICHIAMO SU OPPORTUNIT');
    }

    private function formatDateStart(mixed $dateStart): string
    {
        $timestamp = is_string($dateStart) ? strtotime($dateStart) : false;

        if ($timestamp === false) {
            return (string) $dateStart;
        }

        $dt = new \DateTime('@' . $timestamp);
        $dt->setTimezone(new \DateTimeZone('Europe/Rome'));

        return $dt->format('d/m/Y H:i');
    }
}
