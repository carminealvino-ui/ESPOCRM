# Script deprecati — NON ESEGUIRE

Questi script causano **regressioni** sovrascrivendo file condivisi con versioni obsolete.

| Script | Problema | Alternativa |
|--------|----------|-------------|
| `deploy-recovery-produzione-9999.sh` | Mega-bundle, entityDefs legacy | Deploy mirati da `tools/DEPLOY-VIETATI.md` |
| `deploy-appuntamento-emergenza-produzione.sh` | entityDefs da `main` obsoleto | `export-delta` + `deploy-appuntamento-stati-esito-ui.sh` |

Vedi: [`../DEPLOY-VIETATI.md`](../DEPLOY-VIETATI.md), [`../../REGOLE-PRODUZIONE/12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md`](../../REGOLE-PRODUZIONE/12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md)

**Deprecati il 2026-07-22** dopo regressioni stati/esito.
