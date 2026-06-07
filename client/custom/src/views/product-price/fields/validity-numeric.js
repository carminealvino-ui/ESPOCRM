define('custom:views/product-price/fields/validity-numeric', ['views/fields/base'], function (Dep) {

    return Dep.extend({

        detailTemplate: 'fields/base/detail',

        data: function () {
            return {
                value: this.formatRange(),
            };
        },

        formatRange: function () {
            var start = this.formatDate(this.model.get('dateStart'));
            var end = this.formatDate(this.model.get('dateEnd'));

            return (start || '—') + ' → ' + (end || '∞');
        },

        formatDate: function (value) {
            if (!value) {
                return '';
            }

            return this.getDateTime().toMoment(value).format('DD/MM/YYYY');
        },

        getStringValue: function () {
            return this.formatRange();
        },
    });
});
