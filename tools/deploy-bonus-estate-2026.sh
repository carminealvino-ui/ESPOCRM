#!/usr/bin/env bash
# Seed regola ESTATE 2026 (+ schema gettone) su produzione.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
cd "$CRM_ROOT"

echo "=== Schema patch regola_provvigionale (gettone_importo) ==="
php tools/run-regola-provvigionale-schema-patch.php

echo ""
echo "=== Seed regole Ariel (include bonusEstate2026) ==="
php tools/seed-regole-provvigioni-ariel.php

echo ""
echo "=== Verifica ==="
php -r '
require "bootstrap.php";
$app = new Espo\Core\Application();
$app->setupSystemUser();
$em = $app->getContainer()->get("entityManager");
$r = $em->getEntityById("RegolaProvvigionale", "bonusEstate2026");
if (!$r || $r->get("deleted")) { fwrite(STDERR, "ERR bonusEstate2026 assente\n"); exit(1); }
echo "OK " . $r->get("name") . " gettone=" . $r->get("gettoneImporto") . " attiva=" . ($r->get("attiva") ? "1" : "0") . "\n";
'

echo "Fatto. Ricalcolare i contratti luglio/agosto weekend se già creati."
