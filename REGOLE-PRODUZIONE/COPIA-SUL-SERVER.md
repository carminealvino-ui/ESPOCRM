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

## Una tantum (da PC con repo, o SFTP)

Copiare l’intera cartella `REGOLE-PRODUZIONE/` dal repository in `mec-group/`.

## Verifica

```bash
ls -la ~/public_html/crm/mec-group/REGOLE-PRODUZIONE/README.md
```

Da quel momento: ogni intervento segue `REGOLE-PRODUZIONE/00-ORDINE-DI-LAVORO.md`.
