# Backup sviluppo — `backup_dev/`

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

## 4. Migrazione da vecchia cartella `backup/hooks_cleanup`

```bash
cd ~/public_html/crm/mec-group
mv backup/hooks_cleanup backup_dev   # se esiste ancora
bash backup_dev/_scripts/migra-struttura-server.sh
```
