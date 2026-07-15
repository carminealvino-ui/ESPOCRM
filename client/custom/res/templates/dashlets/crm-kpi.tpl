<div class="crm-kpi-dashlet">
    {{#if loadError}}
        <div class="alert alert-danger">{{loadError}}</div>
    {{else}}
        <div class="crm-kpi-period text-muted small margin-bottom">
            <label class="crm-kpi-period-filter">
                <span class="crm-kpi-period-filter-label">Periodo</span>
                <select class="form-control input-sm crm-kpi-period-select" data-action="changePeriod">
                    {{#each periodOptions}}
                        <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                    {{/each}}
                </select>
            </label>
            {{#if brandLabel}}
                <span class="crm-kpi-period-meta"> · {{brandLabel}}</span>
            {{/if}}
            {{#if showDateRange}}
                <span class="crm-kpi-period-meta"> · {{from}} → {{to}}</span>
            {{/if}}
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
                        </div>
                        <div class="crm-kpi-pipeline-results">
                            <div class="crm-kpi-pipeline-results-note">Percentuali su lordi · su totali (netti) o su opportunità (contratti)</div>
                            <div class="crm-kpi-pipeline-results-grid">
                                <div class="crm-kpi-pipeline-results-head">
                                    <span>Risultato</span>
                                    <span>Valore</span>
                                    <span>Percentuali</span>
                                </div>
                                {{#each pipelineResultsRows}}
                                    <div class="crm-kpi-pipeline-results-row">
                                        <span class="crm-kpi-pipeline-results-label">{{label}}</span>
                                        <span class="crm-kpi-pipeline-results-value">{{value}}</span>
                                        <span class="crm-kpi-pipeline-results-detail">{{detail}}</span>
                                    </div>
                                {{/each}}
                            </div>
                        </div>
                    {{else}}
                        <div class="text-muted small">Nessun dato nel periodo selezionato.</div>
                    {{/if}}
                </div>
            </div>
            <div class="crm-kpi-bottom-col crm-kpi-bottom-side">
                <div class="crm-kpi-yields-row">
                    <div class="crm-kpi-panel crm-kpi-panel-yields crm-kpi-panel-yields-day">
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
                                            <th class="crm-kpi-yields-row-label" title="{{#if labelFull}}{{labelFull}}{{else}}{{label}}{{/if}}">{{label}}</th>
                                            {{#each cells}}
                                                <td>
                                                    <div class="crm-kpi-cell-inner">
                                                        <span class="crm-kpi-cell-value">{{value}}</span>{{#each percents}}<span class="crm-kpi-cell-percent">{{this}}%</span>{{/each}}
                                                    </div>
                                                </td>
                                            {{/each}}
                                        </tr>
                                    {{/each}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="crm-kpi-panel crm-kpi-panel-yields crm-kpi-panel-yields-week">
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
                                            <th class="crm-kpi-yields-row-label" title="{{#if labelFull}}{{labelFull}}{{else}}{{label}}{{/if}}">{{label}}</th>
                                            {{#each cells}}
                                                <td>
                                                    <div class="crm-kpi-cell-inner">
                                                        <span class="crm-kpi-cell-value">{{value}}</span>{{#each percents}}<span class="crm-kpi-cell-percent">{{this}}%</span>{{/each}}
                                                    </div>
                                                </td>
                                            {{/each}}
                                        </tr>
                                    {{/each}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="crm-kpi-criticita-section">
                    <div class="crm-kpi-panel crm-kpi-panel-alerts crm-kpi-panel-alerts-criticita">
                        <div class="crm-kpi-panel-title">Criticità</div>
                        <div class="crm-kpi-panel-note text-muted small">Valori totali (non filtrati per periodo) · 0 = nessuna criticità</div>
                        <div class="crm-kpi-criticita-row">
                            {{#each criticitaBoxes}}
                                <div class="crm-kpi-panel crm-kpi-panel-criticita-entity">
                                    <div class="crm-kpi-panel-subtitle">{{title}}</div>
                                    <div class="crm-kpi-alerts">
                                        {{#each alerts}}
                                            {{#if value}}
                                                <div class="crm-kpi-alert crm-kpi-alert-criticita crm-kpi-alert-criticita-active" data-action="openAlert" data-key="{{key}}">
                                                    <span class="crm-kpi-alert-value">{{value}}</span>
                                                    <span class="crm-kpi-alert-body">
                                                        <span class="crm-kpi-alert-label">{{label}}</span>
                                                        {{#if meta}}
                                                            <span class="crm-kpi-alert-meta">{{meta}}</span>
                                                        {{/if}}
                                                    </span>
                                                </div>
                                            {{/if}}
                                        {{/each}}
                                        {{#if showEmpty}}
                                            <div class="crm-kpi-criticita-empty text-muted small">Nessuna criticità</div>
                                        {{/if}}
                                    </div>
                                </div>
                            {{/each}}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    {{/if}}
</div>
