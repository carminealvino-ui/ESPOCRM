# Copia regole sul server produzione

La cartella `REGOLE-PRODUZIONE/` va nella **root CRM**, accanto a `backup_dev/` e `tools/`:

```
~/public_html/crm/mec-group/
├── REGOLE-PRODUZIONE/    ← questa cartella (da repo Git)
├── backup_dev/
├── tools/
├── custom/
└── …
```

## Una tantum (da GitHub branch **main** — non usare branch feature tipo account-subpanel)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-regole-produzione.sh?t=$(date +%s)" | bash
```

Oppure copiare l’intera cartella `REGOLE-PRODUZIONE/` dal repository (SFTP).

## Verifica

```bash
ls -la ~/public_html/crm/mec-group/REGOLE-PRODUZIONE/README.md
```

Da quel momento: ogni intervento segue `REGOLE-PRODUZIONE/00-ORDINE-DI-LAVORO.md`.
