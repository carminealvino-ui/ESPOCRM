define('custom:views/dashlets/crm-kpi', ['views/dashlets/abstract/base', 'lib!espo-funnel-chart'], function (Dep) {

    return Dep.extend({

        name: 'CrmKpi',
        template: 'custom:dashlets/crm-kpi',

        pipelineColors: ['#63a7c2', '#ccc058', '#c96947', '#b770e0', '#5cb85c'],
        successColor: '#5cb85c',

        events: {
            'click [data-action="refresh"]': function () {
                this.actionRefresh();
            },
            'click [data-action="openAlert"]': function (e) {
                const key = $(e.currentTarget).data('key');
                this.actionOpenAlert({key: key});
            },
        },

        setup: function () {
            this.summary = null;
            this.loadError = null;
            this.pipelineChart = null;

            Dep.prototype.setup.call(this);

            this.wait(true);
            this.loadSummary();
        },

        actionRefresh: function () {
            this.loadSummary();
        },

        autoRefresh: function () {
            this.loadSummary(true);
        },

        loadSummary: function (silent) {
            if (!silent) {
                this.wait(true);
            }

            const params = {
                period: this.getOption('period') || 'currentMonth',
            };

            const productBrandId = this.getOption('productBrandId')
                || this.getOption('productBrand');

            if (productBrandId) {
                params.productBrandId = productBrandId;
            }

            return Espo.Ajax.getRequest('Appuntamento/action/getSummary', params)
                .then(response => {
                    this.summary = response;
                    this.loadError = null;
                    this.wait(false);
                    this.reRender();
                })
                .catch(xhr => {
                    this.loadError = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Errore caricamento KPI';
                    this.wait(false);
                    this.reRender();
                });
        },

        data: function () {
            const summary = this.summary || {};
            const tiles = summary.tiles || {};
            const pipeline = summary.salesPipeline || [];
            const hasPipeline = pipeline.some(step => Number(step.value || 0) > 0);

            return {
                loadError: this.loadError,
                periodLabel: this.getPeriodLabel(),
                brandLabel: summary.productBrandName || null,
                from: summary.from,
                to: summary.to,
                showDateRange: summary.from && summary.to,
                hasPipeline: hasPipeline,
                tiles: {
                    appuntamenti: this.mapAppuntamentiTile(tiles.appuntamenti),
                    opportunita: this.mapOpportunitaTile(tiles.opportunita),
                    contratti: this.mapContrattiTile(tiles.contratti),
                    valoreProduzione: this.mapValoreProduzioneTile(tiles.valoreProduzione),
                    provvigioni: this.mapProvvigioniTile(tiles.provvigioni),
                },
                alerts: (summary.alerts || []).map(function (alert) {
                    return {
                        key: alert.key,
                        label: alert.label,
                        value: alert.value,
                        meta: alert.meta || null,
                    };
                }),
                yieldsByWeekday: this.mapYieldRows(summary.yieldsByWeekday),
                yieldsByWeek: this.mapYieldRows(summary.yieldsByWeek),
                yieldColumns: summary.yieldColumns || this.getDefaultYieldColumns(),
            };
        },

        getDefaultYieldColumns: function () {
            return [
                {key: 'appuntamentiLordi', label: 'Lordi'},
                {key: 'appuntamentiNetti', label: 'Netti'},
                {key: 'opportunita', label: 'Opp.'},
                {key: 'contratti', label: 'Contr.'},
                {key: 'contrattiNetti', label: 'C. netti'},
            ];
        },

        mapYieldRows: function (rows) {
            return (rows || []).map(row => {
                const cells = (row.cells || []).map(cell => ({
                    value: Number(cell.value || 0),
                    percents: (cell.percents || []).map(percent => Number(percent)),
                }));

                return {
                    label: row.label,
                    labelFull: row.labelFull || null,
                    cells: cells,
                };
            });
        },

        afterRender: function () {
            if (this.loadError || !this.summary) {
                return;
            }

            this.drawSalesPipeline();
        },

        getPipelineSteps: function () {
            return (this.summary && this.summary.salesPipeline) || [];
        },

        getPipelineColor: function (index, total) {
            const colors = this.pipelineColors;

            if (index + 1 === total) {
                return this.successColor;
            }

            return colors[index % colors.length];
        },

        drawSalesPipeline: function () {
            const steps = this.getPipelineSteps();
            const $container = this.$el.find('[data-name="pipeline-chart"]');
            const $legend = this.$el.find('.crm-kpi-pipeline-legend');

            if (!$container.length || !steps.length) {
                return;
            }

            $container.empty();
            $legend.empty();

            const hasValues = steps.some(step => Number(step.value || 0) > 0);

            if (!hasValues) {
                return;
            }

            const chartData = steps.map((step, index) => ({
                stageTranslated: step.label,
                value: Number(step.value || 0),
                stage: step.key,
                color: this.getPipelineColor(index, steps.length),
                percentOfNetti: step.percentOfNetti,
                percentOfOpportunita: step.percentOfOpportunita,
                percentOfPrevious: step.percentOfPrevious,
            }));

            if (typeof EspoFunnel !== 'undefined' && EspoFunnel.Funnel) {
                this.pipelineChart = new EspoFunnel.Funnel($container.get(0), {
                    colors: chartData.map(item => item.color),
                    outlineColor: '#444',
                    gapWidth: 0.015,
                    callbacks: {
                        tooltipHtml: index => this.getPipelineTooltip(chartData[index]),
                    },
                    tooltipClassName: 'crm-kpi-pipeline-tooltip',
                    tooltipStyleString:
                        'opacity:0.9;background-color:#000;color:#fff;position:absolute;' +
                        'padding:4px 10px;border-radius:4px;white-space:nowrap;z-index:1000;',
                }, chartData);
            } else {
                this.drawPipelineFallback($container, chartData);
            }

            this.drawPipelineLegend($legend, chartData);
        },

        getPipelinePercentMeta: function (item) {
            if (!item) {
                return '';
            }

            const parts = [];

            if (item.stage === 'appuntamentiNetti' && item.percentOfPrevious != null) {
                parts.push(item.percentOfPrevious + '% su lordi');
            }

            if (item.percentOfNetti != null) {
                parts.push(item.percentOfNetti + '% su app. netti');
            }

            if (item.percentOfOpportunita != null) {
                parts.push(item.percentOfOpportunita + '% su opp.');
            }

            if (item.stage === 'contrattiNetti' && item.percentOfPrevious != null) {
                parts.push(item.percentOfPrevious + '% prec');
            }

            return parts.join(' · ');
        },

        getPipelineTooltip: function (item) {
            if (!item) {
                return '';
            }

            const meta = this.getPipelinePercentMeta(item);
            let text = item.stageTranslated + ' ' + this.formatNumber(item.value);

            if (meta) {
                text += ' (' + meta + ')';
            }

            return text;
        },

        drawPipelineFallback: function ($container, chartData) {
            const max = Math.max.apply(null, chartData.map(item => item.value).concat([1]));
            let html = '<div class="crm-kpi-pipeline-fallback">';

            chartData.forEach(item => {
                const width = Math.max((item.value / max) * 100, 12);

                html += '<div class="crm-kpi-pipeline-fallback-step" style="' +
                    'width:' + width + '%;' +
                    'background-color:' + item.color + ';">' +
                    this.getHelper().escapeString(item.stageTranslated) +
                    ' · ' + this.formatNumber(item.value) +
                    '</div>';
            });

            html += '</div>';
            $container.html(html);
        },

        drawPipelineLegend: function ($container, chartData) {
            let html = '<div class="row"><div class="col-sm-12">';

            chartData.forEach(item => {
                const meta = this.getPipelinePercentMeta(item);
                const valueLine = this.formatNumber(item.value) + (meta ? ' · ' + meta : '');

                html += '<div class="legend-item">' +
                    '<span class="legend-box" style="background-color:' + item.color + ';"></span>' +
                    '<span>' +
                    '<span class="legend-label">' + this.getHelper().escapeString(item.stageTranslated) + '</span>' +
                    '<span class="legend-meta">' + valueLine + '</span>' +
                    '</span>' +
                    '</div>';
            });

            html += '</div></div>';
            $container.html(html);
        },

        mapAppuntamentiTile: function (tile) {
            const source = tile || {};
            const base = Number(source.lordi || 0);

            return [
                        {key: 'lordi', label: 'Appuntamenti lordi'},
                        {key: 'annullati', label: 'Appuntamenti annullati'},
                        {key: 'totali', label: 'Appuntamenti totali'},
                        {key: 'ingestibili', label: 'Appuntamenti ingestibili'},
                        {key: 'netti', label: 'Appuntamenti netti'},
                    ].map(def => {
                const raw = Number(source[def.key] || 0);
                const percent = base > 0 ? ((raw / base) * 100).toFixed(1) : '0.0';

                return {
                    label: def.label,
                    value: this.formatNumber(raw) + ' · ' + percent + '%',
                };
            });
        },

        mapOpportunitaTile: function (tile) {
            const source = tile || {};
            const base = Number(source.totali || 0);

            return [
                {key: 'totali', label: 'Opportunità totali'},
                {key: 'perse', label: 'Opportunità perse'},
                {key: 'pending', label: 'Opportunità pending'},
                {key: 'concluse', label: 'Opportunità concluse positivamente'},
            ].map(def => {
                const raw = Number(source[def.key] || 0);
                const percent = base > 0 ? ((raw / base) * 100).toFixed(1) : '0.0';

                return {
                    label: def.label,
                    value: this.formatNumber(raw) + ' · ' + percent + '%',
                };
            });
        },

        mapContrattiTile: function (tile) {
            const source = tile || {};
            const base = Number(source.totali || 0);

            return [
                {key: 'totali', label: 'Contratti totali'},
                {key: 'finanziamentiRifiutati', label: 'Contratti con finanziamenti rifiutati'},
                {key: 'lordi', label: 'Contratti lordi'},
                {key: 'recessi', label: 'Contratti con recessi'},
                {key: 'netti', label: 'Contratti netti'},
            ].map(def => {
                const raw = Number(source[def.key] || 0);
                const percent = base > 0 ? ((raw / base) * 100).toFixed(1) : '0.0';

                return {
                    label: def.label,
                    value: this.formatNumber(raw) + ' · ' + percent + '%',
                };
            });
        },

        mapValoreProduzioneTile: function (tile) {
            const source = tile || {};
            const base = Number(source.totali || 0);

            return [
                {key: 'totali', label: 'Valore produzione totale'},
                {key: 'finanziamentiRifiutati', label: 'Valore con finanziamenti rifiutati'},
                {key: 'lordi', label: 'Valore produzione lordo'},
                {key: 'recessi', label: 'Valore con recessi'},
                {key: 'netti', label: 'Valore produzione netto'},
            ].map(def => {
                const raw = Number(source[def.key] || 0);
                const percent = base > 0 ? ((raw / base) * 100).toFixed(1) : '0.0';

                return {
                    label: def.label,
                    value: this.formatCurrency(raw) + ' · ' + percent + '%',
                };
            });
        },

        mapProvvigioniTile: function (tile) {
            const source = tile || {};
            const base = Number(source.totali || 0);

            return [
                {key: 'totali', label: 'Provvigioni totali'},
                {key: 'finanziamentiRifiutati', label: 'Provvigioni con finanziamenti rifiutati'},
                {key: 'lordi', label: 'Provvigioni lordi'},
                {key: 'recessi', label: 'Provvigioni con recessi'},
                {key: 'netti', label: 'Provvigioni nette'},
            ].map(def => {
                const raw = Number(source[def.key] || 0);
                const percent = base > 0 ? ((raw / base) * 100).toFixed(1) : '0.0';

                return {
                    label: def.label,
                    value: this.formatCurrency(raw) + ' · ' + percent + '%',
                };
            });
        },

        getPeriodLabel: function () {
            const period = this.getOption('period') || 'currentMonth';
            const translated = this.translate(period, 'options', 'CrmKpi', 'period');

            if (translated && translated !== period) {
                return translated;
            }

            const labels = {
                totals: 'Totali',
                currentYear: 'Totali Anno in Corso',
                previousYear: 'Totali Anno Precedente',
                currentQuarter: 'Totali Trimestre in Corso',
                previousQuarter: 'Totali Trimestre Precedente',
                currentMonth: 'Totali Mese in Corso',
                previousMonth: 'Totali Mese Precedente',
            };

            return labels[period] || labels.currentMonth;
        },

        formatNumber: function (value) {
            const n = Number(value || 0);

            return n.toLocaleString('it-IT');
        },

        formatCurrency: function (value) {
            const n = Number(value || 0);

            return n.toLocaleString('it-IT', {
                style: 'currency',
                currency: 'EUR',
                maximumFractionDigits: 0,
            });
        },

        actionOpenAlert: function (data) {
            const key = data && data.key;
            const period = this.getOption('period') || 'currentMonth';

            if (key === 'opportunityWithoutPhoneFollowUp') {
                const filter = period === 'previousMonth'
                    ? 'senzaRiscontroMesePrecedente'
                    : 'senzaRiscontroPeriodo';

                this.getRouter().navigate('#Opportunity/filter/' + filter, {trigger: true});

                return;
            }

            if (key === 'phoneContactsTodo') {
                this.getRouter().navigate('#Call/filter/contattiDaFare', {trigger: true});

                return;
            }

            if (key === 'contractsBacklog') {
                this.getRouter().navigate('#Quote/filter/contrattiBacklog', {trigger: true});

                return;
            }

            if (key === 'contractsInProgress') {
                this.getRouter().navigate('#Quote/filter/contrattiInLavorazione', {trigger: true});

                return;
            }

            if (key === 'contractsInPayment') {
                const filter = period === 'previousMonth'
                    ? 'dataInstallazioneMesePrecedente'
                    : 'dataInstallazionePeriodo';

                this.getRouter().navigate('#Quote/filter/' + filter, {trigger: true});

                return;
            }

            const alerts = (this.summary && this.summary.alerts) || [];
            const alert = alerts.find(function (item) {
                return item.key === key;
            });

            if (alert && alert.link) {
                this.getRouter().navigate(alert.link, {trigger: true});
            }
        },
    });
});
