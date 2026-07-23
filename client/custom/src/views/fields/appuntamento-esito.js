define('custom:views/fields/appuntamento-esito', [
    'views/fields/enum',
    'custom:helpers/appuntamento-sottostato-map',
], function (Dep, MapHelper) {

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

            this.listenTo(this.model, 'change:sottostato', () => {
                this.applyAllowedOptions();
                this.reRender();
            });

            this.listenTo(this.model, 'change:esito', () => {
                this.applyEsitoMapping();
            });
        },

        applyEsitoMapping: function () {
            const esito = (this.model.get('esito') || '').toString();
            const mapped = MapHelper.getEsitoMapping(esito);

            if (!mapped) {
                return;
            }

            this.model.set('status', mapped.status, {ui: true});

            // Per Ingestibile il sottostato è indipendente (motivo): non azzerarlo.
            if (mapped.sottostato !== '' && mapped.sottostato !== null && mapped.sottostato !== undefined) {
                this.model.set('sottostato', mapped.sottostato, {ui: true});
            }
        },

        applyAllowedOptions: function () {
            const status = (this.model.get('status') || '').toString();
            const sottostato = (this.model.get('sottostato') || '').toString();
            const isPlanned = status === 'Planned' || status === '';
            const allowed = isPlanned
                ? []
                : MapHelper.getAllowedEsiti(status, sottostato);
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

            if (!current || !allowed.length) {
                return;
            }

            if (allowed.indexOf(current) !== -1) {
                return;
            }

            const mapped = MapHelper.getEsitoMapping(current);

            if (
                mapped &&
                mapped.status === status &&
                (mapped.sottostato || '') === sottostato
            ) {
                return;
            }

            this.model.set(this.name, '', {silent: true});
        },
    });
});
