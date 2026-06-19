<?php

namespace Espo\Custom\Classes\Select\Call\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Entities\User;
use Espo\ORM\Query\SelectBuilder;

class DaRiscontrare implements Filter
{
    public const NAME = 'daRiscontrare';

    public function __construct(
        private User $user,
    ) {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'status' => 'Planned',
            'assignedUserId' => $this->user->getId(),
        ]);
    }
}
