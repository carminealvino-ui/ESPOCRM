# Ordine di lavoro in produzione

Usare questa sequenza per **ogni** intervento (fix, deploy, layout).

```
┌─────────────────────────────────────┐
│ 0. Leggere REGOLE-PRODUZIONE/       │
└─────────────────┬───────────────────┘
                  ▼
┌─────────────────────────────────────┐
│ 1. Backup (backup_dev + layout se UI)│
└─────────────────┬───────────────────┘
                  ▼
┌─────────────────────────────────────┐
│ 2. UNA istruzione → esecuzione      │
└─────────────────┬───────────────────┘
                  ▼
┌─────────────────────────────────────┐
│ 3. Screenshot / verifica esito      │
└─────────────────┬───────────────────┘
                  ▼
         OK? ──no──► rollback dal backup
          │
         sì
          ▼
┌─────────────────────────────────────┐
│ 4. Prossima istruzione (torna a 2)  │
└─────────────────┬───────────────────┘
                  ▼
┌─────────────────────────────────────┐
│ 5. Allineamento prod → GitHub       │
│    (vedi 05-SYNC: export SEMPRE     │
│     per primo, poi PC apply+push)   │
└─────────────────────────────────────┘
```

## Allineamento produzione → repository

Sequenza fissa (dettaglio in [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md)):

1. **`export-delta`** sul server (obbligatorio, primo passo operativo).
2. Scarica ZIP sul PC.
3. **`apply-delta`** + `git commit` + `git push` sul PC.

Non saltare l’export usando delta vecchi.

## Checklist rapida (spuntare mentalmente)

- [ ] Ho letto le tre regole in `REGOLE-PRODUZIONE/README.md`
- [ ] Esiste `tools/` sul server (`bootstrap-server-tools.sh` se manca)
- [ ] Backup fatto e percorso annotato (timestamp)
- [ ] Un solo passo eseguito
- [ ] Screenshot o output terminale verificato
- [ ] Solo ora passo al passo successivo
