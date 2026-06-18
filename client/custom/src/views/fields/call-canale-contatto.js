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
                label: this.translate(value, 'options', this.entityType, this.name),
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
                CallEsitoDefaults.applyWhatsAppDescription(this.model);
                this.refreshDescriptionField();
            }
        },

        refreshDescriptionField: function () {
            const recordView = this.getParentView && this.getParentView();

            if (!recordView || typeof recordView.getFieldView !== 'function') {
                return;
            }

            const descriptionField = recordView.getFieldView('description');

            if (descriptionField && typeof descriptionField.reRender === 'function') {
                descriptionField.reRender();
            }
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
