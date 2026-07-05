<?php
/**
 * Diagnostica salvataggio Appuntamento — replica scenari calendario/modale.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/diagnose-appuntamento-save.php
 */

declare(strict_types=1);

$crmRoot = dirname(__DIR__);

if (!is_file($crmRoot . '/bootstrap.php')) {
    fwrite(STDERR, "ERRORE: eseguire da root CRM (bootstrap.php mancante)\n");
    exit(1);
}

require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

echo "=== Diagnostica Appuntamento save ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

$globalLogic = (string) @file_get_contents(
    $crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php'
);
$requiredDefaults = (string) @file_get_contents(
    $crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/RequiredDefaults.php'
);

$checks = [
    'GlobalLogic BeforeSave' => str_contains($globalLogic, 'implements BeforeSave'),
    'RequiredDefaults hook' => is_file($crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/RequiredDefaults.php'),
    'normalizeMultiEnum tipo' => str_contains($requiredDefaults, 'normalizeMultiEnum'),
    'Services/Appuntamento.php' => is_file($crmRoot . '/custom/Espo/Custom/Services/Appuntamento.php'),
    'AppuntamentoPendingCallCreator' => is_file(
        $crmRoot . '/custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php'
    ),
    'Controller senza CrmKpi hardcoded' => is_file($crmRoot . '/custom/Espo/Custom/Controllers/Appuntamento.php')
        && !str_contains(
            (string) @file_get_contents($crmRoot . '/custom/Espo/Custom/Controllers/Appuntamento.php'),
            'CrmKpiService'
        ),
];

foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[MANCA] ') . $label . "\n";
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

$scenarios = [
    'Not Held senza prospect' => [
        'status' => 'Not Held',
        'sottostato' => 'Non Gestito',
        'esito' => 'Annullato dal Consulente',
        'tipo' => 'Appuntamento Call Center',
    ],
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
];

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$dateStart = $now->format('Y-m-d H:i:s');
$dateEnd = $now->modify('+90 minutes')->format('Y-m-d H:i:s');

foreach ($scenarios as $label => $extra) {
    echo "\n=== Test: {$label} ===\n";

    $entity = $em->getNewEntity('Appuntamento');

    $payload = array_merge([
        'name' => 'DIAG ' . substr(md5($label . microtime(true)), 0, 8),
        'dateStart' => $dateStart,
        'dateEnd' => $dateEnd,
        'fornitorePartnerId' => $partner?->getId(),
        'fornitorePartnerName' => $partner?->get('name'),
        'productBrandId' => $brand?->getId(),
        'productBrandName' => $brand?->get('name'),
        'productCategoryId' => $category?->getId(),
        'productCategoryName' => $category?->get('name'),
        'assignedUserId' => $user?->getId(),
    ], array_filter($extra, static fn ($v) => $v !== null));

    $entity->set($payload);

    try {
        $em->saveEntity($entity);
        echo "[OK] id={$entity->getId()} tipo=" . json_encode($entity->get('tipo')) . "\n";
        echo "     videoCallTelefonico=" . var_export($entity->get('videoCallTelefonico'), true) . "\n";
        $em->removeEntity($entity);
        echo "[OK] record test rimosso\n";
    } catch (Throwable $e) {
        echo "[ERRORE] {$e->getMessage()}\n";
        echo "  in {$e->getFile()}:{$e->getLine()}\n";
        echo substr($e->getTraceAsString(), 0, 1200) . "\n";
    }
}

echo "\n=== Dove cercare errori se il CRM mostra 500 ma espo.log e' vuoto ===\n";
echo "1. php tools/diagnose-appuntamento-save.php  (questo script)\n";
echo "2. tail -50 ~/logs/error_log  (o error.log del virtual host)\n";
echo "3. grep -i error /var/log/apache2/error.log | tail -20\n";
echo "4. tail -80 data/logs/espo-" . date('Y-m-d') . ".log\n";
