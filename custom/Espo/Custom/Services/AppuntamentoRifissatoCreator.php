<?php

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AppuntamentoRifissatoCreator
{
    private const DURATION_SECONDS = 5400;

    /** @var string[] */
    private const COPY_FIELDS = [
        'name',
        'prospectId',
        'prospectName',
        'parentType',
        'parentId',
        'parentName',
        'leadId',
        'leadName',
        'azienda',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
        'cAPId',
        'cAPName',
        'assignedUserId',
        'assignedUserName',
        'teamsIds',
        'teamsNames',
        'tipo',
        'callCenter',
        'indirizzoStreet',
        'indirizzoCity',
        'indirizzoPostalCode',
        'indirizzoState',
        'indirizzoCountry',
        'location',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function create(string $sourceId, string $dateStart): string
    {
        $sourceId = trim($sourceId);
        $dateStart = trim($dateStart);

        if ($sourceId === '' || $dateStart === '') {
            throw new BadRequest('sourceId e dateStart sono obbligatori.');
        }

        $source = $this->entityManager->getEntityById('Appuntamento', $sourceId);

        if (!$source) {
            throw new NotFound('Appuntamento origine non trovato.');
        }

        if ($source->get('sottostato') !== 'Rifissato') {
            throw new BadRequest('L\'appuntamento origine deve avere sottostato Rifissato.');
        }

        $new = $this->entityManager->getNewEntity('Appuntamento');

        foreach (self::COPY_FIELDS as $field) {
            $value = $source->get($field);

            if ($value !== null && $value !== '') {
                $new->set($field, $value);
            }
        }

        if (!$new->get('parentType') && $new->get('prospectId')) {
            $new->set('parentType', 'Prospect');
            $new->set('parentId', $new->get('prospectId'));
            $new->set('parentName', $new->get('prospectName'));
        }

        $new->set([
            'status' => 'Planned',
            'sottostato' => null,
            'esito' => null,
            'noteEsito' => null,
            'dateStart' => $dateStart,
            'dateEnd' => $this->resolveDateEnd($dateStart),
            'description' => $this->buildDescription($source),
        ]);

        $this->entityManager->saveEntity($new);

        return (string) $new->getId();
    }

    private function buildDescription(Entity $source): string
    {
        $dateStart = (string) $source->get('dateStart');

        if ($dateStart === '') {
            return 'appuntamento rifissato';
        }

        try {
            $dateTime = new \DateTime($dateStart);
        } catch (\Throwable) {
            return 'appuntamento rifissato';
        }

        return sprintf(
            'appuntamento rifissato del %s ore %s',
            $dateTime->format('d/m/Y'),
            $dateTime->format('H:i')
        );
    }

    private function resolveDateEnd(string $dateStart): string
    {
        try {
            $dateTime = new \DateTime($dateStart);
        } catch (\Throwable) {
            return $dateStart;
        }

        $dateTime->modify('+' . self::DURATION_SECONDS . ' seconds');

        return $dateTime->format('Y-m-d H:i:s');
    }
}
