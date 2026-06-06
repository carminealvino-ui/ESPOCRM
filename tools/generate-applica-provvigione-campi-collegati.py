#!/usr/bin/env python3
"""Rigenera tools/applica-provvigione-campi-collegati.php con i file sorgente correnti."""

from __future__ import annotations

import base64
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'tools' / 'applica-provvigione-campi-collegati.php'
MARKER = 'provvigione-campi-collegati-php-20260607a'

FILES = [
    'custom/Espo/Custom/Hooks/Provvigione/BeforeSave.php',
    'custom/Espo/Custom/Resources/metadata/formula/Provvigione.json',
    'tools/backfill-provvigione-campi-collegati.php',
]


def main() -> None:
    payload: dict[str, str] = {}

    for rel in FILES:
        path = ROOT / rel
        if not path.is_file():
            raise SystemExit(f'Missing file: {rel}')
        payload[rel] = base64.b64encode(path.read_bytes()).decode('ascii')

    json_blob = json.dumps(payload, separators=(',', ':'))

    php = f"""<?php
/** Deploy fix Provvigione Cliente/Contratto/nome. Marker: {MARKER} */
declare(strict_types=1);
$marker = '{MARKER}';
$root = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');
if (!is_dir($root)) {{ fwrite(STDERR, "ERRORE: cartella CRM non trovata: {{$root}}\\n"); exit(1); }}
chdir($root);
$files = json_decode(<<<'JSON'
{json_blob}
JSON, true, 512, JSON_THROW_ON_ERROR);
$ts = date('Ymd-His');
$bk = "custom/backup-layouts/provvigione-campi-collegati-{{$ts}}";
@mkdir($bk, 0755, true);
echo "=== Backup ({{$marker}}) ===\\n";
foreach (array_keys($files) as $rel) {{ $src = $root . '/' . $rel; if (is_file($src)) @copy($src, $bk . '/' . basename(str_replace('/', '-', $rel))); }}
echo "Backup: {{$bk}}/\\n\\n=== Scrivo file ({{$marker}}) ===\\n";
foreach ($files as $rel => $b64) {{
  $dest = $root . '/' . $rel; $dir = dirname($dest);
  if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {{ fwrite(STDERR, "ERRORE mkdir {{$dir}}\\n"); exit(1); }}
  $bytes = file_put_contents($dest, base64_decode($b64, true));
  if ($bytes === false) {{ fwrite(STDERR, "ERRORE write {{$rel}}\\n"); exit(1); }}
  echo "OK {{$rel}} ({{$bytes}} bytes)\\n";
}}
echo "\\n=== Verifica PHP ===\\n";
passthru('php -l custom/Espo/Custom/Hooks/Provvigione/BeforeSave.php', $c1);
passthru('php -l tools/backfill-provvigione-campi-collegati.php', $c2);
if ($c1 !== 0 || $c2 !== 0) exit(1);
echo "\\n=== Rebuild EspoCRM ===\\n";
passthru('php command.php rebuild', $c3);
passthru('php command.php clear-cache 2>/dev/null || true');
@array_map('unlink', glob('data/cache/*') ?: []);
echo "\\nDeploy completato ({{$marker}}).\\n";
echo "Backfill provvigioni esistenti: php tools/backfill-provvigione-campi-collegati.php --verbose\\n";
echo "Singolo contratto: php tools/backfill-provvigione-campi-collegati.php --quote-id=ID --verbose\\n";
"""

    OUT.write_text(php, encoding='utf-8')
    print(f'Wrote {OUT} ({OUT.stat().st_size} bytes) marker={MARKER}')


if __name__ == '__main__':
    main()
