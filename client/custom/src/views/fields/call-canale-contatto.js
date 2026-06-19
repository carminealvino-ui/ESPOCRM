define('custom:views/fields/call-canale-contatto', [
    'views/fields/base',
    'custom:helpers/call-esito-popup-defaults',
], function (Dep, CallEsitoDefaultsModule) {

    const CallEsitoDefaults = CallEsitoDefaultsModule.default || CallEsitoDefaultsModule;

    return Dep.extend({

        editTemplate: 'custom:fields/call-canale-contatto/edit',

        data: function () {
            return {
                value: this.getValue(),
                options: this.getOptions(),
            };
        },

        events: {
            'change input[data-name="canaleContatto"]': function () {
                this.syncChannelFields();
            },
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.model.get(this.name)) {
                this.model.set(this.name, this.deriveValueFromModel(), {silent: true});
            }
        },

        deriveValueFromModel: function () {
            if (this.model.get('whatsApp')) {
                return 'whatsapp';
            }

            return 'call';
        },

        getValue: function () {
            return this.model.get(this.name) || this.deriveValueFromModel();
        },

        getOptions: function () {
            return ['call', 'whatsapp'].map(value => ({
                value: value,
                label: CallEsitoDefaults.getCanaleLabel(value),
            }));
        },

        syncChannelFields: function () {
            const value = this.$el.find('input[data-name="canaleContatto"]:checked').val()
                || this.getValue();

            this.model.set({
                [this.name]: value,
                vocale: value === 'call',
                whatsApp: value === 'whatsapp',
            });

            if (value === 'whatsapp') {
                CallEsitoDefaults.applyWhatsAppTesto(this.model);
                CallEsitoDefaults.refreshRecordFields(this.getRecordView(), ['testo']);
            }
        },

        getRecordView: function () {
            let view = this;

            while (view) {
                if (view.name === 'esitoRecord' || view.scope === 'Call' && view.type === 'edit') {
                    return view;
                }

                view = view.getParentView && view.getParentView();
            }

            return this.getParentView && this.getParentView();
        },

        refreshDescriptionField: function () {
            CallEsitoDefaults.refreshRecordFields(this.getRecordView(), ['testo']);
        },

        fetch: function () {
            this.syncChannelFields();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            const value = this.getValue();
            const $input = this.$el.find('input[data-name="canaleContatto"][value="' + value + '"]');

            if ($input.length) {
                $input.prop('checked', true);
            }
        },
    });
});
