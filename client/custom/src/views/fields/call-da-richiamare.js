define('custom:views/fields/call-da-richiamare', [
    'views/fields/bool',
    'custom:helpers/call-esito-popup-defaults',
], function (Dep, CallEsitoDefaultsModule) {

    const CallEsitoDefaults = CallEsitoDefaultsModule.default || CallEsitoDefaultsModule;

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:status', () => {
                this.updateDynamicLabel();
            });
        },

        translateFieldLabel: function () {
            return CallEsitoDefaults.getDaRichiamareLabel(this.model.get('status'));
        },

        updateDynamicLabel: function () {
            const label = this.translateFieldLabel();

            this.$label && this.$label.text(label);
            this.$el.find('label').first().text(label);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.updateDynamicLabel();
        },
    });
});
