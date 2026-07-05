<?php
/**
 * Diagnostica salvataggio Appuntamento — replica scenari calendario/modale.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/diagnose-appuntamento-save.php
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(120);

$crmRoot = dirname(__DIR__);

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if (
        $error &&
        in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
    ) {
        echo "\n[FATAL PHP] {$error['message']}\n";
        echo "  in {$error['file']}:{$error['line']}\n";
    }
});

if (!is_file($crmRoot . '/bootstrap.php')) {
    fwrite(STDERR, "ERRORE: eseguire da root CRM (bootstrap.php mancante)\n");
    exit(1);
}

require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

function diag_line(string $message): void
{
    echo $message . "\n";

    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    flush();
}

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

diag_line('=== Diagnostica Appuntamento save ===');
diag_line('Data: ' . date('Y-m-d H:i:s'));
diag_line('');

$globalLogic = (string) @file_get_contents(
    $crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php'
);
$requiredDefaults = (string) @file_get_contents(
    $crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/RequiredDefaults.php'
);

$checks = [
    'GlobalLogic BeforeSave' => str_contains($globalLogic, 'implements BeforeSave'),
    'GlobalLogic no assignedUsersIds' => !str_contains($globalLogic, "assignedUsersIds"),
    'RequiredDefaults hook' => is_file($crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/RequiredDefaults.php'),
    'normalizeMultiEnum tipo' => str_contains($requiredDefaults, 'normalizeMultiEnum'),
    'Services/Appuntamento.php' => is_file($crmRoot . '/custom/Espo/Custom/Services/Appuntamento.php'),
    'AppuntamentoPendingCallCreator' => is_file(
        $crmRoot . '/custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php'
    ),
    'AutoCreatePendingCall hook' => is_file(
        $crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/AutoCreatePendingCall.php'
    ),
];

foreach ($checks as $label => $ok) {
    diag_line(($ok ? '[OK] ' : '[--] ') . $label);
}

$brand = $em->getRDBRepository('ProductBrand')->where(['name' => 'ARIEL'])->findOne();
$partner = $em->getRDBRepository('FornitorePartner')->where(['name' => 'GDL'])->findOne();
$category = $em->getRDBRepository('ProductCategory')->where(['name' => 'CLIMATIZZATORI'])->findOne();
$user = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne();
$prospect = $em->getRDBRepository('Prospect')
    ->where(['lastName' => 'CORNALI ANNA MARIA'])
    ->findOne();

if (!$prospect) {
    $prospect = $em->getRDBRepository('Prospect')
        ->where(['name' => 'CORNALI ANNA MARIA'])
        ->findOne();
}

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$dateStart = $now->format('Y-m-d H:i:s');
$dateEnd = $now->modify('+90 minutes')->format('Y-m-d H:i:s');

$basePayload = [
    'name' => 'DIAG-MIN-' . date('His'),
    'dateStart' => $dateStart,
    'dateEnd' => $dateEnd,
    'status' => 'Planned',
    'fornitorePartnerId' => $partner?->getId(),
    'fornitorePartnerName' => $partner?->get('name'),
    'productBrandId' => $brand?->getId(),
    'productBrandName' => $brand?->get('name'),
    'productCategoryId' => $category?->getId(),
    'productCategoryName' => $category?->get('name'),
    'assignedUserId' => $user?->getId(),
];

diag_line('');
diag_line('=== Test 0: minimo senza hook ===');

$min = $em->getNewEntity('Appuntamento');
$min->set($basePayload);

try {
    diag_line('saveEntity(skipHooks=true)...');
    $em->saveEntity($min, ['skipHooks' => true]);
    diag_line('[OK] id=' . $min->getId());
    $em->removeEntity($min, ['skipHooks' => true]);
    diag_line('[OK] rimosso');
} catch (Throwable $e) {
    diag_line('[ERRORE] ' . $e->getMessage());
    diag_line('  in ' . $e->getFile() . ':' . $e->getLine());
}

$scenarios = [
    'Planned con prospect CORNALI' => [
        'status' => 'Planned',
        'tipo' => 'Appuntamento Call Center',
        'callCenter' => 'ARIEL',
        'prospectId' => $prospect?->getId(),
        'prospectName' => $prospect?->get('name'),
        'parentType' => $prospect ? 'Prospect' : null,
        'parentId' => $prospect?->getId(),
        'parentName' => $prospect?->get('name'),
    ],
    'Not Held senza prospect' => [
        'status' => 'Not Held',
        'sottostato' => 'Non Gestito',
        'esito' => 'Annullato dal Consulente',
        'tipo' => 'Appuntamento Call Center',
    ],
];

foreach ($scenarios as $label => $extra) {
    diag_line('');
    diag_line("=== Test: {$label} ===");

    $entity = $em->getNewEntity('Appuntamento');

    $payload = array_merge($basePayload, [
        'name' => 'DIAG ' . substr(md5($label . microtime(true)), 0, 8),
    ], array_filter($extra, static fn ($v) => $v !== null));

    $entity->set($payload);

    try {
        diag_line('saveEntity(con hook)...');
        $em->saveEntity($entity);
        diag_line('[OK] id=' . $entity->getId() . ' tipo=' . json_encode($entity->get('tipo')));
        diag_line('     videoCallTelefonico=' . var_export($entity->get('videoCallTelefonico'), true));
        diag_line('     hookVersion=' . $entity->get('hookVersion'));
        $em->removeEntity($entity);
        diag_line('[OK] record test rimosso');
    } catch (Throwable $e) {
        diag_line('[ERRORE] ' . $e->getMessage());
        diag_line('  in ' . $e->getFile() . ':' . $e->getLine());
        diag_line(substr($e->getTraceAsString(), 0, 1500));
    }
}

diag_line('');
diag_line('=== Fine diagnostica ===');
diag_line('Se [FATAL PHP] sopra: incollare quel messaggio.');
diag_line('Log server: tail -50 ~/logs/error_log');
