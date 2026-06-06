#!/usr/bin/env bash
# Layout ProductPrice: date validità + ordine campi (modale e modulo completo).
# Autocontenuto: scrive i JSON in locale (no dipendenza cache GitHub raw).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LAYOUT_DIR="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/ProductPrice"
I18N="${CRM_ROOT}/custom/Espo/Custom/Resources/i18n/it_IT/ProductPrice.json"
MARKER="layout-productprice-date-validita-20260606"

cd "${CRM_ROOT}" || exit 1

echo "=== Backup layout ProductPrice ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="${CRM_ROOT}/custom/backup-layouts/productprice-layout-${TS}"
mkdir -p "${BK}"
cp -a "${LAYOUT_DIR}/detail.json" "${BK}/" 2>/dev/null || true
cp -a "${LAYOUT_DIR}/detailSmall.json" "${BK}/" 2>/dev/null || true
echo "Backup: ${BK}/"

mkdir -p "${LAYOUT_DIR}" "$(dirname "${I18N}")"

echo "=== Scrivo detailSmall.json (${MARKER}) ==="
cat > "${LAYOUT_DIR}/detailSmall.json" << 'EOFJSON'
[
    {
        "rows": [
            [
                {
                    "name": "product"
                }
            ],
            [
                {
                    "name": "priceBook"
                }
            ],
            [
                {
                    "name": "aliquotaIva"
                },
                {
                    "name": "status"
                }
            ],
            [
                {
                    "name": "dateStart"
                },
                {
                    "name": "dateEnd"
                }
            ],
            [
                {
                    "name": "prezzoListinoIvaEsclusa"
                },
                {
                    "name": "prezzoListinoIvaInclusa"
                }
            ],
            [
                {
                    "name": "prezzoCodice"
                },
                {
                    "name": "prezzoCodiceIvaInclusa"
                }
            ]
        ]
    }
]
EOFJSON

echo "=== Scrivo detail.json (${MARKER}) ==="
cat > "${LAYOUT_DIR}/detail.json" << 'EOFJSON'
[
    {
        "rows": [
            [
                {
                    "name": "product"
                },
                {
                    "name": "priceBook"
                }
            ],
            [
                {
                    "name": "aliquotaIva"
                },
                {
                    "name": "status"
                }
            ],
            [
                {
                    "name": "dateStart"
                },
                {
                    "name": "dateEnd"
                }
            ],
            [
                {
                    "name": "prezzoListinoIvaEsclusa"
                },
                {
                    "name": "prezzoListinoIvaInclusa"
                }
            ],
            [
                {
                    "name": "prezzoCodice"
                },
                {
                    "name": "prezzoCodiceIvaInclusa"
                }
            ],
            [
                {
                    "name": "taxCodeListino"
                },
                false
            ],
            [
                {
                    "name": "description"
                }
            ]
        ]
    }
]
EOFJSON

# Etichette date (merge minimo su i18n esistente)
if [[ -f "${I18N}" ]]; then
  php -r "
    \$f = '${I18N}';
    \$j = json_decode(file_get_contents(\$f), true);
    if (!is_array(\$j)) { exit(1); }
    \$j['fields']['dateStart'] = 'Data inizio validità';
    \$j['fields']['dateEnd'] = 'Data fine validità';
    file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    echo 'OK i18n dateStart/dateEnd' . PHP_EOL;
  "
else
  echo "ATTENZIONE: manca ${I18N}, salta i18n"
fi

echo "=== Verifica ordine layout (aliquotaIva prima di dateStart) ==="
php -r "
  foreach (['detailSmall.json', 'detail.json'] as \$file) {
    \$path = '${LAYOUT_DIR}/' . \$file;
    \$j = json_decode(file_get_contents(\$path), true);
    \$names = [];
    foreach (\$j[0]['rows'] as \$row) {
      foreach (\$row as \$cell) {
        if (is_array(\$cell) && isset(\$cell['name'])) {
          \$names[] = \$cell['name'];
        }
      }
    }
    \$posAliquota = array_search('aliquotaIva', \$names, true);
    \$posDate = array_search('dateStart', \$names, true);
    \$posPrezzo = array_search('prezzoListinoIvaEsclusa', \$names, true);
    if (\$posAliquota === false || \$posDate === false || \$posPrezzo === false) {
      fwrite(STDERR, \"ERRORE ordine in \$file\\n\");
      exit(1);
    }
    if (!(\$posAliquota < \$posDate && \$posDate < \$posPrezzo)) {
      fwrite(STDERR, \"ERRORE: ordine atteso aliquota < date < prezzi in \$file\\n\");
      exit(1);
    }
    echo \"OK ordine \$file: \" . implode(' > ', array_slice(\$names, \$posAliquota, 3)) . PHP_EOL;
  }
"

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "Layout ProductPrice aggiornati (${MARKER})."
echo "Modale rapida + Modulo completo: Ctrl+F5 (o logout/login se persiste)."
echo "Se ancora vecchio layout: Admin > Layout Manager > ProductPrice > ripristina da file custom."
echo "Rollback: cp ${BK}/detail*.json ${LAYOUT_DIR}/ && php command.php rebuild"
