<?php
/**
 * Estrae righe listino da PDF Ariel 07.05.26 → CSV per sync-listino-prodotti.php
 *
 *   php tools/extract-listino-ariel-pdf.php \
 *     --pdf="database/listini/Listino Prodotti ARIEL ENERGIA 07.05.26.pdf" \
 *     --out=database/data/listino-ariel-prodotti-07052026.csv
 */

declare(strict_types=1);

$options = getopt('', ['pdf:', 'out:', 'brand::', 'mono-plus', 'no-mono-plus']);

if (empty($options['pdf']) || empty($options['out'])) {
    fwrite(STDERR, "Usage: php extract-listino-ariel-pdf.php --pdf=FILE --out=FILE.csv\n");
    exit(1);
}

$brand = $options['brand'] ?? 'ARIEL';
$monoPlus = !array_key_exists('no-mono-plus', $options);
$pdfPath = $options['pdf'];
$outPath = $options['out'];

if (!is_file($pdfPath)) {
    fwrite(STDERR, "PDF non trovato: {$pdfPath}\n");
    exit(1);
}

$lines = preg_split('/\r\n|\r|\n/', extractPdfText($pdfPath)) ?: [];
$rows = [];

$ctx = [
    'listino' => '',
    'caldaia' => '',
    'biomassa' => '',
    'section' => '',
    'promo' => false,
    'accessori' => '',
    'acc_titolo' => '',
    'pending_desc' => '',
];

$n = count($lines);

for ($i = 0; $i < $n; $i++) {
    $line = normalizeLine($lines[$i]);

    if ($line === '') {
        continue;
    }

    updateContext($ctx, $line);

    // --- Caldaie + Falcon (Prezzo Retail) ---
    if ($ctx['caldaia'] !== ''
        && $ctx['biomassa'] === ''
        && stripos($ctx['listino'], 'CALDAIE + Clima') !== false
        && preg_match('/^(00\.\d{2}\.\d{2}\.\d)\s+(\d+\s*kW|\d+-\d+\s*LT)\s+[\d.,\s€]+\s+[\d.,\s€]+\s+([\d.]+,\d{2})\s*€/iu', $line, $m)
    ) {
        $rows[] = makeRow(
            $brand,
            'CALDAIE',
            $ctx['caldaia'] . ' ' . trim($m[2]) . ' + FALCON 9000',
            $m[1],
            parseEuro($m[3]),
            null,
            'prodotto',
            'PDF caldaie+Falcon 9k'
        );

        continue;
    }

    // --- Scaldabagno ---
    if (preg_match('/^SCALDABAGNO/i', $line) || (preg_match('/^Codice\s+Potenza/i', $line) && stripos($ctx['listino'], 'SCALDABAGNO') !== false)) {
        if (preg_match('/^(00\.\d{2}\.\d{2}\.\d)\s+(.+?)\s+[\d.,\s€]+\s+[\d.,\s€]+\s+([\d.]+,\d{2})\s*€/u', $line, $m)) {
            $rows[] = makeRow($brand, 'CALDAIE', 'SCALDABAGNO ' . trim($m[2]), $m[1], parseEuro($m[3]), null, 'prodotto', 'PDF scaldabagno');
        }
    }

    if (stripos($ctx['caldaia'], 'ECO WIND EASY') !== false
        && preg_match('/Totale Iva inclusa al 10%\s+([\d.]+,\d{2})\s*€/i', $line, $m)
    ) {
        $rows[] = makeRow($brand, 'CALDAIE', 'ECO WIND EASY 24 kW PACCHETTO INSTALLATO', '', parseEuro($m[1]), null, 'prodotto', 'PDF ECO WIND EASY');
        $ctx['caldaia'] = '';

        continue;
    }

    // --- Biomassa righe codice ---
    if ($ctx['biomassa'] !== ''
        && stripos($ctx['listino'], 'BIOMASSA') !== false
        && preg_match('/^(00\.\d{2}\.\d{2}\.\d)\s+(.+?)\s+([\d.]+,\d{2})\s*€/u', $line, $m)
    ) {
        $potenza = rtrim(trim($m[2]), '*');
        $label = $ctx['biomassa'];

        if (stripos($label, 'CALDAIA PELLET') !== false) {
            $label = 'CALDAIA PELLET BOILER 2S';
        }

        $rows[] = makeRow(
            $brand,
            'BIOMASSA',
            $label . ' ' . $potenza,
            $m[1],
            parseEuro($m[3]),
            null,
            'prodotto',
            'PDF biomassa'
        );

        continue;
    }

    // --- Accessori (titolo sezione + descrizione + prezzo) ---
    if ($ctx['accessori'] !== '') {
        $acc = parseAccessorioLine($line, $ctx);

        if ($acc !== null) {
            $cat = match ($ctx['accessori']) {
                'CLIMA' => 'CLIMATIZZATORI - ACCESSORI',
                'CALDAIE' => 'CALDAIE',
                default => 'BIOMASSA',
            };
            $rows[] = makeRow($brand, $cat, $acc['denom'], $acc['codice'], $acc['prezzo'], null, $acc['tipo'], 'PDF accessorio');
            $ctx['pending_desc'] = '';

            continue;
        }
    }

    // --- Puffer biomassa (tabella codice+desc+prezzo) ---
    if (preg_match('/^Listino ACCESSORI.*BIOMASSA/i', $ctx['listino']) && preg_match('/^(00\.\d{2}\.\d{2}\.\d)\s+(.+?)\s+([\d.]+,\d{2})\s*€/u', $line, $m)) {
        $rows[] = makeRow($brand, 'BIOMASSA', strtoupper(trim($m[2])), $m[1], parseEuro($m[3]), null, 'prodotto', 'PDF puffer biomassa');

        continue;
    }

    // --- Blocchi con TOTALE (clima) ---
    if (preg_match('/\bTOTALE\b\s+([\d.]+,\d{2})\s*€/iu', $line, $m)) {
        $window = [];

        for ($j = max(0, $i - 12); $j <= $i; $j++) {
            $window[] = normalizeLine($lines[$j]);
        }

        $parsed = parseTotaleWindow($window, $ctx, $monoPlus);

        if ($parsed !== null) {
            $parsed['prezzo_listino'] = (string) (int) round(parseEuro($m[1]));
            $rows[] = $parsed;
        }
    }
}

