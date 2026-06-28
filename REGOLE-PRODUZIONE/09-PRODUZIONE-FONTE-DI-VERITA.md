# Produzione = fonte di verità (regola d’oro)

**Obiettivo:** ciò che funiona e è già testato in produzione **non deve mai essere sovrascritto** da file vecchi nel repository o da deploy “larghe” dell’agent.

---

## Perché succede il “casino”

In EspoCRM ci sono **tre livelli** distinti. Confonderli causa regressioni:

| Livello | File tipici | Cosa significa “eliminato” |
|---------|-------------|---------------------------|
| **Layout** | `layouts/Quote/detail.json` | Campo non visibile nel form |
| **Entità** | `metadata/entityDefs/Quote.json` | Campo non esiste più (né in UI né in API) |
| **Etichette** | `i18n/it_IT/Quote.json` | Testo mostrato all’utente |

**Problema ricorrente:** in produzione avevi eliminato campi **anche dall’entità** (via Layout Manager / Entity Manager). Nel repo Git restavano versioni **vecchie** con quei campi ancora definiti. Un deploy **repo → produzione** (curl da branch agent) ha **ripristinato** layout, entityDefs e i18n obsoleti.

Non è Espo che “si rompe da solo”: è **direzione di sync sbagliata** o **branch non allineato alla produzione**.

---

## Regola 1 — Direzione del flusso

```
Produzione (testata)  ──export-delta──►  GitHub (main)
                              ▲
                              │
                    SOLO hotfix mirati
                    (whitelist file)
```

| Direzione | Quando | Come |
|-----------|--------|------|
| **Prod → GitHub** | Dopo ogni intervento OK in produzione | `export-delta` → apply sul PC → `git push` (vedi `05-SYNC-REPO-DAL-SERVER.md`) |
| **GitHub → Prod** | Solo fix urgente, scope minimo | Script con **lista fissa di file** + backup + verifica |
| **Mai** | “Allineare il server al repo” senza export prima | Sovrascrive produzione con metadata vecchi |

---

## Regola 2 — Cosa l’agent NON deve toccare (default)

Senza richiesta **esplicita** nel task, l’agent **non modifica** e **non deploya**:

- `metadata/entityDefs/**` (campi entità)
- `layouts/**` (layout form)
- `i18n/**` (etichette)
- `metadata/clientDefs/**` (viste, pannelli)
- Deploy “completi” da branch feature (`quote-stati`, `opportunity-globallogic`, ecc.)

**Eccezione:** il task nomina il file esatto (es. “solo `CreateContratto.php`”).

---

## Regola 3 — Un fix = pochi file

Ogni intervento agent deve avere:

1. **Obiettivo unico** (es. “Crea Contratto div/0”, non “stati + layout + formula” insieme)
2. **Whitelist file** nel messaggio di commit e nello script deploy
3. **Nessun** `git checkout branch -- cartella/intera`
4. Verifica post-deploy con comando grep/script dedicato

Esempio corretto (hotfix):

```bash
# Solo 2 file, non l’intero branch
FILES=(
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
)
```

---

## Regola 4 — Prima di ogni deploy repo → prod

Checklist obbligatoria (anche per l’agent):

- [ ] `php tools/sync-custom-prod-repo.php status --branch=main` — screenshot
- [ ] Backup `backup_dev` con manifest (`02-BACKUP-FIX-E-ROLLBACK.md`)
- [ ] Elenco file del deploy ** ⊆ ** file del task (niente sorprese)
- [ ] Se il deploy include `entityDefs` o `layouts` → **fermarsi** e chiedere conferma umana
- [ ] Dopo deploy: verifica + screenshot CRM
- [ ] Se OK in prod → **export-delta** per allineare GitHub (altrimenti il problema si ripresenta)

---

## Regola 5 — Campi eliminati dall’entità

Se in produzione un campo è stato **rimosso dall’entità** (non solo dal layout):

1. Quel campo **non deve più esistere** in `entityDefs` su `main`
2. Finché non si fa **export-delta** da prod, il repo continuerà a contenerlo
3. Qualsiasi deploy da repo **lo ricrea** in produzione (colonne + UI)

**Verifica rapida sul server:**

```bash
grep -E "prezzoListinoIvaEsclusa|minusPlus|totaleProvvigioni" \
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
```

- **Nessun output** = entità pulita in produzione  
- **Match nel repo** dopo export = da rimuovere anche su Git con commit dedicato

---

## Regola 6 — Branch agent vs produzione

| Branch | Uso |
|--------|-----|
| `main` | Deve riflettere produzione testata (via export-delta) |
| `cursor/*-9999` | Sperimentazione / PR; **non** deployare l’intero branch in prod |
| Hotfix prod | Cherry-pick **solo** i file necessari; script deploy con whitelist |

**Non** usare `cursor/opportunity-globallogic-9999` o branch feature come sorgente deploy “generale”: contengono spesso metadata **più vecchi** della produzione reale.

---

## Regola 7 — Costi e responsabilità (piano Pro)

- Ogni regressione (layout, etichette, campi fantasma) genera **turni agent extra** → consumo risorse piano.
- La prevenzione è **processo**, non altri fix al volo:
  - export prod → Git **prima** di far lavorare l’agent su metadata
  - deploy **whitelist** dopo
  - un task per volta (`01-UN-ISTRUZIONE-ALLA-VOLTA.md`)

L’agent deve **preferire** leggere `REGOLE-PRODUZIONE/` e chiedere conferma prima di toccare entity/layout/i18n.

---

## Riepilogo in una frase

> **La produzione comanda.** GitHub si aggiorna da produzione. Il repo deploya in produzione solo file espliciti e già approvati — mai l’intero branch.

Vedi anche: [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md), [`tools/LAYOUT-NON-SOVRASCRIVERE.md`](../tools/LAYOUT-NON-SOVRASCRIVERE.md)
