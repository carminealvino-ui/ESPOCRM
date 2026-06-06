# LEGGI PRIMA — script `tools/` e produzione

**Obbligatorio** prima di creare o eseguire script in questa cartella:

→ **[`../REGOLE-PRODUZIONE/README.md`](../REGOLE-PRODUZIONE/README.md)**

In sintesi:

1. **Un’istruzione alla volta** — passo successivo solo dopo screenshot di successo.
2. **Backup del fix** — `backup_dev/` + `custom/backup-layouts/` (vedi `REGOLE-PRODUZIONE/02-BACKUP-FIX-E-ROLLBACK.md`).
3. **Istruzioni complete** — dove, backup, comando, verifica, rollback (`REGOLE-PRODUZIONE/03-ISTRUZIONI-COMPLETE.md`).
4. **Allineamento completo server ↔ repo** — sempre dopo ogni modifica prod e prima di ogni deploy (`REGOLE-PRODUZIONE/11-ALLINEAMENTO-COMPLETO-SERVER-REPO.md`).

## Primo avvio sul server (cartella `tools/` assente)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

Poi rispettare le regole sopra per ogni deploy.
