define('custom:views/fields/call-canale-contatto', ['views/fields/base'], function (Dep) {

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
            return [
                {
                    value: 'call',
                    label: this.translate('call', 'options', 'Call'),
                },
                {
                    value: 'whatsapp',
                    label: this.translate('whatsapp', 'options', 'Call'),
                },
            ];
        },

        syncChannelFields: function () {
            const value = this.$el.find('input[data-name="canaleContatto"]:checked').val()
                || this.getValue();

            this.model.set({
                [this.name]: value,
                vocale: value === 'call',
                whatsApp: value === 'whatsapp',
            });
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
