#!/usr/bin/env python3
"""Rigenera tools/applica-provvigione-campi-collegati.php con i file sorgente correnti."""

from __future__ import annotations

import base64
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'tools' / 'applica-provvigione-campi-collegati.php'
MARKER = 'provvigione-campi-collegati-php-20260607d'

FILES = [
    'custom/Espo/Custom/Hooks/Provvigione/BeforeSave.php',
    'custom/Espo/Custom/Services/QuoteProvvigioniSync.php',
    'custom/Espo/Custom/Resources/metadata/formula/Provvigione.json',
    'tools/backfill-provvigione-campi-collegati.php',
]

REMOVE_ON_DEPLOY = [
    'custom/Espo/Custom/Hooks/Provvigione/SyncQuoteTotaleProvvigioni.php',
]


def main() -> None:
    payload: dict[str, str] = {}

    for rel in FILES:
        path = ROOT / rel
        if not path.is_file():
            raise SystemExit(f'Missing file: {rel}')
        payload[rel] = base64.b64encode(path.read_bytes()).decode('ascii')

    json_blob = json.dumps(payload, separators=(',', ':'))
    remove_json = json.dumps(REMOVE_ON_DEPLOY, separators=(',', ':'))

    php = f"""<?php
/** Deploy fix Provvigione. Marker: {MARKER} */
declare(strict_types=1);
$marker = '{MARKER}';
$root = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');
if (!is_dir($root)) {{ fwrite(STDERR, "ERRORE: cartella CRM non trovata: {{$root}}\\n"); exit(1); }}
chdir($root);
$files = json_decode(<<<'JSON'
{json_blob}
JSON, true, 512, JSON_THROW_ON_ERROR);
$remove = json_decode('{remove_json}', true, 512, JSON_THROW_ON_ERROR);
$ts = date('Ymd-His');
$bk = "custom/backup-layouts/provvigione-campi-collegati-{{$ts}}";
@mkdir($bk, 0755, true);
echo "=== Backup ({{$marker}}) ===\\n";
foreach (array_keys($files) as $rel) {{ $src = $root . '/' . $rel; if (is_file($src)) @copy($src, $bk . '/' . basename(str_replace('/', '-', $rel))); }}
echo "Backup: {{$bk}}/\\n\\n=== Rimuovo hook obsoleto ===\\n";
foreach ($remove as $rel) {{
  $dest = $root . '/' . $rel;
  if (is_file($dest) && @unlink($dest)) {{ echo "RM {{$rel}}\\n"; }}
}}
echo "\\n=== Scrivo file ({{$marker}}) ===\\n";
foreach ($files as $rel => $b64) {{
  $dest = $root . '/' . $rel; $dir = dirname($dest);
  if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {{ fwrite(STDERR, "ERRORE mkdir {{$dir}}\\n"); exit(1); }}
  $bytes = file_put_contents($dest, base64_decode($b64, true));
  if ($bytes === false) {{ fwrite(STDERR, "ERRORE write {{$rel}}\\n"); exit(1); }}
  echo "OK {{$rel}} ({{$bytes}} bytes)\\n";
}}
echo "\\n=== Verifica PHP ===\\n";
passthru('php -l custom/Espo/Custom/Hooks/Provvigione/BeforeSave.php', $c1);
passthru('php -l custom/Espo/Custom/Services/QuoteProvvigioniSync.php', $c2);
passthru('php -l tools/backfill-provvigione-campi-collegati.php', $c3);
if ($c1 !== 0 || $c2 !== 0 || $c3 !== 0) exit(1);
echo "\\n=== Rebuild EspoCRM ===\\n";
passthru('php command.php rebuild', $c4);
passthru('php command.php clear-cache 2>/dev/null || true');
@array_map('unlink', glob('data/cache/*') ?: []);
echo "\\nDeploy completato ({{$marker}}).\\n";
echo "Backfill: php tools/backfill-provvigione-campi-collegati.php --verbose\\n";
"""

    OUT.write_text(php, encoding='utf-8')
    print(f'Wrote {OUT} ({OUT.stat().st_size} bytes) marker={MARKER}')


if __name__ == '__main__':
    main()
