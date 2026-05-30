# Layout (visualizzazioni) — regole MEC Group

## Regola

I file in `custom/Espo/Custom/Resources/layouts/{Entità}/` **non vanno sovrascritti** da script `tools/deploy-*.sh` né da sync automatico prod→repo.

- Modifiche elenco / scheda / filtri: **commit dedicato** + copia manuale sul server (o Layout Manager in admin, poi export nel repo).
- I deploy tecnici (fix hook, client, metadata) **non includono** `layouts/`.

## Layout Appuntamento

| File | Uso |
|------|-----|
| `list.json` | Elenco principale (colonne essenziali, larghezze % che sommano ~100) |
| `listSmall.json` | Elenco compatto / relazioni |
| `detail.json` | Scheda dettaglio |
| `detailSmall.json` | Scheda ridotta |
| `filters.json` | Filtri |
| `massUpdate.json` | Aggiornamento di massa |

## Dopo modifica layout sul server

```bash
php command.php clearCache
rm -rf data/cache/*
```

Poi Ctrl+F5.

## Elenco “scombinato”

Troppe colonne o `width` troppo piccoli (8, 12 senza %) costringono scroll orizzontale e testo troncato.  
Preferire **8 colonne max** in `list.json` e larghezze in **percentuale** (come `listSmall.json`).
