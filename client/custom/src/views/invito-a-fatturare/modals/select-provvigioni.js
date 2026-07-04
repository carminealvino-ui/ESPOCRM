/* global define, Espo */

define('custom:views/invito-a-fatturare/modals/select-provvigioni', ['views/modal'], function (Dep) {

    return Dep.extend({

        template: 'custom:invito-a-fatturare/modals/select-provvigioni',
        cssName: 'invito-a-fatturare',
        className: 'dialog dialog-record invito-provvigioni-dialog',

        events: {
            'change .row-checkbox': function () {
                this.updateTotals();
            },
            'change .group-checkbox': function (e) {
                this.toggleGroup($(e.currentTarget), $(e.currentTarget).prop('checked'));
            },
            'change .venditore-checkbox': function (e) {
                this.toggleVenditore($(e.currentTarget), $(e.currentTarget).prop('checked'));
            },
        },

        setup: function () {
            this.invitoModel = this.options.invitoModel;
            this.loading = true;
            this.groups = [];
            this.selectedIds = {};
            this.totaleSelezionato = 0;

            this.headerText = 'Seleziona provvigioni';

            this.buttonList = [
                {
                    name: 'save',
                    label: 'Conferma selezione',
                    style: 'primary',
                    onClick: function () {
                        this.save();
                    }.bind(this),
                },
                {
                    name: 'cancel',
                    label: 'Annulla',
                    onClick: function () {
                        this.close();
                    }.bind(this),
                },
            ];

            Dep.prototype.setup.call(this);

            this.loadData();
        },

        data: function () {
            return {
                loading: this.loading,
                groups: this.groups,
                hasGroups: this.groups.length > 0,
                totaleSelezionatoFormatted: this.formatCurrency(this.totaleSelezionato),
            };
        },

        loadData: function () {
            var self = this;
            var consulenteId = this.invitoModel.get('consulenteId')
                || this.invitoModel.get('assignedUserId');
            var mese = this.invitoModel.get('meseCompetenza');

            if (!consulenteId || !mese) {
                this.loading = false;
                this.reRender();

                return;
            }

            Espo.Ajax.postRequest('InvitoAFatturare/action/getProvvigioniEleggibili', {
                consulenteId: consulenteId,
                meseCompetenza: mese,
                fornitorePartnerId: this.invitoModel.get('fornitorePartnerId'),
                productBrandId: this.invitoModel.get('productBrandId'),
                invitoId: this.invitoModel.id,
            }).then(function (response) {
                self.groups = self.enrichGroups(response.groups || []);
                self.selectedIds = {};
                self.indexRows();
                self.loading = false;
                self.reRender();
                self.updateTotals();
            }).catch(function () {
                self.loading = false;
                self.reRender();
            });
        },

        indexRows: function () {
            var self = this;

            this.rowById = {};

            this.groups.forEach(function (group) {
                (group.venditori || []).forEach(function (venditore) {
                    (venditore.rows || []).forEach(function (row) {
                        self.rowById[row.id] = row;

                        if (row.selected) {
                            self.selectedIds[row.id] = true;
                        }
                    });
                });
            });
        },

        enrichGroups: function (groups) {
            var self = this;

            return groups.map(function (group) {
                var enrichedGroup = Object.assign({}, group);
                enrichedGroup.totaleProvvInPagFormatted = self.formatCurrency(group.totaleProvvInPag);
                enrichedGroup.venditori = (group.venditori || []).map(function (venditore) {
                    var enrichedVenditore = Object.assign({}, venditore);
                    enrichedVenditore.totaleProvvInPagFormatted = self.formatCurrency(venditore.totaleProvvInPag);
                    enrichedVenditore.rows = (venditore.rows || []).map(function (row) {
                        return Object.assign({}, row, {
                            dataVenditaFormatted: self.formatDate(row.dataVendita),
                            prezzoVenditaFormatted: self.formatCurrency(row.prezzoVendita),
                            imponibileFormatted: self.formatCurrency(row.imponibile),
                            minusPlusFormatted: self.formatCurrency(row.minusPlus),
                            provvGiaPagFormatted: self.formatCurrency(row.provvGiaPag),
                            provvInPagFormatted: self.formatCurrency(row.provvInPag),
                            percProvFormatted: row.percProv ? row.percProv + '%' : '—',
                        });
                    });

                    return enrichedVenditore;
                });

                return enrichedGroup;
            });
        },

        updateTotals: function () {
            var self = this;
            var total = 0;

            this.selectedIds = {};

            this.$el.find('.row-checkbox:checked').each(function () {
                var id = $(this).data('id');
                self.selectedIds[id] = true;

                if (self.rowById[id]) {
                    total += self.rowById[id].provvInPag || 0;
                }
            });

            this.totaleSelezionato = total;
            this.$el.find('.invito-provvigioni-toolbar strong.text-success')
                .text('Totale selezionato: ' + this.formatCurrency(total));
        },

        toggleGroup: function ($checkbox, checked) {
            var groupIndex = $checkbox.data('group-index');
            var $group = this.$el.find('.invito-provvigioni-group').eq(groupIndex);

            $group.find('.row-checkbox, .venditore-checkbox, .group-checkbox')
                .prop('checked', checked);
            this.updateTotals();
        },

        toggleVenditore: function ($checkbox, checked) {
            var groupIndex = $checkbox.data('group-index');
            var venditoreIndex = $checkbox.data('venditore-index');
            var $venditore = this.$el.find('.invito-provvigioni-group')
                .eq(groupIndex)
                .find('.invito-provvigioni-venditore')
                .eq(venditoreIndex);

            $venditore.find('.row-checkbox').prop('checked', checked);
            this.updateTotals();
        },

        save: function () {
            var self = this;
            var ids = Object.keys(this.selectedIds);

            Espo.Ui.notify(' ... ');

            Espo.Ajax.postRequest('InvitoAFatturare/action/collegaProvvigioni', {
                invitoId: this.invitoModel.id,
                provvigioneIds: ids,
            }).then(function (result) {
                Espo.Ui.success('Provvigioni collegate: ' + (result.count || 0));
                self.trigger('saved', result);
                self.close();
            });
        },

        formatCurrency: function (value) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }

            var num = parseFloat(value);

            if (isNaN(num)) {
                return '—';
            }

            return num.toLocaleString('it-IT', {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 2,
            });
        },

        formatDate: function (value) {
            if (!value) {
                return '—';
            }

            var date = new Date(value);

            if (isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleDateString('it-IT');
        },
    });
});