$rows = dedupeRows($rows);
writeCsv($outPath, $rows);

fwrite(STDOUT, "Scritte " . count($rows) . " righe in {$outPath}\n");

foreach (countByCategory($rows) as $cat => $cnt) {
    fwrite(STDOUT, "  {$cat}: {$cnt}\n");
}

exit(0);

function extractPdfText(string $pdfPath): string
{
    $cmd = 'pdftotext -layout ' . escapeshellarg($pdfPath) . ' - 2>/dev/null';
    $text = shell_exec($cmd);

    if (!is_string($text) || trim($text) === '') {
        fwrite(STDERR, "pdftotext fallito\n");
        exit(1);
    }

    return $text;
}

function updateContext(array &$ctx, string $line): void
{
    if (preg_match('/Listino\s+.+$/i', $line) && strlen($line) < 100 && !preg_match('/€/', $line)) {
        $ctx['listino'] = $line;
    }

    if (preg_match('/Listino ACCESSORI.*CALDAIE/i', $line)) {
        $ctx['accessori'] = 'CALDAIE';
        $ctx['acc_titolo'] = '';
    } elseif (preg_match('/Listino ACCESSORI.*CLIMA/i', $line)) {
        $ctx['accessori'] = 'CLIMA';
        $ctx['acc_titolo'] = '';
    } elseif (preg_match('/Listino ACCESSORI.*BIOMASSA/i', $line)) {
        $ctx['accessori'] = 'BIOMASSA';
        $ctx['acc_titolo'] = '';
    } elseif (preg_match('/^Listino CLIMA|^Listino CLIMATIZZAZIONE/i', $line)) {
        $ctx['accessori'] = '';
        $ctx['caldaia'] = '';
        $ctx['biomassa'] = '';
    }

    if (preg_match('/Listino BIOMASSA/i', $line)) {
        $ctx['listino'] = 'BIOMASSA';
        $ctx['biomassa'] = '';
        $ctx['caldaia'] = '';
        $ctx['accessori'] = '';
    }

    if (preg_match('/CONDIZIONI DI VENDITA/i', $line)) {
        $ctx['accessori'] = '';
        $ctx['acc_titolo'] = '';
    }

    if ($ctx['accessori'] !== '' && preg_match('/^Descrizione\s+Prezzo$/i', str_replace('  ', ' ', $line))) {
        return;
    }

    if ($ctx['accessori'] !== '' && !preg_match('/€|Codice|Descrizione|Prezzo|PREZZI|Listino /i', $line)
        && preg_match('/^[A-Z0-9][A-Z0-9\s\-\'\(\)\/\.]+$/u', $line) && strlen($line) < 80
    ) {
        $ctx['acc_titolo'] = cleanProductLabel(trim($line));
    }

    if (preg_match('/^Caldaia\s+(.+)$/i', $line, $m) && !preg_match('/€/', $line)) {
        $ctx['caldaia'] = cleanProductLabel($m[1]);
        $ctx['biomassa'] = '';

        if (stripos($ctx['caldaia'], 'ECO WIND EASY') !== false) {
            $ctx['caldaia'] = 'ECO WIND EASY';
        }
    }

    if (preg_match('/^SCALDABAGNO/i', $line)) {
        $ctx['caldaia'] = 'SCALDABAGNO';
    }

    if (preg_match('/^Stufa a pellet\s+(.+)$/i', $line, $m) || preg_match('/^Stufa a legna\s+(.+)$/i', $line, $m)) {
        $ctx['biomassa'] = 'STUFA ' . cleanProductLabel($m[1]);
        $ctx['caldaia'] = '';
    }

    if (preg_match('/^Termostufa a pellet\s+(.+)$/i', $line, $m)) {
        $ctx['biomassa'] = 'TERMOSTUFA ' . cleanProductLabel($m[1]);
    }

    if (preg_match('/^Caldaia a pellet\s+(.+)$/i', $line, $m)) {
        $ctx['biomassa'] = 'CALDAIA PELLET ' . cleanProductLabel($m[1]);
    }

    if (preg_match('/Promo 1\+1/i', $line)) {
        $ctx['promo'] = true;
    }

    if (preg_match('/Climatizzatore FALCON (Mono|Dual|Trial)/i', $line, $m)) {
        $ctx['section'] = 'FALCON_' . strtoupper($m[1]);
        $ctx['promo'] = false;
    } elseif (preg_match('/CLIMA FALCON (MONO|DUAL|TRIAL)/i', $line, $m)) {
        $ctx['section'] = 'PROMO_' . strtoupper($m[1]);
        $ctx['promo'] = true;
    } elseif (preg_match('/CLIMA A3ION/i', $line)) {
        $ctx['section'] = 'A3ION';
        $ctx['promo'] = stripos($line, 'Promo') !== false || stripos($ctx['listino'], 'Promo') !== false;
    } elseif (preg_match('/CLIMA FLOOR ONE/i', $line)) {
        $ctx['section'] = 'FLOOR_ONE';
    } elseif (preg_match('/Climatizzatore A3ION/i', $line)) {
        $ctx['section'] = 'A3ION';
        $ctx['promo'] = false;
    } elseif (preg_match('/FLOOR ONE/i', $line) && preg_match('/Climatizzatore|CLIMA FLOOR/i', $line)) {
        $ctx['section'] = 'FLOOR_ONE';
    } elseif (preg_match('/Climatizzatore VANGUARD (Mono|Dual)/i', $line, $m)) {
        $ctx['section'] = 'VANGUARD_' . strtoupper($m[1]);
        $ctx['promo'] = false;
    }
}

