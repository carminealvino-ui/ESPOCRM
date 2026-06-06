#!/usr/bin/env bash
# Contratto: numeroContratto valorizzato → stato Presentato (da Bozza).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Backup hook stato Presentato ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/quote-stato-presentato-${TS}"
mkdir -p "${BK}"
cp -a custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php "${BK}/" 2>/dev/null || true
echo "Backup: ${BK}/"

curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
echo "OK SetPresentedWhenNumeroContratto.php"

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "=== Allinea contratti già con Numero Contratto ma ancora Bozza ==="
php -r "
require 'bootstrap.php';
\$app = new \Espo\Core\Application();
\$app->setupSystemUser();
\$pdo = \$app->getContainer()->get('entityManager')->getPDO();
\$stmt = \$pdo->prepare(\"UPDATE quote SET status = 'Presented' WHERE deleted = 0 AND status = 'Draft' AND numero_contratto IS NOT NULL AND TRIM(numero_contratto) != ''\");
\$stmt->execute();
echo 'Contratti aggiornati a Presentato: ' . \$stmt->rowCount() . PHP_EOL;
"

echo ""
echo "Deploy completato. Modifica/salva un contratto con Numero Contratto → stato Presentato."
echo "Rollback: rm custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php && php command.php rebuild"
