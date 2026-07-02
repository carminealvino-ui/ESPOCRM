#!/usr/bin/env bash
# Deploy fix contratto: provvigioni totali, numero contratto, finanziamento.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-contratto-provvigioni-finanziamento-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Backup ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/contratto-fix-${TS}"
mkdir -p "${BK}"

for f in \
  custom/Espo/Custom/Hooks/Quote/BeforeSave.php \
  custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php \
  custom/Espo/Custom/Hooks/Quote/NormalizeDefaults.php \
  custom/Espo/Custom/Hooks/Quote/SyncTotaleProvvigioni.php \
  custom/Espo/Custom/Hooks/Quote/ComputeImportoSaldo.php \
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php \
  custom/Espo/Custom/Hooks/Provvigione/UpdateQuoteTotaleProvvigioni.php \
  custom/Espo/Custom/Services/QuoteTotaleProvvigioniService.php \
  custom/Espo/Custom/Resources/layouts/Quote/detail.json \
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json \
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json \
  custom/Espo/Custom/Resources/metadata/formula/Quote.json \
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
do
  cp -a "${f}" "${BK}/" 2>/dev/null || true
done
echo "Backup: ${BK}/"

fetch() {
  local rel="$1"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${rel}"
  echo "OK ${rel}"
}

fetch custom/Espo/Custom/Hooks/Quote/BeforeSave.php
fetch custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
fetch custom/Espo/Custom/Hooks/Quote/NormalizeDefaults.php
fetch custom/Espo/Custom/Hooks/Quote/SyncTotaleProvvigioni.php
fetch custom/Espo/Custom/Hooks/Quote/ComputeImportoSaldo.php
fetch custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
fetch custom/Espo/Custom/Hooks/Provvigione/UpdateQuoteTotaleProvvigioni.php
fetch custom/Espo/Custom/Services/QuoteTotaleProvvigioniService.php
fetch custom/Espo/Custom/Resources/layouts/Quote/detail.json
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
fetch custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
fetch custom/Espo/Custom/Resources/metadata/formula/Quote.json
fetch custom/Espo/Custom/Resources/i18n/it_IT/Quote.json

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "=== Allinea totaleProvvigioni su contratti esistenti ==="
php -r "
require 'bootstrap.php';
\$app = new \Espo\Core\Application();
\$app->setupSystemUser();
\$em = \$app->getContainer()->get('entityManager');
\$service = new \Espo\Custom\Services\QuoteTotaleProvvigioniService(\$em);
\$pdo = \$em->getPDO();
\$ids = \$pdo->query(\"SELECT DISTINCT contratto_id FROM provvigione WHERE deleted = 0 AND contratto_id IS NOT NULL\")->fetchAll(PDO::FETCH_COLUMN);
\$n = 0;
foreach (\$ids as \$id) {
    \$service->syncForQuoteId(\$id);
    \$n++;
}
echo 'Contratti con provvigioni allineati: ' . \$n . PHP_EOL;
"

echo ""
echo "=== Allinea stato Presentato (numero contratto compilato) ==="
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
echo "Deploy completato. Ctrl+Shift+R nel browser."
echo "Rollback: ripristinare file da ${BK}/ && php command.php rebuild"
