# Cartella `backup/`

| Percorso | Contenuto |
|----------|-----------|
| **`hooks_cleanup/`** | Backup mirati per **entità** — vedi sotto |
| `create-contratto-2026-05-25/` | Storico CreateContratto |
| `provvigioni-2026-05-26/` | PHP provvigioni |
| `pre-upgrade-9.3.7/` | Pre-aggiornamento Espo |

Sul server esiste anche **`custom/backup-layouts/`** (snapshot prima deploy, non in questa cartella).

---

## `hooks_cleanup` — in sintesi

**Cartelle** = entità CRM (`Appuntamento`, `Opportunity`, `Quote`, …)  
**Sottocartelle** = tipo file (`hooks`, `layouts`, `entityDefs`, `client-handlers`, …)  
**Nome file** = `DATA_FIX_AGGIORNAMENTO_OBIETTIVO.ext`

Esempio:

```
Appuntamento/hooks/20260529-143052_duplica-appuntamento_hooks_GlobalLogic.php
```

Guida completa: [`hooks_cleanup/README.md`](hooks_cleanup/README.md)

### Salvataggio rapido

```bash
bash tools/backup-hooks-cleanup-save.sh Appuntamento duplica-appuntamento hooks GlobalLogic.php
```

### Allineare server (file ancora in root piatta)

```bash
bash backup/hooks_cleanup/_scripts/migra-struttura-server.sh
```
