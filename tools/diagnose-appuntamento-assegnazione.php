#!/usr/bin/env php
<?php
/**
 * Diagnostica assegnazione appuntamento Not Held (es. Panci 00196).
 *
 * Uso:
 *   php tools/diagnose-appuntamento-assegnazione.php --search=Panci
 *   php tools/diagnose-appuntamento-assegnazione.php --id=<uuid>
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoGoogleSync;

$search = null;
$id = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--search=')) {
        $search = substr($arg, 9);
    }
    if (str_starts_with($arg, '--id=')) {
        $id = substr($arg, 5);
    }
}

if ($id === null && ($search === null || $search === '')) {
    fwrite(STDERR, "Specifica --search=Nome o --id=uuid\n");
    exit(1);
}

$application = new Application();
$application->setupSystemUser();

$container = $application->getContainer();
$entityManager = $container->get('entityManager');
$sync = $container->get('injectableFactory')->create(AppuntamentoGoogleSync::class);

$where = $id !== null && $id !== ''
    ? ['id' => $id]
    : ['name*' => '%' . $search . '%'];

$appointments = $entityManager
    ->getRDBRepository('Appuntamento')
    ->where($where)
    ->limit(0, 20)
    ->find();

if (count($appointments) === 0) {
    fwrite(STDOUT, "Nessun appuntamento trovato.\n");
    exit(0);
}

fwrite(STDOUT, 'Admin di sistema: ' . $sync->describePrimarySystemAdmin() . "\n\n");

foreach ($appointments as $appointment) {
    $entityId = (string) $appointment->getId();
    [$assignedUserId, $assignedUsersIds] = $sync->fetchAssigneeStateFromDb($entityId);
    $needsFix = $sync->needsNotHeldAdminAssigneeFix($appointment);

    fwrite(STDOUT, "=== {$appointment->get('name')} ===\n");
    fwrite(STDOUT, "id: {$entityId}\n");
    fwrite(STDOUT, 'status: ' . (string) ($appointment->get('status') ?? '') . "\n");
    fwrite(STDOUT, 'sottostato: ' . (string) ($appointment->get('sottostato') ?? '') . "\n");
    fwrite(STDOUT, 'esito: ' . (string) ($appointment->get('esito') ?? '') . "\n");
    fwrite(STDOUT, 'assigned_user_id (DB): ' . ($assignedUserId !== '' ? $assignedUserId : '(vuoto)') . "\n");
    fwrite(STDOUT, 'entity_user (DB): ' . ($assignedUsersIds !== [] ? implode(', ', $assignedUsersIds) : '(vuoto)') . "\n");
    fwrite(STDOUT, 'UI assegnatario: ' . $sync->describeAssignee($appointment) . "\n");
    fwrite(STDOUT, 'needsNotHeldAdminAssigneeFix: ' . ($needsFix ? 'SI' : 'NO') . "\n");
    fwrite(STDOUT, 'isAssignedToPrimarySystemAdmin: ' . ($sync->isAssignedToPrimarySystemAdmin($appointment) ? 'SI' : 'NO') . "\n\n");
}
