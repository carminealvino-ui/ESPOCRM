define('custom:views/dashlets/crm-kpi', ['views/dashlets/abstract/base'], function (Dep) {

    return Dep.extend({

        name: 'CrmKpi',
        template: 'custom:dashlets/crm-kpi',

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
            return {
                loadError: this.loadError,
                periodLabel: this.getPeriodLabel(),
                brandLabel: summary.productBrandName || null,
                from: summary.from,
                to: summary.to,
                showDateRange: summary.from && summary.to,
                tiles: {
                    appuntamenti: this.mapAppuntamentiTile(tiles.appuntamenti),
                    opportunita: this.mapMetricTile(tiles.opportunita, [
                        {key: 'totali', label: 'Opportunità totali'},
                        {key: 'concluse', label: 'Concluse positivamente'},
                        {key: 'pending', label: 'Opportunità Pending'},
                        {key: 'perse', label: 'Opportunità perse'},
                    ]),
                    contratti: this.mapMetricTile(tiles.contratti, [
                        {key: 'totali', label: 'Contratti totali'},
                        {key: 'nettiFinKo', label: 'Contratti netti (finanziamento KO)'},
                        {key: 'nettiRecessi', label: 'Contratti netti (recessi)'},
                    ]),
                    valoreProduzione: this.mapMetricTile(tiles.valoreProduzione, [
                        {key: 'totali', label: 'Valore totale lordo', currency: true},
                        {key: 'netti', label: 'Valore totale netto', currency: true},
                        {key: 'koFinanziamenti', label: 'Valore fin. KO', currency: true},
                        {key: 'recessi', label: 'Valore recessi', currency: true},
                    ], true),
                    provvigioni: this.mapMetricTile(tiles.provvigioni, [
                        {key: 'totali', label: 'Provvigioni totali', currency: true},
                        {key: 'nette', label: 'Provvigioni nette', currency: true},
                        {key: 'koFinanziamenti', label: 'Provvigioni fin. KO', currency: true},
                        {key: 'koRecessi', label: 'Provvigioni recessi', currency: true},
                    ], true),
                },
                alerts: (summary.alerts || []).map(function (alert) {
                    return {
                        key: alert.key,
                        label: alert.label,
                        value: alert.value,
                        meta: alert.meta || null,
                    };
                }),
            };
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

        mapMetricTile: function (tile, definitions, currencyDefault) {
            const source = tile || {};

            return definitions.map(def => {
                const raw = source[def.key];
                const value = currencyDefault || def.currency
                    ? this.formatCurrency(raw)
                    : this.formatNumber(raw);

                return {
                    label: def.label,
                    value: value,
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
