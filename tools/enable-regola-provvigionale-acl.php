<?php

/**
 * Abilita ACL RegolaProvvigionale su tutti i ruoli (read/create/edit/delete).
 * Senza ACL, Espo mostra 404 su #RegolaProvvigionale anche se l'entità esiste.
 *
 *   php tools/enable-regola-provvigionale-acl.php
 *   php tools/enable-regola-provvigionale-acl.php --dry-run
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$scope = 'RegolaProvvigionale';
$aclEntry = (object) [
    'create' => 'yes',
    'read' => 'all',
    'edit' => 'all',
    'delete' => 'all',
    'stream' => 'no',
];

$roles = $em->getRDBRepository('Role')->find();
$updated = 0;
$skipped = 0;

foreach ($roles as $role) {
    $data = $role->get('data');

    if (is_string($data)) {
        $decoded = json_decode($data);
        $data = is_object($decoded) ? $decoded : (object) [];
    }

    if (is_array($data)) {
        $data = (object) $data;
    }

    if (!is_object($data)) {
        $data = (object) [];
    }

    $current = $data->$scope ?? null;
    $needsUpdate = true;

    if (is_object($current) || is_array($current)) {
        $cur = (object) $current;
        if (
            ($cur->create ?? null) === 'yes'
            && in_array($cur->read ?? null, ['all', 'yes', 'team', 'own'], true)
            && ($cur->edit ?? null) !== 'no'
            && ($cur->read ?? null) !== 'no'
        ) {
            $needsUpdate = false;
        }
    }

    if (!$needsUpdate) {
        echo "SKIP role {$role->getId()} ({$role->get('name')}): già abilitato\n";
        $skipped++;
        continue;
    }

    $data->$scope = $aclEntry;

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . "role {$role->getId()} ({$role->get('name')}): grant {$scope}\n";

    if (!$dryRun) {
        $role->set('data', $data);
        $em->saveEntity($role, [
            'silent' => true,
            'skipHooks' => true,
            'skipAll' => true,
        ]);
    }

    $updated++;
}

echo "\nRuoli aggiornati={$updated} già_ok={$skipped}" . ($dryRun ? ' (dry-run)' : '') . "\n";

if (!$dryRun && $updated > 0) {
    // Invalida cache ACL utenti
    try {
        $app->getContainer()->get('dataManager')->clearCache();
        echo "OK clearCache\n";
    } catch (Throwable $e) {
        echo "WARN clearCache: " . $e->getMessage() . "\n";
    }
}

exit(0);
