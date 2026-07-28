<?php
/**
 * Patch IN-PLACE layout Quote/Task: aggiunge campi verifica senza sostituire layout.
 *
 *   php tools/patch-layouts-verifica-installazione.php
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

function layoutHasField(array $layout, string $field): bool
{
    $json = json_encode($layout);

    return is_string($json) && str_contains($json, '"name": "' . $field . '"');
}

function appendRowToFirstPanel(array &$layout, array $row): void
{
    if (!isset($layout[0]['rows']) || !is_array($layout[0]['rows'])) {
        fwrite(STDERR, "ERR layout senza rows\n");
        exit(1);
    }

    $layout[0]['rows'][] = $row;
}

$quoteLayoutPath = "{$root}/custom/Espo/Custom/Resources/layouts/Quote/detail.json";
$taskLayoutPath = "{$root}/custom/Espo/Custom/Resources/layouts/Task/detail.json";

$quoteLayout = loadJson($quoteLayoutPath);

if (!layoutHasField($quoteLayout, 'verificaInstallazioneTask')) {
    // Aggiunge riga nella sezione Details (secondo pannello se presente, altrimenti primo)
    $panelIndex = isset($quoteLayout[1]['rows']) ? 1 : 0;
    $quoteLayout[$panelIndex]['rows'][] = [
        ['name' => 'verificaInstallazioneTask'],
        false,
    ];
    saveJson($quoteLayoutPath, $quoteLayout);
    echo "[OK] Quote layout: aggiunto verificaInstallazioneTask\n";
} else {
    echo "[SKIP] Quote layout già ha verificaInstallazioneTask\n";
}

$taskLayout = loadJson($taskLayoutPath);

if (!layoutHasField($taskLayout, 'esitoVerificaInstallazione')) {
    // Sostituisce solo la cella false accanto a tipologia, se presente; altrimenti append
    $patched = false;

    foreach ($taskLayout as &$panel) {
        if (!isset($panel['rows']) || !is_array($panel['rows'])) {
            continue;
        }

        foreach ($panel['rows'] as &$row) {
            if (!is_array($row) || count($row) < 2) {
                continue;
            }

            $left = $row[0]['name'] ?? null;

            if ($left === 'tipologia' && ($row[1] === false || $row[1] === null)) {
                $row[1] = ['name' => 'esitoVerificaInstallazione'];
                $patched = true;
                break 2;
            }
        }
    }
    unset($panel, $row);

    if (!$patched) {
        appendRowToFirstPanel($taskLayout, [
            ['name' => 'esitoVerificaInstallazione'],
            false,
        ]);
    }

    saveJson($taskLayoutPath, $taskLayout);
    echo "[OK] Task layout: aggiunto esitoVerificaInstallazione\n";
} else {
    echo "[SKIP] Task layout già ha esitoVerificaInstallazione\n";
}
