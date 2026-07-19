define('custom:views/fields/appuntamento-esito', [
    'views/fields/enum',
    'custom:helpers/appuntamento-sottostato-map',
], function (Dep, MapHelper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
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
            this.model.set('sottostato', mapped.sottostato || '', {ui: true});
        },

        applyAllowedOptions: function () {
            const status = (this.model.get('status') || '').toString();
            const sottostato = (this.model.get('sottostato') || '').toString();
            const isPlanned = status === 'Planned' || status === '';

            if (isPlanned) {
                this.params.options = [''];

                if (this.model.get(this.name)) {
                    this.model.set(this.name, '', {silent: true});
                }

                return;
            }

            const allowed = MapHelper.getAllowedEsiti(status, sottostato);
            this.params.options = [''].concat(allowed);

            const current = (this.model.get(this.name) || '').toString();

            if (current && allowed.length && !allowed.includes(current)) {
                // Se l'esito corrente mappa già a questo status/sottostato, tienilo.
                const mapped = MapHelper.getEsitoMapping(current);

                if (
                    mapped &&
                    mapped.status === status &&
                    (mapped.sottostato || '') === sottostato
                ) {
                    this.params.options = [''].concat(
                        allowed.includes(current) ? allowed : [current].concat(allowed)
                    );

                    return;
                }

                this.model.set(this.name, '', {silent: true});
            }
        },
    });
});
