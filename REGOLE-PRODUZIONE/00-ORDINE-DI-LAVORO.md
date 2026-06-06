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
│ 5. Allineamento COMPLETO prod→repo  │
│    (Regola 11 — OBBLIGATORIO)       │
│    export → apply → push → status   │
└─────────────────────────────────────┘
```

## Allineamento completo produzione → repository (Regola 11)

**Non si chiude un intervento senza allineamento completo.**

Sequenza fissa (dettaglio in [`11-ALLINEAMENTO-COMPLETO-SERVER-REPO.md`](11-ALLINEAMENTO-COMPLETO-SERVER-REPO.md) e [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md)):

1. **`export-delta`** sul server (obbligatorio, primo passo operativo, delta **nuovo**).
2. Scarica ZIP sul PC.
3. **`apply-delta`** + `git commit` + `git push` su `main`.
4. **`status --branch=main`** sul server → **`Solo prod` ≈ 0**.

**Prima di un deploy in produzione** da branch/script: ripetere i punti 1–4 così il repo contiene già ciò che c’è sul server.

Non saltare l’export usando delta vecchi.

## Checklist rapida (spuntare mentalmente)

- [ ] Ho letto le tre regole in `REGOLE-PRODUZIONE/README.md`
- [ ] Esiste `tools/` sul server (`bootstrap-server-tools.sh` se manca)
- [ ] Backup fatto e percorso annotato (timestamp)
- [ ] Un solo passo eseguito
- [ ] Screenshot o output terminale verificato
- [ ] Solo ora passo al passo successivo
- [ ] A fine intervento: allineamento completo server → repo (Regola 11)
