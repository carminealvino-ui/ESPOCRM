define('custom:views/dashlets/options/crm-kpi', ['views/dashlets/options/base'], function (Dep) {

    return Dep.extend({

        init: function () {
            Dep.prototype.init.call(this);
            this.applyCrmKpiFieldLabels();
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.applyCrmKpiHeader();
        },

        applyCrmKpiFieldLabels: function () {
            const fieldNames = ['title', 'period', 'productBrand', 'autorefreshInterval'];

            fieldNames.forEach(name => {
                if (!this.fields[name]) {
                    return;
                }

                const label = this.translate(name, 'fields', 'CrmKpi');

                if (label && label !== name) {
                    this.fields[name].labelText = label;
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            const fieldViews = this.getFieldViews(true) || {};

            Object.keys(fieldViews).forEach(name => {
                const view = fieldViews[name];
                const label = this.translate(name, 'fields', 'CrmKpi');

                if (!view || !label || label === name) {
                    return;
                }

                view.labelText = label;

                const $label = view.getLabelElement && view.getLabelElement();

                if ($label && $label.length) {
                    $label.find('.label-text').text(label);
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
