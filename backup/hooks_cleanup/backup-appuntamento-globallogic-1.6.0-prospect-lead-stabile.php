<?php
// ========================================
// VERSIONE: 1.6.0
// DATA: 07-05-2026 16:36 (Europe/Rome)
// AUTORE: CARMINE ALVINO + CHATGPT
// FILE:
// custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php
// ========================================
//
// STORICO VERSIONI
// ========================================
//
// 1.5.x
// ----------------------------------------
// ✔ Sync Prospect → Appuntamento
// ✔ CAP automatico
// ✔ Location automatica
// ✔ Description automatica
// ✔ Colori automatici
// ✔ Creazione Lead automatica
//
// 1.5.9
// ----------------------------------------
// ✔ Fix sincronizzazione indirizzo
// ✔ Fix location dinamica
//
// 1.6.0
// ----------------------------------------
// ✔ Relazione reale con Lead
// ✔ Nome Lead corretto
// ✔ Fix indirizzo Appuntamento
// ✔ Fix mappe Google
// ✔ Fix CAP
// ✔ Fix colori calendario
// ✔ Fix sottostato Chiuso Positivamente
// ✔ Fix assegnazione ADMIN
//
// FIX 07-05-2026 16:36
// ----------------------------------------
// ✔ Mapping stato Lead automatico
// ✔ Assegnazione utente Lead automatica
// ✔ Sync assegnatario Appuntamento → Lead
//
// MAPPATURA:
//
// Appuntamento.sottostato
// → Lead.status
//
// Pending
// → In Process
//
// Non Interessato
// → Dead
//
// Chiuso Positivamente
// → Assegnato
//
// ========================================

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\ORM\Entity;
use Espo\Core\ORM\EntityManager;

class GlobalLogic
{
    private EntityManager $entityManager;

    private static bool $processing = false;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        if (self::$processing) {
            return;
        }

        self::$processing = true;

