# Backup sviluppo — `backup_dev/`

**Regole operative (obbligatorie):** [`../REGOLE-PRODUZIONE/README.md`](../REGOLE-PRODUZIONE/README.md)

Copie **prima di modifiche mirate** (hook, layout, metadata, client).  
Sul server: `~/public_html/crm/mec-group/backup_dev/`

Export ZIP interi di `custom/` → `exports/custom/` o `custom/backup-layouts/`.

---

## 1. Struttura cartelle

```
backup_dev/
├── _scripts/
├── _archives/
├── Appuntamento/
│   ├── hooks/
│   ├── layouts/
│   ├── metadata/entityDefs|logicDefs|clientDefs/
│   └── client/detail|handlers|runtime/
├── Opportunity/
├── Quote/
└── …
```

---

## 2. Nome file: `DATA_FIX_AGGIORNAMENTO_OBIETTIVO`

```
{DATA}_{FIX}_{AGGIORNAMENTO}_{OBIETTIVO}.ext
```

| Parte | Esempio |
|--------|---------|
| DATA | `20260529-143052` |
| FIX | `duplica-appuntamento` |
| AGGIORNAMENTO | `hooks`, `layouts`, `entityDefs` |
| OBIETTIVO | `GlobalLogic`, `detail` |

Esempio:  
`Appuntamento/hooks/20260529-143052_duplica-appuntamento_hooks_GlobalLogic.php`

---

## 3. Salvataggio

```bash
bash tools/backup-dev-save.sh Appuntamento duplica-appuntamento hooks GlobalLogic.php
```

---

## 4. Migrazione / ordinamento file in root

Il primo script sposta solo nomi tipo `backup-appuntamento-*`, `backup-opportunity-*`.

I file **`backup-prod-*`**, **`.sql`**, **`.tar.gz`**, `GlobalLogic_OLD_*` richiedono la **seconda passata**:

```bash
cd ~/public_html/crm/mec-group
mv backup/hooks_cleanup backup_dev   # una tantum, se serve
bash backup_dev/_scripts/migra-struttura-server.sh
bash backup_dev/_scripts/organizza-file-legacy-root.sh
```

Dopo la seconda passata la root di `backup_dev` non dovrebbe avere file sparsi (solo cartelle).

Tabella destinazioni: [`DESTINAZIONI-BACKUP.md`](DESTINAZIONI-BACKUP.md)
