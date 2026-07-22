<?php

/**
 * Ripara appuntamenti "(APPUNTAMENTO SENZA PROSPECT)" / Prospect ID grezzo.
 * - se Prospect esiste → compila prospectName e ricostruisce name
 * - se Prospect orfano → prova Lead.prospectId, altrimenti clear
 * - name da CAP + cliente + (Brand - Categoria)
 *
 *   php tools/ripara-appuntamento-senza-prospect.php --dry-run
 *   php tools/ripara-appuntamento-senza-prospect.php
 *   php tools/ripara-appuntamento-senza-prospect.php 6900a442ee92bd824
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

const GHOST_NAME = '(APPUNTAMENTO SENZA PROSPECT)';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$filter = null;

foreach ($argv ?? [] as $i => $arg) {
    if ($i === 0 || str_starts_with((string) $arg, '--')) {
        continue;
    }
    $filter = trim((string) $arg);
    break;
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

if ($filter) {
    $collection = $em->getRDBRepository('Appuntamento')
        ->where([
            'OR' => [
                ['id' => $filter],
                ['name*' => $filter],
                ['name*' => 'SENZA PROSPECT'],
            ],
        ])
        ->find();
} else {
    $collection = $em->getRDBRepository('Appuntamento')
        ->where([
            'OR' => [
                ['name*' => 'SENZA PROSPECT'],
                [
                    'prospectId!=' => null,
                    'prospectName' => null,
                ],
                [
                    'prospectId!=' => null,
                    'prospectName' => '',
                ],
            ],
        ])
        ->find();
}

$fixed = 0;
$skipped = 0;

foreach ($collection as $entity) {
    $beforeName = (string) ($entity->get('name') ?? '');
    $prospectId = trim((string) ($entity->get('prospectId') ?? ''));
    $prospectName = trim((string) ($entity->get('prospectName') ?? ''));

    $prospect = $prospectId !== ''
        ? $em->getEntityById('Prospect', $prospectId)
        : null;

    $lead = null;
    if ($entity->get('parentType') === 'Lead' && $entity->get('parentId')) {
        $lead = $em->getEntityById('Lead', (string) $entity->get('parentId'));
    } elseif ($entity->get('leadId')) {
        $lead = $em->getEntityById('Lead', (string) $entity->get('leadId'));
    }

    $changes = [];

    if ($prospectId !== '' && !$prospect) {
        // Orfano: prova da Lead
        $fromLead = $lead ? trim((string) ($lead->get('prospectId') ?? '')) : '';
        $p2 = $fromLead !== '' ? $em->getEntityById('Prospect', $fromLead) : null;

        if ($p2) {
            $changes['prospectId'] = $p2->getId();
            $changes['prospectName'] = $p2->get('name');
            $prospect = $p2;
            $prospectName = (string) $p2->get('name');
        } else {
            $changes['prospectId'] = null;
            $changes['prospectName'] = null;
            $prospectId = '';
            $prospectName = '';
        }
    } elseif ($prospect && ($prospectName === '' || $prospectName === $prospectId)) {
        $changes['prospectName'] = $prospect->get('name');
        $prospectName = (string) $prospect->get('name');
    } elseif ($prospectId === '' && $lead && $lead->get('prospectId')) {
        $p2 = $em->getEntityById('Prospect', (string) $lead->get('prospectId'));
        if ($p2) {
            $changes['prospectId'] = $p2->getId();
            $changes['prospectName'] = $p2->get('name');
            $prospect = $p2;
            $prospectName = (string) $p2->get('name');
        }
    }

    $clientLabel = $prospectName
        ?: ($prospect ? (string) $prospect->get('name') : '')
        ?: ($lead ? (string) $lead->get('name') : '')
        ?: trim((string) ($entity->get('parentName') ?? ''));

    $needsNameFix = str_contains($beforeName, GHOST_NAME)
        || $beforeName === ''
        || ($clientLabel !== '' && !str_contains(mb_strtoupper($beforeName), mb_strtoupper($clientLabel)));

    if ($needsNameFix && $clientLabel !== '') {
        $newName = buildAppuntamentoName($entity, $clientLabel);
        if ($newName !== '' && $newName !== $beforeName) {
            $changes['name'] = $newName;
        }
    }

    if ($changes === []) {
        $skipped++;
        continue;
    }

    $outProspectId = array_key_exists('prospectId', $changes)
        ? ($changes['prospectId'] ?? '(vuoto)')
        : ($prospectId !== '' ? $prospectId : '(vuoto)');
    $outProspectName = array_key_exists('prospectName', $changes)
        ? (string) ($changes['prospectName'] ?? '(vuoto)')
        : ($prospectName !== '' ? $prospectName : '(vuoto)');

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $entity->getId()
        . ' | ' . $beforeName
        . ' → ' . ($changes['name'] ?? $beforeName)
        . ' | prospect=' . $outProspectId
        . ' / ' . $outProspectName
        . PHP_EOL;

    if (!$dryRun) {
        $entity->set($changes);
        // Con hooks: GlobalLogic riallinea indirizzo/CAP/colori
        $em->saveEntity($entity);
    }

    $fixed++;
}

echo PHP_EOL . "Corretti={$fixed} skip={$skipped}" . ($dryRun ? ' (dry-run)' : '') . PHP_EOL;

function buildAppuntamentoName($entity, string $clientLabel): string
{
    $capName = trim((string) ($entity->get('cAPName') ?? $entity->get('indirizzoPostalCode') ?? ''));
    $capCodice = trim((string) ($entity->get('cAPCodice') ?? ''));
    $capDesc = trim((string) ($entity->get('cAPDescrizione') ?? ''));

    $capBlock = '';
    if ($capName !== '') {
        $capBlock = $capName;
        if ($capCodice !== '') {
            $capBlock .= ' - ' . $capCodice;
        }
        if ($capDesc !== '') {
            $capBlock .= ' (' . $capDesc . ')';
        }
    }

    $brand = trim((string) (
        $entity->get('azienda')
        ?: $entity->get('productBrandName')
        ?: ''
    ));
    $categoria = trim((string) (
        $entity->get('productCategoryName')
        ?: $entity->get('lineaProdotto')
        ?: ''
    ));

    $extra = '';
    if ($brand !== '' && $categoria !== '') {
        $extra = ' (' . $brand . ' - ' . $categoria . ')';
    } elseif ($brand !== '' || $categoria !== '') {
        $extra = ' (' . ($brand !== '' ? $brand : $categoria) . ')';
    }

    $name = ($capBlock !== '' ? $capBlock . ' - ' : '')
        . $clientLabel
        . $extra;

    return trim($name);
}
