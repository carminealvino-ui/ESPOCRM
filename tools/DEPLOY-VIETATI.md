# Deploy vietati o deprecati

**Leggere prima di eseguire qualsiasi script `deploy-*.sh`.**

Regola madre: [`REGOLE-PRODUZIONE/12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md`](../REGOLE-PRODUZIONE/12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md)

---

## ⛔ VIETATO — non eseguire mai

| Script | Motivo |
|--------|--------|
| `tools/deprecated/deploy-recovery-produzione-9999.sh` | Mega-bundle: sovrascrive entityDefs con enum legacy |
| `tools/deprecated/deploy-appuntamento-emergenza-produzione.sh` | Ripristina entityDefs da `main` obsoleto |
| Qualsiasi `curl ... entityDefs/Appuntamento.json` manuale | Stesso rischio regressioni |

---

## ⚠️ MODIFICATI — non curlano più entityDefs intero

| Script | Cosa deploya | Cosa NON tocca più |
|--------|--------------|-------------------|
| `deploy-appuntamento-taxi-provvigione.sh` | hook, layout, schema patch | entityDefs intero |
| `deploy-fix-appuntamento-durata-calendario.sh` | JS calendar/duration | entityDefs intero |
| `deploy-prospect-appuntamento-form-ui.sh` | JS form | entityDefs intero |
| `deploy-fix-appuntamento-duplica.sh` | hook PreventDuplicate | entityDefs intero |
| `deploy-fix-appuntamento-google-sync.sh` | hook Google | entityDefs intero |

---

## ✅ AUTORIZZATI (deploy mirato, dopo backup + verify baseline)

| Script | Dominio |
|--------|---------|
| `deploy-appuntamento-stati-esito-ui.sh` | enum + hook + JS stati/esito (proprietario entityDefs Appuntamento) |
| `deploy-fix-promemoria-solo-sync.sh` | promemoria |
| `deploy-pending-call-popup-fix.sh` | popup Call |
| `deploy-appuntamento-taxi-provvigione.sh` | taxi (senza entityDefs) |

---

## Prima di ogni deploy

```bash
cd ~/public_html/crm/mec-group

# 1. Repo allineato? (se no → export-delta PRIMA)
php tools/verify-appuntamento-baseline.php

# 2. Backup
bash tools/backup-dev-batch.sh NOME-FIX --manifest tools/backup-manifests/appuntamento-baseline-produzione.files

# 3. Check produzione
bash tools/pre-deploy-check-appuntamento.sh

# 4. Solo ora: deploy mirato
bash tools/deploy-....sh
```

---

## Se hai già eseguito uno script vietato

1. Rollback da `backup_dev/_sessions/`
2. `bash tools/deploy-appuntamento-stati-esito-ui.sh` (ripristino enum)
3. `export-delta` → push su `main`
4. Segnalare quale script ha causato la regressione
