# Regola 0 — Nessun deploy se repo e produzione non sono allineati

**Questa regola ha priorità su tutte le altre.**

---

## Enunciato

> **Non si scrive codice sul server se il repository non riflette ciò che funziona in produzione.**
>
> **Non si fa deploy dal repository se il repository contiene versioni obsolete o rotte.**

Produzione funzionante = fonte di verità.  
Repository = copia versionata della produzione + nuovi fix in attesa di deploy.

---

## Sequenza obbligatoria (sempre, senza eccezioni)

```
┌────────────────────────────────────────┐
│ A. Produzione funziona (verificato)    │
└──────────────────┬─────────────────────┘
                   ▼
┌────────────────────────────────────────┐
│ B. export-delta dal server             │  ← PRIMA di qualsiasi nuovo fix
│    php tools/sync-custom-prod-repo.php │
└──────────────────┬─────────────────────┘
                   ▼
┌────────────────────────────────────────┐
│ C. apply-delta + commit + push su main │
└──────────────────┬─────────────────────┘
                   ▼
┌────────────────────────────────────────┐
│ D. verify baseline repo (CI o locale)  │
│    php tools/verify-appuntamento-      │
│    baseline.php                        │
└──────────────────┬─────────────────────┘
                   ▼
┌────────────────────────────────────────┐
│ E. Solo ORA: nuovo fix su branch       │
└──────────────────┬─────────────────────┘
                   ▼
┌────────────────────────────────────────┐
│ F. Deploy mirato (solo file del fix)   │
└──────────────────┬─────────────────────┘
                   ▼
┌────────────────────────────────────────┐
│ G. Verifica produzione + export-delta  │
│    (torna a B)                         │
└────────────────────────────────────────┘
```

**Se salti B o C → il repo può contenere enum/hook/layout vecchi che un deploy successivo rimette in produzione.**

---

## Passo B — Export dal server (comando esatto)

**Dove:** `cd ~/public_html/crm/mec-group`

```bash
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

**Verifica attesa:**
- `Export delta completato`
- ZIP in `exports/sync/delta-YYYYMMDD-HHMMSS.zip`

Scarica ZIP sul PC → `apply-delta` → `git push origin main`.

Dettaglio completo: [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md)

---

## Passo D — Verifica baseline repo

Prima di **ogni** deploy e dopo **ogni** merge su `main`:

```bash
php tools/verify-appuntamento-baseline.php
```

**Atteso:** `OK baseline Appuntamento`

Se fallisce → **bloccare deploy**. Il repo contiene enum o hook obsoleti.

---

## Cosa definisce "produzione che funziona" (Appuntamento)

Dopo sync, in `entityDefs/Appuntamento.json` devono esserci:

| Controllo | Valore atteso |
|-----------|---------------|
| Sottostato enum | 9 valori: Pending, Gestito, Non Interessato, Chiuso Positivamente, Annullato, Non Gestito, Non Ricevuto, Rifissato |
| **NON** devono esserci | Fuori Target, Solo Informazioni, Infattibilità Tecnica, Prodotto non Conforme nel sottostato |
| Esito view | `custom:views/fields/appuntamento-esito` |
| Sottostato view | `custom:views/fields/appuntamento-sottostato` |
| Hook SyncStatiEsito | presente in `hooks/Appuntamento.json` |
| HookVersion GlobalLogic | ≥ 1.7.17 |

Manifest file da sincronizzare: `tools/backup-manifests/appuntamento-baseline-produzione.files`

---

## Vietato

| Azione | Perché |
|--------|--------|
| `curl ... entityDefs/Appuntamento.json` in deploy feature | Sovrascrive enum funzionanti |
| Deploy da branch non allineato a produzione | Branch vecchio = enum vecchi |
| Mega-deploy "recovery" multi-fix | Un file condiviso annulla l'altro fix |
| Nuovo fix senza export-delta precedente | Repo non riflette produzione |
| `git push` di entityDefs non verificati | Ripubblica regressioni su GitHub |

Vedi: [`13-FILE-CONDIVISI-VIETATO-CURL-INTERO.md`](13-FILE-CONDIVISI-VIETATO-CURL-INTERO.md), [`../tools/DEPLOY-VIETATI.md`](../tools/DEPLOY-VIETATI.md)

---

## Checklist prima di ogni intervento

- [ ] Produzione funziona (screenshot verificato)
- [ ] `export-delta` eseguito e pushato su `main`
- [ ] `verify-appuntamento-baseline.php` → OK
- [ ] Backup `backup_dev/` (passo 0)
- [ ] Deploy usa **solo** file del fix (no entityDefs intero)
- [ ] Dopo deploy: verifica UI + nuovo `export-delta`

---

*Aggiornamento: 2026-07-22 — introdotta dopo regressioni stati/esito da deploy recovery/taxi.*