/**
 * @param list<string> $window
 * @return array<string, string>|null
 */
function parseTotaleWindow(array $window, array $ctx, bool $monoPlus): ?array
{
    $text = implode("\n", $window);
    $codice = null;

    if (preg_match_all('/\b(00\.\d{2}\.\d{2}\.\d)\b/', $text, $all)) {
        $codice = normalizeCodice($all[1][array_key_last($all[1])]);
    }

    $paid = [];
    $omaggio = [];

    foreach ($window as $ln) {
        if (preg_match('/Falcon\s+([\d.]+(?:\+[\d.]+)*)\s*btu/iu', $ln, $m)) {
            $val = $m[1];

            if (stripos($ln, 'omaggio') !== false) {
                $omaggio[] = $val;
            } else {
                $paid[] = $val;
            }
        }

        if (preg_match('/A3ION\s+([\d.]+(?:\+[\d.]+)*)/iu', $ln, $m) && stripos($ln, 'Installazione') === false && stripos($ln, '€') === false) {
            if (stripos($ln, 'omaggio') !== false) {
                $omaggio[] = $m[1];
            } else {
                $paid[] = $m[1];
            }
        }

        if (preg_match('/Floor One\s+([\d.]+)\s*btu/iu', $ln, $m)) {
            if (stripos($ln, 'omaggio') !== false) {
                $omaggio[] = $m[1];
            } else {
                $paid[] = $m[1];
            }
        }

        if (preg_match('/Vanguard\s+([\d.]+(?:\+[\d.]+)*)\s*btu/iu', $ln, $m)) {
            $paid[] = $m[1];
        }
    }

    if ($codice === null && $paid === [] && $omaggio === []) {
        return null;
    }

    $categoria = 'CLIMATIZZATORI';
    $note = $ctx['promo'] ? 'PDF promo 1+1' : 'PDF ' . $ctx['section'];

    if (preg_match('/A3ION/i', $text)) {
        $combo = '';

        foreach ($window as $ln) {
            if (preg_match('/A3ION\s+([\d.]+(?:\+[\d.]+)*)/iu', $ln, $m) && stripos($ln, 'Installazione') === false) {
                $combo = normalizeBtuCombo($m[1]);
            }
        }

        if ($combo === '') {
            $combo = normalizeBtuCombo(implode('+', $paid));
        }

        return makeRow(
            'ARIEL',
            $categoria,
            ($ctx['promo'] ? 'A3ION QUADRAL ' . $combo . 'BTU PROMO 1+1' : 'A3ION QUADRAL ' . $combo . 'BTU'),
            $codice ?? '',
            null,
            null,
            'prodotto',
            $note
        );
    }

    if (preg_match('/Floor One/i', $text)) {
        $btu = normalizeBtuSingle($paid[0] ?? $omaggio[0] ?? '12000');

        return makeRow(
            'ARIEL',
            $categoria,
            $ctx['promo'] ? "FLOOR ONE {$btu}BTU PROMO 1+1" : "FLOOR ONE {$btu}BTU",
            $codice ?? '',
            null,
            null,
            'prodotto',
            $note
        );
    }

    if (preg_match('/Vanguard/i', $text)) {
        $raw = $paid[0] ?? '';

        if (str_contains($raw, '+')) {
            return makeRow('ARIEL', $categoria, 'VANGUARD DUAL ' . normalizeBtuCombo($raw) . 'BTU', $codice ?? '', null, null, 'prodotto', $note);
        }

        return makeRow('ARIEL', $categoria, 'VANGUARD MONO ' . normalizeBtuSingle($raw) . 'BTU', $codice ?? '', null, null, 'prodotto', $note);
    }

    if ($ctx['promo']) {
        $denom = buildPromoDenom($paid, $omaggio, $ctx['section'], $monoPlus);

        return makeRow('ARIEL', $categoria, $denom, $codice ?? '', null, null, 'prodotto', $note);
    }

    $monoFromCodice = falconMonoBtuFromCodice($codice ?? '');

    if ($monoFromCodice !== null) {
        return makeRow(
            'ARIEL',
            $categoria,
            buildMonoDenom($monoFromCodice, $monoPlus),
            $codice ?? '',
            null,
            null,
            'prodotto',
            'PDF FALCON_MONO'
        );
    }

    if ($ctx['section'] === 'FALCON_TRIAL' || (isset($paid[0]) && substr_count($paid[0], '+') >= 2)) {
        $combo = normalizeBtuCombo($paid[0] ?? implode('+', $paid));

        return makeRow('ARIEL', $categoria, 'FALCON TRIAL ' . $combo . 'BTU', $codice ?? '', null, null, 'prodotto', $note);
    }

    if ($ctx['section'] === 'FALCON_DUAL' || (isset($paid[0]) && substr_count($paid[0], '+') === 1)) {
        $combo = normalizeBtuCombo($paid[0] ?? '');

        return makeRow('ARIEL', $categoria, 'FALCON DUAL ' . $combo . 'BTU', $codice ?? '', null, null, 'prodotto', $note);
    }

    return null;
}

