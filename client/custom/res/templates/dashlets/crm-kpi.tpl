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

    {{/if}}
</div>
