define('custom:views/fields/call-da-richiamare', [
    'views/fields/bool',
], function (Dep) {

    const getDaRichiamareLabel = function (status) {
        status = (status || '').toString().trim();

        if (status === 'Held' || status === 'Not Held') {
            return 'Crea nuova chiamata';
        }

        return 'Rinvia richiamo';
    };

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:status', () => {
                this.updateDynamicLabel();
            });
        },

        translateFieldLabel: function () {
            return getDaRichiamareLabel(this.model.get('status'));
        },

        updateDynamicLabel: function () {
            const label = this.translateFieldLabel();

            if (this.$label) {
                this.$label.text(label);
            }

            this.$el.find('label').first().text(label);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.updateDynamicLabel();
        },
    });
});
