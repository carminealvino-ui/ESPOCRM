define('custom:views/fields/appuntamento-sottostato', [
    'views/fields/enum',
    'custom:helpers/appuntamento-sottostato-map',
], function (Dep, SottostatoMap) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this._allTranslatedOptions) {
                this._allTranslatedOptions = Object.assign(
                    {},
                    this.translatedOptions || {}
                );
            }

            this.applyAllowedOptions();

            this.listenTo(this.model, 'change:status', () => {
                this.applyAllowedOptions();
                this.reRender();
            });
        },

        applyAllowedOptions: function () {
            const status = (this.model.get('status') || '').toString();
            const isPlanned = status === 'Planned' || status === '';
            const allowed = isPlanned
                ? []
                : SottostatoMap.getAllowedForStatus(status);
            const list = [''].concat(allowed);

            this.params.options = list;
            this.translatedOptions = {};

            list.forEach((value) => {
                if (value === '') {
                    this.translatedOptions[value] = '';

                    return;
                }

                this.translatedOptions[value] =
                    (this._allTranslatedOptions && this._allTranslatedOptions[value]) ||
                    value;
            });

            if (typeof this.setOptionList === 'function') {
                this.setOptionList(list);
            }

            const current = (this.model.get(this.name) || '').toString();

            if (isPlanned || (current && allowed.indexOf(current) === -1)) {
                if (current) {
                    this.model.set(this.name, '', {silent: true});
                }
            }
        },
    });
});
