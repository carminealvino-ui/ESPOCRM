# LEGGI PRIMA — script `tools/` e produzione

**Obbligatorio** prima di creare o eseguire script in questa cartella:

→ **[`../REGOLE-PRODUZIONE/README.md`](../REGOLE-PRODUZIONE/README.md)**

In sintesi:

1. **Un’istruzione alla volta** — passo successivo solo dopo screenshot di successo.
2. **Backup del fix (passo 0 obbligatorio)** — `backup_dev/` prima di ogni modifica:
   - `bash tools/backup-dev-batch.sh FIX --manifest tools/backup-manifests/....files`
   - Vedi `REGOLE-PRODUZIONE/00-PASSO-ZERO-BACKUP-OBBLIGATORIO.md`
3. **Istruzioni complete** — dove, backup, comando, verifica, rollback (`REGOLE-PRODUZIONE/03-ISTRUZIONI-COMPLETE.md`).

## Primo avvio sul server (cartella `tools/` assente)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

Poi rispettare le regole sopra per ogni deploy.
