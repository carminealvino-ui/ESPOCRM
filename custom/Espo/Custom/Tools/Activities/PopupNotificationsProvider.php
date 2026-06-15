<?php

namespace Espo\Custom\Tools\Activities;

use Espo\Entities\User;
use Espo\Modules\Crm\Tools\Activities\PopupNotificationsProvider as BasePopupNotificationsProvider;
use Espo\Tools\PopupNotification\Item;

class PopupNotificationsProvider extends BasePopupNotificationsProvider
{
    /**
     * @return Item[]
     */
    public function get(User $user): array
    {
        $items = parent::get($user);

        usort($items, function (Item $a, Item $b): int {
            return $this->getItemSortDate($a) <=> $this->getItemSortDate($b);
        });

        return $items;
    }

    private function getItemSortDate(Item $item): string
    {
        $data = $item->getData();
        $dateField = $data->dateField ?? 'dateStart';
        $attributes = $data->attributes ?? null;

        if (!$attributes) {
            return '';
        }

        return $attributes->{$dateField} ?? $attributes->dateStart ?? '';
    }
}
