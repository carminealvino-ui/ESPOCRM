<div class="crm-kpi-dashlet">
    {{#if loadError}}
        <div class="alert alert-danger">{{loadError}}</div>
    {{else}}
        <div class="crm-kpi-period text-muted small margin-bottom">
            {{periodLabel}}{{#if showDateRange}} · {{from}} → {{to}}{{/if}}
            <a role="button" class="pull-right" data-action="refresh" title="Aggiorna">
                <span class="fas fa-sync-alt"></span>
            </a>
        </div>

        <div class="row crm-kpi-tiles">
            <div class="col-sm-6 col-md-3">
                <div class="crm-kpi-tile" data-action="openAppuntamentiSvolti">
                    <div class="crm-kpi-tile-label">Appuntamenti svolti</div>
                    <div class="crm-kpi-tile-value">{{tiles.appuntamentiSvolti.value}}</div>
                    <div class="crm-kpi-tile-meta {{tiles.appuntamentiSvolti.changeClass}}">
                        {{#if showComparison}}{{comparisonLabel}} {{tiles.appuntamentiSvolti.change}}{{/if}}
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="crm-kpi-tile" data-action="openOpportunitaAperte">
                    <div class="crm-kpi-tile-label">Opportunità aperte</div>
                    <div class="crm-kpi-tile-value">{{tiles.opportunitaAperte.count}}</div>
                    <div class="crm-kpi-tile-meta">{{tiles.opportunitaAperte.amount}}</div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="crm-kpi-tile" data-action="openContratti">
                    <div class="crm-kpi-tile-label">Contratti firmati</div>
                    <div class="crm-kpi-tile-value">{{tiles.contrattiFirmati.value}}</div>
                    <div class="crm-kpi-tile-meta {{tiles.contrattiFirmati.changeClass}}">
                        {{#if showComparison}}{{comparisonLabel}} {{tiles.contrattiFirmati.change}}{{/if}}
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="crm-kpi-tile" data-action="openContratti">
                    <div class="crm-kpi-tile-label">Valore contratti</div>
                    <div class="crm-kpi-tile-value">{{tiles.valoreContratti.value}}</div>
                    <div class="crm-kpi-tile-meta {{tiles.valoreContratti.changeClass}}">
                        {{#if showComparison}}{{comparisonLabel}} {{tiles.valoreContratti.change}}{{/if}}
                    </div>
                </div>
            </div>
        </div>

        <div class="crm-kpi-section">
            <div class="crm-kpi-section-title">Funnel · {{periodLabel}}</div>
            <div class="crm-kpi-funnel">
                {{#each funnel}}
                    <div class="crm-kpi-funnel-step">
                        <div class="crm-kpi-funnel-label">{{label}}</div>
                        <div class="crm-kpi-funnel-bar-wrap">
                            <div class="crm-kpi-funnel-bar" style="width: {{percentOfTotal}}%;"></div>
                        </div>
                        <div class="crm-kpi-funnel-value">
                            {{value}}
                            <span class="text-muted">
                                ({{percentOfTotal}}% tot{{#if percentOfPrevious}} · {{percentOfPrevious}}% prec{{/if}})
                            </span>
                        </div>
                    </div>
                {{/each}}
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="crm-kpi-section">
                    <div class="crm-kpi-section-title">Contratti per giorno settimana</div>
                    <div class="crm-kpi-weekdays">
                        {{#each contractsByWeekday}}
                            <div class="crm-kpi-weekday">
                                <div class="crm-kpi-weekday-label">{{label}}</div>
                                <div class="crm-kpi-weekday-bar-wrap">
                                    <div class="crm-kpi-weekday-bar" style="width: {{widthPercent}}%;"></div>
                                </div>
                                <div class="crm-kpi-weekday-value">{{value}}</div>
                            </div>
                        {{/each}}
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="crm-kpi-section">
                    <div class="crm-kpi-section-title">Avvisi</div>
                    <div class="crm-kpi-alerts">
                        {{#each alerts}}
                            <div class="crm-kpi-alert{{#if value}} crm-kpi-alert-warn{{/if}}" data-action="openAlert" data-key="{{key}}">
                                <span class="crm-kpi-alert-value">{{value}}</span>
                                <span class="crm-kpi-alert-label">{{label}}</span>
                            </div>
                        {{/each}}
                    </div>
                </div>
            </div>
        </div>
    {{/if}}
</div>