function parseAccessorioLine(string $line, array &$ctx): ?array
{
    if (preg_match('/^(Codice|Descrizione|Prezzo|PREZZI|Listino|N\.B\.|ULTERIORI)/iu', $line)) {
        return null;
    }

    if (!preg_match('/([\d.]+,\d{2})\s*€/u', $line, $m)) {
        if (!preg_match('/€/', $line) && strlen($line) > 15 && !preg_match('/^00\./', $line)) {
            $ctx['pending_desc'] = trim($ctx['pending_desc'] . ' ' . $line);
        }

        return null;
    }

    $prezzo = parseEuro($m[1]);
    $desc = trim(preg_replace('/\s+[\d.]+,\d{2}\s*€.*$/u', '', $line) ?? $line);

    if ($ctx['pending_desc'] !== '') {
        $desc = trim($ctx['pending_desc'] . ' ' . $desc);
    }

    $codice = '';

    if (preg_match('/^(00\.\d{2}\.\d{2}\.\d)\s+(.+)$/u', $desc, $cm)) {
        $codice = $cm[1];
        $desc = $cm[2];
    }

    $titolo = $ctx['acc_titolo'] !== '' ? $ctx['acc_titolo'] . ' — ' : '';
    $denom = strtoupper($titolo . $desc);
    $denom = preg_replace('/\s+/u', ' ', $denom) ?? $denom;

    if (strlen($denom) < 12 || strlen($denom) > 95 || preg_match('/^(VIGENTE|PREZZO PER)/i', $denom)) {
        return null;
    }

    if (preg_match('/CONDIZIONI DI VENDITA|PER LEGGE LA NUOVA|SPECIFICHE PROMO/i', $denom)) {
        return null;
    }

    $tipo = (preg_match('/(installazione|contributo|occupazione|smaltimento|collaudo|rilievo)/iu', $denom))
        ? 'servizio'
        : 'prodotto';

    return ['denom' => $denom, 'codice' => $codice, 'prezzo' => $prezzo, 'tipo' => $tipo];
}

