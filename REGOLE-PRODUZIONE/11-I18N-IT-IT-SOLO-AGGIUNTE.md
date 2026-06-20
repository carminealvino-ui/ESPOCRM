# Regola 11 — i18n it_IT: solo aggiunte, mai modifiche

**Cartella:** `custom/Espo/Custom/Resources/i18n/it_IT/`

---

## Regola

- **Non modificare** etichette, `scopeNames`, `scopeNamesPlural`, campi o chiavi **già presenti** nei file it_IT (né in repo né in produzione via deploy).
- **Consentito solo aggiungere** chiavi nuove per funzionalità introdotte (es. dashlet `CrmKpi`, filtro primario nuovo, campo custom nuovo).
- **Non usare** `Global.json` custom per sovrascrivere nomi modulo esistenti (es. Quote → Contratti): quelle etichette sono gestite altrove (traduzioni Espo core, configurazione Admin, o sync storico da produzione).

---

## Esempio KPI (2026-06)

| Dove | Testo | OK? |
|------|-------|-----|
| Menu Business → Quote | **Preventivi** (default Espo) | Non toccare via it_IT custom |
| Dashlet KPI tile | **Contratti firmati** (template `crm-kpi.tpl`) | OK — testo nel template, non i18n scope |
| `Global.json` | solo `CrmKpi` in `dashlets` / `labels` | OK — chiavi **nuove** |

---

## Se serve rinominare un modulo in menu

1. Verificare in **Amministrazione → Traduzioni** (o configurazione tab/navbar già in uso in produzione prima di un restore).
2. **Non** patchare `it_IT/*.json` esistenti nel repo senza export delta da produzione e approvazione esplicita.
3. Dopo restore Softaculous: ripristinare traduzioni da backup produzione, non reinventare in repo.

---

*Aggiunta: 2026-06-20 — da feedback utente su Preventivi vs Contratti.*
