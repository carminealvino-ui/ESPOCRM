#!/usr/bin/env bash
# Ripristina i promemoria popup (esito Appuntamento + Call) bloccati da Hooks\Base.
#
# Causa: BeforeSaveLegacy.php estendeva Espo\Core\Hooks\Base (rimosso in Espo 10).
# L'errore faceva fallire JobRunner e il caricamento hook → niente sync, niente popup.
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-promemoria-hooks-base-9999/tools/deploy-fix-promemoria-hooks-base.sh?t=$(date +%s)" \
#     -o tools/deploy-fix-promemoria-hooks-base.sh
#   bash tools/deploy-fix-promemoria-hooks-base.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-promemoria-hooks-base-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "tools/aggiorna-appuntamenti-passati-promemoria.php"
)

echo "=== Fix promemoria (Hooks\\Base + popup) → ${CRM_ROOT} ==="
echo "Branch: ${BRANCH}"
echo ""

cd "${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifica BeforeSaveLegacy ==="
if grep -q 'Espo\\Core\\Hooks\\Base' \
  "${CRM_ROOT}/custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"; then
  echo "ERRORE: BeforeSaveLegacy contiene ancora Hooks\\Base" >&2
  exit 1
fi
grep -q 'implements BeforeSave' \
  "${CRM_ROOT}/custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php" || {
  echo "ERRORE: BeforeSaveLegacy non implementa BeforeSave" >&2
  exit 1
}
echo "OK BeforeSaveLegacy compatibile Espo 10"

echo ""
echo "=== Verifica PopupNotificationsProvider ==="
grep -q 'parent::get failed' \
  "${CRM_ROOT}/custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php" || {
  echo "ERRORE: provider senza try/catch su parent::get" >&2
  exit 1
}
echo "OK PopupNotificationsProvider hardened"

echo ""
if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  php clear_cache.php || true
elif [[ -f "${CRM_ROOT}/rebuild.php" ]]; then
  php rebuild.php || true
fi

echo ""
echo "=== Diagnostica rapida Hooks\\Base residui ==="
if grep -R --include='*.php' -n 'Espo\\Core\\Hooks\\Base' \
  custom/Espo/Custom/Hooks 2>/dev/null | grep -v '//' | grep -v '^\s*\*' ; then
  echo "WARN: altri file in Hooks/ citano ancora Hooks\\Base — verificare"
else
  echo "OK nessun use/extends di Hooks\\Base in custom hooks"
fi

echo ""
echo "=== Deploy completato ==="
echo "1) Ricarica il browser (Ctrl+F5)"
echo "2) Aggiorna appuntamenti passati (Call + promemoria):"
echo "     php tools/aggiorna-appuntamenti-passati-promemoria.php           # anteprima"
echo "     php tools/aggiorna-appuntamenti-passati-promemoria.php --apply   # applica"
echo "3) Controlla data/log che non ci siano più errori Hooks\\Base"
