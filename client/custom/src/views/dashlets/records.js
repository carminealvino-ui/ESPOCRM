define('custom:views/dashlets/records', [
    'views/dashlets/records',
    'custom:helpers/call-dashlet-defaults',
], function (Dep, CallDashletDefaultsModule) {

    const CallDashletDefaults = CallDashletDefaultsModule.default || CallDashletDefaultsModule;

    return Dep.extend({

        init: function () {
            if (this.options && this.options.entityType === 'Call') {
                CallDashletDefaults.applyToDashletOptions(this.options);
            }

            Dep.prototype.init.call(this);
        },

        getSearchData: function () {
            if (this.options && this.options.entityType === 'Call') {
                return CallDashletDefaults.getSearchData();
            }

            return Dep.prototype.getSearchData.call(this);
        },
    });
});
