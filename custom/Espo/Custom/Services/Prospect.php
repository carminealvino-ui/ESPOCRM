<?php

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\Conflict;
use Espo\ORM\Entity;
use stdClass;

/**
 * Evita 409 duplicate quando il client tenta di ricreare un Prospect già esistente
 * (es. Crea Appuntamento da scheda Prospect o calendario).
 */
class Prospect extends \Espo\Core\Templates\Services\Person
{
    public function createEntity($data): Entity
    {
        $existing = $this->findDuplicateProspect($data);

        if ($existing) {
            $this->mergeIncomingData($existing, $data);
            $this->getEntityManager()->saveEntity($existing);

            return $existing;
        }

        try {
            return parent::createEntity($data);
        } catch (Conflict $e) {
            $existing = $this->findDuplicateProspect($data);

            if (!$existing) {
                throw $e;
            }

            $this->mergeIncomingData($existing, $data);
            $this->getEntityManager()->saveEntity($existing);

            return $existing;
        }
    }

    private function findDuplicateProspect(stdClass|array $data): ?Entity
    {
        $data = (object) (is_array($data) ? $data : (array) $data);
        $repository = $this->getEntityManager()->getRDBRepository('Prospect');

        $phone = $this->normalizePhone($data->phoneNumber ?? $data->telefono ?? null);

        if ($phone) {
            $byPhone = $repository
                ->where([
                    'OR' => [
                        ['phoneNumber' => $phone],
                        ['phoneNumber=' => '%' . $phone],
                        ['telefono' => $phone],
                        ['telefono=' => '%' . $phone],
                    ],
                ])
                ->findOne();

            if ($byPhone) {
                return $byPhone;
            }
        }

        $firstName = $this->normalizeNamePart($data->firstName ?? '');
        $lastName = $this->normalizeNamePart($data->lastName ?? '');

        if ($firstName !== '' && $lastName !== '') {
            $byName = $repository
                ->where([
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                ])
                ->findOne();

            if ($byName) {
                return $byName;
            }
        }

        $displayName = $this->normalizeNamePart($data->name ?? '');

        if ($displayName !== '') {
            $byDisplayName = $repository
                ->where(['name' => $displayName])
                ->findOne();

            if ($byDisplayName) {
                return $byDisplayName;
            }
        }

        if ($firstName === '' || $lastName === '') {
            return null;
        }

        $where = [
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        $postalCode = trim((string) ($data->addressPostalCode ?? ''));

        if ($postalCode !== '') {
            $where['addressPostalCode'] = $postalCode;
        } else {
            $street = trim((string) ($data->addressStreet ?? ''));

            if ($street !== '') {
                $where['addressStreet'] = $street;
            }
        }

        return $repository->where($where)->findOne();
    }

    private function mergeIncomingData(Entity $prospect, stdClass|array $data): void
    {
        $data = (object) (is_array($data) ? $data : (array) $data);

        $map = [
            'fornitorePartnerId',
            'fornitorePartnerName',
            'productBrandId',
            'productBrandName',
            'productCategoryId',
            'productCategoryName',
            'assignedUserId',
            'assignedUserName',
            'teamsIds',
            'teamsNames',
            'origine',
            'addressStreet',
            'addressCity',
            'addressState',
            'addressCountry',
            'addressPostalCode',
            'cAPId',
            'cAPName',
        ];

        foreach ($map as $field) {
            if (!property_exists($data, $field)) {
                continue;
            }

            $value = $data->{$field};

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($prospect->hasAttribute($field) && !$prospect->get($field)) {
                $prospect->set($field, $value);
            }
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }

    private function normalizeNamePart(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
