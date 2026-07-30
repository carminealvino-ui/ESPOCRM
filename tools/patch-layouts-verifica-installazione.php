<?php
/**
 * Patch IN-PLACE layout Quote/Task: aggiunge campi verifica SENZA duplicare.
 * Detection robusta anche con JSON senza spazi dopo i due punti.
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

    if (!is_string($json)) {
        return false;
    }

    // Accetta "name": "field" e "name":"field"
    return (bool) preg_match('/"name"\s*:\s*"' . preg_quote($field, '/') . '"/', $json);
}

function countFieldOccurrences(array $layout, string $field): int
{
    $count = 0;

    foreach ($layout as $panel) {
        if (!isset($panel['rows']) || !is_array($panel['rows'])) {
            continue;
        }

        foreach ($panel['rows'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $cell) {
                if (is_array($cell) && ($cell['name'] ?? null) === $field) {
                    $count++;
                }
            }
        }
    }

    return $count;
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
$quoteCount = countFieldOccurrences($quoteLayout, 'verificaInstallazioneTask');

if ($quoteCount > 1) {
    fwrite(STDERR, "ERR Quote layout ha {$quoteCount} occorrenze di verificaInstallazioneTask. Esegui prima:\n");
    fwrite(STDERR, "  php tools/fix-quote-todo-verifica-label.php\n");
    exit(1);
}

if ($quoteCount === 0) {
    $panelIndex = isset($quoteLayout[1]['rows']) ? 1 : 0;
    $placed = false;

    foreach ($quoteLayout[$panelIndex]['rows'] as &$row) {
        if (!is_array($row)) {
            continue;
        }

        foreach ($row as $idx => $cell) {
            if (is_array($cell) && ($cell['name'] ?? null) === 'dataInstallazione') {
                // Affianca To-Do a Data Installazione
                if (count($row) === 1) {
                    $row[] = ['name' => 'verificaInstallazioneTask'];
                } elseif (($row[0]['name'] ?? null) === 'dataInstallazione') {
                    $row[1] = ['name' => 'verificaInstallazioneTask'];
                } elseif (($row[1]['name'] ?? null) === 'dataInstallazione') {
                    $row[0] = ['name' => 'dataInstallazione'];
                    $row[1] = ['name' => 'verificaInstallazioneTask'];
                } else {
                    $row = [
                        ['name' => 'dataInstallazione'],
                        ['name' => 'verificaInstallazioneTask'],
                    ];
                }

                $placed = true;
                break 2;
            }
        }
    }
    unset($row);

    if (!$placed) {
        $quoteLayout[$panelIndex]['rows'][] = [
            ['name' => 'verificaInstallazioneTask'],
            false,
        ];
    }

    saveJson($quoteLayoutPath, $quoteLayout);
    echo "[OK] Quote layout: aggiunto verificaInstallazioneTask (una sola volta)\n";
} else {
    echo "[SKIP] Quote layout già ha verificaInstallazioneTask\n";
}

if (!is_file($taskLayoutPath)) {
    echo "[SKIP] Task layout assente\n";
    exit(0);
}

$taskLayout = loadJson($taskLayoutPath);

if (!layoutHasField($taskLayout, 'esitoVerificaInstallazione')) {
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
