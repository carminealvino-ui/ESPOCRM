# Backup stato noto-buono — 2026-07-09

Snapshot **congelato nel repository Git**: non viene sovrascritto dai deploy curl da altre branch.

## Contenuto

| File | Cosa fa |
|------|---------|
| `Controllers/Appuntamento.php` | KPI API Espo 10 (**senza** `getContainer`) |
| `Controllers/CrmKpi.php` | Alias endpoint KPI |
| `Services/ProvvigioneManager.php` | Provvigioni su imponibile netto + `refreshQuoteTotaleProvvigioni` |
| `Hooks/Provvigione/AccrualAndAmount.php` | Senza calcolo legacy che sovrascrive importi |
| `Hooks/Quote/SetPresentedWhenNumeroContratto.php` | Stato Bozza → In lavorazione |
| `Hooks/Quote/AfterSaveTotaleProvvigioni.php` | Totale provvigioni su contratto |
| `metadata/app/client.json` | CSS KPI + script init (da branch KPI v2) |
| `client/custom/css/crm-kpi-dashlet.css` | Layout dashlet KPI |

## Restore sul server (copia da repo Git)

Dopo `git pull` o clone nella cartella CRM:

```bash
cd ~/public_html/crm/mec-group
SNAP=backup/stato-noto-buono-2026-07-09
TS=$(date +%Y%m%d-%H%M%S)
mkdir -p backup/pre-restore-${TS}

# snapshot attuale
cp -a custom/Espo/Custom/Controllers/Appuntamento.php backup/pre-restore-${TS}/ 2>/dev/null || true

# ripristino
cp -a ${SNAP}/custom/Espo/Custom/Controllers/Appuntamento.php custom/Espo/Custom/Controllers/
cp -a ${SNAP}/custom/Espo/Custom/Controllers/CrmKpi.php custom/Espo/Custom/Controllers/
cp -a ${SNAP}/custom/Espo/Custom/Services/ProvvigioneManager.php custom/Espo/Custom/Services/
cp -a ${SNAP}/custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php custom/Espo/Custom/Hooks/Provvigione/
cp -a ${SNAP}/custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php custom/Espo/Custom/Hooks/Quote/
cp -a ${SNAP}/custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php custom/Espo/Custom/Hooks/Quote/
cp -a ${SNAP}/custom/Espo/Custom/Resources/metadata/app/client.json custom/Espo/Custom/Resources/metadata/app/
cp -a ${SNAP}/client/custom/css/crm-kpi-dashlet.css client/custom/css/

rm -f custom/Espo/Custom/Hooks/Quote/BeforeSave.php
php clear_cache.php && php rebuild.php
```

Oppure uno script:

```bash
bash tools/restore-da-backup-repo.sh
```

## Backup automatici sul server (creati dai deploy)

- `backup/Appuntamento.php.bak-<timestamp>`
- `backup/restore-snapshot-YYYYMMDD-HHMMSS/` (da `restore-stato-noto-buono.sh`)
- `backup/crm-kpi-dashlet-v2/server-YYYYMMDD-HHMMSS/` (da deploy KPI v2)

Elenco: `bash tools/list-server-backups.sh`

## Non usare per KPI

`deploy-crm-kpi-v2-hotfix.sh` **da solo** — reintroduce `Appuntamento.php` con `getContainer()`.
