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
│ 5. Commit Git / export-delta (opz.) │
└─────────────────────────────────────┘
```

## Checklist rapida (spuntare mentalmente)

- [ ] Ho letto le tre regole in `REGOLE-PRODUZIONE/README.md`
- [ ] Esiste `tools/` sul server (`bootstrap-server-tools.sh` se manca)
- [ ] Backup fatto e percorso annotato (timestamp)
- [ ] Un solo passo eseguito
- [ ] Screenshot o output terminale verificato
- [ ] Solo ora passo al passo successivo
