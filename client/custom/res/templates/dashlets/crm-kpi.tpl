<div class="crm-kpi-dashlet">
    {{#if loadError}}
        <div class="alert alert-danger">{{loadError}}</div>
    {{else}}
        <div class="crm-kpi-period text-muted small margin-bottom">
            {{periodLabel}}{{#if brandLabel}} · {{brandLabel}}{{/if}}{{#if showDateRange}} · {{from}} → {{to}}{{/if}}
            <a role="button" class="pull-right" data-action="refresh" title="Aggiorna">
                <span class="fas fa-sync-alt"></span>
            </a>
        </div>

        <div class="crm-kpi-tiles">
            <div class="crm-kpi-tile-col">
                <div class="crm-kpi-tile">
                    <div class="crm-kpi-tile-title">Appuntamenti</div>
                    {{#each tiles.appuntamenti}}
                        <div class="crm-kpi-tile-row">
                            <span class="crm-kpi-tile-row-label">{{label}}</span>
                            <span class="crm-kpi-tile-row-value">{{value}}</span>
                        </div>
                    {{/each}}
                </div>
            </div>
            <div class="crm-kpi-tile-col">
                <div class="crm-kpi-tile">
                    <div class="crm-kpi-tile-title">Opportunità</div>
                    {{#each tiles.opportunita}}
                        <div class="crm-kpi-tile-row">
                            <span class="crm-kpi-tile-row-label">{{label}}</span>
                            <span class="crm-kpi-tile-row-value">{{value}}</span>
                        </div>
                    {{/each}}
                </div>
            </div>
            <div class="crm-kpi-tile-col">
                <div class="crm-kpi-tile">
                    <div class="crm-kpi-tile-title">Contratti</div>
                    {{#each tiles.contratti}}
                        <div class="crm-kpi-tile-row">
                            <span class="crm-kpi-tile-row-label">{{label}}</span>
                            <span class="crm-kpi-tile-row-value">{{value}}</span>
                        </div>
                    {{/each}}
                </div>
            </div>
            <div class="crm-kpi-tile-col">
                <div class="crm-kpi-tile">
                    <div class="crm-kpi-tile-title">Valore produzione</div>
                    {{#each tiles.valoreProduzione}}
                        <div class="crm-kpi-tile-row">
                            <span class="crm-kpi-tile-row-label">{{label}}</span>
                            <span class="crm-kpi-tile-row-value">{{value}}</span>
                        </div>
                    {{/each}}
                </div>
            </div>
            <div class="crm-kpi-tile-col">
                <div class="crm-kpi-tile">
                    <div class="crm-kpi-tile-title">Provvigioni</div>
                    {{#each tiles.provvigioni}}
                        <div class="crm-kpi-tile-row">
                            <span class="crm-kpi-tile-row-label">{{label}}</span>
                            <span class="crm-kpi-tile-row-value">{{value}}</span>
                        </div>
                    {{/each}}
                </div>
            </div>
        </div>

        <div class="crm-kpi-bottom">
            <div class="crm-kpi-bottom-col crm-kpi-bottom-pipeline">
                <div class="crm-kpi-panel crm-kpi-panel-pipeline">
                    <div class="crm-kpi-panel-title">Pipeline di vendita</div>
                    {{#if hasPipeline}}
                        <div class="crm-kpi-pipeline">
                            <div class="crm-kpi-pipeline-chart" data-name="pipeline-chart"></div>
                            <div class="crm-kpi-pipeline-legend legend-container"></div>
                        </div>
                    {{else}}
                        <div class="text-muted small">Nessun dato nel periodo selezionato.</div>
                    {{/if}}
                </div>
            </div>
            <div class="crm-kpi-bottom-col crm-kpi-bottom-side">
                <div class="crm-kpi-yields-row">
                    <div class="crm-kpi-panel crm-kpi-panel-yields">
                        <div class="crm-kpi-panel-title">Rese per giorno</div>
                        <div class="crm-kpi-panel-note text-muted small">Pipeline per giorno settimana</div>
                        <div class="crm-kpi-yields-table-wrap">
                            <table class="crm-kpi-yields-table">
                                <thead>
                                    <tr>
                                        <th class="crm-kpi-yields-corner"></th>
                                        {{#each yieldColumns}}
                                            <th>{{label}}</th>
                                        {{/each}}
                                    </tr>
                                </thead>
                                <tbody>
                                    {{#each yieldsByWeekday}}
                                        <tr>
                                            <th class="crm-kpi-yields-row-label" title="{{label}}">{{label}}</th>
                                            {{#each cells}}
                                                <td>
                                                    <span class="crm-kpi-cell-value">{{value}}</span>
                                                    {{#each percents}}
                                                        <span class="crm-kpi-cell-percent">{{this}}%</span>
                                                    {{/each}}
                                                </td>
                                            {{/each}}
                                        </tr>
                                    {{/each}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="crm-kpi-panel crm-kpi-panel-yields">
                        <div class="crm-kpi-panel-title">Rese per settimana</div>
                        <div class="crm-kpi-panel-note text-muted small">Settimane con almeno 4 giorni nel periodo</div>
                        <div class="crm-kpi-yields-table-wrap">
                            <table class="crm-kpi-yields-table">
                                <thead>
                                    <tr>
                                        <th class="crm-kpi-yields-corner"></th>
                                        {{#each yieldColumns}}
                                            <th>{{label}}</th>
                                        {{/each}}
                                    </tr>
                                </thead>
                                <tbody>
                                    {{#each yieldsByWeek}}
                                        <tr>
                                            <th class="crm-kpi-yields-row-label" title="{{label}}">{{label}}</th>
                                            {{#each cells}}
                                                <td>
                                                    <span class="crm-kpi-cell-value">{{value}}</span>
                                                    {{#each percents}}
                                                        <span class="crm-kpi-cell-percent">{{this}}%</span>
                                                    {{/each}}
                                                </td>
                                            {{/each}}
                                        </tr>
                                    {{/each}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="crm-kpi-panel crm-kpi-panel-alerts">
                    <div class="crm-kpi-panel-title">Avvisi</div>
                    <div class="crm-kpi-alerts">
                        {{#each alerts}}
                            <div class="crm-kpi-alert{{#if value}} crm-kpi-alert-warn{{/if}}" data-action="openAlert" data-key="{{key}}">
                                <span class="crm-kpi-alert-value">{{value}}</span>
                                <span class="crm-kpi-alert-body">
                                    <span class="crm-kpi-alert-label">{{label}}</span>
                                    {{#if meta}}
                                        <span class="crm-kpi-alert-meta">{{meta}}</span>
                                    {{/if}}
                                </span>
                            </div>
                        {{/each}}
                    </div>
                </div>
            </div>
        </div>

    {{/if}}
</div>
