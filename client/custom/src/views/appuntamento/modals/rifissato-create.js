/* global define */

define('custom:views/appuntamento/modals/rifissato-create', [
    'views/modals/edit',
    'custom:views/appuntamento/helpers/rifissato',
], function (EditModalModule, RifissatoModule) {

    const Parent = EditModalModule.default || EditModalModule;
    const Rifissato = RifissatoModule.default || RifissatoModule;

    return class AppuntamentoRifissatoCreateModal extends Parent {

        setup() {
            this.scope = 'Appuntamento';
            this.layoutName = 'rifissatoCreate';

            if (!this.options.model) {
                throw new Error('Rifissato create modal requires a new model instance.');
            }

            this.model = this.options.model;

            Parent.prototype.setup.call(this);

            this.listenTo(this.model, 'change:dateStart', () => {
                Rifissato.applyDefaultDuration(this.model, this.getDateTime());
            });

            this.once('after:render', () => {
                Rifissato.applyDefaultDuration(this.model, this.getDateTime());
            });
        }

        actionSave() {
            if (!this.model.isNew()) {
                Espo.Ui.error('Il nuovo appuntamento deve essere creato come record separato.');

                return Promise.reject();
            }

            return Parent.prototype.actionSave.call(this);
        }
    };
});
