/* global define, Espo */

define('custom:views/appuntamento/modals/rifissato-create', ['views/modal'], function (ModalDep) {

    const Parent = ModalDep.default || ModalDep;

    return class AppuntamentoRifissatoCreateModal extends Parent {

        className = 'dialog dialog-record';

        templateContent = `
            <div class="record no-side-margin">
                <div class="cell form-group" data-name="dateStart"></div>
            </div>
        `;

        setup() {
            this.headerText = 'Nuovo appuntamento rifissato';
            this.sourceId = this.options.sourceId;
            this.assignedUsersIds = this.options.assignedUsersIds || [];

            this.buttonList = [
                {
                    name: 'save',
                    label: 'Salva',
                    style: 'primary',
                    onClick: () => this.actionSave(),
                },
                {
                    name: 'cancel',
                    label: 'Annulla',
                    onClick: () => this.close(),
                },
            ];

            this.wait(true);

            return this.getModelFactory().create('Appuntamento')
                .then(model => {
                    this.model = model;
                    this.model.set({status: 'Planned'});
                    this.wait(false);

                    Parent.prototype.setup.call(this);
                });
        }

        afterRender() {
            Parent.prototype.afterRender.call(this);

            this.createView('dateStart', 'views/fields/datetime-optional', {
                model: this.model,
                mode: 'edit',
                el: this.getSelector() + ' [data-name="dateStart"]',
                defs: {
                    name: 'dateStart',
                    required: true,
                },
            }, view => {
                view.render();
            });
        }

        actionSave() {
            const fieldView = this.getView('dateStart');

            if (fieldView && typeof fieldView.fetch === 'function') {
                fieldView.fetch();
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                Espo.Ui.warning('Inserire la data del nuovo appuntamento.');

                return;
            }

            if (!this.sourceId) {
                Espo.Ui.error('Appuntamento origine non valido.');

                return;
            }

            Espo.Ui.notify(' ...');

            Espo.Ajax.postRequest('Appuntamento/action/createRifissato', {
                sourceId: this.sourceId,
                dateStart: dateStart,
                assignedUsersIds: this.assignedUsersIds,
            })
                .then(response => {
                    Espo.Ui.notify(false);
                    Espo.Ui.success('Nuovo appuntamento creato.');
                    this.trigger('after:save', response);
                    this.close();
                })
                .catch(() => {
                    Espo.Ui.notify(false);
                });
        }
    };
});
