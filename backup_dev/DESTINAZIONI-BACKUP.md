# Dove finiscono i backup in `backup_dev/`

Riferimento per i file **legacy** (nomi `backup-prod-*`, `GlobalLogic_*`, `.sql`, …).

| Contenuto nel nome file | Cartella destinazione |
|-------------------------|------------------------|
| `globallogic` + `OLD` | `_archives/hooks-legacy/` |
| `appuntamento`, `globallogic` 1.6.x | `Appuntamento/hooks/` |
| `Appuntamento.json` | `Appuntamento/metadata/entityDefs/` |
| `opportunity` + `detail` + `.js` | `Opportunity/client/detail/` |
| `opportunity` + `clientdefs` | `Opportunity/metadata/clientDefs/` |
| `opportunity` + `entitydefs` | `Opportunity/metadata/entityDefs/` |
| `opportunity` + `logicdefs` | `Opportunity/metadata/logicDefs/` |
| `opportunity` + `globallogic` 2.x | `Opportunity/hooks/` |
| `create-contratto` + `.js` | `Opportunity/client/handlers/` |
| `create-contratto` / `AutoCreateQuote` + `.php` | `Opportunity/hooks/` |
| `prospect` | `Prospect/metadata/` o `client/` |
| `provigione` | `Provvigione/hooks/` |
| `invito` | `InvitoAFatturare/hooks/` |
| `product-category`, `product-brand`, `category-by-brand` | `Product/client/` |
| `linea`, `migrate-linea` + `.sql` | `_archives/sql/` |
| `app-client`, `custom-client` + `.json` | `client/metadata/` |
| `custom-ui` + `.css` | `client/css/` |
| `.tar.gz` | `_archives/` |
| `Lead.json` | `Lead/metadata/entityDefs/` |
| `LeadLinker.php` | `Lead/hooks/` |
| non classificato | `_flat-legacy/` (da rivedere a mano) |

Script:

```bash
cd ~/public_html/crm/mec-group
bash backup_dev/_scripts/organizza-file-legacy-root.sh --dry-run   # anteprima
bash backup_dev/_scripts/organizza-file-legacy-root.sh             # sposta
find backup_dev -maxdepth 1 -type f   # deve essere vuoto
```

Se sul server lo script è vecchio, scaricarlo da `main` (vedi commento in cima a `organizza-file-legacy-root.sh`).
