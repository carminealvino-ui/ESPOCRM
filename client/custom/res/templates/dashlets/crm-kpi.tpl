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
            <div class="crm-kpi-panel">
                <div class="crm-kpi-panel-title">Funnel appuntamenti</div>
                <div class="crm-kpi-funnel-vertical">
                    {{#each funnels.appuntamenti}}
                        <div class="crm-kpi-funnel-vstep">
                            <div class="crm-kpi-funnel-vbar-wrap">
                                <div class="crm-kpi-funnel-vbar" style="height: {{heightPercent}}%;"></div>
                            </div>
                            <div class="crm-kpi-funnel-vmeta">
                                <div class="crm-kpi-funnel-vlabel">{{label}}</div>
                                <div class="crm-kpi-funnel-vvalue">{{value}}</div>
                                <div class="crm-kpi-funnel-vperc text-muted">
                                    {{percentOfTotal}}% tot{{#if percentOfPrevious}} · {{percentOfPrevious}}% prec{{/if}}
                                </div>
                            </div>
                        </div>
                    {{/each}}
                </div>
            </div>

            <div class="crm-kpi-panel">
                <div class="crm-kpi-panel-title">Funnel opportunità</div>
                <div class="crm-kpi-funnel-vertical">
                    {{#each funnels.opportunita}}
                        <div class="crm-kpi-funnel-vstep">
                            <div class="crm-kpi-funnel-vbar-wrap">
                                <div class="crm-kpi-funnel-vbar" style="height: {{heightPercent}}%;"></div>
                            </div>
                            <div class="crm-kpi-funnel-vmeta">
                                <div class="crm-kpi-funnel-vlabel">{{label}}</div>
                                <div class="crm-kpi-funnel-vvalue">{{value}}</div>
                                <div class="crm-kpi-funnel-vperc text-muted">
                                    {{percentOfTotal}}% tot{{#if percentOfPrevious}} · {{percentOfPrevious}}% prec{{/if}}
                                </div>
                            </div>
                        </div>
                    {{/each}}
                </div>
            </div>

            <div class="crm-kpi-panel">
                <div class="crm-kpi-panel-title">Funnel contratti</div>
                <div class="crm-kpi-funnel-vertical">
                    {{#each funnels.contratti}}
                        <div class="crm-kpi-funnel-vstep">
                            <div class="crm-kpi-funnel-vbar-wrap">
                                <div class="crm-kpi-funnel-vbar" style="height: {{heightPercent}}%;"></div>
                            </div>
                            <div class="crm-kpi-funnel-vmeta">
                                <div class="crm-kpi-funnel-vlabel">{{label}}</div>
                                <div class="crm-kpi-funnel-vvalue">{{value}}</div>
                                <div class="crm-kpi-funnel-vperc text-muted">
                                    {{percentOfTotal}}% tot{{#if percentOfPrevious}} · {{percentOfPrevious}}% prec{{/if}}
                                </div>
                            </div>
                        </div>
                    {{/each}}
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
    {{/if}}
</div>
