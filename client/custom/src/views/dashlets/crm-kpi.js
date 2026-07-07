define('custom:views/dashlets/crm-kpi', ['views/dashlets/abstract/base', 'lib!espo-funnel-chart'], function (Dep) {

    // kpi-tile-labels-v5: pipeline results % contratti su opportunità, tabella full-width

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

            return Espo.Ajax.getRequest('CrmKpi/action/getSummary', params)
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
                criticitaBoxes: this.mapCriticitaBoxes(summary.alerts),
                yieldsByWeekday: this.mapYieldRows(summary.yieldsByWeekday),
                yieldsByWeek: this.mapYieldRows(summary.yieldsByWeek),
                yieldColumns: summary.yieldColumns || this.getDefaultYieldColumns(),
                pipelineResultsRows: this.mapPipelineResultsRows(pipeline, tiles.appuntamenti || {}),
            };
        },

        mapPipelineResultsRows: function (pipeline, appuntamentiTile) {
            const steps = pipeline || [];

            if (!steps.length) {
                return [];
            }

            const valueByKey = {};
            steps.forEach(step => {
                valueByKey[step.key] = Number(step.value || 0);
            });

            const tile = appuntamentiTile || {};
            const baseLordi = Number(tile.lordi ?? valueByKey.appuntamentiLordi ?? 0);
            const baseTotali = Number(tile.totali ?? 0);
            const baseNetti = Number(tile.netti ?? valueByKey.appuntamentiNetti ?? 0);
            const baseOpportunita = Number(valueByKey.opportunita ?? 0);
            const baseContratti = Number(valueByKey.contratti ?? 0);
            const baseContrattiNetti = Number(valueByKey.contrattiNetti ?? 0);

            return [
                {
                    label: 'Lordi',
                    value: this.formatNumber(baseLordi),
                    detail: this.formatPercentOf(baseLordi, baseLordi),
                },
                {
                    label: 'Totali',
                    value: this.formatNumber(baseTotali),
                    detail: this.formatPercentOf(baseTotali, baseLordi),
                },
                {
                    label: 'Netti',
                    value: this.formatNumber(baseNetti),
                    detail: this.joinPercentDetails([
                        this.formatPercentOf(baseNetti, baseLordi),
                        this.formatPercentOf(baseNetti, baseTotali),
                    ]),
                },
                {
                    label: 'Contr. lordi',
                    value: this.formatNumber(baseContratti),
                    detail: this.joinPercentDetails([
                        this.formatPercentOf(baseContratti, baseLordi),
                        this.formatPercentOf(baseContratti, baseOpportunita),
                    ]),
                },
                {
                    label: 'Contr. netti',
                    value: this.formatNumber(baseContrattiNetti),
                    detail: this.joinPercentDetails([
                        this.formatPercentOf(baseContrattiNetti, baseLordi),
                        this.formatPercentOf(baseContrattiNetti, baseOpportunita),
                    ]),
                },
            ];
        },

        joinPercentDetails: function (parts) {
            return (parts || []).filter(part => part && part !== '-').join(' · ');
        },

        computePercent: function (value, base) {
            const num = Number(value || 0);
            const den = Number(base || 0);

            if (den <= 0) {
                return '-';
            }

            return ((num / den) * 100).toFixed(1) + '%';
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

        mapAlerts: function (alerts, group) {
            return (alerts || [])
                .filter(function (alert) {
                    return (alert.group || 'avvisi') === group;
                })
                .map(function (alert) {
                    return {
                        key: alert.key,
                        label: alert.label,
                        value: alert.value,
                        meta: alert.meta || null,
                        entity: alert.entity || null,
                    };
                });
        },

        mapCriticitaBoxes: function (alerts) {
            const entities = [
                {key: 'appuntamenti', title: 'Appuntamenti'},
                {key: 'opportunita', title: 'Opportunità'},
                {key: 'contratti', title: 'Contratti'},
                {key: 'chiamate', title: 'Chiamate'},
            ];

            const criticita = this.mapAlerts(alerts, 'criticita');

            return entities.map(entity => {
                const items = criticita
                    .filter(alert => (alert.entity || '') === entity.key)
                    .filter(alert => Number(alert.value || 0) > 0);

                return {
                    key: entity.key,
                    title: entity.title,
                    alerts: items,
                    hasAlerts: items.length > 0,
                    showEmpty: items.length === 0,
                };
            });
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

            if (!$container.length || !steps.length) {
                return;
            }

            $container.empty();

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
            return this.mapMetricTile(tile, [
                {key: 'lordi', label: 'Lordi'},
                {key: 'annullati', label: 'Annullati'},
                {key: 'totali', label: 'Totali'},
                {key: 'ingestibili', label: 'Ingestibili'},
                {key: 'netti', label: 'Netti'},
            ], this.formatNumber);
        },

        mapOpportunitaTile: function (tile) {
            const source = tile || {};
            const base = Number(source.totali || 0);

            return [
                {key: 'totali', label: 'Totali'},
                {key: 'perse', label: 'Perse'},
                {key: 'pending', label: 'Pending'},
                {key: 'concluse', label: 'Concluse positivamente'},
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
            return this.mapMetricTile(tile, [
                {key: 'lordi', label: 'Lordi'},
                {key: 'recessi', label: 'Recessi'},
                {key: 'totali', label: 'Totali'},
                {key: 'finanziamentiRifiutati', label: 'Finanziamenti rifiutati'},
                {key: 'netti', label: 'Netti'},
            ], this.formatNumber);
        },

        mapValoreProduzioneTile: function (tile) {
            return this.mapMetricTile(tile, [
                {key: 'lordi', label: 'Lordo'},
                {key: 'recessi', label: 'Recessi'},
                {key: 'totali', label: 'Totale'},
                {key: 'finanziamentiRifiutati', label: 'Finanziamenti rifiutati'},
                {key: 'netti', label: 'Netto'},
            ], this.formatCurrency);
        },

        mapProvvigioniTile: function (tile) {
            return this.mapMetricTile(tile, [
                {key: 'lordi', label: 'Lordi'},
                {key: 'recessi', label: 'Recessi'},
                {key: 'totali', label: 'Totali'},
                {key: 'finanziamentiRifiutati', label: 'Finanziamenti rifiutati'},
                {key: 'netti', label: 'Nette'},
            ], this.formatCurrency);
        },

        /**
         * Tile metriche gerarchiche: lordi 100% · annullati/recessi/totali % lordi ·
         * ingestibili/fin./netti % lordi e % totali.
         *
         * @param {Function} formatValue
         */
        mapMetricTile: function (tile, rows, formatValue) {
            const source = tile || {};
            const baseLordi = Number(source.lordi || 0);
            const baseTotali = Number(source.totali || 0);

            return rows.map(def => {
                const raw = Number(source[def.key] || 0);
                let value = formatValue.call(this, raw);
                const percentLordi = this.formatPercentOf(raw, baseLordi);

                if (def.key === 'lordi') {
                    value += ' · ' + percentLordi;
                } else if (
                    def.key === 'annullati'
                    || def.key === 'recessi'
                    || def.key === 'totali'
                ) {
                    value += ' · ' + percentLordi;
                } else if (
                    def.key === 'ingestibili'
                    || def.key === 'finanziamentiRifiutati'
                    || def.key === 'netti'
                ) {
                    const percentTotali = this.formatPercentOf(raw, baseTotali);

                    value += ' · ' + this.joinPercentDetails([
                        percentLordi,
                        percentTotali,
                    ]);
                }

                return {
                    label: def.label,
                    value: value,
                };
            });
        },

        mapQuoteMetricTile: function (tile, rows, formatValue) {
            return this.mapMetricTile(tile, rows, formatValue);
        },

        formatPercentOf: function (value, base) {
            const num = Number(value || 0);
            const den = Number(base || 0);

            if (den <= 0) {
                return '0.0%';
            }

            return ((num / den) * 100).toFixed(1) + '%';
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

        buildEntityListUrl: function (entityType, primaryFilter) {
            return '#' + entityType + '/list/primaryFilter=' + primaryFilter;
        },

        actionOpenAlert: function (data) {
            const key = data && data.key;
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
