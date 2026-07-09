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
| `metadata/app/client.json` | CSS KPI + script init (senza custom-product-button duplicato) |
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

## Layout KPI “come prima” (Rese + griglia ampia)

`deploy-popup-save-fix.sh` è **solo** il popup Contatto Telefonico, **non** il layout KPI.

Per il layout che ricordi (branch `fix-kpi-lordi-pianificati-9999`):

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-kpi-layout-lordi-pianificati.sh?t=$(date +%s)" | bash
```

## Contratti completi (stato / finanziamento / provvigioni)

Il backup sopra **non** include metadata Quote (statoContratto, finanziamento). Per fix completo:

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-contratto-completo.sh?t=$(date +%s)" | bash
```

## Non usare per KPI

`deploy-crm-kpi-v2-hotfix.sh` **da solo** — reintroduce `Appuntamento.php` con `getContainer()`.
