# Cose da fare — pendenti

Elenco operazioni **non urgenti** da eseguire quando un lavoro lungo è terminato.

---

## ⏳ IN CORSO — Inserimento contratti storici (~100)

**Stato:** inserimento manuale contratti vecchi nel CRM (aggiornamento DB).

**Già fatto (non ripetere se l’output era OK):**

- Deploy fix prezzi articoli contratto — marker **`quote-item-catalog-prices-php-20260607g`**
- Comando usato:

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/productprice-dual-iva-listino-codice-9999/tools/applica-quote-item-catalog-prices.php?t=$(date +%s)" -o /tmp/applica-quote-item-catalog-prices.php
php /tmp/applica-quote-item-catalog-prices.php
```

**Mentre inserisci i contratti:** apri contratto → Listino ARIEL Energia → Data Contratto → prodotto → **Salva**. Non serve altro per ogni singolo record.

---

## ☑ DA FARE QUANDO HAI FINITO TUTTI I CONTRATTI

Eseguire **una sola volta**, dopo l’ultimo contratto inserito.

### 1) Prova a secco (consigliato)

```bash
cd ~/public_html/crm/mec-group
php tools/backfill-quote-itemlist-catalog-prices.php --dry-run --verbose
```

Controlla quanti contratti verrebbero aggiornati.

### 2) Backfill massivo (operazione reale)

```bash
cd ~/public_html/crm/mec-group
php tools/backfill-quote-itemlist-catalog-prices.php --verbose
```

**Cosa fa:** su ogni contratto con righe articolo, compila **Prezzo di Listino** e **Prezzo Codice** dalle righe **ProductPrice** del listino, usando la **Data Contratto** (`dateQuoted`) per la validità del listino.

### 3) Un solo contratto (se serve correggere solo uno)

```bash
cd ~/public_html/crm/mec-group
php tools/backfill-quote-itemlist-catalog-prices.php --quote-id=ID_CONTRATTO --verbose
```

(`ID_CONTRATTO` = id EspoCRM del contratto, visibile nell’URL in modifica/dettaglio)

### 4) Verifica

- Apri 2–3 contratti a campione → tab **Articoli** → controlla **Prezzo di Listino** e **Prezzo Codice**
- **Ctrl+F5** nel browser se i valori non compaiono subito

---

## Note

| Domanda | Risposta |
|---------|----------|
| Devo aspettare i 100 contratti per il deploy? | **No** — deploy già fatto; continua a inserire |
| Devo lanciare il backfill dopo ogni contratto? | **No** — una volta sola alla fine |
| Cosa succede se lo dimentico? | I contratti salvati senza prezzi in riga restano incompleti finché non lanci il backfill |
| Listino prodotto con date 01–30 apr e contratto 26 apr | OK — il backfill usa la Data Contratto |

---

*Creato: 2026-06-07 — aggiornare questa sezione spuntando «FATTO» quando il backfill è eseguito.*
