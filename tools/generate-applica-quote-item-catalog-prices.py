#!/usr/bin/env python3
"""Rigenera tools/applica-quote-item-catalog-prices.php con i file sorgente correnti."""

from __future__ import annotations

import base64
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'tools' / 'applica-quote-item-catalog-prices.php'
MARKER = 'quote-item-catalog-prices-php-20260607d'

FILES = [
    'custom/Espo/Custom/Services/QuotePricingCalculator.php',
    'custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php',
    'custom/Espo/Custom/Resources/routes.json',
    'custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json',
    'client/custom/src/handlers/quote/catalog-prices.js',
    'client/custom/src/views/quote/fields/item-list.js',
    'client/custom/src/views/quote/record/item.js',
    'client/custom/src/views/quote/record/panels/items.js',
    'custom/Espo/Custom/Resources/client/custom/src/handlers/quote/catalog-prices.js',
    'custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js',
    'custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js',
    'custom/Espo/Custom/Resources/client/custom/src/views/quote/record/panels/items.js',
    'tools/backfill-productprice-native-price-from-dual-iva.php',
    'tools/backfill-quote-itemlist-catalog-prices.php',
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
/** Deploy + backfill contratti/listini. Marker: {MARKER} */
declare(strict_types=1);
$marker = '{MARKER}';
$root = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');
if (!is_dir($root)) {{ fwrite(STDERR, "ERRORE: cartella CRM non trovata: {{$root}}\\n"); exit(1); }}
chdir($root);
$files = json_decode(<<<'JSON'
{json_blob}
JSON, true, 512, JSON_THROW_ON_ERROR);
$ts = date('Ymd-His');
$bk = "custom/backup-layouts/quote-item-catalog-prices-{{$ts}}";
@mkdir($bk, 0755, true);
echo "=== Backup ({{$marker}}) ===\\n";
foreach (array_keys($files) as $rel) {{ $src = $root . '/' . $rel; if (is_file($src)) @copy($src, $bk . '/' . basename($rel)); }}
echo "Backup: {{$bk}}/\\n\\n=== Scrivo file ({{$marker}}) ===\\n";
foreach ($files as $rel => $b64) {{
  $dest = $root . '/' . $rel; $dir = dirname($dest);
  if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {{ fwrite(STDERR, "ERRORE mkdir {{$dir}}\\n"); exit(1); }}
  $bytes = file_put_contents($dest, base64_decode($b64, true));
  if ($bytes === false) {{ fwrite(STDERR, "ERRORE write {{$rel}}\\n"); exit(1); }}
  echo "OK {{$rel}} ({{$bytes}} bytes)\\n";
}}
echo "\\n=== Verifica PHP ===\\n";
passthru('php -l custom/Espo/Custom/Services/QuotePricingCalculator.php', $c1);
passthru('php -l tools/backfill-quote-itemlist-catalog-prices.php', $c2);
if ($c1 !== 0 || $c2 !== 0) exit(1);
echo "\\n=== Backfill price nativo ProductPrice ===\\n";
passthru('php tools/backfill-productprice-native-price-from-dual-iva.php --verbose', $c3);
echo "\\n=== Rebuild EspoCRM ===\\n";
passthru('php command.php rebuild', $c4);
passthru('php command.php clear-cache 2>/dev/null || true');
@array_map('unlink', glob('data/cache/*') ?: []);
echo "\\nDeploy completato ({{$marker}}).\\n";
echo "Backfill massivo contratti: php tools/backfill-quote-itemlist-catalog-prices.php --verbose\\n";
echo "Singolo contratto: php tools/backfill-quote-itemlist-catalog-prices.php --quote-id=ID --verbose\\n";
"""

    OUT.write_text(php, encoding='utf-8')
    print(f'Wrote {OUT} ({OUT.stat().st_size} bytes) marker={MARKER}')


if __name__ == '__main__':
    main()
