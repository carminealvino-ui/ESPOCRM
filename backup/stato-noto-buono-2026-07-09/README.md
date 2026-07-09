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

Oppure **senza git** (server produzione):

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-restore-da-backup.sh?t=$(date +%s)" | bash
```

Con clone Git nel repo:

## Backup automatici sul server (creati dai deploy)

- `backup/Appuntamento.php.bak-<timestamp>`
- `backup/restore-snapshot-YYYYMMDD-HHMMSS/` (da `restore-stato-noto-buono.sh`)
- `backup/crm-kpi-dashlet-v2/server-YYYYMMDD-HHMMSS/` (da deploy KPI v2)

Elenco: `bash tools/list-server-backups.sh`

## Non usare per KPI

`deploy-crm-kpi-v2-hotfix.sh` **da solo** — reintroduce `Appuntamento.php` con `getContainer()`.
