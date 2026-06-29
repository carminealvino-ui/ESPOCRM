# LEGGI PRIMA — script `tools/` e produzione

**Obbligatorio** prima di creare o eseguire script in questa cartella:

→ **[`../REGOLE-PRODUZIONE/README.md`](../REGOLE-PRODUZIONE/README.md)**

In sintesi:

0. **Server ↔ repo** — ogni modifica in produzione va esportata e pushata su GitHub (`00-REGOLA-SERVER-REPO.md`).
1. **Un’istruzione alla volta** — passo successivo solo dopo screenshot di successo.
2. **Backup del fix** — `backup_dev/` (vedi `REGOLE-PRODUZIONE/02-BACKUP-FIX-E-ROLLBACK.md`).
3. **Istruzioni complete** — dove, backup, comando, verifica, rollback (`REGOLE-PRODUZIONE/03-ISTRUZIONI-COMPLETE.md`).

## Primo avvio sul server (cartella `tools/` assente)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

Poi rispettare le regole sopra per ogni deploy.
