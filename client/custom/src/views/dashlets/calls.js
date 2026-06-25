define('custom:views/dashlets/calls', [
    'views/dashlets/abstract/record-list',
    'custom:helpers/call-dashlet-defaults',
], function (Dep, CallDashletDefaultsModule) {

    const CallDashletDefaults = CallDashletDefaultsModule.default || CallDashletDefaultsModule;

    return Dep.extend({

        name: 'Calls',
        scope: 'Call',
        listView: 'crm:views/call/record/list-expanded',
        rowActionsView: 'crm:views/call/record/row-actions/dashlet',

        setup: function () {
            CallDashletDefaults.applyToDashletOptions(this.options);
            Dep.prototype.setup.call(this);
        },

        getSearchData: function () {
            return CallDashletDefaults.getSearchData();
        },
    });
});