        try {

            // ========================================
            // VERSIONE HOOK
            // ========================================

            $entity->set(
                'hookVersion',
                '1.6.0'
            );

            // ========================================
            // RECUPERO STATUS
            // ========================================

            $status = $entity->get('status');

            $sottostato = $entity->get('sottostato');

            // ========================================
            // RECUPERO PROSPECT
            // ========================================

            $prospect = null;

            if (

                $entity->get('parentType') === 'Prospect' &&
                $entity->get('parentId')

            ) {

                $prospect = $this->entityManager->getEntity(
                    'Prospect',
                    $entity->get('parentId')
                );
            }

            if (

                !$prospect &&
                $entity->get('prospectId')

            ) {

                $prospect = $this->entityManager->getEntity(
                    'Prospect',
                    $entity->get('prospectId')
                );
            }

            // ========================================
            // RECUPERO LEAD
            // ========================================

            $lead = null;

            if (

                $entity->get('parentType') === 'Lead' &&
                $entity->get('parentId')

            ) {

                $lead = $this->entityManager->getEntity(
                    'Lead',
                    $entity->get('parentId')
                );
            }

            // ========================================
            // SORGENTE DATI
            // PRIORITÀ:
            // 1. LEAD
            // 2. PROSPECT
            // ========================================

            $source = $lead ?: $prospect;

            // ========================================
            // FIX RELAZIONE PROSPECT
            // ========================================

            if ($prospect) {

                $entity->set(
                    'prospectId',
                    $prospect->getId()
                );

                $entity->set(
                    'prospectName',
                    $prospect->get('name')
                );
            }

            // ========================================
            // FIX RELAZIONE LEAD
            // ========================================

            if ($lead) {

                $entity->set(
                    'parentType',
                    'Lead'
                );

                $entity->set(
                    'parentId',
                    $lead->getId()
                );

                // ========================================
                // FIX NOME LEAD
                // ========================================

                $leadName = $lead->get('name');

                if (!$leadName) {

                    $firstName = $lead->get('firstName');
                    $lastName = $lead->get('lastName');

                    $leadName = trim(
                        $firstName . ' ' . $lastName
                    );
                }

                if (!$leadName) {
                    $leadName = $lead->get('phoneNumber');
                }

                $entity->set(
                    'parentName',
                    $leadName
                );
            }

            // ========================================
            // FIX CAP
            // ========================================

            if (

                $source &&
                $source->get('cAPId')

            ) {

                $entity->set(
                    'cAPId',
                    $source->get('cAPId')
                );
            }

            // ========================================
            // FIX INDIRIZZO APPUNTAMENTO
            // ========================================

            if ($source) {

                $entity->set(
                    'indirizzoStreet',
                    $source->get('addressStreet')
                );

                $entity->set(
                    'indirizzoCity',
                    $source->get('addressCity')
                );

                $entity->set(
                    'indirizzoPostalCode',
                    $source->get('addressPostalCode')
                );

                $entity->set(
                    'indirizzoState',
                    $source->get('addressState')
                );

                $entity->set(
                    'indirizzoCountry',
                    $source->get('addressCountry')
                );
            }

            // ========================================
            // FIX LOCATION
            // ========================================

            if ($source) {

                $location = [];

                if ($source->get('addressStreet')) {
                    $location[] = $source->get('addressStreet');
                }

                if ($source->get('addressPostalCode')) {
                    $location[] = $source->get('addressPostalCode');
                }

                if ($source->get('addressCity')) {
                    $location[] = $source->get('addressCity');
                }

                if ($source->get('addressState')) {
                    $location[] = $source->get('addressState');
                }

                if ($source->get('addressCountry')) {
                    $location[] = $source->get('addressCountry');
                }

                $entity->set(
                    'location',
                    implode(', ', $location)
                );
            }

            // ========================================
            // DESCRIPTION AUTOMATICA
            // ========================================

            if (

                !$entity->get('description') &&
                $source

            ) {

                $description = [];

                $clienteNome = $source->get('name');

                if (!$clienteNome) {

                    $clienteNome = trim(
                        ($source->get('firstName') ?: '') .
                        ' ' .
                        ($source->get('lastName') ?: '')
                    );
                }

                if ($clienteNome) {
                    $description[] = 'Cliente: ' . $clienteNome;
                }

                if ($source->get('phoneNumber')) {
                    $description[] = 'Telefono: ' . $source->get('phoneNumber');
                }

                if ($entity->get('noteCallCenter')) {
                    $description[] = 'Note Call Center: ' . $entity->get('noteCallCenter');
                }

                $entity->set(
                    'description',
                    implode("\n", $description)
                );
            }

            // ========================================
            // COLORI CALENDARIO
            // ========================================

            if (

                $status === 'Held' &&
                $sottostato === 'Chiuso Positivamente'

            ) {

                $entity->set(
                    'color',
                    '#00aa00'
                );

            } elseif ($status === 'Held') {

                $entity->set(
                    'color',
                    '#006400'
                );

            } elseif ($status === 'Planned') {

                $entity->set(
                    'color',
                    '#0000ff'
                );

            } elseif (

                $status === 'Not Held' ||
                $status === 'Ingestibile'

            ) {

                $entity->set(
                    'color',
                    '#cc0000'
                );
            }

            // ========================================
            // CREAZIONE LEAD AUTOMATICA
            // ========================================

            if (

                $status === 'Held' &&
                $prospect

            ) {

                $phone = $prospect->get('phoneNumber');

                $existingLead = null;

                if ($phone) {

                    $existingLead = $this->entityManager
                        ->getRepository('Lead')
                        ->where([
                            'phoneNumber' => $phone
                        ])
                        ->findOne();
                }

                $lead = $existingLead;

                // ========================================
                // MAPPING STATO LEAD
                // ========================================

                $leadStatus = 'In Process';

                if ($sottostato === 'Pending') {

                    $leadStatus = 'In Process';

                } elseif ($sottostato === 'Non Interessato') {

                    $leadStatus = 'Dead';

                } elseif ($sottostato === 'Chiuso Positivamente') {

                    $leadStatus = 'Assegnato';
                }

                // ========================================
                // RECUPERO UTENTE ASSEGNATO
                // ========================================

                $assignedUsersIds = $entity->get('assignedUsersIds') ?: [];

                $leadAssignedUserId = null;

                if (!empty($assignedUsersIds)) {

                    $leadAssignedUserId = $assignedUsersIds[0];
                }

                // ========================================
                // CREA LEAD
                // ========================================

                if (!$lead) {

                    $lead = $this->entityManager->getEntity('Lead');

                    $lead->set([

                        'firstName' => $prospect->get('firstName'),
                        'lastName' => $prospect->get('lastName'),

                        'name' => $prospect->get('name'),

                        'phoneNumber' => $prospect->get('phoneNumber'),

                        'addressStreet' => $prospect->get('addressStreet'),
                        'addressCity' => $prospect->get('addressCity'),
                        'addressPostalCode' => $prospect->get('addressPostalCode'),
                        'addressState' => $prospect->get('addressState'),
                        'addressCountry' => $prospect->get('addressCountry'),

                        'cAPId' => $prospect->get('cAPId'),

                        'azienda' => $prospect->get('azienda'),

                        // ========================================
                        // FIX STATUS LEAD
                        // ========================================

                        'status' => $leadStatus,

                        // ========================================
                        // FIX ASSEGNAZIONE LEAD
                        // ========================================

                        'assignedUserId' => $leadAssignedUserId
                    ]);

                    $this->entityManager->saveEntity($lead);
                }

                // ========================================
                // AGGIORNA LEAD ESISTENTE
                // ========================================

                if ($lead) {

                    $lead->set(
                        'status',
                        $leadStatus
                    );

                    if ($leadAssignedUserId) {

                        $lead->set(
                            'assignedUserId',
                            $leadAssignedUserId
                        );
                    }

                    $this->entityManager->saveEntity($lead);
                }

                // ========================================
                // RELAZIONE APPUNTAMENTO → LEAD
                // ========================================

                $entity->set(
                    'parentType',
                    'Lead'
                );

                $entity->set(
                    'parentId',
                    $lead->getId()
                );

                // ========================================
                // FIX NOME LEAD
                // ========================================

                $leadName = $lead->get('name');

                if (!$leadName) {

                    $leadName = trim(
                        ($lead->get('firstName') ?: '') .
                        ' ' .
                        ($lead->get('lastName') ?: '')
                    );
                }

                if (!$leadName) {
                    $leadName = $lead->get('phoneNumber');
                }

                $entity->set(
                    'parentName',
                    $leadName
                );
            }

            // ========================================
            // FIX ASSEGNAZIONE ADMIN
            // ========================================

            if (

                $status === 'Not Held' ||
                $status === 'Ingestibile'

            ) {

                // ========================================
                // RESET UTENTI ASSEGNATI
                // ========================================

                $entity->set(
                    'assignedUsersIds',
                    []
                );

                // ========================================
                // ASSEGNA SOLO ADMIN
                // USER ID = 1
                // ========================================

                $entity->set(
                    'assignedUsersIds',
                    ['1']
                );
            }

        } finally {

            self::$processing = false;
        }
    }
}
