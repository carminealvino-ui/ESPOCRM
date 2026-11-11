{{#if error}}<div class="text-danger">{{error}}</div>{{else}}
{{#if totals}}
<div style="display:flex;flex-wrap:wrap;gap:8px 28px;padding:6px 0 12px">
    <span>Previsto CRM <strong>{{totals.expected}}</strong></span>
    <span>Fatturato <strong>{{totals.billed}}</strong></span>
    <span>Pagato <strong>{{totals.paid}}</strong></span>
</div>
{{/if}}
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Invito a fatturare</th><th>Data invito</th><th>Competenza</th><th>Stato</th><th>Numero fattura</th><th>Voci del contratto</th><th style="text-align:right">Importo del contratto</th></tr></thead><tbody>
{{#each entries}}<tr><td><a href="{{url}}">{{name}}</a></td><td>{{date}}</td><td>{{month}}</td><td>{{state}}</td><td>{{invoice}}</td><td style="min-width:230px">{{#each items}}<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:5px"><a href="{{url}}">{{#if isCorrection}}Rettifica · {{/if}}{{label}}</a><span style="white-space:nowrap">{{amount}}</span></div>{{/each}}</td><td style="text-align:right;white-space:nowrap">{{amount}}</td></tr>{{else}}<tr><td colspan="7">Nessun invito a fatturare contiene voci di questo contratto.</td></tr>{{/each}}
</tbody></table></div>{{/if}}
