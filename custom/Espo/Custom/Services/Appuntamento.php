<?php

namespace Espo\Custom\Services;

class Appuntamento extends \Espo\Core\Templates\Services\Event
{
    public function createEntity($data)
    {
        // 🔴 BLOCCO DUPLICATI DA GOOGLE (stesso evento creato 2 volte)
        if (!empty($data->dateStart) && !empty($data->name)) {

            $existing = $this->getEntityManager()
                ->getRepository('Appuntamento')
                ->where([
                    'dateStart' => $data->dateStart,
                    'name' => $data->name
                ])
                ->order('createdAt', 'DESC')
                ->limit(1)
                ->findOne();

            if ($existing) {
                $createdAt = strtotime($existing->get('createdAt'));
                $now = time();

                // Se creato negli ultimi 3 secondi = duplicato sync
                if (($now - $createdAt) < 3) {
                    return $existing;
                }
            }
        }

        return parent::createEntity($data);
    }
}