function falconMonoBtuFromCodice(string $codice): ?string
{
    $map = [
        '00.02.95.0' => '9000',
        '00.03.25.0' => '12000',
        '00.03.65.0' => '18000',
        '00.03.85.0' => '24000',
    ];

    return $map[$codice] ?? null;
}

function buildMonoDenom(string $btuRaw, bool $monoPlus): string
{
    $btu = normalizeBtuSingle($btuRaw);
    $kind = $monoPlus ? 'MONO PLUS' : 'MONO';

    return "FALCON {$kind} {$btu}BTU";
}

function buildPromoDenom(array $paid, array $omaggio, string $section, bool $monoPlus): string
{
    $kind = 'MONO';

    if (str_contains($section, 'DUAL')) {
        $kind = 'DUAL';
    } elseif (str_contains($section, 'TRIAL')) {
        $kind = 'TRIAL';
    }

    $parts = [];

    foreach ($paid as $p) {
        foreach (explode('+', $p) as $seg) {
            $parts[] = normalizeBtuSingle($seg);
        }
    }

    foreach ($omaggio as $o) {
        foreach (explode('+', $o) as $seg) {
            $parts[] = normalizeBtuSingle($seg) . ' OMAGGIO';
        }
    }

    $plus = ($monoPlus && $kind === 'MONO') ? ' PLUS' : '';
    $combo = implode('+', $parts);

    return "FALCON {$kind}{$plus} PROMO 1+1 {$combo} BTU";
}

