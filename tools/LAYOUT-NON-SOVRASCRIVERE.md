# Deploy che NON devono sovrascrivere layout / entità

Usare questa lista per **vietare** all’agent (e agli script) di includere file UI/metadata non richiesti.

---

## File ad alto rischio (vietati in hotfix senza conferma)

```
custom/Espo/Custom/Resources/layouts/**
custom/Espo/Custom/Resources/metadata/entityDefs/**
custom/Espo/Custom/Resources/metadata/logicDefs/**
custom/Espo/Custom/Resources/metadata/clientDefs/**
custom/Espo/Custom/Resources/i18n/**
```

Un deploy che contiene questi path **sovrascrive** Layout Manager, campi entità ed etichette testate in produzione.

---

## Script deploy attualmente “sicuri” (whitelist stretta)

| Script | File toccati |
|--------|----------------|
| `deploy-fix-create-contratto-divzero.sh` | `CreateContratto.php`, `formula/Quote.json` |
| `deploy-fix-appuntamento-prospect-prefill.sh` | JS appuntamento + `clientDefs/Appuntamento.json` + layout appuntamento |
| `deploy-fix-quote-layout-ripristino.sh` | layout Quote + `clientDefs/Quote` + hook (⚠ solo se approvato) |

Prima di eseguire **qualsiasi** script: aprire il file e controllare l’array `FILES=(...)`.

---

## Deploy da NON eseguire “per allineare”

- Deploy intero branch `cursor/opportunity-globallogic-9999`
- Deploy intero `deploy-quote-stati-condizionale.sh` **senza** aver prima fatto export-delta da prod
- `curl | bash` su script che non conosci

---

## Dopo un hotfix mirato

1. Verifica CRM (screenshot)
2. **export-delta** da produzione → push su `main`
3. Così il repo non ripresenta il problema al prossimo deploy

Vedi: [`REGOLE-PRODUZIONE/09-PRODUZIONE-FONTE-DI-VERITA.md`](../REGOLE-PRODUZIONE/09-PRODUZIONE-FONTE-DI-VERITA.md)
