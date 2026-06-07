define('custom:views/fields/date-numeric', ['views/fields/date'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.dateFormat = 'DD/MM/YYYY';
        },
    });
});
