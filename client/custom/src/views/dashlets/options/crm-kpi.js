define('custom:views/dashlets/options/crm-kpi', ['views/dashlets/options/base'], function (Dep) {

    return Dep.extend({

        setup: function () {
            this.applyCrmKpiLabels();
            Dep.prototype.setup.call(this);
            this.applyCrmKpiHeader();
        },

        applyCrmKpiLabels: function () {
            const fieldNames = ['title', 'period', 'productBrand', 'autorefreshInterval'];

            fieldNames.forEach(name => {
                if (!this.fields[name]) {
                    return;
                }

                const label = this.translate(name, 'fields', 'CrmKpi');

                if (label && label !== name) {
                    this.fields[name].label = label;
                }
            });
        },

        applyCrmKpiHeader: function () {
            const dashletLabel = this.translate('CrmKpi', 'dashlets')
                || this.translate('CrmKpi', 'labels')
                || 'KPI CRM';

            this.$header =
                $('<span>')
                    .append(
                        $('<span>').text(this.translate('Dashlet Options')),
                        ' · ',
                        $('<span>').text(dashletLabel)
                    );
        },
    });
});
