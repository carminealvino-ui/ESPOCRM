# File condivisi — vietato curl dell'intero file

Alcuni file JSON/PHP sono toccati da **molti fix diversi**.  
Scaricarli interi con `curl` da un branch feature **cancella** le modifiche degli altri fix.

---

## File ad alto rischio (Appuntamento)

| File | Proprietà | Regola deploy |
|------|-----------|---------------|
| `metadata/entityDefs/Appuntamento.json` | enum, view, campi | **MAI curl intero** — solo da sync produzione |
| `metadata/hooks/Appuntamento.json` | ordine hook | curl solo da deploy dedicato hook |
| `metadata/clientDefs/Appuntamento.json` | fieldViews | curl solo se deploy UI stati/esito |
| `Hooks/Appuntamento/GlobalLogic.php` | hookVersion | **MAI** in deploy feature — solo sync prod |

---

## Perché è successo (caso stati/esito 2026-07)

1. Branch `appuntamento-stati-esito-9999` → enum puliti ✅
2. Deploy taxi → `curl entityDefs/Appuntamento.json` dal branch taxi → enum legacy ❌
3. Deploy recovery → stesso file, stessa regressione ❌
4. Fix parziale JS → UI ancora rotta ❌

**10+ script** contenevano lo stesso path. L'ultimo deploy vinceva, anche se era il più vecchio.

---

## Cosa fare al posto del curl intero

### Per aggiungere un campo (es. taxi)

1. Sync produzione → repo (export-delta)
2. Modifica `entityDefs` **nel repo** (commit su branch)
3. Deploy sul server:
   - `php tools/run-appuntamento-taxi-schema-patch.php` (solo DB)
   - layout + hook + i18n (file dedicati)
   - **NON** ridistribuire entityDefs intero da branch vecchio

### Per fix UI (es. stati/esito)

Deploy **pacchetto completo** stati/esito (hook + entityDefs + JS) da branch verificato, **solo dopo** backup e **solo** se `verify-appuntamento-baseline.php` fallisce in produzione.

Script autorizzato: `tools/deploy-appuntamento-stati-esito-ui.sh`

---

## Script deploy: regola per autori

Ogni nuovo `deploy-*.sh` **non deve** includere:

```
custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json
```

Eccezione unica: `deploy-appuntamento-stati-esito-ui.sh` (proprietario del dominio enum stati/esito).

Prima del deploy, eseguire:

```bash
bash tools/pre-deploy-check-appuntamento.sh
```

---

## Verifica post-deploy (enum negativi)

```bash
# Devono fallire (valori NON devono esserci nel sottostato):
grep -q 'Fuori Target' entityDefs/Appuntamento.json && echo "REGRESSIONE"
grep -q 'Solo Informazioni' entityDefs/Appuntamento.json && echo "REGRESSIONE"

# Devono passare:
grep -q 'appuntamento-esito' entityDefs/Appuntamento.json && echo "OK esito view"
grep -q 'SyncStatiEsito' hooks/Appuntamento.json && echo "OK hook"
```

---

Vedi anche: [`12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md`](12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md)