function cleanProductLabel(string $label): string
{
    $label = preg_replace('/\s+10%\s+INCLUSA.*$/iu', '', $label) ?? $label;

    return trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
}

function normalizeBtuSingle(string $raw): string
{
    return str_replace('.', '', preg_replace('/[^\d]/', '', $raw) ?? '');
}

function normalizeBtuCombo(string $raw): string
{
    $parts = preg_split('/\+/', $raw) ?: [];
    $out = [];

    foreach ($parts as $p) {
        $n = normalizeBtuSingle(trim($p));

        if ($n !== '') {
            $out[] = $n;
        }
    }

    return implode('+', $out);
}

function normalizeCodice(string $code): string
{
    $code = trim(str_replace(',', '.', $code));

    if (preg_match('/^00\.(\d)\.(\d{2}\.\d{2}\.\d)$/', $code, $m)) {
        return '00.0' . $m[1] . '.' . $m[2];
    }

    if (preg_match('/^00\.(\d)\.(\d{3}\.\d)$/', $code, $m)) {
        return '00.0' . $m[1] . '.' . $m[2];
    }

    if (preg_match('/^00\.(\d)\.(\d{2}\.\d{2}\.\d)$/', $code, $m)) {
        return '00.0' . $m[1] . '.' . $m[2];
    }

    return $code;
}

function parseEuro(string $s): float
{
    $s = str_replace(['€', ' '], '', $s);
    $s = str_replace('.', '', $s);

    return round((float) str_replace(',', '.', $s), 2);
}

function normalizeLine(string $line): string
{
    return trim(preg_replace('/\x0c/u', '', $line) ?? '');
}

/** @return array<string, string> */
function makeRow(
    string $brand,
    string $categoria,
    string $denominazione,
    string $codice,
    ?float $prezzoListino,
    ?float $prezzoCodice,
    string $tipo,
    string $note
): array {
    return [
        'brand' => $brand,
        'categoria' => $categoria,
        'denominazione' => $denominazione,
        'codice_listino' => $codice,
        'prezzo_listino' => $prezzoListino !== null ? (string) (int) round($prezzoListino) : '',
        'prezzo_codice' => $prezzoCodice !== null ? (string) (int) round($prezzoCodice) : '',
        'tipo' => $tipo,
        'note' => $note,
    ];
}

/** @param list<array<string, string>> $rows */
function dedupeRows(array $rows): array
{
    $seen = [];
    $out = [];

    foreach ($rows as $row) {
        $key = implode('|', [$row['categoria'], $row['denominazione'], $row['codice_listino'], $row['prezzo_listino']]);

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $row;
        }
    }

    return $out;
}

/** @param list<array<string, string>> $rows */
function countByCategory(array $rows): array
{
    $c = [];

    foreach ($rows as $row) {
        $c[$row['categoria']] = ($c[$row['categoria']] ?? 0) + 1;
    }

    ksort($c);

    return $c;
}

/** @param list<array<string, string>> $rows */
function writeCsv(string $path, array $rows): void
{
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fh = fopen($path, 'wb');

    if ($fh === false) {
        throw new RuntimeException("write failed");
    }

    fputcsv($fh, ['brand', 'categoria', 'denominazione', 'codice_listino', 'prezzo_listino', 'prezzo_codice', 'tipo', 'note'], ';');

    foreach ($rows as $row) {
        fputcsv($fh, [
            $row['brand'],
            $row['categoria'],
            $row['denominazione'],
            $row['codice_listino'],
            $row['prezzo_listino'],
            $row['prezzo_codice'],
            $row['tipo'],
            $row['note'],
        ], ';');
    }

    fclose($fh);
}
