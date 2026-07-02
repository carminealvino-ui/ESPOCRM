define('custom:views/fields/appuntamento-sottostato-popup', [
    'views/fields/enum',
], function (Dep) {
    const allowedMap = {
        Held: [
            'Pending',
            'Gestito',
            'Chiuso Positivamente',
            'Non Interessato',
        ],
        'Not Held': [
            'Non Confermato',
            'Non Ricevuto',
            'Non Gestito',
            'Annullato',
            'Rifissato',
        ],
        Ingestibile: [
            'Infattibilità Tecnica',
            'Solo Informazioni',
            'Prodotto non Conforme',
            'Fuori Target',
        ],
    };

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
            const allowed = allowedMap[status] || [];
            this.params.options = [''].concat(allowed);

            const current = (this.model.get(this.name) || '').toString();

            if (current && !allowed.includes(current)) {
                this.model.set(this.name, '', {silent: true});
            }
        },
    });
});
