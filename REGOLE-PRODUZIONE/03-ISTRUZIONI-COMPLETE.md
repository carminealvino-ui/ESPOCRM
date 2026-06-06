# Regola 3 — Istruzioni complete

Un’istruzione è **completa** solo se contiene tutti i blocchi applicabili sotto.

## Template (copiare per ogni passo)

```markdown
### Passo N — [titolo breve]

**Dove:** `cd ~/public_html/crm/mec-group`

**Prima (backup):**
`[comando backup esatto]`

**Comando:**
```bash
[comando completo]
```

**Verifica attesa:**
- [cosa deve comparire in terminale o in CRM]

**Se fallisce / rollback:**
- [comando o cartella backup da ripristinare]
```

## Esempio compilato

### Passo 1 — Bootstrap script tools

**Dove:** `cd ~/public_html/crm/mec-group`

**Prima (backup):** non serve (solo download script).

**Comando:**

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

**Verifica attesa:**

- Righe `OK tools/backup-quote-layouts.sh` (e altri).
- File presente: `ls -la tools/backup-quote-layouts.sh`

**Se fallisce / rollback:** nessuna modifica al CRM; ripetere curl o copiare script a mano da repo.

---

## Controllo qualità (obbligatorio)

Prima di inviare l’istruzione all’operatore, chiedersi:

1. Il comando è **intero** (niente `...`)?
2. C’è il **backup** se si tocca `custom/` o `client/custom/`?
3. C’è la **verifica** (output o schermata)?
4. C’è **un solo** passo (Regola 1)?
5. C’è un **comando bash unico** copiabile (Regola 9)?
6. A fine intervento è previsto l’**allineamento completo** server → repo (Regola 11)?

Se una risposta è «no» → completare prima di pubblicare.

## Comando «tutto in un colpo» (obbligatorio)

Ogni passo deve chiudersi con un blocco del tipo:

```bash
cd ~/public_html/crm/mec-group && bash tools/esempio-deploy.sh
```

Vedi [`09-COMANDO-BASH-UNICO.md`](09-COMANDO-BASH-UNICO.md).
