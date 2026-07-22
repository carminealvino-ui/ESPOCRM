#!/usr/bin/env bash
# ⛔ DEPRECATO — NON ESEGUIRE. Vedi tools/deprecated/README.md e tools/DEPLOY-VIETATI.md
echo "ERRORE: script deprecato (2026-07-22). Usare deploy mirati." >&2
exit 1

# RECUPERO PRODUZIONE — un solo deploy con tutti i fix recenti.
#
# Cosa include:
#  - Fix Hooks\Base (BeforeSaveLegacy) → salva, job, promemoria
#  - Promemoria: provider Appuntamento + sync Reminder (NO nuove Call)
#  - Filtro chiamateScadute su Call
#  - Taxi + bonus 2% (schema + seed)
#  - Durata calendario 1h30 allineata a Date End
#  - UI stati/sottostato/esito filtrati (form calendario + popup esito)
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/recovery-produzione-9999/tools/deploy-recovery-produzione-9999.sh?t=$(date +%s)" \
#     -o tools/deploy-recovery-produzione-9999.sh
#   bash tools/deploy-recovery-produzione-9999.sh
#
# Poi:
#   php tools/sync-promemoria-esistenti.php --apply
#   Ctrl+Shift+R nel browser

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/recovery-produzione-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  # Fix critico Espo 10
  "custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
  # Promemoria popup
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "tools/sync-promemoria-esistenti.php"
  # Filtro Call KPI
  "custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/ChiamateScadute.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Call.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Call.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
  # Taxi
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/defaultSidePanel.json"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Hooks/Appuntamento/RecalculateProvvigioniOnTaxiChange.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/i18n/en_US/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/en_US/Provvigione.json"
  "tools/run-appuntamento-taxi-schema-patch.php"
  "tools/seed-regole-provvigioni-ariel.php"
  # Durata calendario 1h30
  "client/custom/src/helpers/appuntamento-duration.js"
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/calendar/modals/edit.js"
  "client/custom/src/views/fields/appuntamento-duration.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/client/custom/src/helpers/appuntamento-duration.js"
  "custom/Espo/Custom/client/custom/src/views/calendar/calendar.js"
  "custom/Espo/Custom/client/custom/src/views/calendar/modals/edit.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-duration.js"
  "custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/Resources/client/custom/src/helpers/appuntamento-duration.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/calendar/calendar.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/calendar/modals/edit.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-duration.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  # UI stati/sottostato/esito filtrati (form calendario + popup)
  "custom/Espo/Custom/Services/AppuntamentoStatiRules.php"
  "custom/Espo/Custom/Hooks/Appuntamento/SyncStatiEsito.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "client/custom/src/helpers/appuntamento-sottostato-map.js"
  "client/custom/src/views/fields/appuntamento-sottostato.js"
  "client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "client/custom/src/views/fields/appuntamento-esito.js"
  "custom/Espo/Custom/client/custom/src/helpers/appuntamento-sottostato-map.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-sottostato.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-esito.js"
  "custom/Espo/Custom/Resources/client/custom/src/helpers/appuntamento-sottostato-map.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-sottostato.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-esito.js"
)

echo "=============================================="
echo " RECUPERO PRODUZIONE"
echo " Branch: ${BRANCH}"
echo " CRM:    ${CRM_ROOT}"
echo "=============================================="
echo ""

cd "${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifiche ==="
grep -q 'implements BeforeSave' \
  "${CRM_ROOT}/custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php" || {
  echo "ERRORE: BeforeSaveLegacy non aggiornato" >&2; exit 1; }
grep -q 'findPastPlannedAppuntamentoItems' \
  "${CRM_ROOT}/custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php" || {
  echo "ERRORE: PopupNotificationsProvider non aggiornato" >&2; exit 1; }
grep -q 'ChiamateScadute' \
  "${CRM_ROOT}/custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/ChiamateScadute.php" || {
  echo "ERRORE: filtro chiamateScadute mancante" >&2; exit 1; }
grep -q '"taxi"' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: campo taxi mancante" >&2; exit 1; }
grep -q 'createEvent' \
  "${CRM_ROOT}/client/custom/src/views/calendar/calendar.js" || {
  echo "ERRORE: calendar.js senza fix durata" >&2; exit 1; }
grep -q 'SyncStatiEsito' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json" || {
  echo "ERRORE: hook SyncStatiEsito mancante" >&2; exit 1; }
grep -q 'appuntamento-esito' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: entityDefs esito senza view custom" >&2; exit 1; }
grep -q 'getAllowedEsiti' \
  "${CRM_ROOT}/client/custom/src/helpers/appuntamento-sottostato-map.js" || {
  echo "ERRORE: appuntamento-sottostato-map.js incompleto" >&2; exit 1; }
echo "OK tutte le verifiche"

echo ""
echo "=== Schema Taxi (se non già fatto) ==="
php tools/run-appuntamento-taxi-schema-patch.php || true

echo ""
echo "=== Seed bonusTaxi2 ==="
php tools/seed-regole-provvigioni-ariel.php || true

echo ""
if [[ -f clear_cache.php ]]; then
  php clear_cache.php || true
fi
if [[ -f rebuild.php ]]; then
  php rebuild.php || true
elif [[ -f command.php ]]; then
  php command.php clearCache || true
fi

echo ""
echo "=============================================="
echo " DEPLOY FILE COMPLETATO"
echo ""
echo " PASSO 2 — sync promemoria (NON crea Call):"
echo "   php tools/sync-promemoria-esistenti.php"
echo "   php tools/sync-promemoria-esistenti.php --apply"
echo ""
echo " PASSO 3 — Ctrl+Shift+R nel browser"
echo ""
echo " NOTA: le Call create per errore dallo script vecchio"
echo "       vanno eliminate manualmente se non servono."
echo "       Lo script sync NON ne crea di nuove."
echo "=============================================="
