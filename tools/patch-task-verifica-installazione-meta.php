<?php
/**
 * Patch IN-PLACE metadata/i18n/logicDefs Task per verifica installazione.
 * Non sostituisce i file interi.
 *
 *   php tools/patch-task-verifica-installazione-meta.php
 */

declare(strict_types=1);

$root = rtrim(getenv('CRM_ROOT') ?: (isset($argv[1]) ? $argv[1] : getcwd()), '/');

function loadJson(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERR mancante: {$path}\n");
        exit(1);
    }

    $data = json_decode((string) file_get_contents($path), true);

    if (!is_array($data)) {
        fwrite(STDERR, "ERR JSON: {$path}\n");
        exit(1);
    }

    return $data;
}

function saveJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        fwrite(STDERR, "ERR encode: {$path}\n");
        exit(1);
    }

    $json = preg_replace('/^(  +)/m', '$0$0', $json) ?? $json;
    file_put_contents($path, $json . "\n");
}

$metaPath = "{$root}/custom/Espo/Custom/Resources/metadata/entityDefs/Task.json";
$i18nPath = "{$root}/custom/Espo/Custom/Resources/i18n/it_IT/Task.json";
$logicPath = "{$root}/custom/Espo/Custom/Resources/metadata/logicDefs/Task.json";

$meta = loadJson($metaPath);

$parentList = $meta['fields']['parent']['entityList'] ?? [];

if (!in_array('Quote', $parentList, true)) {
    $meta['fields']['parent']['entityList'][] = 'Quote';
}

$tipologiaOptions = $meta['fields']['tipologia']['options'] ?? [];

if (!in_array('Verifica installazione', $tipologiaOptions, true)) {
    $meta['fields']['tipologia']['options'][] = 'Verifica installazione';
    $meta['fields']['tipologia']['style']['Verifica installazione'] = null;
}

$meta['fields']['dateCompleted']['required'] = false;
$meta['fields']['dateCompleted']['notNull'] = false;

$meta['fields']['verificaInstallazioneContratto'] = [
    'type' => 'bool',
    'default' => false,
    'readOnly' => true,
    'isCustom' => true,
];

$meta['fields']['esitoVerificaInstallazione'] = [
    'type' => 'enum',
    'options' => ['', 'Installato', 'Rinviato'],
    'style' => [
        '' => null,
        'Installato' => 'success',
        'Rinviato' => 'warning',
    ],
    'isCustom' => true,
];

saveJson($metaPath, $meta);
echo "[OK] Task entityDefs patch\n";

$i18n = loadJson($i18nPath);
$i18n['fields']['verificaInstallazioneContratto'] = 'Verifica installazione (auto)';
$i18n['fields']['esitoVerificaInstallazione'] = 'Esito verifica installazione';
$i18n['options']['tipologia']['Verifica installazione'] = 'Verifica installazione';
$i18n['options']['esitoVerificaInstallazione'] = [
    '' => '',
    'Installato' => 'Installato',
    'Rinviato' => 'Rinviato',
];
saveJson($i18nPath, $i18n);
echo "[OK] Task i18n patch\n";

$logic = loadJson($logicPath);
$logic['fields']['dateCompleted']['required'] = null;
$logic['fields']['esitoVerificaInstallazione'] = [
    'visible' => [
        'conditionGroup' => [
            ['type' => 'isTrue', 'attribute' => 'verificaInstallazioneContratto'],
        ],
    ],
];
$logic['fields']['verificaInstallazioneContratto'] = [
    'visible' => [
        'conditionGroup' => [
            ['type' => 'isTrue', 'attribute' => 'verificaInstallazioneContratto'],
        ],
    ],
];
saveJson($logicPath, $logic);
echo "[OK] Task logicDefs patch\n";
