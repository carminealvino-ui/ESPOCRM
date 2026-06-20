define('custom:views/dashlets/crm-kpi', ['views/dashlets/abstract/base'], function (Dep) {

    return Dep.extend({

        name: 'CrmKpi',
        template: 'custom:dashlets/crm-kpi',

        events: {
            'click [data-action="refresh"]': function () {
                this.actionRefresh();
            },
            'click [data-action="openAppuntamentiSvolti"]': function () {
                this.actionOpenAppuntamentiSvolti();
            },
            'click [data-action="openOpportunitaAperte"]': function () {
                this.actionOpenOpportunitaAperte();
            },
            'click [data-action="openContratti"]': function () {
                this.actionOpenContratti();
            },
            'click [data-action="openAlert"]': function (e) {
                const key = $(e.currentTarget).data('key');
                this.actionOpenAlert({key: key});
            },
        },

        setup: function () {
            this.summary = null;
            this.loadError = null;

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

            const period = this.getOption('period') || 'currentMonth';

            return Espo.Ajax.getRequest('Appuntamento/action/getSummary', {period: period})
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
            const appuntamenti = tiles.appuntamentiSvolti || {};
            const opportunita = tiles.opportunitaAperte || {};
            const contratti = tiles.contrattiFirmati || {};
            const valore = tiles.valoreContratti || {};

            return {
                loadError: this.loadError,
                periodLabel: this.getPeriodLabel(),
                comparisonLabel: this.getComparisonLabel(),
                showComparison: this.hasComparisonPeriod(),
                from: summary.from,
                to: summary.to,
                showDateRange: summary.from && summary.to,
                tiles: {
                    appuntamentiSvolti: {
                        value: this.formatNumber(appuntamenti.value),
                        change: this.formatChange(appuntamenti.changePercent),
                        changeClass: this.changeClass(appuntamenti.changePercent),
                    },
                    opportunitaAperte: {
                        count: this.formatNumber(opportunita.count),
                        amount: this.formatCurrency(opportunita.amount),
                    },
                    contrattiFirmati: {
                        value: this.formatNumber(contratti.value),
                        change: this.formatChange(contratti.changePercent),
                        changeClass: this.changeClass(contratti.changePercent),
                    },
                    valoreContratti: {
                        value: this.formatCurrency(valore.value),
                        change: this.formatChange(valore.changePercent),
                        changeClass: this.changeClass(valore.changePercent),
                    },
                },
                funnel: (summary.funnel || []).map(step => ({
                    label: step.label,
                    value: step.value,
                    percentOfTotal: step.percentOfHeld,
                    percentOfPrevious: step.percentOfPrevious,
                })),
                contractsByWeekday: this.mapContractChartRows(summary.contractsByWeekday),
                contractsByWeekOfMonth: this.mapContractChartRows(summary.contractsByWeekOfMonth),
                alerts: summary.alerts || [],
            };
        },

        mapContractChartRows: function (rows) {
            const list = rows || [];
            const total = list.reduce(function (sum, row) {
                return sum + Number(row.value || 0);
            }, 0);
            const totalBase = total > 0 ? total : 1;

            return list.map(function (row) {
                const value = Number(row.value || 0);
                const percentOfTotal = row.percentOfTotal !== undefined && row.percentOfTotal !== null
                    ? Number(row.percentOfTotal)
                    : Math.round((value / totalBase) * 1000) / 10;

                return {
                    label: row.label,
                    value: value,
                    widthPercent: row.widthPercent !== undefined ? row.widthPercent : 0,
                    percentOfTotal: percentOfTotal,
                };
            });
        },

        getPeriodLabel: function () {
            const period = this.getOption('period') || 'currentMonth';
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

        getComparisonLabel: function () {
            const period = this.getOption('period') || 'currentMonth';

            if (period === 'totals') {
                return null;
            }

            if (period === 'currentYear' || period === 'previousYear') {
                return 'vs anno prec.';
            }

            if (period === 'currentQuarter' || period === 'previousQuarter') {
                return 'vs trim. prec.';
            }

            return 'vs mese prec.';
        },

        hasComparisonPeriod: function () {
            return (this.getOption('period') || 'currentMonth') !== 'totals';
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

        formatChange: function (value) {
            if (value === null || value === undefined) {
                return 'n.d.';
            }

            const n = Number(value);
            const sign = n > 0 ? '+' : '';

            return sign + n.toLocaleString('it-IT', {maximumFractionDigits: 1}) + '%';
        },

        changeClass: function (value) {
            const n = Number(value || 0);

            if (n > 0) {
                return 'text-success';
            }

            if (n < 0) {
                return 'text-danger';
            }

            return 'text-muted';
        },

        actionOpenAppuntamentiSvolti: function () {
            const period = this.getOption('period') || 'currentMonth';
            let filter = 'svolto';

            if (period === 'currentMonth') {
                filter = 'meseCorrenteSvolto';
            } else if (period === 'previousMonth') {
                filter = 'svolto';
            }

            this.getRouter().navigate('#Appuntamento/filter/' + filter, {trigger: true});
        },

        actionOpenOpportunitaAperte: function () {
            const period = this.getOption('period') || 'currentMonth';
            let filter = 'aperteMeseCorrente';

            if (period === 'previousMonth') {
                filter = 'aperteMesePrecedente';
            }

            this.getRouter().navigate('#Opportunity/filter/' + filter, {trigger: true});
        },

        actionOpenContratti: function () {
            this.getRouter().navigate('#Quote/filter/meseCorrente', {trigger: true});
        },

        actionOpenAlert: function (data) {
            const key = data && data.key;

            if (key === 'callsOverdue') {
                this.getRouter().navigate('#Call/filter/daRiscontrare', {trigger: true});

                return;
            }

            if (key === 'negotiationNoContract') {
                this.getRouter().navigate('#Opportunity/filter/aperte', {trigger: true});

                return;
            }

            if (key === 'pendingNoOpportunity') {
                this.getRouter().navigate('#Appuntamento/filter/svolto', {trigger: true});

                return;
            }

            this.getRouter().navigate('#Appuntamento', {trigger: true});
        },
    });
});
