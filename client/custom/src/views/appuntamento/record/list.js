/* global define */

define('custom:views/appuntamento/record/list', ['views/record/list'], function (Dep) {

    return Dep.extend({

        checkboxes: false,

        massActionList: [],

        checkAllResultMassActionList: [],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.checkboxes = false;
            this.massActionList = [];
            this.checkAllResultMassActionList = [];
            this.massActionsDisabled = true;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$el.find('th.checkbox-cell, td.checkbox-cell').addClass('hidden');
        },
    });
});
