define('custom:views/fields/appuntamento-sottostato', [
    'views/fields/enum',
    'custom:helpers/appuntamento-sottostato-map',
], function (Dep, SottostatoMap) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.applyAllowedOptions();

            this.listenTo(this.model, 'change:status', () => {
                this.applyAllowedOptions();
                this.reRender();
            });
        },

        applyAllowedOptions: function () {
            const status = (this.model.get('status') || '').toString();
            const isPlanned = status === 'Planned' || status === '';
            const allowed = SottostatoMap.getAllowedForStatus(status);

            if (isPlanned) {
                this.params.options = [''];

                if (this.model.get(this.name)) {
                    this.model.set(this.name, '', {silent: true});
                }

                return;
            }

            this.params.options = [''].concat(allowed);

            const current = (this.model.get(this.name) || '').toString();

            if (current && !allowed.includes(current)) {
                this.model.set(this.name, '', {silent: true});
            }
        },
    });
});
