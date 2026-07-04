<div class="invito-provvigioni-modal">
    <div class="invito-provvigioni-toolbar text-muted small margin-bottom">
        {{#if loading}}
            <span class="fas fa-spinner fa-spin"></span> Caricamento provvigioni...
        {{else}}
            Seleziona le provvigioni da includere nell'invito.
            <strong class="text-success pull-right">Totale selezionato: {{totaleSelezionatoFormatted}}</strong>
        {{/if}}
    </div>

    {{#unless loading}}
    {{#if hasGroups}}
        {{#each groups}}
        <div class="panel panel-default invito-provvigioni-group">
            <div class="panel-heading">
                <label class="checkbox-inline no-margin">
                    <input type="checkbox" class="group-checkbox" data-group-index="{{@index}}">
                </label>
                <strong>Tipo Provv: {{tipo}}</strong>
                <span class="text-muted">(Totale Provvigioni = {{totaleProvvInPagFormatted}})</span>
            </div>
            <div class="panel-body no-padding">
                {{#each venditori}}
                <div class="invito-provvigioni-venditore">
                    <div class="invito-provvigioni-venditore-header">
                        <label class="checkbox-inline no-margin">
                            <input type="checkbox" class="venditore-checkbox"
                                   data-group-index="{{@../index}}"
                                   data-venditore-index="{{@index}}">
                        </label>
                        <strong>Venditore: {{venditoreName}}</strong>
                        <span class="text-muted">(Totale Provvigioni = {{totaleProvvInPagFormatted}})</span>
                    </div>
                    <table class="table table-condensed table-bordered table-striped invito-provvigioni-table">
                        <thead>
                            <tr>
                                <th style="width:28px"></th>
                                <th>Data Vendita</th>
                                <th>Cliente</th>
                                <th>Stato</th>
                                <th class="text-right">Prezzo Vend.</th>
                                <th class="text-right">Aliq.</th>
                                <th class="text-right">Imponibile</th>
                                <th class="text-right">MinusPlus</th>
                                <th class="text-right">PercProv</th>
                                <th class="text-right">Provv Già Pag</th>
                                <th class="text-right">Provv In Pag</th>
                                <th>Articoli Portale</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{#each rows}}
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-checkbox"
                                           data-id="{{id}}"
                                           {{#if selected}}checked{{/if}}>
                                </td>
                                <td>{{dataVenditaFormatted}}</td>
                                <td>{{cliente}}</td>
                                <td>{{statoContratto}}</td>
                                <td class="text-right">{{prezzoVenditaFormatted}}</td>
                                <td class="text-right">{{aliquota}}</td>
                                <td class="text-right">{{imponibileFormatted}}</td>
                                <td class="text-right">{{minusPlusFormatted}}</td>
                                <td class="text-right">{{percProvFormatted}}</td>
                                <td class="text-right">{{provvGiaPagFormatted}}</td>
                                <td class="text-right text-success"><strong>{{provvInPagFormatted}}</strong></td>
                                <td class="small">{{articoliPortale}}</td>
                            </tr>
                            {{/each}}
                        </tbody>
                    </table>
                </div>
                {{/each}}
            </div>
        </div>
        {{/each}}
    {{else}}
        <p class="text-muted text-center padding">Nessuna provvigione eleggibile per i filtri impostati.</p>
    {{/if}}
    {{/unless}}
</div>
