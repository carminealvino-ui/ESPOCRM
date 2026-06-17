# Ordine di lavoro in produzione

Usare questa sequenza per **ogni** intervento (fix, deploy, layout).

```
┌─────────────────────────────────────┐
│ 0. Leggere REGOLE-PRODUZIONE/       │
└─────────────────┬───────────────────┘
                  ▼
┌─────────────────────────────────────┐
│ 0b. BACKUP OBBLIGATORIO backup_dev/ │  ← NON SALTARE (vedi 00-PASSO-ZERO)
│     backup-dev-batch.sh o save.sh   │
└─────────────────┬───────────────────┘
                  ▼
┌─────────────────────────────────────┐
│ 1. Backup layout se UI massiva      │
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
│ 5. Allineamento prod → GitHub       │  ← OBBLIGATORIO (Regola 0)
│    Nessun intervento chiuso senza   │
│    export + push se si è scritto    │
│    qualcosa sul server              │
│    (vedi 00-REGOLA-SERVER-REPO)     │
└─────────────────────────────────────┘
```

## Allineamento produzione → repository

Sequenza fissa (dettaglio in [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md)):

1. **`export-delta`** sul server (obbligatorio, primo passo operativo).
2. Scarica ZIP sul PC.
3. **`apply-delta`** + `git commit` + `git push` sul PC.

Non saltare l’export usando delta vecchi.

## Checklist rapida (spuntare mentalmente)

- [ ] Ho letto **Regola 0** in `00-REGOLA-SERVER-REPO.md` (server ↔ repo)
- [ ] Ho letto le regole in `REGOLE-PRODUZIONE/README.md`
- [ ] Esiste `tools/` sul server (`bootstrap-server-tools.sh` se manca)
- [ ] **Passo 0:** backup `backup_dev/` eseguito (`backup-dev-batch.sh` o `backup-dev-save.sh`)
- [ ] Screenshot/output backup con timestamp salvato
- [ ] Un solo passo eseguito
- [ ] Screenshot o output terminale verificato
- [ ] Solo ora passo al passo successivo
- [ ] **Chiusura:** export-delta (o align layout) + push GitHub se ho modificato il server
