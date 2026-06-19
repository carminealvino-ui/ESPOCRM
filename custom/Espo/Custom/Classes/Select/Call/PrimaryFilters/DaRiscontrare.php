<?php

namespace Espo\Custom\Classes\Select\Call\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Entities\User;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Where\OrGroupBuilder;
use Espo\ORM\Query\SelectBuilder;

class DaRiscontrare implements Filter
{
    public const NAME = 'daRiscontrare';

    public function __construct(
        private User $user,
    ) {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        $userId = $this->user->getId();

        $queryBuilder->where([
            'status' => 'Planned',
        ]);

        $queryBuilder->leftJoin('users', 'daRiscontroUsers');

        $orGroup = OrGroupBuilder::create()
            ->add(Cond::equal(Cond::column('assignedUserId'), $userId))
            ->add(Cond::equal(Cond::column('daRiscontroUsersMiddle.userId'), $userId))
            ->build();

        $queryBuilder->where($orGroup);
    }
}
